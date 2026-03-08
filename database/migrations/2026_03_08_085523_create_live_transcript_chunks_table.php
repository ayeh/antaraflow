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
        Schema::create('live_transcript_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_meeting_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_number');
            $table->string('audio_file_path')->nullable();
            $table->text('text')->nullable();
            $table->string('speaker')->nullable();
            $table->double('start_time')->default(0);
            $table->double('end_time')->default(0);
            $table->double('confidence')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['live_meeting_session_id', 'chunk_number'], 'ltc_session_chunk_index');
            $table->index(['live_meeting_session_id', 'status'], 'ltc_session_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_transcript_chunks');
    }
};
