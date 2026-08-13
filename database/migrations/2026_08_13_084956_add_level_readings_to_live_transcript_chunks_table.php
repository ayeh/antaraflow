<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How loud each chunk was when it was captured, as measured on the device.
 *
 * Recorded so that "the transcript is poor" can be answered rather than
 * guessed at. Joined against the confidence already on this row, these three
 * numbers separate a microphone that never heard the speaker from audio that
 * arrived fine and was transcribed badly — which are opposite problems with
 * opposite fixes, and the capture-quality plan currently has no evidence for
 * choosing between them.
 *
 * Nullable throughout: the browser recorder does not measure, older builds of
 * the app do not send, and a chunk shorter than one analysis frame has nothing
 * to report. A null means no measurement was taken, never a measurement of
 * silence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_transcript_chunks', function (Blueprint $table) {
            $table->double('peak_dbfs')->nullable()->after('confidence');
            $table->double('speech_dbfs')->nullable()->after('peak_dbfs');
            $table->double('noise_dbfs')->nullable()->after('speech_dbfs');
        });
    }

    public function down(): void
    {
        Schema::table('live_transcript_chunks', function (Blueprint $table) {
            $table->dropColumn(['peak_dbfs', 'speech_dbfs', 'noise_dbfs']);
        });
    }
};
