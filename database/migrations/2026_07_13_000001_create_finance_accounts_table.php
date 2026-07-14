<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // bank, cash, gold, fixed_deposit, mutual_fund, stocks, property, other
            $table->string('type')->default('bank');
            $table->bigInteger('balance_cents')->default(0);
            $table->string('currency', 3)->default('INR');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_accounts');
    }
};
