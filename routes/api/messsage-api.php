<?php

use App\Http\Controllers\api\ChatController;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;


Route::prefix('api/v1/')->group(function () {
    Route::post('send-message', [ChatController::class, 'sendMessage']);
    Route::post('mark-as-read', [ChatController::class, 'markAsRead']);
    Route::post('delete-message', [ChatController::class, 'deleteMessage']);
    Route::post('get-particular-user-chat', [ChatController::class, 'getAllChatMessages']);
    Route::post('get-inner-chats',[ChatController::class,'getInnerChat']);
});
