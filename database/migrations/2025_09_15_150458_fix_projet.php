<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajouter les colonnes de complétion comme dans la table prospects
        Schema::table('projets', function (Blueprint $table) {
            if (!Schema::hasColumn('projets', 'pipeline_completed_at')) {
                $table->timestamp('pipeline_completed_at')->nullable()->after('pipeline_stage_id');
            }
            if (!Schema::hasColumn('projets', 'pipeline_completed_by')) {
                $table->foreignId('pipeline_completed_by')->nullable()->after('pipeline_completed_at');
                $table->foreign('pipeline_completed_by')->references('id')->on('users')->onDelete('set null');
            }
        });

        // 2. Supprimer la contrainte de clé étrangère pipeline_type_id dans les projets
        Schema::table('projets', function (Blueprint $table) {
            if (Schema::hasColumn('projets', 'pipeline_type_id')) {
                $table->dropForeign(['pipeline_type_id']);
                $table->dropColumn('pipeline_type_id');
            }
        });
        
        // 3. Supprimer la contrainte de clé étrangère pipeline_type_id dans les stages
        Schema::table('project_pipeline_stages', function (Blueprint $table) {
            if (Schema::hasColumn('project_pipeline_stages', 'pipeline_type_id')) {
                $table->dropForeign(['pipeline_type_id']);
                $table->dropColumn('pipeline_type_id');
            }
            
            // Ajouter is_final si ce n'est pas déjà présent
            if (!Schema::hasColumn('project_pipeline_stages', 'is_final')) {
                $table->boolean('is_final')->default(false)->after('order');
            }
        });
        
        // 4. Supprimer la table des types de pipeline
        Schema::dropIfExists('project_pipeline_types');
    }

    public function down(): void
    {
        // 1. Recréer la table des types de pipeline
        Schema::create('project_pipeline_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        
        // 2. Insérer un type par défaut
        DB::table('project_pipeline_types')->insert([
            'name' => 'Processus standard',
            'description' => 'Pipeline de projet par défaut',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $defaultTypeId = DB::getPdo()->lastInsertId();
        
        // 3. Ajouter pipeline_type_id dans project_pipeline_stages
        Schema::table('project_pipeline_stages', function (Blueprint $table) {
            $table->foreignId('pipeline_type_id')->nullable()->after('id');
            $table->foreign('pipeline_type_id')->references('id')->on('project_pipeline_types')->onDelete('cascade');
        });
        
        // 4. Mettre à jour toutes les étapes avec le type par défaut
        DB::table('project_pipeline_stages')->update([
            'pipeline_type_id' => $defaultTypeId
        ]);
        
        // 5. Ajouter pipeline_type_id dans projects
        Schema::table('projets', function (Blueprint $table) {
            $table->foreignId('pipeline_type_id')->nullable()->after('industrial_zone');
            $table->foreign('pipeline_type_id')->references('id')->on('project_pipeline_types')->onDelete('set null');
        });
        
        // 6. Mettre à jour tous les projets avec le type par défaut
        DB::table('projets')->update([
            'pipeline_type_id' => $defaultTypeId
        ]);
        
        // 7. Supprimer les colonnes de complétion
        Schema::table('projets', function (Blueprint $table) {
            if (Schema::hasColumn('projets', 'pipeline_completed_by')) {
                $table->dropForeign(['pipeline_completed_by']);
                $table->dropColumn('pipeline_completed_by');
            }
            if (Schema::hasColumn('projets', 'pipeline_completed_at')) {
                $table->dropColumn('pipeline_completed_at');
            }
        });
    }
};