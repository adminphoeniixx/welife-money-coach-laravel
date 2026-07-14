<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key'); // preset slug
            $table->string('title');
            $table->string('description')->nullable();
            $table->bigInteger('target_cents')->default(0);
            $table->bigInteger('progress_cents')->default(0);
            $table->string('status')->default('active'); // active | completed
            $table->date('started_on');
            $table->date('ends_on');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
