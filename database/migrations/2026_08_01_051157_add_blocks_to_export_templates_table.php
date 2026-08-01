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
        Schema::table('export_templates', function (Blueprint $table) {
            $table->json('blocks')->nullable()->after('is_default');
            $table->json('labels')->nullable()->after('blocks');
            $table->json('page_setup')->nullable()->after('labels');
            $table->json('glossary')->nullable()->after('page_setup');
            $table->string('meeting_type', 50)->nullable()->after('glossary');
            $table->boolean('is_system')->default(false)->after('meeting_type');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('is_system');
        });
    }

    public function down(): void
    {
        Schema::table('export_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['blocks', 'labels', 'page_setup', 'glossary', 'meeting_type', 'is_system']);
        });
    }
};
