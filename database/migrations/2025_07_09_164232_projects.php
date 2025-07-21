<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            
            // Statut du projet
            $table->boolean('idea')->default(true);
            $table->boolean('in_progress')->default(false);
            $table->boolean('in_production')->default(false);
            
            // Informations de base
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('company_name');
            
            // Relations
            $table->unsignedBigInteger('secteur_id');
            //$table->foreignId('governorate_id')->nullable()->constrained('governorates');
            $table->unsignedBigInteger('responsable_id');
            
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
            
            // Origine du projet
            $table->enum('contact_source', [
                'action_promo', 'visite', 'reference', 'salon', 'direct', 'autre'
            ])->nullable();
            $table->string('initial_contact_person')->nullable();
            $table->date('first_contact_date')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

        });
        schema::enableForeignKeyConstraints();

    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};