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
        Schema::create('live_meeting_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('started_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->json('config')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('total_duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['minutes_of_meeting_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_meeting_sessions');
    }
};
