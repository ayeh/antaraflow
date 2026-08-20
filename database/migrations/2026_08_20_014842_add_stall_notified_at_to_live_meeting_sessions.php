<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_meeting_sessions', function (Blueprint $table) {
            // When the user was told this session's recording appears to have
            // stopped. Set once so the minute-by-minute watcher alerts only
            // once per stall, and cleared if chunks start flowing again.
            $table->timestamp('stall_notified_at')->nullable()->after('ended_at');
        });
    }

    public function down(): void
    {
        Schema::table('live_meeting_sessions', function (Blueprint $table) {
            $table->dropColumn('stall_notified_at');
        });
    }
};
