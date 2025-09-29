<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Supprimer la contrainte de clé étrangère pipeline_type_id dans les prospects
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropForeign(['pipeline_type_id']);
            $table->dropColumn('pipeline_type_id');
        });
        
        // 2. Supprimer la contrainte de clé étrangère pipeline_type_id dans les stages
        Schema::table('prospect_pipeline_stages', function (Blueprint $table) {
            $table->dropForeign(['pipeline_type_id']);
            $table->dropColumn('pipeline_type_id');
            
            // Ajouter conversion_eligible comme dans le modèle Invite
            if (!Schema::hasColumn('prospect_pipeline_stages', 'conversion_eligible')) {
                $table->boolean('conversion_eligible')->default(false)->after('is_active');
            }
        });
        
        // 3. Supprimer la table des types de pipeline
        Schema::dropIfExists('prospect_pipeline_types');
    }

    public function down(): void
    {
        // 1. Recréer la table des types de pipeline
        Schema::create('prospect_pipeline_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
        
        // 2. Ajouter pipeline_type_id dans prospect_pipeline_stages
        Schema::table('prospect_pipeline_stages', function (Blueprint $table) {
            $table->foreignId('pipeline_type_id')->nullable()->after('id');
            $table->dropColumn('conversion_eligible');
        });
        
        // 3. Ajouter pipeline_type_id dans prospects
        Schema::table('prospects', function (Blueprint $table) {
            $table->foreignId('pipeline_type_id')->nullable()->after('secteur_id');
        });
    }
};