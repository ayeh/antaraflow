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
        Schema::table('comments', function (Blueprint $table) {
            // Allow guest comments by making user_id optional.
            $table->foreignId('user_id')->nullable()->change();

            // Track which circulation recipient left the remark.
            $table->foreignId('mom_circulation_recipient_id')
                ->nullable()
                ->constrained('mom_circulation_recipients')
                ->nullOnDelete()
                ->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['mom_circulation_recipient_id']);
            $table->dropColumn('mom_circulation_recipient_id');
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
