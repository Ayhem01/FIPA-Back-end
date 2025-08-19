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
        Schema::create('investisseurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->onDelete('cascade');
            $table->string('nom');
            
            // Traçabilité de conversion
            $table->unsignedBigInteger('prospect_id')->nullable();
            // $table->foreign('prospect_id')->references('id')->on('prospects')->onDelete('set null');
            
            // Informations de contact
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->string('adresse')->nullable();
            $table->foreignId('pays_id')->nullable()->constrained('pays')->onDelete('set null');
            
            // Informations d'investissement
            $table->foreignId('secteur_id')->nullable()->constrained('secteurs')->onDelete('set null');
            $table->decimal('montant_investissement', 15, 2)->nullable();
            $table->string('devise')->default('EUR');
            $table->text('interets_specifiques')->nullable();
            $table->text('criteres_investissement')->nullable();
            
            // Statut et phase
            $table->enum('statut', [
                'actif', 'negociation', 'engagement', 'finalisation', 'investi', 'suspendu', 'inactif'
            ])->default('actif');
            $table->date('date_engagement')->nullable();
            $table->date('date_signature')->nullable();
            
            // Suivi
            $table->foreignId('responsable_id')->nullable()->constrained('users')->onDelete('set null');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->text('notes_internes')->nullable();
            $table->date('date_dernier_contact')->nullable();
            $table->date('prochain_contact_prevu')->nullable();
            
            // Conversion vers projet
            $table->timestamp('converted_to_project_at')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            // $table->foreign('project_id')->references('id')->on('projets')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investisseurs');
    }
};