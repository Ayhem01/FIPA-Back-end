<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->foreignId('investisseur_id')->nullable()->constrained('investisseurs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropForeign(['investisseur_id']);
            $table->dropColumn('investisseur_id');
        });
    }
};