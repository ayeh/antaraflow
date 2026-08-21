<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audio_transcriptions', function (Blueprint $table) {
            // Human-readable "who recorded this and on what": browser + OS for
            // web recordings, platform + model for the app, each carrying an
            // app-vs-web tag. Nullable because every recording made before this
            // column existed has no way to know, and the UI falls back to the
            // old Browser/Live source badge for those.
            $table->string('device_label')->nullable()->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('audio_transcriptions', function (Blueprint $table) {
            $table->dropColumn('device_label');
        });
    }
};
