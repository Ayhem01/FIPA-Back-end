<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Project;
use App\Models\Entreprise;

class AddEntrepriseIdToProjetsTable extends Migration
{
    public function up()
    {
        Schema::table('projets', function (Blueprint $table) {
            // ✅ Ajouter la nouvelle colonne (ne pas supprimer company_name)
            $table->unsignedBigInteger('entreprise_id')->nullable()->after('company_name');
            $table->index('entreprise_id'); // Index pour performance
        });

        // Migrer les données existantes
        $this->migrateExistingData();

        // Ajouter la contrainte APRÈS migration des données
        Schema::table('projets', function (Blueprint $table) {
            $table->foreign('entreprise_id')->references('id')->on('entreprises')
                  ->onDelete('set null');
        });

        // Log du résultat
        $this->logMigrationResults();
    }

    private function migrateExistingData()
    {
        \Log::info('Début migration company_name vers entreprise_id');
        
        $projets = Project::whereNotNull('company_name')
                         ->whereNull('entreprise_id') // Éviter les doublons
                         ->get();
        
        $success = 0;
        $failed = 0;
        
        foreach ($projets as $projet) {
            $entreprise = Entreprise::where('nom', $projet->company_name)->first();
            
            if ($entreprise) {
                $projet->entreprise_id = $entreprise->id;
                $projet->save();
                $success++;
            } else {
                \Log::warning("Entreprise non trouvée pour le projet {$projet->id}: {$projet->company_name}");
                $failed++;
            }
        }
        
        \Log::info("Migration terminée: {$success} succès, {$failed} échecs");
    }

    private function logMigrationResults()
    {
        $total = Project::count();
        $withEntrepriseId = Project::whereNotNull('entreprise_id')->count();
        $withCompanyName = Project::whereNotNull('company_name')->count();
        
        \Log::info("État après migration:", [
            'total_projets' => $total,
            'avec_entreprise_id' => $withEntrepriseId,
            'avec_company_name' => $withCompanyName,
            'pourcentage_migré' => round(($withEntrepriseId / $total) * 100, 2) . '%'
        ]);
    }

    public function down()
    {
        Schema::table('projets', function (Blueprint $table) {
            $table->dropForeign(['entreprise_id']);
            $table->dropIndex(['entreprise_id']);
            $table->dropColumn('entreprise_id');
        });
    }
}