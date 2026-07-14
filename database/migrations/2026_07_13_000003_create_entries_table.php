<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // income | expense
            $table->string('category')->nullable();
            $table->bigInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('INR');
            $table->string('description')->nullable();
            $table->string('payee')->nullable(); // paid to / received from
            $table->string('method')->nullable(); // UPI, card, cash, auto-debit...
            $table->date('occurred_on');
            $table->timestamps();

            $table->index(['user_id', 'type', 'occurred_on']);
            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
