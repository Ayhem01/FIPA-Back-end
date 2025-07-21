<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises');
            $table->foreignId('action_id')->nullable()->constrained('actions');
            $table->foreignId('etape_id')->nullable()->constrained('etapes');
            $table->string('nom');
            $table->string('prenom');
            $table->string('email');
            $table->string('telephone')->nullable();
            $table->string('fonction')->nullable();
            $table->enum('type_invite', ['interne', 'externe'])->default('externe');
            $table->enum('statut', [
                'en_attente', 'envoyee', 'confirmee', 'refusee', 
                'details_envoyes', 'participation_confirmee', 
                'participation_sans_suivi', 'absente', 'aucune_reponse'
            ])->default('en_attente');
            $table->boolean('suivi_requis')->default(false);
            $table->datetime('date_invitation')->nullable();
            $table->datetime('date_evenement')->nullable();
            $table->text('commentaires')->nullable();
            $table->foreignId('proprietaire_id')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
    }
};