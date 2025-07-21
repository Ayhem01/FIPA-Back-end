<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SuivieProjet\StatsExceptionHandler;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Sector;
use App\Models\Governorate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /**
     * Récupère les statistiques des projets par statut
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function projectsByStatus(Request $request)
    {
        try {
            $query = Project::query();
            
            // Appliquer le filtre de période si demandé
            if ($request->has('period')) {
                $this->applyPeriodFilter($query, $request->period);
            }

            // Statistiques par statut (idée, en cours, en production)
            $stats = [
                'idea' => (clone $query)->where('idea', true)->count(),
                'in_progress' => (clone $query)->where('in_progress', true)->count(),
                'in_production' => (clone $query)->where('in_production', true)->count(),
                'total' => $query->count()
            ];

            // Obtenir aussi les statistiques par étape de pipeline
            $pipelineStats = DB::table('projects')
                ->join('pipeline_stages', 'projects.pipeline_stage_id', '=', 'pipeline_stages.id')
                ->select('pipeline_stages.name', DB::raw('count(*) as count'))
                ->groupBy('pipeline_stages.name')
                ->get();

            return response()->json([
                'status_stats' => $stats,
                'pipeline_stats' => $pipelineStats,
            ]);

        } catch (\Exception $e) {
            return StatsExceptionHandler::handle($e);
        }
    }

    /**
     * Récupère les statistiques des projets par secteur
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function projectsBySector(Request $request)
    {
        try {
            // Statistiques de base par secteur
            $query = DB::table('projects')
                ->join('sectors', 'projects.sector_id', '=', 'sectors.id')
                ->select('sectors.name', DB::raw('count(*) as project_count'));

            // Appliquer le filtre de période si demandé
            if ($request->has('period')) {
                $this->applyPeriodFilter($query, $request->period, 'projects');
            }

            // Si demandé, inclure le montant total d'investissement par secteur
            if ($request->has('include_investment') && $request->include_investment) {
                $query->addSelect(DB::raw('SUM(projects.investment_amount) as total_investment'));
            }
            
            // Si demandé, inclure le nombre total d'emplois par secteur
            if ($request->has('include_jobs') && $request->include_jobs) {
                $query->addSelect(DB::raw('SUM(projects.jobs_expected) as jobs_expected'));
            }

            $stats = $query->groupBy('sectors.name')
                ->orderByDesc('project_count')
                ->get();

            return response()->json($stats);

        } catch (\Exception $e) {
            return StatsExceptionHandler::handle($e);
        }
    }

    /**
     * Récupère les statistiques des investissements par région
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function investmentByRegion(Request $request)
    {
        try {
            $query = DB::table('projects')
                ->join('governorates', 'projects.governorate_id', '=', 'governorates.id')
                ->select('governorates.name', 
                    DB::raw('SUM(projects.investment_amount) as total_investment'),
                    DB::raw('COUNT(*) as project_count'))
                ->whereNotNull('projects.investment_amount');

            // Appliquer le filtre de statut si demandé
            if ($request->has('status')) {
                $status = $request->status;
                if ($status === 'idea') {
                    $query->where('projects.idea', true);
                } elseif ($status === 'in_progress') {
                    $query->where('projects.in_progress', true);
                } elseif ($status === 'in_production') {
                    $query->where('projects.in_production', true);
                }
            }

            // Appliquer le filtre de période si demandé
            if ($request->has('period')) {
                $this->applyPeriodFilter($query, $request->period, 'projects');
            }

            $stats = $query->groupBy('governorates.name')
                ->orderByDesc('total_investment')
                ->get();

            return response()->json($stats);

        } catch (\Exception $e) {
            return StatsExceptionHandler::handle($e);
        }
    }

    /**
     * Récupère les statistiques des emplois créés
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function jobsCreated(Request $request)
    {
        try {
            // Statistiques de base des emplois
            $query = Project::select(
                DB::raw('SUM(jobs_expected) as total_jobs'),
                DB::raw('AVG(jobs_expected) as avg_jobs_per_project'),
                DB::raw('COUNT(*) as project_count')
            )->whereNotNull('jobs_expected');

            // Appliquer le filtre de statut si demandé
            if ($request->has('status')) {
                $status = $request->status;
                if ($status === 'idea') {
                    $query->where('idea', true);
                } elseif ($status === 'in_progress') {
                    $query->where('in_progress', true);
                } elseif ($status === 'in_production') {
                    $query->where('in_production', true);
                }
            }

            // Appliquer le filtre de période si demandé
            if ($request->has('period')) {
                $this->applyPeriodFilter($query, $request->period);
            }

            $generalStats = $query->first();

            // Répartition par gouvernorat
            $jobsByRegion = DB::table('projects')
                ->join('governorates', 'projects.governorate_id', '=', 'governorates.id')
                ->select('governorates.name', DB::raw('SUM(projects.jobs_expected) as total_jobs'))
                ->whereNotNull('projects.jobs_expected')
                ->groupBy('governorates.name')
                ->orderByDesc('total_jobs')
                ->get();

            // Répartition par secteur
            $jobsBySector = DB::table('projects')
                ->join('sectors', 'projects.sector_id', '=', 'sectors.id')
                ->select('sectors.name', DB::raw('SUM(projects.jobs_expected) as total_jobs'))
                ->whereNotNull('projects.jobs_expected')
                ->groupBy('sectors.name')
                ->orderByDesc('total_jobs')
                ->get();

            return response()->json([
                'general_stats' => $generalStats,
                'jobs_by_region' => $jobsByRegion,
                'jobs_by_sector' => $jobsBySector
            ]);

        } catch (\Exception $e) {
            return StatsExceptionHandler::handle($e);
        }
    }

    /**
     * Méthode utilitaire pour appliquer des filtres de période aux requêtes
     *
     * @param $query
     * @param string $period
     * @param string|null $tablePrefix
     * @return void
     */
    private function applyPeriodFilter($query, string $period, string $tablePrefix = null)
    {
        $column = $tablePrefix ? "{$tablePrefix}.created_at" : "created_at";
        
        switch ($period) {
            case 'today':
                $query->whereDate($column, Carbon::today());
                break;
            case 'yesterday':
                $query->whereDate($column, Carbon::yesterday());
                break;
            case 'this_week':
                $query->whereBetween($column, [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'last_week':
                $query->whereBetween($column, [
                    Carbon::now()->subWeek()->startOfWeek(),
                    Carbon::now()->subWeek()->endOfWeek()
                ]);
                break;
            case 'this_month':
                $query->whereYear($column, Carbon::now()->year)
                    ->whereMonth($column, Carbon::now()->month);
                break;
            case 'last_month':
                $date = Carbon::now()->subMonth();
                $query->whereYear($column, $date->year)
                    ->whereMonth($column, $date->month);
                break;
            case 'this_quarter':
                $query->whereBetween($column, [
                    Carbon::now()->startOfQuarter(),
                    Carbon::now()->endOfQuarter()
                ]);
                break;
            case 'this_year':
                $query->whereYear($column, Carbon::now()->year);
                break;
            case 'last_year':
                $query->whereYear($column, Carbon::now()->subYear()->year);
                break;
            case 'custom':
                if (request()->has('from') && request()->has('to')) {
                    $query->whereBetween($column, [
                        Carbon::parse(request()->from)->startOfDay(),
                        Carbon::parse(request()->to)->endOfDay()
                    ]);
                }
                break;
        }
    }

    /**
     * Récupère des statistiques générales pour le tableau de bord
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function dashboardStats()
    {
        try {
            // Nombre total de projets
            $totalProjects = Project::count();
            
            // Projets par statut
            $projectsByStatus = [
                'idea' => Project::where('idea', true)->count(),
                'in_progress' => Project::where('in_progress', true)->count(),
                'in_production' => Project::where('in_production', true)->count()
            ];
            
            // Total des investissements
            $totalInvestment = Project::whereNotNull('investment_amount')
                ->sum('investment_amount');
                
            // Total des emplois prévus
            $totalJobs = Project::whereNotNull('jobs_expected')
                ->sum('jobs_expected');
                
            // Projets bloqués
            $blockedProjects = Project::where('is_blocked', true)->count();
            
            // Projets créés ce mois-ci
            $projectsThisMonth = Project::whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->count();
                
            // Projets par origine
            $projectsBySource = Project::whereNotNull('contact_source')
                ->select('contact_source', DB::raw('COUNT(*) as count'))
                ->groupBy('contact_source')
                ->get();
                
            // Prochains suivis à effectuer
            $upcomingFollowUps = DB::table('project_follow_ups')
                ->join('projects', 'project_follow_ups.project_id', '=', 'projects.id')
                ->join('users', 'project_follow_ups.user_id', '=', 'users.id')
                ->select(
                    'projects.id as project_id',
                    'projects.title',
                    'project_follow_ups.follow_up_date',
                    'users.name as user_name'
                )
                ->where('project_follow_ups.completed', false)
                ->where('project_follow_ups.follow_up_date', '>=', Carbon::today())
                ->where('project_follow_ups.follow_up_date', '<=', Carbon::now()->addDays(7))
                ->orderBy('project_follow_ups.follow_up_date')
                ->limit(5)
                ->get();

            return response()->json([
                'total_projects' => $totalProjects,
                'projects_by_status' => $projectsByStatus,
                'total_investment' => $totalInvestment,
                'total_jobs' => $totalJobs,
                'blocked_projects' => $blockedProjects,
                'projects_this_month' => $projectsThisMonth,
                'projects_by_source' => $projectsBySource,
                'upcoming_follow_ups' => $upcomingFollowUps
            ]);
            
        } catch (\Exception $e) {
            return StatsExceptionHandler::handle($e);
        }
    }
}