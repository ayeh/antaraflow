<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recording_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('acknowledged_by')->constrained('users')->cascadeOnDelete();
            $table->string('notice_version', 32);
            $table->boolean('includes_tab_audio')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            // dateTime, not timestamp: on this project's MySQL a bare TIMESTAMP
            // column silently gains ON UPDATE CURRENT_TIMESTAMP and would rewrite
            // the moment consent was given on every later touch. This value is
            // set once, explicitly, and must never move.
            $table->dateTime('acknowledged_at');
            $table->timestamps();

            $table->index('minutes_of_meeting_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recording_consents');
    }
};
