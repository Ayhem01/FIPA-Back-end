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
        // 1. Ajouter les clés étrangères à la table pipeline_stages
        if (Schema::hasTable('pipeline_stages') && Schema::hasTable('project_pipeline_types')) {
            Schema::table('pipeline_stages', function (Blueprint $table) {
                $table->foreign('pipeline_type_id')
                      ->references('id')
                      ->on('project_pipeline_types')
                      ->onDelete('cascade');
            });
        }
        
        // 2. Ajouter les clés étrangères à la table projects
        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                if (Schema::hasTable('secteurs')) {
                    $table->foreign('secteur_id')
                          ->references('id')
                          ->on('secteurs')
                          ->onDelete('cascade');
                }
                
                if (Schema::hasTable('users')) {
                    $table->foreign('responsable_id')
                          ->references('id')
                          ->on('users');
                }
                
                if (Schema::hasTable('project_pipeline_types')) {
                    $table->foreign('pipeline_type_id')
                          ->references('id')
                          ->on('project_pipeline_types')
                          ->onDelete('set null');
                }
                
                if (Schema::hasTable('pipeline_stages')) {
                    $table->foreign('pipeline_stage_id')
                          ->references('id')
                          ->on('pipeline_stages')
                          ->onDelete('set null');
                }
                
                // Gouvernorat commenté dans votre migration originale
                // if (Schema::hasTable('governorates')) {
                //     $table->foreign('governorate_id')
                //           ->references('id')
                //           ->on('governorates')
                //           ->onDelete('set null');
                // }
            });
        }
        
        // 3. Ajouter les clés étrangères à project_blockages
        if (Schema::hasTable('project_blockages')) {
            Schema::table('project_blockages', function (Blueprint $table) {
                if (Schema::hasTable('projects')) {
                    $table->foreign('project_id')
                          ->references('id')
                          ->on('projects')
                          ->onDelete('cascade');
                }
                
                if (Schema::hasTable('users')) {
                    $table->foreign('assigned_to')
                          ->references('id')
                          ->on('users')
                          ->onDelete('set null');
                }
            });
        }
        
        // 4. Ajouter les clés étrangères à project_follow_ups
        if (Schema::hasTable('project_follow_ups')) {
            Schema::table('project_follow_ups', function (Blueprint $table) {
                if (Schema::hasTable('projects')) {
                    $table->foreign('project_id')
                          ->references('id')
                          ->on('projects')
                          ->onDelete('cascade');
                }
                
                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')
                          ->references('id')
                          ->on('users')
                          ->onDelete('cascade');
                }
            });
        }
        
        // 5. Ajouter les clés étrangères à project_contacts
        if (Schema::hasTable('project_contacts')) {
            Schema::table('project_contacts', function (Blueprint $table) {
                if (Schema::hasTable('projects')) {
                    $table->foreign('project_id')
                          ->references('id')
                          ->on('projects')
                          ->onDelete('cascade');
                }
            });
        }
        
        // 6. Ajouter les clés étrangères à project_documents
        if (Schema::hasTable('project_documents')) {
            Schema::table('project_documents', function (Blueprint $table) {
                if (Schema::hasTable('projects')) {
                    $table->foreign('project_id')
                          ->references('id')
                          ->on('projects')
                          ->onDelete('cascade');
                }
                
                if (Schema::hasTable('users')) {
                    $table->foreign('uploaded_by')
                          ->references('id')
                          ->on('users')
                          ->onDelete('cascade');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 6. Supprimer les clés étrangères de project_documents
        if (Schema::hasTable('project_documents')) {
            Schema::table('project_documents', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
                $table->dropForeign(['uploaded_by']);
            });
        }
        
        // 5. Supprimer les clés étrangères de project_contacts
        if (Schema::hasTable('project_contacts')) {
            Schema::table('project_contacts', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
            });
        }
        
        // 4. Supprimer les clés étrangères de project_follow_ups
        if (Schema::hasTable('project_follow_ups')) {
            Schema::table('project_follow_ups', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
                $table->dropForeign(['user_id']);
            });
        }
        
        // 3. Supprimer les clés étrangères de project_blockages
        if (Schema::hasTable('project_blockages')) {
            Schema::table('project_blockages', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
                $table->dropForeign(['assigned_to']);
            });
        }
        
        // 2. Supprimer les clés étrangères de projects
        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropForeign(['secteur_id']);
                $table->dropForeign(['responsable_id']);
                $table->dropForeign(['pipeline_type_id']);
                $table->dropForeign(['pipeline_stage_id']);
                // $table->dropForeign(['governorate_id']);
            });
        }
        
        // 1. Supprimer les clés étrangères de pipeline_stages
        if (Schema::hasTable('pipeline_stages')) {
            Schema::table('pipeline_stages', function (Blueprint $table) {
                $table->dropForeign(['pipeline_type_id']);
            });
        }
    }
};