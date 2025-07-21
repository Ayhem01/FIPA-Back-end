<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('etapes', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->integer('ordre')->default(0);
            $table->string('couleur')->default('#3498db'); // Code couleur hexadécimal
            $table->integer('duree_estimee')->nullable(); // En minutes
            $table->boolean('est_obligatoire')->default(true);
            $table->foreignId('action_id')->constrained('actions')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('etapes');
    }
};