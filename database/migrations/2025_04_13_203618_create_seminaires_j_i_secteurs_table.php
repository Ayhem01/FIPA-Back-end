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
        Schema::create('seminaires_j_i_secteurs', function (Blueprint $table) {
            $table->id();
            $table->boolean('proposee')->default(false);
            $table->boolean('programmee')->default(false);
            $table->boolean('non_programmee')->default(false);
            $table->boolean('validee')->default(false);
            $table->boolean('realisee')->default(false);
            $table->boolean('reportee')->default(false);
            $table->boolean('annulee')->default(false);
            $table->text('motif')->nullable();
            $table->string('intitule');
            $table->string('theme')->nullable();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->string('region')->nullable();
            $table->string('proposee_par')->nullable();
            $table->text('objectifs')->nullable();
            $table->string('lieu')->nullable();
            $table->text('details_participation_active')->nullable();
            $table->text('partenaires_tunisiens')->nullable();
            $table->text('partenaires_etrangers')->nullable();
            $table->text('officiels')->nullable();
            $table->boolean('presence_dg')->default(false);
            $table->boolean('programme_deroulement')->default(false);
            $table->text('diaspora_details')->nullable();
            $table->text('location_salle')->nullable();
            $table->text('media_communication')->nullable();
            $table->text('besoin_binome')->nullable();
            $table->text('autre_organisme')->nullable();
            $table->text('outils_promotionnels')->nullable();
            $table->date('date_butoir')->nullable();
            $table->decimal('budget_prevu', 10, 2)->nullable();
            $table->decimal('budget_realise', 10, 2)->nullable();
            $table->unsignedInteger('nb_entreprises')->nullable();
            $table->unsignedInteger('nb_multiplicateurs')->nullable();
            $table->unsignedInteger('nb_institutionnels')->nullable();
            $table->unsignedInteger('nb_articles_presse')->nullable();
            $table->string('fichier_presence')->nullable(); // PDF uploadé
            $table->text('evaluation_recommandations')->nullable();
            $table->unsignedInteger('contacts_realises')->default(0);
            $table->unsignedBigInteger('action_id'); // Ajoute cette ligne AVANT la contrainte


            $table->foreignId('responsable_fipa_id')->constrained('responsable_fipa')->onDelete('cascade');
            $table->foreignId('pays_id')->constrained('pays')->onDelete('cascade');
            $table->foreignId('secteur_id')->constrained('secteurs')->onDelete('cascade');
            $table->foreignId('groupe_id')->constrained('groupes')->onDelete('cascade');
            $table->foreignId('binome_id')->constrained('binomes')->onDelete('cascade');
            $table->foreign('action_id')->references('id')->on('actions')->onDelete('cascade');


            $table->enum('inclure', ['comptabilisée','non comptabilisée'])->default('comptabilisée');
            $table->enum('action_conjointe', ['conjointe', 'non conjointe'])->nullable();
            $table->enum('type_participation', ['organisatrice', 'Co-organisateur', 'Participation active', 'simple présence'])->nullable();
            $table->enum('type_organisation', ['partenaires étrangers', 'partenaires tunisiens', 'les deux à la fois', ])->nullable();
            $table->enum('avec_diaspora', ['organisée pour la diaspora', ' organisée avec la diaspora ' ])->nullable();



            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seminaires_j_i_secteurs');
    }
};
