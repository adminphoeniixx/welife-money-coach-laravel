<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store the preferences the onboarding + settings screens collect
 * (currency/region, the user's primary goal, notification toggles) so the
 * mobile API can persist them rather than keeping them client-side only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('currency', 3)->default('INR')->after('email');
            $table->string('locale', 12)->default('en-IN')->after('currency');
            $table->string('country', 2)->nullable()->after('locale');
            $table->string('primary_goal')->nullable()->after('country');
            $table->boolean('notifications_enabled')->default(true)->after('primary_goal');
            $table->json('notification_prefs')->nullable()->after('notifications_enabled');
            $table->boolean('onboarded')->default(false)->after('notification_prefs');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'currency', 'locale', 'country', 'primary_goal',
                'notifications_enabled', 'notification_prefs', 'onboarded',
            ]);
        });
    }
};
