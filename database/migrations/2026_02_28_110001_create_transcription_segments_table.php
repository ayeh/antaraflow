<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcription_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audio_transcription_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->string('speaker')->nullable();
            $table->float('start_time');
            $table->float('end_time');
            $table->float('confidence')->nullable();
            $table->integer('sequence_order');
            $table->boolean('is_edited')->default(false);
            $table->timestamps();
            $table->index(['audio_transcription_id', 'sequence_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcription_segments');
    }
};
