<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a task belongs to, when that is not somebody with an account.
 *
 * The AI extracts an assignee from the minutes and matchAssignee tries to turn
 * it into a user. On this product's actual minutes it almost never can —
 * government sittings assign work to MBPJ, to a department, to a committee.
 * The name was discarded and the task landed owned by nobody, which lost the
 * one piece of information the minutes were clearest about.
 *
 * `assigned_to` stays: a task addressed to a real colleague should still show
 * up on their list. This sits beside it for everything else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_items', function (Blueprint $table) {
            $table->string('assignee_name')->nullable()->after('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::table('action_items', function (Blueprint $table) {
            $table->dropColumn('assignee_name');
        });
    }
};
