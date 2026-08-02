<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * share_with_client never gated anything: no policy, controller or query ever read it,
 * so setting it granted no access and clearing it revoked none. Whether a meeting is
 * shared is now derived from active rows in mom_guest_accesses, which is what the
 * public guest route actually checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('minutes_of_meetings', 'share_with_client')) {
            return;
        }

        Schema::table('minutes_of_meetings', function (Blueprint $table) {
            $table->dropColumn('share_with_client');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('minutes_of_meetings', 'share_with_client')) {
            return;
        }

        Schema::table('minutes_of_meetings', function (Blueprint $table) {
            $table->boolean('share_with_client')->default(false)->after('prepared_by');
        });
    }
};
