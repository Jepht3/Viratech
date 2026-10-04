<?php

use App\Http\Controllers\Admin\AdminIntegrationsController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\KycAdminController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PayoutMethodController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect(auth()->user()->isStaff() ? '/admin' : '/tableau-de-bord') : view('welcome'));

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/connexion', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/inscription', [AuthController::class, 'showRegister']);
    Route::post('/inscription', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [AuthController::class, 'logout']);

    // Espace client (le site web fait tout ce que fait l'application mobile)
    Route::middleware('role:client')->group(function () {
        Route::get('/tableau-de-bord', [ClientController::class, 'dashboard']);
        Route::get('/echange/nouveau', [OrderController::class, 'create']);
        Route::post('/echange/devis', [OrderController::class, 'quote']);
        Route::post('/echange', [OrderController::class, 'store']);
        Route::get('/commandes', [OrderController::class, 'index']);
        Route::post('/commandes/{reference}/preuve', [OrderController::class, 'proof']);
        Route::post('/commandes/{reference}/flexpay', [OrderController::class, 'flexpay']);
        Route::post('/commandes/{reference}/simuler-paiement', [OrderController::class, 'simulatePayment']);
        Route::get('/moyens-de-reception', [PayoutMethodController::class, 'index']);
        Route::post('/moyens-de-reception', [PayoutMethodController::class, 'store']);
        Route::delete('/moyens-de-reception/{method}', [PayoutMethodController::class, 'destroy']);
    });

    Route::get('/commandes/{reference}', [OrderController::class, 'show']);
    Route::get('/commandes/{reference}/preuves/{proof}', [OrderController::class, 'proofFile']);

    // Profil : photo, téléphone, vérification d'identité (clients et équipe)
    Route::get('/profil', [ProfileController::class, 'show']);
    Route::post('/profil/photo', [ProfileController::class, 'avatar']);
    Route::post('/profil/email/code', [ProfileController::class, 'sendEmailCode'])->middleware('throttle:5,10');
    Route::post('/profil/email/verifier', [ProfileController::class, 'verifyEmail'])->middleware('throttle:10,10');
    Route::post('/profil/verification/piece', [ProfileController::class, 'kycDocument'])->middleware('throttle:10,60');
    Route::post('/profil/verification/code', [ProfileController::class, 'newChallenge']);
    Route::post('/profil/verification/selfie', [ProfileController::class, 'kycSelfie'])->middleware('throttle:10,60');
    Route::get('/avatar/{id}', [ProfileController::class, 'avatarFile']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/lire', [NotificationController::class, 'readAll']);
    Route::get('/notifications/{id}', [NotificationController::class, 'open']);
    Route::post('/preferences-notifications', [NotificationController::class, 'preferences']);

    // Console opérateur / admin
    Route::prefix('admin')->middleware('role:operator,admin')->group(function () {
        Route::get('/', [AdminOrderController::class, 'queue']);
        Route::get('/commandes', [AdminOrderController::class, 'index']);
        Route::get('/commandes/{reference}', [AdminOrderController::class, 'show']);
        Route::post('/commandes/{reference}/etape', [AdminOrderController::class, 'step']);
        Route::post('/commandes/{reference}/bloquer', [AdminOrderController::class, 'block']);
        Route::post('/commandes/{reference}/debloquer', [AdminOrderController::class, 'unblock']);
        Route::post('/commandes/{reference}/refuser', [AdminOrderController::class, 'reject']);
        Route::post('/commandes/{reference}/versement-flexpay', [AdminOrderController::class, 'flexpayPayout']);
        Route::post('/commandes/{reference}/simuler-versement', [AdminOrderController::class, 'simulatePayout']);
        Route::get('/verifications', [KycAdminController::class, 'index']);
        Route::get('/verifications/{submission}', [KycAdminController::class, 'show']);
        Route::get('/verifications/{submission}/fichier/{type}', [KycAdminController::class, 'file']);
        Route::post('/verifications/{submission}/approuver', [KycAdminController::class, 'approve']);
        Route::post('/verifications/{submission}/refuser', [KycAdminController::class, 'reject']);
        Route::get('/clients', [AdminSettingsController::class, 'clients']);
        Route::post('/clients/{user}/kyc', [AdminSettingsController::class, 'setKyc']);

        // Réservé à l'administrateur : barème de frais, comptes de réception, versions
        Route::middleware('role:admin')->group(function () {
            Route::get('/frais', [AdminSettingsController::class, 'fees']);
            Route::post('/frais/{corridor}', [AdminSettingsController::class, 'updateFees']);
            Route::get('/comptes', [AdminSettingsController::class, 'accounts']);
            Route::post('/comptes/{account}', [AdminSettingsController::class, 'updateAccount']);
            Route::get('/audit', [AdminSettingsController::class, 'audit']);
            Route::post('/commandes/{reference}/lever-delai', [AdminOrderController::class, 'releaseHold']);
            Route::get('/parametres', [AdminIntegrationsController::class, 'show']);
            Route::post('/parametres', [AdminIntegrationsController::class, 'save']);
            Route::post('/parametres/test-email', [AdminIntegrationsController::class, 'testMail']);
        });
    });
});
