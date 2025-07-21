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
        Schema::create('salons', function (Blueprint $table) {
            $table->id();
            $table->boolean('proposee')->default(false);
            $table->boolean('programmee')->default(false);
            $table->boolean('non_programmee')->default(false);
            $table->boolean('validee')->default(false);
            $table->boolean('realisee')->default(false);
            $table->boolean('reportee')->default(false);
            $table->boolean('annulee')->default(false);
            $table->text('motif')->nullable(); 
            $table->unsignedBigInteger('action_id'); // Ajoute cette ligne AVANT la contrainte

            $table->string('intitule');
            $table->string('numero_edition')->nullable();
            $table->string('site_web')->nullable();
            $table->string('organisateur')->nullable();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->string('region')->nullable();
            $table->string('theme')->nullable();
            $table->enum('presence_conjointe', ['Conjointe', 'Non Conjointe'])->nullable();
            $table->enum('inclure', ['Yes', 'No'])->default('Yes');
            $table->enum('categorie', ['Incontournable', 'Prospection simple','Nouveau à prospecter'])->nullable();
            $table->foreignId('initiateur_id')->constrained('initiateurs')->onDelete('cascade');
            $table->foreignId('binome_id')->constrained('binomes')->onDelete('cascade');
            $table->foreignId('pays_id')->constrained('pays')->onDelete('cascade');
            $table->foreign('action_id')->references('id')->on('actions')->onDelete('cascade');

            $table->unsignedInteger('contacts_initiateur')->nullable();
            $table->unsignedInteger('contacts_binome')->nullable();
            $table->unsignedInteger('contacts_total')->default(0);

            $table->boolean('objectif_contacts')->default(false);
            $table->boolean('objectif_veille_concurrentielle')->default(false);
            $table->boolean('objectif_veille_technologique')->default(false);
            $table->boolean('objectif_relation_relais')->default(false);

            $table->text('historique_edition')->nullable();

            $table->text('besoin_stand')->nullable();
            $table->text('besoin_media')->nullable();
            $table->text('besoin_binome')->nullable();
            $table->text('besoin_autre_organisme')->nullable();
            $table->text('outils_promotionnels')->nullable();
            $table->date('date_butoir')->nullable();
            $table->decimal('budget_prevu')->nullable();
            $table->decimal('budget_realise')->nullable();

            $table->text('resultat_veille_concurrentielle')->nullable();
            $table->text('resultat_veille_technologique')->nullable();
            $table->text('resultat_relation_institutions')->nullable();
            $table->text('evaluation_recommandations')->nullable();
            $table->unsignedInteger('contacts_realises')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salons');
    }
};
