<?php

use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\SubscriptionPlans;
use App\Livewire\Tenants;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Prefixed with /god and using the 'web' middleware group - see
// bootstrap/app.php's `then:` closure. Never wrapped by tenant.identify.

Route::get('/login', Login::class)->name('god.login');

Route::post('/logout', function () {
    Auth::guard('godadmin')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/god/login');
})->middleware('auth:godadmin')->name('god.logout');

Route::middleware('auth:godadmin')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('god.dashboard');

    Route::get('/subscription-plans', SubscriptionPlans\Index::class)->name('god.subscription-plans.index');
    Route::get('/subscription-plans/create', SubscriptionPlans\Form::class)->name('god.subscription-plans.create');
    Route::get('/subscription-plans/{planId}/edit', SubscriptionPlans\Form::class)->name('god.subscription-plans.edit');

    Route::get('/tenants', Tenants\Index::class)->name('god.tenants.index');
    Route::get('/tenants/create', Tenants\Create::class)->name('god.tenants.create');
    Route::get('/tenants/{tenantId}', Tenants\Show::class)->name('god.tenants.show');
});
