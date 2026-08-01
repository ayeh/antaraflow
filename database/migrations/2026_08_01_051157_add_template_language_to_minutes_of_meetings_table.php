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
        Schema::table('minutes_of_meetings', function (Blueprint $table) {
            $table->foreignId('export_template_id')->nullable()->constrained('export_templates')->nullOnDelete()->after('meeting_template_id');
            $table->string('output_language', 10)->nullable()->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('minutes_of_meetings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('export_template_id');
            $table->dropColumn('output_language');
        });
    }
};
