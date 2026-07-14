<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('institution')->nullable();
            // loan | credit_card
            $table->string('kind')->default('loan');
            // home, vehicle, gold, personal, education, business, custom (loans only)
            $table->string('category')->nullable();
            $table->bigInteger('principal_cents')->default(0);
            $table->bigInteger('balance_cents')->default(0);
            $table->decimal('interest_rate', 6, 2)->default(0); // annual APR %
            $table->bigInteger('emi_cents')->default(0); // monthly payment / min due
            $table->bigInteger('credit_limit_cents')->nullable(); // cards only
            $table->bigInteger('min_due_cents')->nullable(); // cards only
            $table->unsignedTinyInteger('due_day')->nullable(); // day of month 1-31
            $table->string('currency', 3)->default('INR');
            $table->string('status')->default('active'); // active | closed
            $table->date('opened_on')->nullable();
            $table->date('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
