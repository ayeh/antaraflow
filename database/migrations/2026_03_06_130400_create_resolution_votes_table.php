<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resolution_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resolution_id')->constrained('meeting_resolutions')->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained('mom_attendees')->cascadeOnDelete();
            $table->string('vote', 10);
            $table->timestamp('voted_at');
            $table->timestamps();

            $table->unique(['resolution_id', 'attendee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resolution_votes');
    }
};
