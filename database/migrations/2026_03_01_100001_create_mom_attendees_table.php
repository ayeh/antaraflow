<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mom_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('role')->default('participant');
            $table->string('rsvp_status')->default('pending');
            $table->boolean('is_present')->default(false);
            $table->boolean('is_external')->default(false);
            $table->string('department')->nullable();
            $table->timestamps();
            $table->unique(['minutes_of_meeting_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mom_attendees');
    }
};
