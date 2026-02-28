<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mom_ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->longText('message');
            $table->json('context')->nullable();
            $table->integer('token_usage')->nullable();
            $table->string('provider')->nullable();
            $table->timestamps();
            $table->index(['minutes_of_meeting_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mom_ai_conversations');
    }
};
