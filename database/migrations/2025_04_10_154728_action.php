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
        Schema::create('actions', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->string('type'); // séminaire, réunion, webinaire, formation, etc.
            $table->datetime('date_debut');
            $table->datetime('date_fin')->nullable();
            $table->string('lieu')->nullable();
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('pays')->nullable();
            $table->enum('statut', [
                'planifiee', 
                'en_preparation', 
                'confirmee', 
                'en_cours', 
                'terminee', 
                'annulee'
            ])->default('planifiee');
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes_internes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actions');
    }
};