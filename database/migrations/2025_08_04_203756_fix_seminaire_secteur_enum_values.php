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
        // Correction pour inclure
        DB::statement('ALTER TABLE seminaires_j_i_secteurs MODIFY inclure VARCHAR(30)');
        DB::statement("UPDATE seminaires_j_i_secteurs SET inclure = 'comptabilisée' WHERE inclure = ' comptabilisée'");
        DB::statement("ALTER TABLE seminaires_j_i_secteurs MODIFY inclure ENUM('comptabilisée', 'non comptabilisée') DEFAULT 'comptabilisée'");
        
        // Correction pour action_conjointe
        DB::statement('ALTER TABLE seminaires_j_i_secteurs MODIFY action_conjointe VARCHAR(30)');
        DB::statement("UPDATE seminaires_j_i_secteurs SET action_conjointe = 'conjointe' WHERE action_conjointe = ' conjointe'");
        DB::statement("UPDATE seminaires_j_i_secteurs SET action_conjointe = 'non conjointe' WHERE action_conjointe = ' non conjointe'");
        DB::statement("ALTER TABLE seminaires_j_i_secteurs MODIFY action_conjointe ENUM('conjointe', 'non conjointe') NULL");
        
        // Correction pour type_participation - normaliser les valeurs
        DB::statement('ALTER TABLE seminaires_j_i_secteurs MODIFY type_participation VARCHAR(50)');
        DB::statement("UPDATE seminaires_j_i_secteurs SET type_participation = 'organisatrice' WHERE type_participation = ' organisatrice'");
        DB::statement("UPDATE seminaires_j_i_secteurs SET type_participation = 'co-organisateur' WHERE type_participation = 'Co-organisateur'");
        DB::statement("UPDATE seminaires_j_i_secteurs SET type_participation = 'participation active' WHERE type_participation = 'Participation active'");
        DB::statement("UPDATE seminaires_j_i_secteurs SET type_participation = 'simple présence' WHERE type_participation = ' simple présence'");
        DB::statement("ALTER TABLE seminaires_j_i_secteurs MODIFY type_participation ENUM('organisatrice', 'co-organisateur', 'participation active', 'simple présence') NULL");
        
        // Correction pour type_organisation - retirer la virgule finale et normaliser
        DB::statement('ALTER TABLE seminaires_j_i_secteurs MODIFY type_organisation VARCHAR(50)');
        DB::statement("UPDATE seminaires_j_i_secteurs SET type_organisation = 'partenaires étrangers' WHERE type_organisation = ' partenaires étrangers'");
        DB::statement("UPDATE seminaires_j_i_secteurs SET type_organisation = 'partenaires tunisiens' WHERE type_organisation = ' partenaires tunisiens'");
        DB::statement("UPDATE seminaires_j_i_secteurs SET type_organisation = 'les deux à la fois' WHERE type_organisation = ' les deux à la fois'");
        DB::statement("ALTER TABLE seminaires_j_i_secteurs MODIFY type_organisation ENUM('partenaires étrangers', 'partenaires tunisiens', 'les deux à la fois') NULL");
        
        // Correction pour avec_diaspora - supprimer les espaces inutiles
        DB::statement('ALTER TABLE seminaires_j_i_secteurs MODIFY avec_diaspora VARCHAR(50)');
        DB::statement("UPDATE seminaires_j_i_secteurs SET avec_diaspora = 'organisée pour la diaspora' WHERE avec_diaspora = ' organisée pour la diaspora'");
        DB::statement("UPDATE seminaires_j_i_secteurs SET avec_diaspora = 'organisée avec la diaspora' WHERE avec_diaspora = ' organisée avec la diaspora '");
        DB::statement("ALTER TABLE seminaires_j_i_secteurs MODIFY avec_diaspora ENUM('organisée pour la diaspora', 'organisée avec la diaspora') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Si besoin de revenir en arrière, on restaure les définitions originales
        DB::statement('ALTER TABLE seminaires_j_i_secteurs MODIFY inclure VARCHAR(30)');
        DB::statement("ALTER TABLE seminaires_j_i_secteurs MODIFY inclure ENUM('comptabilisée', 'non comptabilisée') DEFAULT 'comptabilisée'");
        
        DB::statement('ALTER TABLE seminaires_j_i_secteurs MODIFY action_conjointe VARCHAR(30)');
        DB::statement("ALTER TABLE seminaires_j_i_secteurs MODIFY action_conjointe ENUM('conjointe', 'non conjointe') NULL");
        
        DB::statement('ALTER TABLE seminaires_j_i_secteurs MODIFY type_participation VARCHAR(50)');
        DB::statement("ALTER TABLE seminaires_j_i_secteurs MODIFY type_participation ENUM('organisatrice', 'Co-organisateur', 'Participation active', 'simple présence') NULL");
        
        DB::statement('ALTER TABLE seminaires_j_i_secteurs MODIFY type_organisation VARCHAR(50)');
        DB::statement("ALTER TABLE seminaires_j_i_secteurs MODIFY type_organisation ENUM('partenaires étrangers', 'partenaires tunisiens', 'les deux à la fois') NULL");
        
        DB::statement('ALTER TABLE seminaires_j_i_secteurs MODIFY avec_diaspora VARCHAR(50)');
        DB::statement("ALTER TABLE seminaires_j_i_secteurs MODIFY avec_diaspora ENUM('organisée pour la diaspora', ' organisée avec la diaspora ') NULL");
    }
};