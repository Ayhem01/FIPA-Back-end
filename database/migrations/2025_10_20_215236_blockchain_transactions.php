<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blockchain_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Lien fonctionnel
            $table->string('related_type', 50)->nullable();   // ex: 'invite'
            $table->unsignedBigInteger('related_id')->nullable();

            // Opération/metadonnées
            $table->string('action', 100);                    // ex: 'add_inviter'
            $table->string('status', 20)->default('pending'); // pending|success|failed

            // Données blockchain
            $table->string('tx_hash', 100)->nullable()->unique();
            $table->unsignedBigInteger('block_number')->nullable();
            $table->string('contract_address', 100)->nullable();
            $table->string('from_address', 100)->nullable();
            $table->string('to_address', 100)->nullable();
            $table->unsignedBigInteger('gas_used')->nullable();
            $table->string('gas_price', 100)->nullable();
            $table->unsignedInteger('chain_id')->nullable();
            $table->string('network', 50)->nullable();        // ex: ganache

            // Traces requête/réponse (sans clés privées)
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['related_type', 'related_id']);
            $table->index(['action', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blockchain_transactions');
    }
};