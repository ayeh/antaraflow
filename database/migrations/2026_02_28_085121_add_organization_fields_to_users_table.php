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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_organization_id')->nullable()->after('remember_token')->constrained('organizations')->nullOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->string('timezone')->default('UTC')->after('avatar_path');
            $table->string('language')->default('en')->after('timezone');
            $table->json('preferences')->nullable()->after('language');
            $table->timestamp('last_login_at')->nullable()->after('preferences');
            $table->softDeletes()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_organization_id']);
            $table->dropColumn([
                'current_organization_id',
                'phone',
                'avatar_path',
                'timezone',
                'language',
                'preferences',
                'last_login_at',
                'deleted_at',
            ]);
        });
    }
};
