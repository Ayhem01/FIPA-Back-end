<?php
// filepath: database/migrations/2025_01_12_add_pipeline_completion_to_invites.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            if (!Schema::hasColumn('invites', 'pipeline_completed_at')) {
                $table->timestamp('pipeline_completed_at')->nullable()->after('pipeline_stage_id');
            }
            if (!Schema::hasColumn('invites', 'pipeline_completed_by')) {
                $table->foreignId('pipeline_completed_by')->nullable()->after('pipeline_completed_at');
                $table->foreign('pipeline_completed_by')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            if (Schema::hasColumn('invites', 'pipeline_completed_by')) {
                $table->dropForeign(['pipeline_completed_by']);
                $table->dropColumn('pipeline_completed_by');
            }
            if (Schema::hasColumn('invites', 'pipeline_completed_at')) {
                $table->dropColumn('pipeline_completed_at');
            }
        });
    }
};