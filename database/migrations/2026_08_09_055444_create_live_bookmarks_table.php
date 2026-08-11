<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moments flagged by hand while a live session is running. The phone is on the
 * table during the meeting, so marking "this bit matters" has to be one tap and
 * cannot wait for the transcript to catch up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_meeting_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->double('at_seconds');
            $table->string('label')->nullable();
            $table->string('kind', 20)->default('general');
            $table->timestamps();

            $table->index(['live_meeting_session_id', 'at_seconds'], 'live_bookmarks_session_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_bookmarks');
    }
};
