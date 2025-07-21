<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        schema::disableForeignKeyConstraints();
        Schema::create('project_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id');
            $table->date('follow_up_date');
            $table->text('description');
            $table->date('next_follow_up_date')->nullable();
            $table->boolean('completed')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('project_follow_ups');
    }
};