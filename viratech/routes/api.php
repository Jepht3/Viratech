<?php

use App\Http\Controllers\Api\AdminApiController;
use App\Http\Controllers\Api\AdminExtrasApiController;
use App\Http\Controllers\Api\ProfileApiController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\ClientApiController;
use App\Http\Controllers\Api\VersionController;
use Illuminate\Support\Facades\Route;

// Système de mise à jour des applications (identique à LeWebPOS).
Route::get('/application/version', VersionController::class);

// Rappel FlexPay (protégé par un secret ; l'état réel est vérifié auprès de FlexPay)
Route::post('/webhooks/flexpay', [WebhookController::class, 'flexpay']);

Route::post('/auth/login', [AuthApiController::class, 'login'])->middleware('throttle:10,1');
Route::post('/auth/register', [AuthApiController::class, 'register'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthApiController::class, 'me']);
    Route::post('/auth/logout', [AuthApiController::class, 'logout']);
    Route::post('/preferences', [AuthApiController::class, 'preferences']);

    // Profil, photo, téléphone, vérification d'identité, notifications push
    Route::get('/profile', [ProfileApiController::class, 'show']);
    Route::post('/profile/avatar', [ProfileApiController::class, 'avatar']);
    Route::get('/avatar/{id}', [ProfileApiController::class, 'avatarFile']);
    Route::post('/email/send', [ProfileApiController::class, 'sendEmailCode'])->middleware('throttle:5,10');
    Route::post('/email/verify', [ProfileApiController::class, 'verifyEmail'])->middleware('throttle:10,10');
    Route::post('/kyc/document', [ProfileApiController::class, 'kycDocument'])->middleware('throttle:10,60');
    Route::post('/kyc/challenge', [ProfileApiController::class, 'newChallenge']);
    Route::post('/kyc/selfie', [ProfileApiController::class, 'kycSelfie'])->middleware('throttle:10,60');
    Route::post('/devices', [ProfileApiController::class, 'registerDevice']);

    Route::get('/notifications', [ClientApiController::class, 'notifications']);
    Route::post('/notifications/read-all', [ClientApiController::class, 'readAllNotifications']);
    Route::post('/notifications/{id}/read', [ClientApiController::class, 'readNotification']);
    Route::get('/orders/{reference}/proofs/{proof}', [ClientApiController::class, 'proofFile']);

    // Application cliente
    Route::middleware(['abilities:client', 'role:client'])->group(function () {
        Route::get('/dashboard', [ClientApiController::class, 'dashboard']);
        Route::get('/corridors', [ClientApiController::class, 'corridors']);
        Route::post('/quote', [ClientApiController::class, 'quote']);
        Route::get('/payout-methods', [ClientApiController::class, 'methods']);
        Route::post('/payout-methods', [ClientApiController::class, 'storeMethod']);
        Route::delete('/payout-methods/{method}', [ClientApiController::class, 'destroyMethod']);
        Route::get('/orders', [ClientApiController::class, 'orders']);
        Route::post('/orders', [ClientApiController::class, 'storeOrder']);
        Route::get('/orders/{reference}', [ClientApiController::class, 'showOrder']);
        Route::post('/orders/{reference}/proof', [ClientApiController::class, 'proof']);
        Route::post('/orders/{reference}/flexpay', [ClientApiController::class, 'flexpay']);
        Route::post('/orders/{reference}/simulate-payment', [ClientApiController::class, 'simulatePayment']);
    });

    // Application Viratech Admin
    Route::prefix('admin')->middleware(['abilities:admin', 'role:operator,admin'])->group(function () {
        Route::get('/queue', [AdminApiController::class, 'queue']);
        Route::get('/orders', [AdminApiController::class, 'orders']);
        Route::get('/orders/{reference}', [AdminApiController::class, 'showOrder']);
        Route::post('/orders/{reference}/step', [AdminApiController::class, 'step']);
        Route::post('/orders/{reference}/block', [AdminApiController::class, 'block']);
        Route::post('/orders/{reference}/unblock', [AdminApiController::class, 'unblock']);
        Route::post('/orders/{reference}/reject', [AdminApiController::class, 'reject']);
        Route::get('/clients', [AdminApiController::class, 'clients']);
        Route::post('/clients/{user}/kyc', [AdminApiController::class, 'setKyc']);
        Route::get('/kyc', [AdminExtrasApiController::class, 'kycList']);
        Route::get('/kyc/{submission}', [AdminExtrasApiController::class, 'kycShow']);
        Route::get('/kyc/{submission}/file/{type}', [AdminExtrasApiController::class, 'kycFile']);
        Route::post('/kyc/{submission}/approve', [AdminExtrasApiController::class, 'kycApprove']);
        Route::post('/kyc/{submission}/reject', [AdminExtrasApiController::class, 'kycReject']);
        Route::post('/orders/{reference}/flexpay-payout', [AdminExtrasApiController::class, 'flexpayPayout']);
        Route::post('/orders/{reference}/simulate-payout', [AdminExtrasApiController::class, 'simulatePayout']);

        Route::middleware('role:admin')->group(function () {
            Route::get('/fees', [AdminApiController::class, 'fees']);
            Route::post('/fees/{corridor}', [AdminApiController::class, 'updateFees']);
            Route::post('/orders/{reference}/release-hold', [AdminExtrasApiController::class, 'releaseHold']);
            Route::get('/settings', [AdminExtrasApiController::class, 'settings']);
            Route::post('/settings', [AdminExtrasApiController::class, 'saveSettings']);
            Route::post('/settings/test-mail', [AdminExtrasApiController::class, 'testMail']);
            Route::get('/accounts', [AdminApiController::class, 'accounts']);
            Route::post('/accounts/{account}', [AdminApiController::class, 'updateAccount']);
        });
    });
});
