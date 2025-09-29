<?php
// Nouvelle migration: fix_invite_pipeline_stages_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer la contrainte pipeline_type_id si elle existe
        Schema::table('invite_pipeline_stages', function (Blueprint $table) {
            // Vérifier si la colonne existe avant de la supprimer
            if (Schema::hasColumn('invite_pipeline_stages', 'pipeline_type_id')) {
                $table->dropForeign(['pipeline_type_id']);
                $table->dropColumn('pipeline_type_id');
            }
            
            // Ajouter conversion_eligible si pas présent
            if (!Schema::hasColumn('invite_pipeline_stages', 'conversion_eligible')) {
                $table->boolean('conversion_eligible')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invite_pipeline_stages', function (Blueprint $table) {
            $table->foreignId('pipeline_type_id')->nullable()->after('id');
            $table->dropColumn('conversion_eligible');
        });
    }
};