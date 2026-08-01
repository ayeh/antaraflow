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
        Schema::table('mom_attendees', function (Blueprint $table) {
            // Full job title, e.g. "Ketua Pusat Pengajian Pengurusan Landskap"
            $table->string('position')->nullable()->after('role');
            // Secondary org line, e.g. "Institut Latihan Perumahan dan Kerajaan Tempatan"
            $table->string('organization_unit')->nullable()->after('position');
            // Three-way attendance: present, also_present (turut hadir), absent
            $table->string('attendance_group', 20)->default('present')->after('is_present');
            // Inline role note, e.g. "-Pengerusi-", "-Pencatit minit-"
            $table->string('annotation', 100)->nullable()->after('attendance_group');
            // Reason for absence, e.g. "cuti separuh gaji"
            $table->string('absence_reason', 255)->nullable()->after('annotation');
            // Display order within each attendance group
            $table->unsignedSmallInteger('sort_order')->default(0)->after('absence_reason');
        });
    }

    public function down(): void
    {
        Schema::table('mom_attendees', function (Blueprint $table) {
            $table->dropColumn([
                'position',
                'organization_unit',
                'attendance_group',
                'annotation',
                'absence_reason',
                'sort_order',
            ]);
        });
    }
};
