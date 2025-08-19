<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invite_pipeline_progressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invite_id')->constrained('invites')->onDelete('cascade');
            $table->foreignId('stage_id')->constrained('invite_pipeline_stages')->onDelete('cascade');
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->timestamps();
            
            // Empêcher les doublons pour un même invité/étape
            $table->unique(['invite_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invite_pipeline_progressions');
    }
};