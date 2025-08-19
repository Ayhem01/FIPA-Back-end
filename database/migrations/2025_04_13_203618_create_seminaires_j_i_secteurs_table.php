<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seminaires_j_i_secteurs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('secteur_id');
            $table->unsignedBigInteger('binome_id');
            $table->unsignedBigInteger('pays_id');
            $table->unsignedBigInteger('responsable_fipa_id');
            $table->unsignedBigInteger('groupe_id');
            $table->string('intitule');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->text('outils_promotionnels')->nullable();
            $table->date('date_butoir')->nullable();
            $table->decimal('budget_prevu', 15, 2)->nullable();
            $table->decimal('budget_realise', 15, 2)->nullable();
            $table->integer('nb_entreprises')->nullable();
            $table->integer('nb_multiplicateurs')->nullable();
            $table->integer('nb_institutionnels')->nullable();
            $table->integer('nb_articles_presse')->nullable();
            $table->string('fichier_presence')->nullable();
            $table->text('evaluation_recommandations')->nullable();
            $table->integer('contacts_realises')->nullable();
            $table->enum('inclure', ['comptabilisée', 'non comptabilisée'])->nullable();
            $table->enum('action_conjointe', ['conjointe', 'non conjointe'])->nullable();
            $table->enum('type_participation', ['organisatrice', 'Co-organisateur', 'Participation active', 'simple présence'])->nullable();
            $table->enum('type_organisation', ['partenaires étrangers', 'partenaires tunisiens', 'les deux à la fois'])->nullable();
            $table->enum('avec_diaspora', ['organisée pour la diaspora', 'organisée avec la diaspora'])->nullable();
            $table->timestamps();
            
            // Clés étrangères
            $table->foreign('secteur_id')->references('id')->on('secteurs')->onDelete('cascade');
            $table->foreign('binome_id')->references('id')->on('binomes')->onDelete('cascade');
            $table->foreign('pays_id')->references('id')->on('pays')->onDelete('cascade');
            $table->foreign('responsable_fipa_id')->references('id')->on('responsable_fipa')->onDelete('cascade');
            $table->foreign('groupe_id')->references('id')->on('groupes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seminaires_j_i_secteurs');
    }
};