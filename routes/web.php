<?php

use App\Livewire\Pages\Dig;
use App\Livewire\Pages\History;
use App\Livewire\Pages\Labels;
use App\Livewire\Pages\LabelShow;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dig');
Route::get('/dig', Dig::class)->name('dig');
Route::get('/labels', Labels::class)->name('labels');
Route::get('/label/{label?}', LabelShow::class)->name('label.show');
Route::get('/history', History::class)->name('history');
