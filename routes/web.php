<?php

use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

// Available to the impersonated (possibly non-admin) session, so it lives
// outside the admin route group.
Route::middleware('auth')->post('stop-impersonating', [UserController::class, 'stopImpersonating'])
    ->name('impersonate.stop');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
