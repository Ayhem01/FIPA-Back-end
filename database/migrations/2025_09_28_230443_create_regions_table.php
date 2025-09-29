<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRegionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); 
            $table->text('description')->nullable(); 
            $table->timestamps(); 
        });

        // Ajouter la clé étrangère dans la table projets
        Schema::table('projets', function (Blueprint $table) {
            $table->unsignedBigInteger('region_id')->nullable()->after('secteur_id'); // Clé étrangère
            $table->foreign('region_id')->references('id')->on('regions')->onDelete('set null'); // Relation avec regions
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('projets', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropColumn('region_id');
        });

        Schema::dropIfExists('regions');
    }
}
