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
        Schema::create('mom_circulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('minutes_of_meeting_id')->constrained('minutes_of_meetings')->cascadeOnDelete();
            $table->foreignId('sent_by')->constrained('users');
            $table->unsignedTinyInteger('round')->default(1);
            $table->string('subject');
            $table->text('body_note')->nullable();
            $table->dateTime('deadline_at');
            $table->string('status')->default('open');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mom_circulations');
    }
};
