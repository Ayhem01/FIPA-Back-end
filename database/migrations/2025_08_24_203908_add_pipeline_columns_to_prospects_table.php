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
        Schema::table('prospects', function (Blueprint $table) {
            // Ajouter les colonnes de pipeline
            $table->foreignId('pipeline_type_id')->nullable()->after('secteur_id');
            $table->foreignId('pipeline_stage_id')->nullable()->after('pipeline_type_id');
            
            // Ajouter les contraintes de clés étrangères
            $table->foreign('pipeline_type_id')
                  ->references('id')
                  ->on('prospect_pipeline_types')
                  ->onDelete('set null');
                  
            $table->foreign('pipeline_stage_id')
                  ->references('id')
                  ->on('prospect_pipeline_stages')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            // Supprimer les contraintes de clés étrangères
            $table->dropForeign(['pipeline_type_id']);
            $table->dropForeign(['pipeline_stage_id']);
            
            // Supprimer les colonnes
            $table->dropColumn(['pipeline_type_id', 'pipeline_stage_id']);
        });
    }
};