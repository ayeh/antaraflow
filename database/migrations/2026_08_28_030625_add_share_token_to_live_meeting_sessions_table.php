<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The opaque token a primary hands to a colleague in the room so their
     * phone can join the same sitting as a satellite. Minted lazily on the
     * first invite and unique, so it doubles as the lookup key.
     */
    public function up(): void
    {
        Schema::table('live_meeting_sessions', function (Blueprint $table): void {
            $table->string('share_token', 64)->nullable()->unique()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('live_meeting_sessions', function (Blueprint $table): void {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
