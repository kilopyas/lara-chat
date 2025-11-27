<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\LobbyController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Route::get('/', function () {
//     return Inertia::render('Welcome');
// })->name('home');
Route::get('/', [LobbyController::class, 'index'])->name('lobby');
Route::get('/chat/{roomId?}', [ChatController::class, 'index'])->name('chat');