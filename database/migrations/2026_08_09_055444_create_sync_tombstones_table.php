<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records deletions for entities that are removed outright rather than soft
 * deleted, so an offline client can learn a row is gone. Without it a device
 * that was offline during the delete keeps the record forever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_tombstones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('entity', 64);
            $table->unsignedBigInteger('entity_id');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'entity', 'deleted_at'], 'sync_tombstones_pull_index');
            $table->unique(['entity', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_tombstones');
    }
};
