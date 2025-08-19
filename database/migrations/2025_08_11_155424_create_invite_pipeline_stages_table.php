<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invite_pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_type_id')
                ->constrained('invite_pipeline_types')
                ->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_final')->default(false);
            $table->string('color')->default('#4A90E2');
            $table->string('status')->default('open'); // open, success, lost
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invite_pipeline_stages');
    }
};