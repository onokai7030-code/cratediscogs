@extends('layouts.app')
@section('content')
    <div class="flex flex-col gap-6">
        <section><p class="text-xs font-semibold uppercase tracking-[.2em] text-amber-300">Amministrazione</p><h1 class="mt-2 text-3xl font-semibold text-white">Utenti</h1><p class="mt-2 text-sm text-zinc-500">Account registrati e relativi livelli di accesso.</p></section>
        <section class="panel overflow-hidden"><div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Utente</th><th>Email</th><th>Ruolo</th><th>Registrato</th></tr></thead><tbody>@foreach($users as $user)<tr><td class="font-medium text-white">{{ $user->name }}</td><td>{{ $user->email }}</td><td><span @class(['badge', 'border-amber-300/20 text-amber-200' => $user->is_admin])>{{ $user->is_admin ? 'Admin' : 'Utente' }}</span></td><td>{{ $user->created_at->format('d/m/Y H:i') }}</td></tr>@endforeach</tbody></table></div><div class="p-4">{{ $users->links() }}</div></section>
    </div>
@endsection
