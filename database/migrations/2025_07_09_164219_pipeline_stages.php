<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        schema::disableForeignKeyConstraints();
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pipeline_type_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('open'); // 'open', 'success', 'lost'
            $table->string('color')->default('#3498db');
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_stages');
    }
};