<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ImageProxyController;
use App\Http\Controllers\Api\ScottyVerdictController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->name('api.auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    });
});

Route::post('contact', [ContactController::class, 'submit'])
    ->middleware('throttle:5,1')
    ->name('api.contact.submit');

Route::get('images/{carGuid}', [ImageProxyController::class, 'showByCar'])->name('api.images.car');
Route::get('images/{carGuid}/{imageId}', [ImageProxyController::class, 'show'])->name('api.images.show');
Route::get('verdicts/vehicle-types', [ScottyVerdictController::class, 'vehicleTypes'])->name('api.verdicts.vehicle-types');
Route::get('verdicts/top-best', [ScottyVerdictController::class, 'topBest'])->name('api.verdicts.top-best');
Route::get('verdicts/top-worst', [ScottyVerdictController::class, 'topWorst'])->name('api.verdicts.top-worst');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('verdicts/search', [ScottyVerdictController::class, 'search'])->name('api.verdicts.search');
    Route::get('verdicts/video-mentions', [ScottyVerdictController::class, 'videoMentions'])->name('api.verdicts.video-mentions');
    Route::get('listings/near-me', [ScottyVerdictController::class, 'nearbyListings'])->name('api.listings.near-me');
});
