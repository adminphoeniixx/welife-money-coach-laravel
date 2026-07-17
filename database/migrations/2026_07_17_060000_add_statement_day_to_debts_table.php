<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit-card statement date (day of month) — collected by the addCard screen
 * ("Statement date: 5th") but previously not stored. Separate from `due_day`,
 * which is the payment due date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->unsignedTinyInteger('statement_day')->nullable()->after('due_day'); // 1-31
        });
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->dropColumn('statement_day');
        });
    }
};
