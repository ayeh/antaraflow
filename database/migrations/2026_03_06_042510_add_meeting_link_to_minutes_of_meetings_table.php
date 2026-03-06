<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('minutes_of_meetings', function (Blueprint $table) {
            $table->string('meeting_link', 2048)->nullable()->after('meeting_type');
            $table->string('meeting_platform')->nullable()->after('meeting_link');
        });
    }

    public function down(): void
    {
        Schema::table('minutes_of_meetings', function (Blueprint $table) {
            $table->dropColumn(['meeting_link', 'meeting_platform']);
        });
    }
};
