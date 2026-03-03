<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('host');
            $table->integer('port')->default(587);
            $table->string('username');
            $table->text('password');
            $table->string('encryption')->default('tls');
            $table->string('from_address');
            $table->string('from_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_configurations');
    }
};
