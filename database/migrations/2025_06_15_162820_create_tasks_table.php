<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Titre de la tâche
            $table->text('description')->nullable(); // Description détaillée
            $table->dateTime('start')->nullable(); // Date de début
            $table->dateTime('end')->nullable(); // Date de fin
            $table->boolean('all_day')->default(false); // Tâche sur toute la journée
            $table->enum('type', ['call', 'meeting', 'email_journal', 'note', 'todo']); // Type de tâche
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'deferred', 'waiting'])
                  ->default('not_started'); // Statut
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal'); // Priorité
            $table->string('color')->nullable(); // Couleur dans le calendrier
            
            // Relations
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Créateur
            $table->foreignId('assignee_id')->nullable()->constrained('users')->onDelete('set null'); // Assigné à
        
            
            $table->timestamps();
            $table->softDeletes(); // Pour la suppression douce
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};