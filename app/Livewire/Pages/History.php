<?php

namespace App\Livewire\Pages;

use App\Models\Search;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class History extends Component
{
    use WithPagination;

    public string $type = 'all';

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $searches = Search::query()
            ->with(['releases' => fn ($query) => $query->orderBy('release_search.position')->limit(5)])
            ->when($this->type !== 'all', fn ($query) => $query->where('type', $this->type))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(12);

        return view('livewire.pages.history', ['searches' => $searches])->title('Cronologia · Crate');
    }
}
