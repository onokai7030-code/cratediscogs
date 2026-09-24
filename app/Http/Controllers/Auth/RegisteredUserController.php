<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $isFirstUser = ! User::query()->lockForUpdate()->exists();
            $user = User::create($request->safe()->only(['name', 'email', 'password']));
            $user->is_admin = $isFirstUser;
            $user->save();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dig');
    }
}
