@extends('layouts.guest')
@section('title', 'Accedi')
@section('content')
    <section class="panel p-6 sm:p-8">
        <p class="text-xs font-semibold uppercase tracking-[.2em] text-amber-300">Bentornato</p><h1 class="mt-2 text-2xl font-semibold text-white">Accedi a Crate</h1><p class="mt-2 text-sm text-zinc-500">Entra nel tuo workspace di digging.</p>
        <form method="POST" action="{{ route('login') }}" class="mt-7 flex flex-col gap-4">@csrf
            <div><label for="email" class="label">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" class="field" autocomplete="email" required autofocus>@error('email')<p class="mt-1.5 text-xs text-red-300">{{ $message }}</p>@enderror</div>
            <div><label for="password" class="label">Password</label><input id="password" name="password" type="password" class="field" autocomplete="current-password" required>@error('password')<p class="mt-1.5 text-xs text-red-300">{{ $message }}</p>@enderror</div>
            <label class="flex items-center gap-2 text-sm text-zinc-400"><input name="remember" type="checkbox" value="1" class="accent-amber-300"> Ricordami</label><button class="btn-primary mt-1 w-full">Accedi</button>
        </form>
        <p class="mt-6 text-center text-sm text-zinc-500">Non hai un account? <a href="{{ route('register') }}" class="text-amber-200 hover:underline">Registrati</a></p>
    </section>
@endsection
