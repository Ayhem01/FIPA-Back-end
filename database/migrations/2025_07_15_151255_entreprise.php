<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entreprises', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('logo')->nullable();
            $table->string('site_web')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->text('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('code_postal')->nullable();
            $table->string('pays')->nullable();
            $table->foreignId('secteur_id')->nullable()->constrained('secteurs')->nullOnDelete();
            $table->string('taille')->nullable(); // TPE, PME, ETI, GE
            $table->decimal('capital', 15, 2)->nullable();
            $table->decimal('chiffre_affaires', 15, 2)->nullable();
            $table->date('date_creation')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('statut')->default('prospect'); // actif, inactif, prospect, client
            $table->string('type')->nullable(); // entreprise, organisme public, association
            $table->foreignId('proprietaire_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pipeline_stage_id')->nullable()->constrained('pipeline_stages')->nullOnDelete();
            $table->foreignId('pipeline_type_id')->nullable()->constrained('project_pipeline_types')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entreprises');
    }
};