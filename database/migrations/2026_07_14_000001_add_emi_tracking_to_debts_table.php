<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            // Loan tenure and progress tracking (loans only).
            $table->unsignedInteger('total_emis')->nullable()->after('emi_cents');
            $table->unsignedInteger('emis_paid')->default(0)->after('total_emis');
        });
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->dropColumn(['total_emis', 'emis_paid']);
        });
    }
};
