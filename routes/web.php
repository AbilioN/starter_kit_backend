<?php

use App\Http\Controllers\Web\TenantSignupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Self-service tenant signup - deliberately NOT behind tenant.identify,
// since a signer-upper hasn't picked a subdomain yet by definition.
Route::post('/signup', TenantSignupController::class)->name('tenant.signup');
