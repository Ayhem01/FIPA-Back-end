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
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('action_id'); // Ajoute cette ligne AVANT la contrainte

           
            $table->date('date_visite');
            $table->string('delegation');
            

            $table->string('contact')->nullable();
            $table->string('fonction')->nullable();
            $table->string('adresse')->nullable();
            $table->string('telephone')->nullable();
            $table->string('fax')->nullable();
            
            $table->string('email_site')->nullable();
            $table->string('activite')->nullable();

            $table->text('programme_visite');
            $table->text('evaluation_suivi');
            $table->string('liste_membres_pdf')->nullable(); // fichier PDF scanné

            $table->foreignId('responsable_fipa_id')->constrained('responsable_fipa')->onDelete('cascade');
            $table->foreignId('initiateur_id')->constrained('initiateurs')->onDelete('cascade');
            $table->foreignId('nationalite_id')->constrained('nationalites')->onDelete('cascade');
            $table->foreignId('groupe_id')->constrained('groupes')->onDelete('cascade');
            $table->foreignId('secteur_id')->constrained('secteurs')->onDelete('cascade');
            $table->foreign('action_id')->references('id')->on('actions')->onDelete('cascade');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delegations');
    }
};
