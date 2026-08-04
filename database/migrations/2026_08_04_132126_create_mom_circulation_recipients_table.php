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
        Schema::create('mom_circulation_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mom_circulation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mom_attendee_id')->nullable()->constrained('mom_attendees')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->string('response')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->dateTime('deemed_confirmed_at')->nullable();
            $table->dateTime('first_opened_at')->nullable();
            $table->dateTime('last_opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->string('responded_ip', 45)->nullable();
            $table->string('responded_user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mom_circulation_recipients');
    }
};
