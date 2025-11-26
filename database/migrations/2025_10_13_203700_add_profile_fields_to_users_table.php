<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
 

            // Profil
            $table->string('phone', 20)->nullable()->after('password');
            $table->string('position', 100)->nullable()->after('phone');
            $table->string('address')->nullable()->after('position');
            $table->string('photo')->nullable()->after('address'); // chemin vers l’avatar
            $table->date('birth_date')->nullable()->after('photo');
            $table->string('gender', 20)->nullable()->after('birth_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'birth_date',
                'photo',
                'address',
                'position',
                'phone',
              
            ]);
        });
    }
};