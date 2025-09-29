<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->boolean('is_converted')->default(false)->after('date_conversion');
        });
        
        // Mettre à jour les enregistrements existants
        // DB::statement('UPDATE invites SET is_converted = 1 WHERE date_conversion IS NOT NULL');
    }

    public function down()
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->dropColumn('is_converted');
        });
    }
};