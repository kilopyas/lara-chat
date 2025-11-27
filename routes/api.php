<?php

use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\RoomController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/rooms', [RoomController::class, 'index']);
Route::post('/rooms', [RoomController::class, 'store']);
Route::delete('/rooms/{roomId}', [RoomController::class, 'destroy']);
Route::get('/rooms/{roomId}/messages', [MessageController::class, 'index']);

Route::post('/messages', [MessageController::class, 'store']);
