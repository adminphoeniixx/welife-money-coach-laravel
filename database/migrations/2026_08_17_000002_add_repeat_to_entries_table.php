<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recurring metadata for a ledger entry, so a transaction the user marked
     * as repeating can be shown with its repeat schedule on the detail screen
     * and projected onto the calendar.
     */
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            if (! Schema::hasColumn('entries', 'repeat')) {
                $table->string('repeat', 20)->default('none');
            }
            if (! Schema::hasColumn('entries', 'repeat_until')) {
                $table->date('repeat_until')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            foreach (['repeat', 'repeat_until'] as $column) {
                if (Schema::hasColumn('entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
