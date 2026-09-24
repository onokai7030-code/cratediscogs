<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users', [
            'users' => User::query()->latest('id')->paginate(20),
        ]);
    }
}
