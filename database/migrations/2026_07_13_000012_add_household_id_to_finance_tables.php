<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->foreignId('household_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::table('budgets', function (Blueprint $table) {
            // Personal vs family budgets now share this table, so uniqueness is
            // enforced in the application layer (per user, or per household).
            $table->dropUnique(['user_id', 'category']);
            $table->foreignId('household_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('household_id');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('household_id');
            $table->unique(['user_id', 'category']);
        });
    }
};
