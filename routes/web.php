<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/', function () {
    return view('lobby');
})->name('lobby');

Route::get('/lobby', function ($roomId = 'general') {
    return view('chat', ['roomId' => $roomId]);
})->name('chat');

Route::get('/chat/{roomId?}', function ($roomId = 'general') {
    return view('chat', ['roomId' => $roomId]);
})->name('chat');