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
        <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_15%_0%,rgba(245,158,11,.10),transparent_32%),radial-gradient(circle_at_85%_15%,rgba(34,211,238,.07),transparent_28%)]"></div>
        <header class="sticky top-0 z-40 border-b border-white/8 bg-[#090b10]/85 backdrop-blur-xl">
            <div class="mx-auto flex max-w-[1600px] items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                <a href="{{ route('dig') }}" wire:navigate class="group flex items-center gap-3">
                    <span class="grid size-9 place-items-center rounded-xl border border-amber-300/30 bg-amber-300/10 text-amber-300">C</span>
                    <span><strong class="block text-sm tracking-[.22em] text-white">CRATE</strong><small class="block text-[10px] uppercase tracking-[.18em] text-zinc-500">Discogs intelligence</small></span>
                </a>
                <nav class="flex items-center gap-1 rounded-xl border border-white/8 bg-white/[.03] p-1 text-sm">
                    @foreach ([['dig', 'Dig'], ['labels', 'Labels'], ['label.show', 'Esplora'], ['history', 'Cronologia']] as [$route, $label])
                        <a href="{{ route($route) }}" wire:navigate @class(['rounded-lg px-3 py-2 transition', 'bg-white/10 text-white' => request()->routeIs($route), 'text-zinc-400 hover:bg-white/5 hover:text-white' => ! request()->routeIs($route)])>{{ $label }}</a>
                    @endforeach
                </nav>
            </div>
        </header>
        <main class="relative mx-auto max-w-[1600px] px-4 py-8 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>
        <footer class="relative mx-auto max-w-[1600px] border-t border-white/8 px-6 py-6 text-xs text-zinc-600">Crate · Laravel + Livewire · dati da Discogs</footer>

        @livewireScripts
    </body>
</html>
