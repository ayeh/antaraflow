<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_registration_tokens', function (Blueprint $table) {
            $table->string('join_code', 8)->nullable()->after('token');
            $table->unsignedInteger('max_attendees')->nullable()->after('is_active');
            $table->json('required_fields')->nullable()->after('max_attendees');
            $table->text('welcome_message')->nullable()->after('required_fields');
            $table->unsignedInteger('registrations_count')->default(0)->after('welcome_message');
        });
    }

    public function down(): void
    {
        Schema::table('qr_registration_tokens', function (Blueprint $table) {
            $table->dropColumn(['join_code', 'max_attendees', 'required_fields', 'welcome_message', 'registrations_count']);
        });
    }
};
