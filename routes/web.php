<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtCoachController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\InsightController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\VaultController;
use App\Http\Middleware\EnsureVaultUnlocked;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Transactions (income & expenses)
    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::post('entries', [TransactionController::class, 'store'])->name('entries.store');
    Route::put('entries/{entry}', [TransactionController::class, 'update'])->name('entries.update');
    Route::delete('entries/{entry}', [TransactionController::class, 'destroy'])->name('entries.destroy');

    // Debts (loans + credit cards) + payoff coach
    Route::get('debts', [DebtController::class, 'index'])->name('debts.index');
    Route::post('debts', [DebtController::class, 'store'])->name('debts.store');
    Route::put('debts/{debt}', [DebtController::class, 'update'])->name('debts.update');
    Route::delete('debts/{debt}', [DebtController::class, 'destroy'])->name('debts.destroy');
    Route::post('debts/{debt}/payment', [DebtController::class, 'recordPayment'])->name('debts.payment');
    Route::get('coach', [DebtCoachController::class, 'index'])->name('coach.index');

    // Assets / Net Worth
    Route::get('net-worth', [AssetController::class, 'index'])->name('assets.index');
    Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
    Route::put('assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
    Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');

    // Budgets & goals
    Route::get('planning', [BudgetController::class, 'index'])->name('planning.index');
    Route::post('budgets', [BudgetController::class, 'store'])->name('budgets.store');
    Route::put('budgets/{budget}', [BudgetController::class, 'update'])->name('budgets.update');
    Route::delete('budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');
    Route::post('goals', [GoalController::class, 'store'])->name('goals.store');
    Route::put('goals/{goal}', [GoalController::class, 'update'])->name('goals.update');
    Route::delete('goals/{goal}', [GoalController::class, 'destroy'])->name('goals.destroy');
    Route::post('goals/{goal}/contribute', [GoalController::class, 'contribute'])->name('goals.contribute');

    // Reminders (bills, EMIs, subscriptions)
    Route::get('reminders', [ReminderController::class, 'index'])->name('reminders.index');
    Route::post('bills', [ReminderController::class, 'store'])->name('bills.store');
    Route::put('bills/{bill}', [ReminderController::class, 'update'])->name('bills.update');
    Route::delete('bills/{bill}', [ReminderController::class, 'destroy'])->name('bills.destroy');
    Route::post('bills/{bill}/paid', [ReminderController::class, 'markPaid'])->name('bills.paid');

    // Family Finance Mode
    Route::get('family', [FamilyController::class, 'index'])->name('family.index');
    Route::post('family', [FamilyController::class, 'store'])->name('family.store');
    Route::delete('family', [FamilyController::class, 'destroy'])->name('family.destroy');
    Route::post('family/leave', [FamilyController::class, 'leave'])->name('family.leave');
    Route::post('family/invite', [FamilyController::class, 'invite'])->name('family.invite');
    Route::delete('family/invitations/{invitation}', [FamilyController::class, 'cancelInvite'])->name('family.invite.cancel');
    Route::get('family/join/{token}', [FamilyController::class, 'showJoin'])->name('family.join');
    Route::post('family/join/{token}', [FamilyController::class, 'join'])->name('family.join.accept');
    Route::delete('family/members/{member}', [FamilyController::class, 'removeMember'])->name('family.members.remove');
    Route::post('family/expenses', [FamilyController::class, 'storeExpense'])->name('family.expenses.store');
    Route::delete('family/expenses/{entry}', [FamilyController::class, 'destroyExpense'])->name('family.expenses.destroy');
    Route::post('family/budgets', [FamilyController::class, 'storeBudget'])->name('family.budgets.store');
    Route::delete('family/budgets/{budget}', [FamilyController::class, 'destroyBudget'])->name('family.budgets.destroy');

    // Calendar, search, insights, reports, challenges
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('search', [SearchController::class, 'index'])->name('search.index');
    Route::get('achievements', [InsightController::class, 'achievements'])->name('achievements.index');
    Route::get('notifications', [InsightController::class, 'notifications'])->name('notifications.index');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'exportCsv'])->name('reports.export');
    Route::get('challenges', [ChallengeController::class, 'index'])->name('challenges.index');
    Route::post('challenges', [ChallengeController::class, 'store'])->name('challenges.store');
    Route::post('challenges/{challenge}/progress', [ChallengeController::class, 'progress'])->name('challenges.progress');
    Route::delete('challenges/{challenge}', [ChallengeController::class, 'destroy'])->name('challenges.destroy');

    // Secure Documents Vault — PIN-gated.
    Route::prefix('vault')->name('vault.')->group(function () {
        Route::get('gate', [VaultController::class, 'gate'])->name('gate');
        Route::post('pin', [VaultController::class, 'setPin'])->name('pin');
        Route::post('unlock', [VaultController::class, 'unlock'])->middleware('throttle:6,1')->name('unlock');
        Route::post('lock', [VaultController::class, 'lock'])->name('lock');

        Route::middleware(EnsureVaultUnlocked::class)->group(function () {
            Route::get('/', [VaultController::class, 'index'])->name('index');
            Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
            Route::get('documents/{document}/view', [DocumentController::class, 'view'])->name('documents.view');
            Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
            Route::post('documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
            Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
        });
    });
});

// Available to the impersonated (possibly non-admin) session, so it lives
// outside the admin route group.
Route::middleware('auth')->post('stop-impersonating', [UserController::class, 'stopImpersonating'])
    ->name('impersonate.stop');

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
