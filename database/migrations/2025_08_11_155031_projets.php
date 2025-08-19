<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('projets', function (Blueprint $table) {
            $table->id();
            
            // Statut du projet
            $table->boolean('idea')->default(true);
            $table->boolean('in_progress')->default(false);
            $table->boolean('in_production')->default(false);
            
            // Informations de base
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('company_name')->nullable();
            
            // Relations
            $table->unsignedBigInteger('secteur_id')->nullable();
            $table->unsignedBigInteger('governorate_id')->nullable();
            $table->unsignedBigInteger('responsable_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('investisseur_id')->nullable(); // Relation avec investisseur
            
            // Détails du projet
            $table->enum('market_target', ['local', 'export', 'both'])->default('local');
            $table->string('nationality')->nullable();
            $table->decimal('foreign_percentage', 5, 2)->default(0);
            $table->decimal('investment_amount', 15, 2)->nullable();
            $table->integer('jobs_expected')->default(0);
            $table->string('industrial_zone')->nullable();
            
            // Pipeline et suivi
            $table->unsignedBigInteger('pipeline_type_id')->nullable();
            $table->unsignedBigInteger('pipeline_stage_id')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed', 'abandoned', 'suspended', 'on_hold'])->default('planned');
            
            // Origine du projet
            $table->enum('contact_source', [
                'action_promo', 'visite', 'reference', 'salon', 'direct', 'autre'
            ])->nullable();
            $table->string('initial_contact_person')->nullable();
            $table->date('first_contact_date')->nullable();
            $table->timestamp('converted_from_investisseur_at')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Clés étrangères non-circulaires
            $table->foreign('secteur_id')->references('id')->on('secteurs')->nullOnDelete();
            $table->foreign('governorate_id')->references('id')->on('governorates')->nullOnDelete();
            $table->foreign('responsable_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('pipeline_type_id')->references('id')->on('project_pipeline_types')->nullOnDelete();
            $table->foreign('pipeline_stage_id')->references('id')->on('project_pipeline_stages')->nullOnDelete();
            
            // Ne pas ajouter la contrainte pour investisseur_id ici
            // Elle sera ajoutée dans la migration add_circular_foreign_keys.php
        });
        
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('projets');
    }
};