<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minutes_of_meetings', function (Blueprint $table) {
            $table->string('calendar_event_id')->nullable()->after('metadata');
            $table->string('calendar_provider')->nullable()->after('calendar_event_id');
            $table->timestamp('calendar_synced_at')->nullable()->after('calendar_provider');
        });
    }

    public function down(): void
    {
        Schema::table('minutes_of_meetings', function (Blueprint $table) {
            $table->dropColumn(['calendar_event_id', 'calendar_provider', 'calendar_synced_at']);
        });
    }
};
