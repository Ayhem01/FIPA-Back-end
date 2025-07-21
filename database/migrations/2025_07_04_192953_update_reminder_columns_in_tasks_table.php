<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateReminderColumnsInTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Vérifier si la colonne existe avant de la supprimer
            if (Schema::hasColumn('tasks', 'reminder_sent')) {
                $table->dropColumn('reminder_sent');
            }
            
            // Ajouter les nouvelles colonnes
            $table->boolean('reminder_24h_sent')->default(false);
            $table->boolean('reminder_10min_sent')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Supprimer les nouvelles colonnes
            $table->dropColumn(['reminder_24h_sent', 'reminder_10min_sent']);
            
            // Recréer l'ancienne colonne en cas de rollback
            $table->boolean('reminder_sent')->default(false);
        });
    }
}