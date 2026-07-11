<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryTemplateController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DataRequestController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\LogAdminActivity;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin', LogAdminActivity::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // User support
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/admin', [UserController::class, 'toggleAdmin'])->name('users.toggle-admin');
        Route::patch('users/{user}/verified', [UserController::class, 'toggleVerified'])->name('users.toggle-verified');
        Route::patch('users/{user}/suspend', [UserController::class, 'toggleSuspend'])->name('users.toggle-suspend');
        Route::post('users/{user}/password-reset', [UserController::class, 'sendPasswordReset'])->name('users.password-reset');
        Route::post('users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // Category templates
        Route::get('categories', [CategoryTemplateController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryTemplateController::class, 'store'])->name('categories.store');
        Route::patch('categories/{category}', [CategoryTemplateController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [CategoryTemplateController::class, 'destroy'])->name('categories.destroy');

        // Subscription plans
        Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
        Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
        Route::patch('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');

        // Subscriptions & revenue
        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::patch('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
        Route::patch('subscriptions/{subscription}/reactivate', [SubscriptionController::class, 'reactivate'])->name('subscriptions.reactivate');

        // Content management
        Route::get('content', [ContentController::class, 'index'])->name('content.index');
        Route::post('content', [ContentController::class, 'store'])->name('content.store');
        Route::patch('content/{content}', [ContentController::class, 'update'])->name('content.update');
        Route::delete('content/{content}', [ContentController::class, 'destroy'])->name('content.destroy');

        // Platform settings
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::patch('settings', [SettingController::class, 'update'])->name('settings.update');

        // Audit log
        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');

        // Compliance (data export / deletion requests)
        Route::get('compliance', [DataRequestController::class, 'index'])->name('compliance.index');
        Route::patch('compliance/{dataRequest}/complete', [DataRequestController::class, 'complete'])->name('compliance.complete');
        Route::patch('compliance/{dataRequest}/reject', [DataRequestController::class, 'reject'])->name('compliance.reject');
    });
