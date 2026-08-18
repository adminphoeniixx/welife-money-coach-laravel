<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Read receipts for the derived notification feed.
     *
     * Notifications are computed from live finance data rather than stored, so
     * only the "seen" state needs persisting. The key is deterministic per
     * notification (e.g. `bill_due:12:2026-08-20`), which means a bill that
     * rolls forward to a new due date becomes unread again on its own.
     */
    public function up(): void
    {
        if (Schema::hasTable('notification_reads')) {
            return;
        }

        Schema::create('notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 191);
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
    }
};
