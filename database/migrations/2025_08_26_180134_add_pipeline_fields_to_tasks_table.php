<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('entity_type')->nullable()->after('assignee_id');
            $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');
            $table->unsignedBigInteger('pipeline_stage_id')->nullable()->after('entity_id');
            
            // Optional: Add index for better performance
            $table->index(['entity_type', 'entity_id']);
            $table->index('pipeline_stage_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['entity_type', 'entity_id']);
            $table->dropIndex(['pipeline_stage_id']);
            $table->dropColumn(['entity_type', 'entity_id', 'pipeline_stage_id']);
        });
    }
};