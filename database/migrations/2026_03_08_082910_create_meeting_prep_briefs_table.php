<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_prep_briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained('mom_attendees')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('content');
            $table->json('summary_highlights')->nullable();
            $table->unsignedInteger('estimated_prep_minutes')->default(0);
            $table->timestamp('generated_at');
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->json('sections_read')->nullable();
            $table->timestamps();

            $table->index(['minutes_of_meeting_id', 'attendee_id']);
            $table->index(['user_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_prep_briefs');
    }
};
