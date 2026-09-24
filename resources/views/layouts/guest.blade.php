<!DOCTYPE html>
<html lang="it" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="theme-color" content="#090b10">
        <title>@yield('title') · Crate</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#090b10] text-zinc-100 antialiased selection:bg-amber-300 selection:text-black">
        <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_20%_0%,rgba(245,158,11,.14),transparent_35%),radial-gradient(circle_at_90%_80%,rgba(34,211,238,.08),transparent_30%)]"></div>
        <main class="relative grid min-h-screen place-items-center px-4 py-10">
            <div class="w-full max-w-md">
                <a href="{{ route('login') }}" class="mx-auto mb-8 flex w-fit items-center gap-3"><span class="grid size-11 place-items-center rounded-xl border border-amber-300/30 bg-amber-300/10 font-semibold text-amber-300">C</span><span><strong class="block tracking-[.22em]">CRATE</strong><small class="text-[10px] uppercase tracking-[.18em] text-zinc-500">Discogs intelligence</small></span></a>
                @yield('content')
            </div>
        </main>
    </body>
</html>
