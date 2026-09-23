<div class="flex flex-col gap-6">
    <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
        <div><p class="text-xs font-semibold uppercase tracking-[.2em] text-amber-300">Discovery engine</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-white sm:text-4xl">Trova il prossimo disco<br><span class="text-zinc-500">prima degli altri.</span></h1></div>
        <div class="max-w-md text-sm leading-6 text-zinc-500">Filtra il catalogo Discogs, ordina per desiderabilità e scopri release rare senza ripetizioni.</div>
    </section>

    <form wire:submit="search" class="panel grid gap-4 p-5 md:grid-cols-4 xl:grid-cols-6">
        <div class="md:col-span-2"><label class="label">Stile principale</label><input wire:model="style" class="field" placeholder="Deep House"></div>
        <div><label class="label">Genere</label><input wire:model="genre" class="field" placeholder="Electronic"></div>
        <div><label class="label">Paese</label><input wire:model="country" class="field" placeholder="UK"></div>
        <div><label class="label">Formato</label><input wire:model="format" class="field" placeholder="Vinyl, 12…"></div>
        <div><label class="label">Risultati</label><input wire:model="limit" type="number" min="1" max="250" class="field"></div>
        <div class="md:col-span-2"><label class="label">Sottogeneri / stili aggiuntivi <span class="normal-case tracking-normal">(separati da virgola)</span></label><input wire:model="also" class="field" placeholder="Progressive House, Breaks"></div>
        <div><label class="label">Corrispondenza stili</label><select wire:model.live="styleMatch" class="field"><option value="any">Qualsiasi (OR)</option><option value="all">Tutti insieme (AND)</option></select></div>
        <div class="flex items-end"><p class="pb-2 text-xs leading-5 text-zinc-500">{{ $styleMatch === 'any' ? 'Include gli stili singoli e mostra prima le release che ne contengono più di uno.' : 'Ogni release deve contenere tutti gli stili.' }}</p></div>
        <div class="md:col-span-2"><label class="label">Escludi</label><input wire:model="exclude" class="field" placeholder="Tech House"></div>
        <div><label class="label">Anno da</label><input wire:model="yearFrom" type="number" class="field" placeholder="1994"></div>
        <div><label class="label">Anno a</label><input wire:model="yearTo" type="number" class="field" placeholder="2002"></div>
        <div><label class="label">Have minimo</label><input wire:model="minHave" type="number" min="0" class="field"></div>
        <div><label class="label">Have massimo</label><input wire:model="maxHave" type="number" min="0" class="field"></div>
        <div><label class="label">Want minimo</label><input wire:model="minWant" type="number" min="0" class="field"></div>
        <div><label class="label">Ordina per</label><select wire:model="sort" class="field"><option value="want_ratio">Want ratio</option><option value="want">Want</option><option value="have">Have</option><option value="year">Anno</option></select></div>
        <div class="flex flex-wrap items-center gap-4 md:col-span-2">
            <label class="flex items-center gap-2 text-sm text-zinc-400"><input wire:model="hideSeen" type="checkbox" class="accent-amber-300"> Nascondi viste</label>
            <label class="flex items-center gap-2 text-sm text-zinc-400"><input wire:model="fresh" type="checkbox" class="accent-amber-300"> Ignora cache</label>
        </div>
        <div class="flex items-end gap-2 md:col-span-4 xl:col-span-2"><button class="btn-primary flex-1" wire:loading.attr="disabled">Scava <span wire:loading>…</span></button>@if($results)<button type="button" wire:click="export" class="btn-secondary">CSV</button>@endif</div>
        @error('*') <p class="text-sm text-red-300 md:col-span-full">{{ $message }}</p> @enderror
    </form>

    @if($error)<div class="rounded-xl border border-red-400/25 bg-red-400/10 p-4 text-sm text-red-100"><strong class="block">Ricerca non eseguita</strong><span class="mt-1 block text-red-200/80">{{ $error }}</span></div>@endif
    @if(session('status'))<div class="rounded-xl border border-emerald-400/20 bg-emerald-400/10 p-4 text-sm text-emerald-200">{{ session('status') }}</div>@endif

    @if($results)
        <section class="panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-white/8 px-5 py-4"><div><strong class="text-white">{{ count($results) }} release</strong><span class="ml-2 text-sm text-zinc-500">pagina {{ $page }} / {{ $pageCount }}</span></div><span class="badge">{{ $sort }}</span></div>
            <div class="overflow-x-auto"><table class="data-table"><thead><tr><th><button wire:click="sortBy('artist')">Artista</button></th><th><button wire:click="sortBy('title')">Titolo</button></th><th>Label / Stili</th><th><button wire:click="sortBy('year')">Anno</button></th><th><button wire:click="sortBy('have')">Have</button></th><th><button wire:click="sortBy('want')">Want</button></th><th><button wire:click="sortBy('want_ratio')">Ratio</button></th><th>Azioni</th></tr></thead>
                <tbody>@foreach($visibleResults as $release)<tr wire:key="release-{{ $release['id'] }}"><td class="font-medium text-white">{{ $release['artist'] }}</td><td><a href="{{ $release['url'] }}" target="_blank" rel="noopener" class="text-amber-200 hover:underline">{{ $release['title'] }}</a><div class="mt-1 text-xs text-zinc-600">{{ $release['catalog_number'] ?: '—' }} · {{ $release['country'] ?: '—' }}</div></td><td><div>{{ implode(', ', $release['labels']) }}</div><div class="mt-1 flex flex-wrap gap-1">@foreach($release['styles'] as $item)<span class="badge">{{ $item }}</span>@endforeach</div></td><td>{{ $release['year'] ?: '—' }}</td><td>{{ $release['have'] }}</td><td>{{ $release['want'] }}</td><td class="font-semibold text-cyan-300">{{ number_format($release['want_ratio'], 2) }}</td><td><div class="flex gap-2"><button wire:click="markSeen({{ $release['id'] }})" class="text-xs text-zinc-400 hover:text-white">Vista</button><button wire:click="loadVideos({{ $release['id'] }})" class="text-xs text-zinc-400 hover:text-white">Video</button></div>@if(array_key_exists($release['id'], $videos))<div class="mt-2 flex flex-col gap-1">@forelse($videos[$release['id']] as $video)<a href="{{ $video }}" target="_blank" rel="noopener" class="text-xs text-red-300 hover:underline">YouTube ↗</a>@empty<span class="text-xs text-zinc-600">Nessun video</span>@endforelse</div>@endif</td></tr>@endforeach</tbody></table></div>
            <div class="flex justify-end gap-2 p-4"><button wire:click="previousPage" @disabled($page === 1) class="btn-secondary disabled:opacity-30">Indietro</button><button wire:click="nextPage" @disabled($page === $pageCount) class="btn-secondary disabled:opacity-30">Avanti</button></div>
        </section>
    @elseif($hasSearched && !$error)
        <section class="panel grid min-h-56 place-items-center p-8 text-center"><div><div class="mx-auto grid size-14 place-items-center rounded-2xl border border-amber-300/15 bg-amber-300/5 text-2xl text-amber-300">0</div><p class="mt-4 font-medium text-zinc-300">Nessuna release corrisponde ai filtri.</p><p class="mt-2 max-w-lg text-sm leading-6 text-zinc-500">{{ $styleMatch === 'all' ? 'La modalità “Tutti insieme” richiede che ogni release contenga contemporaneamente tutti gli stili. Prova “Qualsiasi”.' : 'Prova ad ampliare gli anni, rimuovere un filtro oppure verificare gli stili selezionati.' }}</p></div></section>
    @else
        <section class="panel grid min-h-56 place-items-center p-8 text-center"><div><div class="mx-auto grid size-14 place-items-center rounded-2xl border border-white/10 bg-white/5 text-2xl text-zinc-600">⌁</div><p class="mt-4 text-zinc-400">Imposta i filtri e avvia il digging.</p></div></section>
    @endif
</div>
