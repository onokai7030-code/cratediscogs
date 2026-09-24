<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Livewire\Pages\Dig;
use App\Livewire\Pages\History;
use App\Livewire\Pages\Labels;
use App\Livewire\Pages\LabelShow;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dig');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dig', Dig::class)->name('dig');
    Route::get('/labels', Labels::class)->name('labels');
    Route::get('/label/{label?}', LabelShow::class)->name('label.show');
    Route::get('/history', History::class)->name('history');
    Route::get('/admin/users', [UserController::class, 'index'])
        ->middleware('can:manage-users')
        ->name('admin.users.index');
});
