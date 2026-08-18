<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('entry_attachments')) {
            return;
        }

        Schema::create('entry_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->bigInteger('size_bytes')->default(0);
            $table->string('path');
            $table->timestamps();

            $table->index(['user_id', 'entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_attachments');
    }
};
