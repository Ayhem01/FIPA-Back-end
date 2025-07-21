<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        schema::disableForeignKeyConstraints();
        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('name');
            $table->string('file_path');
            $table->string('file_type');
            $table->integer('file_size')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('project_documents');
    }
};