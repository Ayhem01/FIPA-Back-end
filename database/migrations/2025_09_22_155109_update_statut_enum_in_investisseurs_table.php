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
        DB::statement("ALTER TABLE investisseurs MODIFY statut ENUM('actif', 'negociation', 'engagement', 'finalisation', 'investi', 'suspendu', 'inactif', 'converti') DEFAULT 'actif'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE investisseurs MODIFY statut ENUM('actif', 'negociation', 'engagement', 'finalisation', 'investi', 'suspendu', 'inactif') DEFAULT 'actif'");
    }
};
