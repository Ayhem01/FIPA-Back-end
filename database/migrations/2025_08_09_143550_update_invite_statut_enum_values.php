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
        // Convertir d'abord en VARCHAR pour pouvoir modifier l'enum
        DB::statement('ALTER TABLE invites MODIFY statut VARCHAR(50)');
        
        // Mettre à jour les valeurs qui ne sont plus valides
        DB::statement("UPDATE invites SET statut = 'confirmee' WHERE statut IN ('envoyee', 'details_envoyes', 'participation_confirmee', 'participation_sans_suivi')");
        DB::statement("UPDATE invites SET statut = 'refusee' WHERE statut IN ('absente', 'aucune_reponse')");
        
        // Reconvertir en ENUM avec les nouvelles valeurs
        DB::statement("ALTER TABLE invites MODIFY statut ENUM('en_attente', 'confirmee', 'refusee') DEFAULT 'en_attente'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pour revenir en arrière, on reconvertit en ENUM avec toutes les valeurs précédentes
        DB::statement('ALTER TABLE invites MODIFY statut VARCHAR(50)');
        DB::statement("ALTER TABLE invites MODIFY statut ENUM(
            'en_attente', 'envoyee', 'confirmee', 'refusee', 
            'details_envoyes', 'participation_confirmee', 
            'participation_sans_suivi', 'absente', 'aucune_reponse'
        ) DEFAULT 'en_attente'");
    }
};