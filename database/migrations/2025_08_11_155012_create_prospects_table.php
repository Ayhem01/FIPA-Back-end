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
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->onDelete('cascade');
            $table->string('nom');
            $table->unsignedBigInteger('invite_id')->nullable();
            // $table->foreign('invite_id')->references('id')->on('invites')->onDelete('set null');
            
            // Informations de contact
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->string('adresse')->nullable();
            $table->foreignId('pays_id')->nullable()->constrained('pays')->onDelete('set null');
            
            // Classification et catégorisation
            $table->foreignId('secteur_id')->nullable()->constrained('secteurs')->onDelete('set null');
            // $table->foreignId('source_id')->nullable()->constrained('sources')->onDelete('set null');
            $table->enum('statut', [
                'nouveau', 'en_cours', 'qualifie', 'non_qualifie', 'converti', 'perdu'
            ])->default('nouveau');
            
            // Gestion interne
            $table->foreignId('responsable_id')->nullable()->constrained('users')->onDelete('set null');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            
            // Détails et suivi
            $table->text('description')->nullable();
            $table->text('notes_internes')->nullable();
            $table->decimal('valeur_potentielle', 15, 2)->nullable();
            $table->string('devise')->default('EUR');
            $table->date('date_dernier_contact')->nullable();
            $table->date('prochain_contact_prevu')->nullable();
            
            // Conversion
            $table->timestamp('converted_at')->nullable();
            $table->unsignedBigInteger('converted_to_id')->nullable(); // ID de l'investisseur si converti
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};