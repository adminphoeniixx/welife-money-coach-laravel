<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mobile app's profile and region screens need a phone number, a timezone
 * and a number-grouping preference alongside the existing currency/locale.
 *
 * `number_format` defaults to `indian` so existing (all-INR) accounts keep the
 * exact formatting they have today; new accounts get theirs from the country
 * chosen at sign-up (see User::applyRegion).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('timezone', 64)->nullable()->after('locale');
            $table->string('number_format', 16)->default('indian')->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'timezone', 'number_format']);
        });
    }
};
