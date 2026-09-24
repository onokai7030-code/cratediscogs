<!DOCTYPE html>
<html lang="it" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <meta name="theme-color" content="#090b10">
        <title>{{ $title ?? 'Crate' }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-[#090b10] text-zinc-100 antialiased selection:bg-amber-300 selection:text-black">
        @php
            $navigation = [
                ['route' => 'dig', 'label' => 'Dig', 'icon' => '⌕'],
                ['route' => 'labels', 'label' => 'Labels', 'icon' => '◈'],
                ['route' => 'label.show', 'label' => 'Esplora', 'icon' => '◎'],
                ['route' => 'history', 'label' => 'Cronologia', 'icon' => '↺'],
            ];
        @endphp
        <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_15%_0%,rgba(245,158,11,.10),transparent_32%),radial-gradient(circle_at_85%_15%,rgba(34,211,238,.07),transparent_28%)]"></div>
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-72 flex-col border-r border-white/8 bg-[#0c0e13]/95 p-5 backdrop-blur-xl lg:flex">
            <a href="{{ route('dig') }}" wire:navigate class="flex items-center gap-3 px-2 py-1"><span class="grid size-10 place-items-center rounded-xl border border-amber-300/30 bg-amber-300/10 font-semibold text-amber-300">C</span><span><strong class="block text-sm tracking-[.22em] text-white">CRATE</strong><small class="block text-[10px] uppercase tracking-[.18em] text-zinc-500">Discogs intelligence</small></span></a>
            <nav class="mt-10 flex flex-1 flex-col gap-1">
                <p class="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[.2em] text-zinc-600">Workspace</p>
                @foreach ($navigation as $item)
                    <a href="{{ route($item['route']) }}" wire:navigate @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition', 'bg-amber-300/10 text-amber-200' => request()->routeIs($item['route']), 'text-zinc-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs($item['route'])])><span class="w-5 text-center text-base">{{ $item['icon'] }}</span>{{ $item['label'] }}</a>
                @endforeach
                @can('manage-users')
                    <p class="mb-2 mt-7 px-3 text-[10px] font-semibold uppercase tracking-[.2em] text-zinc-600">Amministrazione</p>
                    <a href="{{ route('admin.users.index') }}" @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition', 'bg-amber-300/10 text-amber-200' => request()->routeIs('admin.*'), 'text-zinc-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs('admin.*')])><span class="w-5 text-center">☷</span>Utenti</a>
                @endcan
            </nav>
            <div class="rounded-2xl border border-white/8 bg-white/[.035] p-3"><div class="flex items-center gap-3"><span class="grid size-9 place-items-center rounded-full bg-white/8 text-sm font-semibold text-white">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</span><div class="min-w-0 flex-1"><p class="truncate text-sm font-medium text-white">{{ auth()->user()->name }}</p><p class="truncate text-xs text-zinc-600">{{ auth()->user()->is_admin ? 'Amministratore' : 'Utente' }}</p></div></div><form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="w-full rounded-lg border border-white/8 px-3 py-2 text-left text-xs text-zinc-500 transition hover:bg-white/5 hover:text-white">Esci dall'account</button></form></div>
        </aside>
        <header class="sticky top-0 z-40 border-b border-white/8 bg-[#090b10]/90 px-4 py-3 backdrop-blur-xl lg:hidden"><div class="flex items-center justify-between"><a href="{{ route('dig') }}" wire:navigate class="flex items-center gap-2"><span class="grid size-9 place-items-center rounded-xl bg-amber-300/10 text-amber-300">C</span><strong class="text-sm tracking-[.2em]">CRATE</strong></a><details class="relative"><summary class="cursor-pointer list-none rounded-lg border border-white/10 px-3 py-2 text-sm text-zinc-300">Menu</summary><nav class="absolute right-0 mt-2 flex w-56 flex-col gap-1 rounded-xl border border-white/10 bg-[#11141b] p-2 shadow-2xl">@foreach ($navigation as $item)<a href="{{ route($item['route']) }}" wire:navigate class="rounded-lg px-3 py-2 text-sm text-zinc-300 hover:bg-white/5">{{ $item['label'] }}</a>@endforeach @can('manage-users')<a href="{{ route('admin.users.index') }}" class="rounded-lg px-3 py-2 text-sm text-zinc-300 hover:bg-white/5">Utenti</a>@endcan<form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-sm text-red-300 hover:bg-white/5">Esci</button></form></nav></details></div></header>
        <div class="relative lg:pl-72"><main class="mx-auto max-w-[1600px] px-4 py-8 sm:px-6 lg:px-8">@isset($slot){{ $slot }}@else @yield('content') @endisset</main><footer class="mx-auto max-w-[1600px] border-t border-white/8 px-6 py-6 text-xs text-zinc-600">Crate · Laravel + Livewire · dati da Discogs</footer></div>
        @livewireScripts
    </body>
</html>
