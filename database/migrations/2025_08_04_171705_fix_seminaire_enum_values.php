<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. D'abord, on convertit l'enum en VARCHAR pour pouvoir modifier les données
        DB::statement('ALTER TABLE seminaire_j_i_pays MODIFY type_organisation VARCHAR(100)');
        
        // 2. Mettre à jour les valeurs existantes - corriger l'espace au début
        DB::statement("UPDATE seminaire_j_i_pays SET type_organisation = 'partenaires tunisiens' WHERE type_organisation = ' partenaires tunisiens'");
        
        // 3. Recréer l'enum avec les valeurs EXACTES du frontend
        DB::statement("ALTER TABLE seminaire_j_i_pays MODIFY type_organisation ENUM('partenaires étrangers', 'partenaires tunisiens', 'les deux à la fois') NULL");
        
        // 4. Si vous avez d'autres enums à corriger, faites-le ici
        // Exemple pour diaspora
        DB::statement('ALTER TABLE seminaire_j_i_pays MODIFY diaspora VARCHAR(100)');
        DB::statement("ALTER TABLE seminaire_j_i_pays MODIFY diaspora ENUM('Pour la diaspora', 'Avec la diaspora') NULL");
        
        // Exemple pour presence_conjointe
        DB::statement('ALTER TABLE seminaire_j_i_pays MODIFY presence_conjointe VARCHAR(100)');
        DB::statement("ALTER TABLE seminaire_j_i_pays MODIFY presence_conjointe ENUM('Conjointe', 'Non Conjointe') NULL");
        
        // Exemple pour type_participation
        DB::statement('ALTER TABLE seminaire_j_i_pays MODIFY type_participation VARCHAR(100)');
        DB::statement("ALTER TABLE seminaire_j_i_pays MODIFY type_participation ENUM('Co-organisateur', 'Participation active', 'Simple présence') NULL");
        
        // Exemple pour inclure
        DB::statement('ALTER TABLE seminaire_j_i_pays MODIFY inclure VARCHAR(10)');
        DB::statement("ALTER TABLE seminaire_j_i_pays MODIFY inclure ENUM('Yes', 'No') DEFAULT 'Yes'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // En cas de rollback, remettre les enums d'origine (avec l'espace)
        DB::statement('ALTER TABLE seminaire_j_i_pays MODIFY type_organisation VARCHAR(100)');
        DB::statement("ALTER TABLE seminaire_j_i_pays MODIFY type_organisation ENUM('partenaires étrangers', ' partenaires tunisiens', 'les deux à la fois') NULL");
        
        // Revenir aux définitions originales des autres enums si nécessaire
    }
};