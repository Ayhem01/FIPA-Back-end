<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Désactiver temporairement la vérification des clés étrangères
        Schema::disableForeignKeyConstraints();

        // 1. Relations pour les tables de base
        Schema::table('entreprises', function (Blueprint $table) {
            if (!$this->hasConstraint('entreprises', 'entreprises_secteur_id_foreign')) {
                $table->foreign('secteur_id')->references('id')->on('secteurs')->onDelete('set null');
            }
            
            if (!$this->hasConstraint('entreprises', 'entreprises_proprietaire_id_foreign')) {
                $table->foreign('proprietaire_id')->references('id')->on('users')->onDelete('set null');
            }
        });
        
        Schema::table('invites', function (Blueprint $table) {
            if (!$this->hasConstraint('invites', 'invites_entreprise_id_foreign')) {
                $table->foreign('entreprise_id')->references('id')->on('entreprises')->onDelete('cascade');
            }
            
            if (!$this->hasConstraint('invites', 'invites_action_id_foreign')) {
                $table->foreign('action_id')->references('id')->on('actions')->onDelete('cascade');
            }
            
            if (!$this->hasConstraint('invites', 'invites_pays_id_foreign')) {
                $table->foreign('pays_id')->references('id')->on('pays')->onDelete('set null');
            }
            
            if (!$this->hasConstraint('invites', 'invites_secteur_id_foreign')) {
                $table->foreign('secteur_id')->references('id')->on('secteurs')->onDelete('set null');
            }
            
            // Contraintes pour le pipeline des invités
            if (!$this->hasConstraint('invites', 'invites_pipeline_type_id_foreign')) {
                $table->foreign('pipeline_type_id')->references('id')->on('invite_pipeline_types')->onDelete('set null');
            }
            
            if (!$this->hasConstraint('invites', 'invites_pipeline_stage_id_foreign')) {
                $table->foreign('pipeline_stage_id')->references('id')->on('invite_pipeline_stages')->onDelete('set null');
            }
        });

        // 2. Relations pour le flux de conversion
        
        // 2.1 Relation: prospect → invite
        Schema::table('prospects', function (Blueprint $table) {
            // Vérifier si la colonne existe déjà
            if (!Schema::hasColumn('prospects', 'invite_id')) {
                $table->unsignedBigInteger('invite_id')->nullable();
            }
            
            // Ajouter la contrainte
            if (!$this->hasConstraint('prospects', 'prospects_invite_id_foreign')) {
                $table->foreign('invite_id')
                    ->references('id')
                    ->on('invites')
                    ->onDelete('set null');
            }
        });

        // 2.2 Relation: investisseur → prospect
        Schema::table('investisseurs', function (Blueprint $table) {
            // Vérifier si la colonne existe déjà
            if (!Schema::hasColumn('investisseurs', 'prospect_id')) {
                $table->unsignedBigInteger('prospect_id')->nullable();
            }
            
            // Ajouter la contrainte
            if (!$this->hasConstraint('investisseurs', 'investisseurs_prospect_id_foreign')) {
                $table->foreign('prospect_id')
                    ->references('id')
                    ->on('prospects')
                    ->onDelete('set null');
            }
        });

        // 2.3 Relation: projet → investisseur
        Schema::table('projets', function (Blueprint $table) {
            // Vérifier si la colonne existe déjà
            if (!Schema::hasColumn('projets', 'investisseur_id')) {
                $table->unsignedBigInteger('investisseur_id')->nullable();
            }
            
            // Ajouter la contrainte
            if (!$this->hasConstraint('projets', 'projets_investisseur_id_foreign')) {
                $table->foreign('investisseur_id')
                    ->references('id')
                    ->on('investisseurs')
                    ->onDelete('set null');
            }
        });
        
        // 3. Relations pour les progressions de pipeline
        Schema::table('invite_pipeline_progressions', function (Blueprint $table) {
            if (!$this->hasConstraint('invite_pipeline_progressions', 'invite_pipeline_progressions_invite_id_foreign')) {
                $table->foreign('invite_id')->references('id')->on('invites')->onDelete('cascade');
            }
            
            if (!$this->hasConstraint('invite_pipeline_progressions', 'invite_pipeline_progressions_stage_id_foreign')) {
                $table->foreign('stage_id')->references('id')->on('invite_pipeline_stages')->onDelete('cascade');
            }
            
            if (!$this->hasConstraint('invite_pipeline_progressions', 'invite_pipeline_progressions_assigned_to_foreign')) {
                $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            }
        });

        // 3.1 Relations pour les étapes de pipeline
        Schema::table('invite_pipeline_stages', function (Blueprint $table) {
            if (!$this->hasConstraint('invite_pipeline_stages', 'invite_pipeline_stages_pipeline_type_id_foreign')) {
                $table->foreign('pipeline_type_id')->references('id')->on('invite_pipeline_types')->onDelete('cascade');
            }
        });

        // Réactiver la vérification des clés étrangères
        Schema::enableForeignKeyConstraints();
    }
        
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. Supprimer les contraintes de pipeline stages
        if (Schema::hasTable('invite_pipeline_stages')) {
            Schema::table('invite_pipeline_stages', function (Blueprint $table) {
                $table->dropForeign(['pipeline_type_id']);
            });
        }

        // 2. Supprimer les contraintes de progression de pipeline
        if (Schema::hasTable('invite_pipeline_progressions')) {
            Schema::table('invite_pipeline_progressions', function (Blueprint $table) {
                $table->dropForeign(['assigned_to']);
                $table->dropForeign(['stage_id']);
                $table->dropForeign(['invite_id']);
            });
        }

        // 3. Supprimer les contraintes du flux de conversion (ordre inverse)
        if (Schema::hasTable('projets')) {
            Schema::table('projets', function (Blueprint $table) {
                $table->dropForeign(['investisseur_id']);
            });
        }

        if (Schema::hasTable('investisseurs')) {
            Schema::table('investisseurs', function (Blueprint $table) {
                $table->dropForeign(['prospect_id']);
            });
        }

        if (Schema::hasTable('prospects')) {
            Schema::table('prospects', function (Blueprint $table) {
                $table->dropForeign(['invite_id']);
            });
        }
        
        // 4. Supprimer les contraintes des tables de base
        if (Schema::hasTable('invites')) {
            Schema::table('invites', function (Blueprint $table) {
                $table->dropForeign(['pipeline_stage_id']);
                $table->dropForeign(['pipeline_type_id']);
                $table->dropForeign(['secteur_id']);
                $table->dropForeign(['pays_id']);
                $table->dropForeign(['action_id']);
                $table->dropForeign(['entreprise_id']);
            });
        }
        
        if (Schema::hasTable('entreprises')) {
            Schema::table('entreprises', function (Blueprint $table) {
                $table->dropForeign(['proprietaire_id']);
                $table->dropForeign(['secteur_id']);
            });
        }

        Schema::enableForeignKeyConstraints();
    }
    
    /**
     * Vérifie si une contrainte existe déjà
     */
    private function hasConstraint($table, $constraintName) 
    {
        try {
            // Requête SQL directe pour vérifier l'existence de la contrainte
            $results = DB::select("
                SELECT * 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND CONSTRAINT_NAME = ?
            ", [$table, $constraintName]);
            
            return count($results) > 0;
        } catch (\Exception $e) {
            // En cas d'erreur, logger et retourner false
            \Log::error("Erreur lors de la vérification de la contrainte: " . $e->getMessage());
            return false;
        }
    }
};