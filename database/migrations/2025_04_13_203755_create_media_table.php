<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            $table->boolean('proposee')->default(false);
            $table->boolean('programmee')->default(false);
            $table->boolean('realisee')->default(false);
            $table->boolean('reportee')->default(false);
            $table->boolean('annulee')->default(false);
            $table->unsignedBigInteger('action_id'); // Ajoute cette ligne AVANT la contrainte

            $table->text('action');
            $table->string('proposee_par')->nullable();

            $table->foreignId('responsable_bureau_media_id')->constrained('responsables_bureau_media')->onDelete('cascade');
            $table->foreignId('vav_siege_media_id')->nullable()->constrained('vav_sieges_media')->onDelete('cascade');
            $table->foreignId('nationalite_id')->constrained('nationalites')->onDelete('cascade');
            $table->foreign('action_id')->references('id')->on('actions')->onDelete('cascade');

    
            $table->enum('type_action', [ 'Annonce presse', 
            'Communique de presse', 
            'Article de presse', 
            'Interview', 
            'Reportage', 
            'Spécial pays', 
            'Conférence de presse', 
            'Affiche', 
            'Spot TV', 
            'Reportage TV', 
            'Film institutionnel', 
            'Spot radio', 
            'Bannière web'])->nullable();
            $table->enum('devise', ['USD', 'EUR', 'TND', 'Yen'])->nullable();
            $table->enum('imputation_financiere', ['Régie au siège', 'Régie au RE'])->nullable();
            $table->enum('type_media', ['Magasine', 'Journal', 'Groupe de publications', 'Newsletter externe', 'Bulletin d info', 'Chaine TV', 'Radios', 'Site internet','Espace d affichage'])->nullable();
            $table->enum('diffusion', ['Locale', 'Régionale', 'Internationale'])->nullable();
            $table->enum('evaluation', ['Satisfaisante', 'Non satisfaisante', 'Tres satisfaisante'])->nullable();
            $table->enum('reconduction', ['Fortement recommandée', 'Déconseillée', 'Sans intéret'])->nullable();
            $table->string('duree');
            $table->text('zone_impact')->nullable();
            $table->text('cible')->nullable();
            $table->text('objectif')->nullable();
            $table->text('resultats_attendus')->nullable();
            $table->decimal('budget', 15, 2)->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->string('langue')->nullable();
            $table->string('tirage_audience')->nullable();
            $table->text('composition_lectorat')->nullable();
            $table->text('collaboration_fipa')->nullable();
            $table->text('volume_couverture')->nullable();
            $table->text('regie_publicitaire')->nullable();
            $table->text('media_contact')->nullable();
            $table->text('commentaires_specifiques')->nullable();

            $table->timestamps();
    

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
