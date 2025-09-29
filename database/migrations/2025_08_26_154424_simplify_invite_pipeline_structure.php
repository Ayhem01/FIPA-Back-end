<?php
// Nouvelle migration: simplify_invite_pipeline_structure.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer la colonne pipeline_type_id de la table invites
        Schema::table('invites', function (Blueprint $table) {
            $table->dropForeign(['pipeline_type_id']);
            $table->dropColumn('pipeline_type_id');
        });
        
        // Supprimer la colonne pipeline_type_id de la table invite_pipeline_stages
        Schema::table('invite_pipeline_stages', function (Blueprint $table) {
            $table->dropForeign(['pipeline_type_id']);
            $table->dropColumn('pipeline_type_id');
        });
        
        // Supprimer les tables des types de pipeline (optionnel)
        Schema::dropIfExists('invite_pipeline_types');
    }

    public function down(): void
    {
        // Recréer la table invite_pipeline_types
        Schema::create('invite_pipeline_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
        
        // Remettre pipeline_type_id dans invite_pipeline_stages
        Schema::table('invite_pipeline_stages', function (Blueprint $table) {
            $table->foreignId('pipeline_type_id')->nullable()->after('id');
        });
        
        // Remettre pipeline_type_id dans invites
        Schema::table('invites', function (Blueprint $table) {
            $table->foreignId('pipeline_type_id')->nullable()->after('secteur_id');
        });
    }
};