@extends('layouts.guest')
@section('title', 'Registrati')
@section('content')
    <section class="panel p-6 sm:p-8">
        <p class="text-xs font-semibold uppercase tracking-[.2em] text-amber-300">Nuovo account</p><h1 class="mt-2 text-2xl font-semibold text-white">Registrati a Crate</h1><p class="mt-2 text-sm text-zinc-500">Il primo account creato diventa amministratore.</p>
        <form method="POST" action="{{ route('register') }}" class="mt-7 flex flex-col gap-4">@csrf
            <div><label for="name" class="label">Nome</label><input id="name" name="name" value="{{ old('name') }}" class="field" autocomplete="name" required autofocus>@error('name')<p class="mt-1.5 text-xs text-red-300">{{ $message }}</p>@enderror</div>
            <div><label for="email" class="label">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" class="field" autocomplete="email" required>@error('email')<p class="mt-1.5 text-xs text-red-300">{{ $message }}</p>@enderror</div>
            <div><label for="password" class="label">Password</label><input id="password" name="password" type="password" class="field" autocomplete="new-password" required>@error('password')<p class="mt-1.5 text-xs text-red-300">{{ $message }}</p>@enderror</div>
            <div><label for="password_confirmation" class="label">Conferma password</label><input id="password_confirmation" name="password_confirmation" type="password" class="field" autocomplete="new-password" required></div><button class="btn-primary mt-1 w-full">Crea account</button>
        </form>
        <p class="mt-6 text-center text-sm text-zinc-500">Hai già un account? <a href="{{ route('login') }}" class="text-amber-200 hover:underline">Accedi</a></p>
    </section>
@endsection
