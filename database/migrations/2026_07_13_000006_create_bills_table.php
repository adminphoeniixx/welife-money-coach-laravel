<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('debt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            // bill | subscription | emi
            $table->string('kind')->default('bill');
            $table->string('category')->nullable();
            $table->bigInteger('amount_cents')->default(0);
            $table->string('currency', 3)->default('INR');
            $table->date('due_date');
            $table->string('repeat')->default('monthly'); // none | monthly | yearly | weekly
            $table->unsignedTinyInteger('remind_days_before')->default(3);
            $table->string('status')->default('upcoming'); // upcoming | paid | overdue
            $table->date('paid_on')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
