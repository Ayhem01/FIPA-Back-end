<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        schema::disableForeignKeyConstraints();
        Schema::create('project_blockages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('name');
            $table->string('type');
            $table->text('description')->nullable();
            $table->string('status')->default('active'); // active, resolved, cancelled
            $table->string('priority')->default('normal'); // high, normal, low
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->boolean('blocks_progress')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('project_blockages');
    }
};