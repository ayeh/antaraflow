<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('quorum_type', 20)->default('percentage');
            $table->integer('quorum_value')->default(50);
            $table->boolean('require_chair')->default(false);
            $table->boolean('require_secretary')->default(false);
            $table->boolean('voting_enabled')->default(true);
            $table->boolean('chair_casting_vote')->default(false);
            $table->boolean('block_finalization_without_quorum')->default(false);
            $table->timestamps();

            $table->unique('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_settings');
    }
};
