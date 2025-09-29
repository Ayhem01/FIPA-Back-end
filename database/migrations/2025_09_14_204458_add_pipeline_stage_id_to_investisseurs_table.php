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
        Schema::table('investisseurs', function (Blueprint $table) {
            $table->unsignedBigInteger('pipeline_stage_id')->nullable()->after('secteur_id');
            
            // Ajouter la clé étrangère
            $table->foreign('pipeline_stage_id')
                ->references('id')
                ->on('investor_pipeline_stages')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investisseurs', function (Blueprint $table) {
            $table->dropForeign(['pipeline_stage_id']);
            $table->dropColumn('pipeline_stage_id');
        });
    }
};