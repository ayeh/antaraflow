<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('subdomain', 63)->nullable()->unique();
            $table->string('custom_domain')->nullable()->unique();
            $table->boolean('is_reseller')->default(false);
            $table->json('allowed_plans')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->integer('max_sub_organizations')->nullable();
            $table->json('branding_overrides')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_settings');
    }
};
