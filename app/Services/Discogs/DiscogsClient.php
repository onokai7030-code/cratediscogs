<?php

namespace App\Services\Discogs;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class DiscogsClient
{
    private const PAGE_SIZE = 100;

    private const MAX_RATE_LIMIT_ATTEMPTS = 5;

    private ?int $rateLimitRemaining = null;

    public function search(array $parameters, bool $fresh = false): array
    {
        return $this->paginate('/database/search', 'results', $parameters, $fresh);
    }

    public function searchPage(array $parameters, bool $fresh = false): array
    {
        return $this->get('/database/search', [
            ...$parameters,
            'page' => 1,
            'per_page' => self::PAGE_SIZE,
        ], $fresh);
    }

    public function release(int $id, bool $fresh = false): array
    {
        return $this->get("/releases/{$id}", fresh: $fresh);
    }

    public function master(int $id, bool $fresh = false): array
    {
        return $this->get("/masters/{$id}", fresh: $fresh);
    }

    public function label(int $id, bool $fresh = false): array
    {
        return $this->get("/labels/{$id}", fresh: $fresh);
    }

    public function labelReleases(int $id, array $parameters = [], bool $fresh = false): array
    {
        return $this->paginate("/labels/{$id}/releases", 'releases', $parameters, $fresh);
    }

    public function artist(int $id, bool $fresh = false): array
    {
        return $this->get("/artists/{$id}", fresh: $fresh);
    }

    public function artistReleases(int $id, array $parameters = [], bool $fresh = false): array
    {
        return $this->paginate("/artists/{$id}/releases", 'releases', $parameters, $fresh);
    }

    public function identity(): array
    {
        return $this->get('/oauth/identity', fresh: true);
    }

    public function rateLimitRemaining(): ?int
    {
        return $this->rateLimitRemaining;
    }

    private function paginate(string $path, string $itemsKey, array $parameters, bool $fresh): array
    {
        $maximum = max(1, (int) config('discogs.max_results', 1000));
        $page = 1;
        $items = [];

        do {
            $payload = $this->get($path, [
                ...$parameters,
                'page' => $page,
                'per_page' => self::PAGE_SIZE,
            ], $fresh);

            $pageItems = $payload[$itemsKey] ?? [];
            $items = array_merge($items, $pageItems);
            $pages = (int) data_get($payload, 'pagination.pages', 1);
            $page++;
        } while ($page <= $pages && count($items) < $maximum && $pageItems !== []);

        return array_slice($items, 0, $maximum);
    }

    private function get(string $path, array $query = [], bool $fresh = false): array
    {
        $this->guardConfiguration();

        ksort($query);
        $cacheKey = 'discogs:'.hash('sha256', $path.'?'.http_build_query($query));

        if (! $fresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response = $this->sendWithRateLimitRetry($path, $query);
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new DiscogsException('Discogs ha restituito una risposta non valida.');
        }

        Cache::put($cacheKey, $payload, now()->addDays(max(1, (int) config('discogs.cache_days', 7))));

        return $payload;
    }

    private function sendWithRateLimitRetry(string $path, array $query): Response
    {
        $attempt = 0;

        do {
            $this->throttleIfNeeded();
            $response = $this->request()->get($path, $query);
            $this->captureRateLimit($response);

            if ($response->status() !== 429) {
                return $response->throw();
            }

            $attempt++;

            if ($attempt >= self::MAX_RATE_LIMIT_ATTEMPTS) {
                return $response->throw();
            }

            Sleep::for($this->retryDelayMilliseconds($response, $attempt))->milliseconds();
        } while (true);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('discogs.base_url'))
            ->acceptJson()
            ->withHeaders([
                'Authorization' => 'Discogs token='.config('discogs.token'),
                'User-Agent' => (string) config('discogs.user_agent'),
            ])
            ->timeout(30);
    }

    private function captureRateLimit(Response $response): void
    {
        $remaining = $response->header('X-Discogs-Ratelimit-Remaining');

        if ($remaining !== null && is_numeric($remaining)) {
            $this->rateLimitRemaining = (int) $remaining;
        }
    }

    private function throttleIfNeeded(): void
    {
        if ($this->rateLimitRemaining === null || $this->rateLimitRemaining > 5) {
            return;
        }

        Sleep::for($this->rateLimitRemaining === 0 ? 5 : 1)->seconds();
    }

    private function retryDelayMilliseconds(Response $response, int $attempt): int
    {
        $retryAfter = $response->header('Retry-After');

        if ($retryAfter !== null && is_numeric($retryAfter)) {
            return max(1, (int) $retryAfter) * 1000;
        }

        return min(1000 * (2 ** ($attempt - 1)), 60000);
    }

    private function guardConfiguration(): void
    {
        if (blank(config('discogs.token'))) {
            throw new DiscogsException('DISCOGS_TOKEN non configurato. Aggiungilo al file .env.');
        }

        if (blank(config('discogs.user_agent'))) {
            throw new DiscogsException('DISCOGS_USER_AGENT non può essere vuoto.');
        }
    }
}
