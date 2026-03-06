<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('minutes_of_meetings')->cascadeOnDelete();
            $table->string('resolution_number', 20);
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('mover_id')->nullable()->constrained('mom_attendees')->nullOnDelete();
            $table->foreignId('seconder_id')->nullable()->constrained('mom_attendees')->nullOnDelete();
            $table->string('status', 20)->default('proposed');
            $table->timestamps();

            $table->index(['meeting_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_resolutions');
    }
};
