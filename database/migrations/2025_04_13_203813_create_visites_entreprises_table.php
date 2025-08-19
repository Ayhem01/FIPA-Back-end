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
        Schema::create('visites_entreprises', function (Blueprint $table) {
            $table->id();

            $table->boolean('encadre_avec_programme')->default(false);
            $table->boolean('entreprise_importante')->default(false);
            $table->unsignedInteger('nombre_visites')->default(1);
            $table->unsignedBigInteger('action_id'); // Ajoute cette ligne AVANT la contrainte

            $table->date('date_contact')->nullable();
            $table->string('raison_sociale');
            $table->string('responsable')->nullable();
            $table->string('fonction')->nullable();
            $table->foreignId('nationalite_id')->constrained('nationalites')->onDelete('cascade');
            $table->foreignId('initiateur_id')->constrained('initiateurs')->onDelete('cascade');
            $table->foreignId('secteur_id')->constrained('secteurs')->onDelete('cascade');
            $table->foreignId('responsable_suivi_id')->constrained('responsable_suivi')->onDelete('cascade');
            $table->foreign('action_id')->references('id')->on('actions')->onDelete('cascade');

            $table->string('activite')->nullable();
            $table->string('adresse')->nullable();
            $table->string('telephone')->nullable();
            $table->string('fax')->nullable();
            $table->string('email')->nullable();
            $table->string('site_web')->nullable();
            $table->enum('pr', ['Prévue', 'Réalisée'])->nullable();

            $table->date('date_visite');
            $table->text('programme_pdf')->nullable(); 
            $table->text('services_appreciation')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visites_entreprises');
    }
};
