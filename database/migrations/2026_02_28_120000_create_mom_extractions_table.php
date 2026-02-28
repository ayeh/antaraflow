<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mom_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->longText('content');
            $table->json('structured_data')->nullable();
            $table->string('provider');
            $table->string('model');
            $table->float('confidence_score')->nullable();
            $table->integer('token_usage')->nullable();
            $table->timestamps();

            $table->index(['minutes_of_meeting_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mom_extractions');
    }
};
