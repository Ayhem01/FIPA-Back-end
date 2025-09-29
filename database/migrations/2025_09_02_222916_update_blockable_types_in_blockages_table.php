<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Option 1: Si vous souhaitez convertir les noms de classes complets en noms courts
        DB::table('blockages')
            ->where('blockable_type', 'App\\Models\\Invite')
            ->update(['blockable_type' => 'invite']);

        DB::table('blockages')
            ->where('blockable_type', 'App\\Models\\Prospect')
            ->update(['blockable_type' => 'prospect']);
            
        DB::table('blockages')
            ->where('blockable_type', 'App\\Models\\Investisseur')
            ->update(['blockable_type' => 'investisseur']);
            
        DB::table('blockages')
            ->where('blockable_type', 'App\\Models\\Project')
            ->update(['blockable_type' => 'projet']);

        DB::table('blockages')
            ->where('pipeline_stageable_type', 'App\\Models\\InvitePipelineStage')
            ->update(['pipeline_stageable_type' => 'invite_pipeline_stage']);
            
        DB::table('blockages')
            ->where('pipeline_stageable_type', 'App\\Models\\ProspectPipelineStage')
            ->update(['pipeline_stageable_type' => 'prospect_pipeline_stage']);
            
        DB::table('blockages')
            ->where('pipeline_stageable_type', 'App\\Models\\InvestorPipelineStage')
            ->update(['pipeline_stageable_type' => 'investisseur_pipeline_stage']);
            
        DB::table('blockages')
            ->where('pipeline_stageable_type', 'App\\Models\\ProjectPipelineStage')
            ->update(['pipeline_stageable_type' => 'projet_pipeline_stage']);
    
            
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

    }
};