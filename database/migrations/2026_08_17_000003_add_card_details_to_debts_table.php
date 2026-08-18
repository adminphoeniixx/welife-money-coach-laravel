<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Credit-card identity + statement fields the app's card screens need:
     * network (Visa / Mastercard / RuPay / Amex), the last four digits, and
     * the current statement due (which is separate from the running balance).
     */
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            if (! Schema::hasColumn('debts', 'card_network')) {
                $table->string('card_network', 30)->nullable();
            }
            if (! Schema::hasColumn('debts', 'card_last4')) {
                $table->string('card_last4', 4)->nullable();
            }
            if (! Schema::hasColumn('debts', 'current_due_cents')) {
                $table->bigInteger('current_due_cents')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            foreach (['card_network', 'card_last4', 'current_due_cents'] as $column) {
                if (Schema::hasColumn('debts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
