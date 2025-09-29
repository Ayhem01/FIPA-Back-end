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
        Schema::create('blockages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
        
            // Polymorphisme (liée à Invite, Prospect, Investisseur ou Projet)
            $table->morphs('blockable'); // => blockable_type + blockable_id
        
            // Polymorphisme pour pipeline stage (n’importe quel type de stage)
            $table->morphs('pipeline_stageable'); // => pipeline_stageable_type + pipeline_stageable_id
        
            $table->enum('blockage_type', ['process', 'data', 'technical', 'other'])->default('other');
            $table->enum('status', ['actif', 'resolu', 'annule'])->default('actif');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
        
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->boolean('is_blocking')->default(true);
        
            $table->timestamps();
        });
        
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blockages');
    }
};
