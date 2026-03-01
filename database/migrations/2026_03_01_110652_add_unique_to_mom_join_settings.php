<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mom_join_settings', function (Blueprint $table) {
            $table->unique('minutes_of_meeting_id');
        });
    }

    public function down(): void
    {
        Schema::table('mom_join_settings', function (Blueprint $table) {
            $table->dropUnique(['minutes_of_meeting_id']);
        });
    }
};
