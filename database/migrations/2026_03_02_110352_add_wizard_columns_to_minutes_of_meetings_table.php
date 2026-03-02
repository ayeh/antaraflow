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
            $table->string('mom_number')->nullable()->after('status');
            $table->time('start_time')->nullable()->after('meeting_date');
            $table->time('end_time')->nullable()->after('start_time');
            $table->string('language', 10)->default('ms')->after('location');
            $table->string('prepared_by')->nullable()->after('language');
            $table->boolean('share_with_client')->default(false)->after('prepared_by');
            $table->foreignId('project_id')->nullable()->after('organization_id')
                ->constrained('projects')->nullOnDelete();

            $table->unique(['organization_id', 'mom_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('minutes_of_meetings', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropUnique(['organization_id', 'mom_number']);
            $table->dropColumn([
                'mom_number', 'start_time', 'end_time', 'language',
                'prepared_by', 'share_with_client', 'project_id',
            ]);
        });
    }
};
