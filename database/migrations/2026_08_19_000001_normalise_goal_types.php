<?php

use App\Support\Options;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fold legacy goal types onto their canonical keys.
 *
 * `goals.type` is a plain string with no enum constraint, so rows written
 * before the `Rule::in` validation landed can hold "Emergency Fund" or
 * "emergency-fund". The model normalises on read, but queries that filter in
 * SQL (`where('type', 'emergency_fund')`) see the raw column, so the stored
 * values have to be corrected too.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Options::goalTypeKeys() as $key) {
            DB::table('goals')
                ->where('type', '!=', $key)
                ->whereRaw("replace(replace(lower(trim(type)), ' ', '_'), '-', '_') = ?", [$key])
                ->update(['type' => $key]);
        }
    }

    /**
     * Irreversible on purpose: the original spelling of a folded row is not
     * worth restoring, and the canonical key is what every reader expects.
     */
    public function down(): void
    {
        //
    }
};
