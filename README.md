# Crate

Crate è un'applicazione Laravel per fare digging su Discogs da terminale e, nella prossima fase, dal web. Cerca release poco conosciute, misura il rapporto fra utenti che desiderano e possiedono un disco, individua label specializzate ed esplora le relazioni fra etichette.

## Requisiti

- PHP 8.3 o successivo
- Composer
- SQLite e le relative estensioni PHP
- Un token personale Discogs

## Installazione

```bash
git clone <repository> crate
cd crate
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

Configura `.env` senza committare il token:

```dotenv
DB_CONNECTION=sqlite
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
DISCOGS_TOKEN=il_tuo_token
DISCOGS_USER_AGENT="Crate/0.1"
DISCOGS_CACHE_DAYS=7
DISCOGS_MAX_RESULTS=1000
```

Verifica la connessione:

```bash
php artisan crate:ping
```

## Digging per stile

```bash
php artisan crate:dig "Deep House" \
  --years=1994-2002 \
  --format=Vinyl \
  --max-have=500 \
  --sort=want_ratio \
  --limit=25
```

Filtri disponibili: `--genre`, `--also`, `--exclude`, `--years`, `--country`, `--format`, `--min-have`, `--max-have`, `--min-want`, `--sort` e `--limit`.

L'output contiene artista, titolo, label, numero di catalogo, anno, paese, stili, have, want, rapporto want/have e link Discogs.

## Mappa delle label

```bash
php artisan crate:labels "Deep House" --max-releases=500 --limit=10
```

Le label vengono ordinate per specializzazione, rapporto want/have medio e numero di release nello stile.

## Esplorazione di una label

Sono accettati nome, ID o URL Discogs:

```bash
php artisan crate:label "Crisp Recordings" --years=1995-2010
php artisan crate:label 492766 --style="Deep House"
```

Per cercare anche le label sorelle tramite gli artisti condivisi:

```bash
php artisan queue:work
```

In un altro terminale:

```bash
php artisan crate:label "Crisp Recordings" --depth=2
php artisan crate:label:status ID
```

## Persistenza, release viste ed export

Ogni ricerca viene registrata in `searches`; release, label e stili vengono aggiornati senza duplicare gli identificativi Discogs. Le release effettivamente mostrate vengono marcate come viste.

Per ricevere solo risultati mai mostrati prima:

```bash
php artisan crate:dig "Deep House" --hide-seen
php artisan crate:label "Crisp Recordings" --hide-seen
```

Tutti i comandi di ricerca supportano l'export CSV:

```bash
php artisan crate:dig "Deep House" --export=risultati.csv
php artisan crate:labels "Deep House" --export=labels.csv
php artisan crate:label "Crisp Recordings" --export=catalogo.csv
```

Con `--hide-seen`, il CSV contiene lo stesso insieme filtrato mostrato nel terminale. I valori potenzialmente interpretabili come formule dai fogli di calcolo vengono neutralizzati.

Per ignorare la cache Discogs aggiungi `--fresh` a qualsiasi comando che interroga l'API.

## Test e qualità

```bash
php artisan test --compact
vendor/bin/pint --test
```

## Interfaccia web

Compila gli asset e avvia applicazione e worker:

```bash
npm install
npm run build
php artisan serve
php artisan queue:work
```

Apri `http://127.0.0.1:8000`. Sono disponibili:

- `/dig`: ricerca con filtri, ordinamento, paginazione, video, release viste ed export;
- `/labels`: classifica delle label specializzate;
- `/label`: catalogo, gerarchia, video e avanzamento delle label sorelle;
- `/history`: cronologia filtrabile e ricerche rilanciabili.

## Stato del progetto

Sono completate le fasi 1–6: client Discogs, digging, mappa label, esplorazione asincrona, persistenza ed interfaccia web Livewire/Tailwind.

## Laravel

Il progetto è costruito con Laravel. Le informazioni generali del framework sono mantenute qui sotto come riferimento.

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
