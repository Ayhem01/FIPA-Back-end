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
        Schema::create('demarchage_directs', function (Blueprint $table) {
            $table->id();
            $table->boolean('proposee')->default(false);
            $table->boolean('programmee')->default(false);
            $table->boolean('realisee')->default(false);
            $table->boolean('reportee')->default(false);
            $table->boolean('annulee')->default(false);
            $table->text('presentation');
            $table->string('regions')->nullable();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();

            $table->unsignedInteger('contacts_interessants_initiateur')->nullable();
            $table->unsignedInteger('contacts_interessants_binome')->nullable();
            $table->unsignedBigInteger('action_id'); // Ajoute cette ligne AVANT la contrainte

            $table->text('besoins_logistiques')->nullable();
            $table->text('frais_deplacement')->nullable();
            $table->text('frais_mission')->nullable();
            $table->date('date_butoir')->nullable();
            $table->decimal('budget_prevu', 10, 2)->nullable();
            $table->decimal('budget_realise', 10, 2)->nullable();

            $table->date('date_premier_mailing')->nullable();
            $table->unsignedInteger('nb_entreprises_ciblees')->nullable();
            $table->text('source_ciblage')->nullable();
            $table->text('dates_relances')->nullable();
            $table->unsignedInteger('contacts_telephoniques')->nullable();
            $table->text('lettre_argumentaire')->nullable();

            $table->unsignedInteger('nb_reponses_positives')->nullable();
            $table->text('resultat_action')->nullable();
            $table->text('evaluation_action')->nullable();

            $table->foreign('action_id')->references('id')->on('actions')->onDelete('cascade');

            $table->foreignId('initiateur_id')->constrained('initiateurs')->onDelete('cascade');
            $table->foreignId('secteur_id')->constrained('secteurs')->onDelete('cascade');
            $table->foreignId('pays_id')->constrained('pays')->onDelete('cascade');
            $table->enum(('inclure'), ['comptabilisée', 'non comptabilisée'])->default('comptabilisée');
            $table->enum(('groupe_secteur'), ['Aéronautique', 'Composants autos', 'Environnement', 'Offshoring', 'Santé','Industrie ferroviaire'])->nullable();
            $table->enum(('coinjointe'), ['conjointe', 'non conjointe'])->nullable();
            $table->enum(('cadre_siege'), ['binôme', 'vis-à-vis du siège'])->nullable();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demarchage_directs');
    }
};
