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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('nom');
            $table->string('prenom');
            $table->string('fonction')->nullable();
            $table->string('email')->nullable();
            $table->string('telephone_fixe')->nullable();
            $table->string('telephone_mobile')->nullable();
            $table->text('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('code_postal')->nullable();
            $table->string('pays')->nullable();
            $table->boolean('est_principal')->default(false);
            $table->string('linkedin')->nullable();
            $table->text('notes')->nullable();
            $table->string('statut')->default('actif');
            $table->date('date_naissance')->nullable();
            $table->foreignId('proprietaire_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};