<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment history for a loan / card, so the loanDetail / cardDetail screens can
 * show a real "Payment history" list (June EMI, May EMI, …) instead of a mock.
 * One row per recorded payment; `balance_after_cents` snapshots the balance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('balance_after_cents');
            $table->unsignedInteger('emi_number')->nullable();
            $table->date('paid_on');
            $table->timestamps();

            $table->index(['debt_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_payments');
    }
};
