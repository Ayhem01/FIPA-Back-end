<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_conversions', function (Blueprint $table) {
            $table->id();
            $table->string('source_type'); // invite, prospect, investor
            $table->unsignedBigInteger('source_id');
            $table->string('target_type'); // prospect, investor, project
            $table->unsignedBigInteger('target_id');
            $table->unsignedBigInteger('converted_by');
            $table->text('conversion_notes')->nullable();
            $table->timestamps();
            
            $table->foreign('converted_by')->references('id')->on('users')->onDelete('cascade');
            
            // Index composites
            $table->index(['source_type', 'source_id']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_conversions');
    }
};