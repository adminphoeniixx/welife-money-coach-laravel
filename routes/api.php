<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BudgetController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\CoachController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\DebtDocumentController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FamilyController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\InsightController;
use App\Http\Controllers\Api\LegalController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\VaultController;
use App\Http\Middleware\EnsureVaultUnlockedApi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MoneyCoach mobile API (Sanctum token auth)
|--------------------------------------------------------------------------
| Consumed by the native iOS app. Every screen in
| docs/MoneyCoach-iOS-app.html maps to endpoints below.
*/

// --- Public: auth & password reset (welcome / login / register / forgot) ---
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');
});

// Public legal content (legalPrivacy / legalTerms screens).
Route::get('legal/{document}', [LegalController::class, 'show'])->whereIn('document', ['privacy', 'terms']);

// --- Authenticated ---
Route::middleware('auth:sanctum')->group(function () {
    // Session / identity
    Route::get('user', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);

    // Onboarding (onbCurrency / onbGoal / onbNotif)
    Route::get('onboarding', [OnboardingController::class, 'show']);
    Route::post('onboarding', [OnboardingController::class, 'store']);

    // Home dashboard + debt payoff coach
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('coach', [CoachController::class, 'index']);

    // Transactions (income & expenses)
    Route::get('transactions', [TransactionController::class, 'index']);
    Route::post('entries', [TransactionController::class, 'store']);
    Route::put('entries/{entry}', [TransactionController::class, 'update']);
    Route::delete('entries/{entry}', [TransactionController::class, 'destroy']);

    // Debts (loans + credit cards) + payments + attachments
    Route::get('debts', [DebtController::class, 'index']);
    Route::get('debts/{debt}', [DebtController::class, 'show']);
    Route::post('debts', [DebtController::class, 'store']);
    Route::put('debts/{debt}', [DebtController::class, 'update']);
    Route::delete('debts/{debt}', [DebtController::class, 'destroy']);
    Route::post('debts/{debt}/payment', [DebtController::class, 'recordPayment']);
    Route::post('debts/{debt}/documents', [DebtDocumentController::class, 'store']);
    Route::get('debt-documents/{document}/view', [DebtDocumentController::class, 'view']);
    Route::get('debt-documents/{document}/download', [DebtDocumentController::class, 'download']);
    Route::delete('debt-documents/{document}', [DebtDocumentController::class, 'destroy']);

    // Assets / Net Worth
    Route::get('net-worth', [AssetController::class, 'index']);
    Route::post('assets', [AssetController::class, 'store']);
    Route::put('assets/{asset}', [AssetController::class, 'update']);
    Route::delete('assets/{asset}', [AssetController::class, 'destroy']);

    // Budgets, goals & emergency fund
    Route::get('planning', [BudgetController::class, 'index']);
    Route::post('budgets', [BudgetController::class, 'store']);
    Route::put('budgets/{budget}', [BudgetController::class, 'update']);
    Route::delete('budgets/{budget}', [BudgetController::class, 'destroy']);
    Route::post('goals', [GoalController::class, 'store']);
    Route::put('goals/{goal}', [GoalController::class, 'update']);
    Route::delete('goals/{goal}', [GoalController::class, 'destroy']);
    Route::post('goals/{goal}/contribute', [GoalController::class, 'contribute']);

    // Reminders (bills, EMIs, subscriptions)
    Route::get('reminders', [ReminderController::class, 'index']);
    Route::post('bills', [ReminderController::class, 'store']);
    Route::put('bills/{bill}', [ReminderController::class, 'update']);
    Route::delete('bills/{bill}', [ReminderController::class, 'destroy']);
    Route::post('bills/{bill}/paid', [ReminderController::class, 'markPaid']);

    // Family Finance Mode
    Route::get('family', [FamilyController::class, 'index']);
    Route::post('family', [FamilyController::class, 'store']);
    Route::delete('family', [FamilyController::class, 'destroy']);
    Route::post('family/leave', [FamilyController::class, 'leave']);
    Route::post('family/invite', [FamilyController::class, 'invite']);
    Route::delete('family/invitations/{invitation}', [FamilyController::class, 'cancelInvite']);
    Route::get('family/join/{token}', [FamilyController::class, 'showJoin']);
    Route::post('family/join/{token}', [FamilyController::class, 'join']);
    Route::delete('family/members/{member}', [FamilyController::class, 'removeMember']);
    Route::post('family/expenses', [FamilyController::class, 'storeExpense']);
    Route::delete('family/expenses/{entry}', [FamilyController::class, 'destroyExpense']);
    Route::post('family/budgets', [FamilyController::class, 'storeBudget']);
    Route::delete('family/budgets/{budget}', [FamilyController::class, 'destroyBudget']);

    // Insights: yearly analytics, calendar, search, achievements, notifications, reports, challenges
    Route::get('insights', [AnalyticsController::class, 'index']);
    Route::get('calendar', [CalendarController::class, 'index']);
    Route::get('search', [SearchController::class, 'index']);
    Route::get('achievements', [InsightController::class, 'achievements']);
    Route::get('notifications', [InsightController::class, 'notifications']);
    Route::get('reports', [ReportController::class, 'index']);
    Route::get('reports/export', [ReportController::class, 'exportCsv']);
    Route::get('challenges', [ChallengeController::class, 'index']);
    Route::post('challenges', [ChallengeController::class, 'store']);
    Route::post('challenges/{challenge}/progress', [ChallengeController::class, 'progress']);
    Route::delete('challenges/{challenge}', [ChallengeController::class, 'destroy']);

    // Secure Documents Vault — PIN-gated (vaultLock / vault)
    Route::prefix('vault')->group(function () {
        Route::get('gate', [VaultController::class, 'gate']);
        Route::post('pin', [VaultController::class, 'setPin']);
        Route::post('unlock', [VaultController::class, 'unlock'])->middleware('throttle:6,1');
        Route::post('lock', [VaultController::class, 'lock']);

        Route::middleware(EnsureVaultUnlockedApi::class)->group(function () {
            Route::get('/', [VaultController::class, 'index']);
            Route::post('documents', [DocumentController::class, 'store']);
            Route::get('documents/{document}/view', [DocumentController::class, 'view']);
            Route::get('documents/{document}/download', [DocumentController::class, 'download']);
            Route::post('documents/{document}', [DocumentController::class, 'update']);
            Route::delete('documents/{document}', [DocumentController::class, 'destroy']);
        });
    });

    // Settings & profile (profile / editProfile / setRegion / setNotif / setSecurity / dataPrivacy)
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::post('profile/photo', [ProfileController::class, 'updatePhoto']);
    Route::delete('profile/photo', [ProfileController::class, 'destroyPhoto']);
    Route::put('password', [ProfileController::class, 'updatePassword'])->middleware('throttle:6,1');
    Route::delete('account', [ProfileController::class, 'destroyAccount']);

    Route::get('settings/region', [SettingsController::class, 'showRegion']);
    Route::put('settings/region', [SettingsController::class, 'updateRegion']);
    Route::get('settings/notifications', [SettingsController::class, 'showNotifications']);
    Route::put('settings/notifications', [SettingsController::class, 'updateNotifications']);
    Route::get('settings/data-privacy', [SettingsController::class, 'dataPrivacy']);
    Route::get('settings/data-privacy/export', [SettingsController::class, 'exportData']);
});
