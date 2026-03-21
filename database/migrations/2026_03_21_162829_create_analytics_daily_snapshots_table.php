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
        Schema::create('analytics_daily_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('total_meetings')->default(0);
            $table->unsignedInteger('total_action_items')->default(0);
            $table->unsignedInteger('completed_action_items')->default(0);
            $table->unsignedInteger('overdue_action_items')->default(0);
            $table->unsignedInteger('total_attendees')->default(0);
            $table->unsignedInteger('ai_usage_count')->default(0);
            $table->decimal('avg_meeting_duration_minutes', 8, 2)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'snapshot_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_snapshots');
    }
};
