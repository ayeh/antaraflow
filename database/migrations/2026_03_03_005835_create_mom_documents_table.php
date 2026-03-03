<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mom_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('status')->default('uploaded');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mom_documents');
    }
};
