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
        Schema::create('c_t_e_s', function (Blueprint $table) {
            $table->id();
            $table->string('prenom')->nullable();
            $table->string('nom');
            $table->string('adresse');
            $table->string('tel');
            $table->string('fax');
            $table->string('email');
            $table->string('age');
            $table->unsignedBigInteger('action_id'); // Ajoute cette ligne AVANT la contrainte

            $table->foreignId('initiateur_id')->constrained('initiateurs')->onDelete('cascade');
            $table->foreign('action_id')->references('id')->on('actions')->onDelete('cascade');

            $table->date('date_contact');

            $table->string('poste')->nullable();
            $table->string('diplome')->nullable();
            $table->string('ste')->nullable();
            $table->foreignId('pays_id')->constrained('pays')->onDelete('cascade');
            $table->foreignId('secteur_id')->constrained('secteurs')->onDelete('cascade');
            $table->string('lieu')->nullable();

            $table->date('historique_date_debut')->nullable();
            $table->boolean('historique_ste')->default(false);
            $table->string('historique_poste')->nullable();
            $table->date('historique_date_fin')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('c_t_e_s');
    }
};
