<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->string('feature')->nullable()->after('operation');
            $table->unsignedInteger('cached_tokens')->default(0)->after('completion_tokens');
            $table->unsignedInteger('duration_ms')->nullable()->after('audio_seconds');
            $table->string('status')->default('success')->after('duration_ms');

            $table->index(['feature', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropIndex(['feature', 'created_at']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['feature', 'cached_tokens', 'duration_ms', 'status']);
        });
    }
};
