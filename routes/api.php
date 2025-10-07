<?php

use App\Http\Controllers\Api\AuthenticationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VavSiegeMediaController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\ResponsableBureauMediaController;
use App\Http\Controllers\Api\InitiateurController;
use App\Http\Controllers\Api\PaysController;
use App\Http\Controllers\Api\SecteurController;
use App\Http\Controllers\Api\CTEController;
use App\Http\Controllers\Api\BinomeController;
use App\Http\Controllers\Api\SalonsController;
use App\Http\Controllers\Api\SeminaireJIPaysController;
use App\Http\Controllers\Api\NationaliteController;
use App\Http\Controllers\Api\ResponsableFipaController;
use App\Http\Controllers\Api\GroupeController;
use App\Http\Controllers\Api\DelegationsController;
use App\Http\Controllers\Api\DemarchageDirectController;
use App\Http\Controllers\Api\ResponsableSuiviController;
use App\Http\Controllers\Api\VisitesEntrepriseController;
use App\Http\Controllers\Api\SeminaireJISecteurController;
use App\Http\Controllers\Api\SalonSectorielController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\BlockageController;
use App\Http\Controllers\Api\PipelineStageController;
use App\Http\Controllers\Api\ProjectPipelineTypeController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\ProjectContactController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\EntrepriseController;
use App\Http\Controllers\Api\ActionController;
use App\Http\Controllers\Api\EtapeController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ProspectController;
use App\Http\Controllers\Api\InvestisseurController;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Mail;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::group(['namespace' => 'Api', 'prefix' => 'auth'], function () {
  Route::post('register', [AuthenticationController::class, 'register']);
  Route::post('login', [AuthenticationController::class, 'login']);
  Route::get('logout', [AuthenticationController::class, 'destroy'])->middleware('auth:api');
  Route::post('change-password', [AuthenticationController::class, 'changePassword'])->middleware('auth:api');
  Route::post('forgot-password', [AuthenticationController::class, 'forgotPassword']);
  Route::post('reset-password', [AuthenticationController::class, 'resetPassword']);
  Route::post('verify2fa', [AuthenticationController::class, 'verify2FA'])->middleware('auth:api');
  Route::post('enable2fa', [AuthenticationController::class, 'enable2FA'])->middleware('auth:api');
  Route::get('server-time', [AuthenticationController::class, 'getServerTime'])->middleware('auth:api');
  Route::get('two-factor-status', [AuthenticationController::class, 'twoFactorStatus'])->middleware('auth:api');
  Route::post('disable2fa', [AuthenticationController::class, 'disable2FA'])->middleware('auth:api');
  Route::post('verify-login-2fa', [AuthenticationController::class, 'verifyLogin2FA'])->middleware('auth:api', 'scope:2fa-temp');
  Route::get('users', [AuthenticationController::class, 'getAllUsers'])->middleware(['auth:api', 'role:admin']);
  Route::get('user', [AuthenticationController::class, 'getCurrentUser'])->middleware('auth:api');
});
Route::group(['prefix' => 'media', 'namespace' => 'Api', 'middleware' => ['auth:api']], function () {

  Route::post('/', [MediaController::class, 'store'])->middleware('role:admin');
  Route::put('/{id}', [MediaController::class, 'update'])->middleware('role:admin');
  Route::delete('/delete/{id}', [MediaController::class, 'destroy'])->middleware('role:admin');

  Route::get('/all', [MediaController::class, 'index'])->middleware('role_or_permission:admin|responsable fipa');
  Route::get('/show/{id}', [MediaController::class, 'show'])->middleware('role_or_permission:admin|responsable fipa');
});
Route::group(['namespace' => 'Api', 'prefix' => 'responsablebureaumedia'], function () {
  Route::post('/', [ResponsableBureauMediaController::class, 'store']);
  Route::get('/all', [ResponsableBureauMediaController::class, 'index']);
  Route::get('/show/{id}', [ResponsableBureauMediaController::class, 'show']);
  Route::put('/{id}', [ResponsableBureauMediaController::class, 'update']);
  Route::delete('/delete/{id}', [ResponsableBureauMediaController::class, 'destroy']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'vavsiegemedia'], function () {
  Route::post('/', [VavSiegeMediaController::class, 'store']);
  Route::get('/all', [VavSiegeMediaController::class, 'index']);
  Route::get('/show/{id}', [VavSiegeMediaController::class, 'show']);
  Route::put('/{id}', [VavSiegeMediaController::class, 'update']);
  Route::delete('/delete/{id}', [VavSiegeMediaController::class, 'destroy']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'initiateur'], function () {
  Route::post('/', [InitiateurController::class, 'store']);
  Route::get('/all', [InitiateurController::class, 'index']);
  Route::get('/show/{id}', [InitiateurController::class, 'show']);
  Route::put('/{id}', [InitiateurController::class, 'update']);
  Route::delete('/delete/{id}', [InitiateurController::class, 'destroy']);
});

Route::group(['namespace' => 'Api', 'prefix' => 'pays'], function () {
  Route::post('/', [PaysController::class, 'store']);
  Route::get('/all', [PaysController::class, 'index']);
  Route::get('/show/{id}', [PaysController::class, 'show']);
  Route::put('/{id}', [PaysController::class, 'update']);
  Route::delete('/delete/{id}', [PaysController::class, 'destroy']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'secteur'], function () {
  Route::post('/', [SecteurController::class, 'store']);
  Route::get('/all', [SecteurController::class, 'index']);
  Route::get('/show/{id}', [SecteurController::class, 'show']);
  Route::put('/{id}', [SecteurController::class, 'update']);
  Route::delete('/delete/{id}', [SecteurController::class, 'destroy']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'cte'], function () {
  Route::post('/', [CTEController::class, 'store']);
  Route::get('/all', [CTEController::class, 'index']);
  Route::get('/show/{id}', [CTEController::class, 'show']);
  Route::put('/{id}', [CTEController::class, 'update']);
  Route::delete('/delete/{id}', [CTEController::class, 'destroy']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'binome'], function () {
  Route::post('/', [BinomeController::class, 'store']);
  Route::get('/all', [BinomeController::class, 'index']);
  Route::get('/show/{id}', [BinomeController::class, 'show']);
  Route::put('/{id}', [BinomeController::class, 'update']);
  Route::delete('/delete/{id}', [BinomeController::class, 'destroy']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'salon'], function () {
  Route::post('/', [SalonsController::class, 'store']);
  Route::get('/all', [SalonsController::class, 'index']);
  Route::get('/show/{id}', [SalonsController::class, 'show']);
  Route::put('/{id}', [SalonsController::class, 'update']);
  Route::delete('/delete/{id}', [SalonsController::class, 'destroy']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'seminaire_jipays'], function () {
  Route::post('/', [SeminaireJIPaysController::class, 'store']);
  Route::get('/all', [SeminaireJIPaysController::class, 'index']);
  Route::get('/show/{id}', [SeminaireJIPaysController::class, 'show']);
  Route::put('/{id}', [SeminaireJIPaysController::class, 'update']);
  Route::delete('/delete/{id}', [SeminaireJIPaysController::class, 'destroy']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'nationalite'], function () {
  Route::post('/', [NationaliteController::class, 'store']);
  Route::get('/all', [NationaliteController::class, 'index']);
  Route::get('/show/{id}', [NationaliteController::class, 'show']);
  Route::put('/{id}', [NationaliteController::class, 'update']);
  Route::delete('/delete/{id}', [NationaliteController::class, 'destroy']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'responsable_fipa'], function () {
  Route::post('/', [ResponsableFipaController::class, 'store']);
  Route::get('/all', [ResponsableFipaController::class, 'index']);
  Route::get('/show/{id}', [ResponsableFipaController::class, 'show']);
  Route::put('/{id}', [ResponsableFipaController::class, 'update']);
  Route::delete('/delete/{id}', [ResponsableFipaController::class, 'destroy']);
});

Route::group(['namespace' => 'Api', 'prefix' => 'groupe'], function () {
  Route::post('/', [GroupeController::class, 'store']);
  Route::get('/all', [GroupeController::class, 'index']);
  Route::get('/show/{id}', [GroupeController::class, 'show']);
  Route::put('/{id}', [GroupeController::class, 'update']);
  Route::delete('/delete/{id}', [GroupeController::class, 'destroy']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'delegations'], function () {
  Route::post('/', [DelegationsController::class, 'store']);
  Route::get('/all', [DelegationsController::class, 'index']);
  Route::get('/show/{id}', [DelegationsController::class, 'show']);
  Route::put('/{id}', [DelegationsController::class, 'update']);
  Route::delete('/delete/{id}', [DelegationsController::class, 'destroy']);
});

Route::group(['namespace' => 'Api', 'prefix' => 'responsable_suivi'], function () {
  Route::post('/', [ResponsableSuiviController::class, 'store']);
  Route::get('/all', [ResponsableSuiviController::class, 'index']);
  Route::get('/show/{id}', [ResponsableSuiviController::class, 'show']);
  Route::put('/{id}', [ResponsableSuiviController::class, 'update']);
  Route::delete('/delete/{id}', [ResponsableSuiviController::class, 'destroy']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'visites_entreprises'], function () {
  Route::post('/', [VisitesEntrepriseController::class, 'store']);
  Route::get('/all', [VisitesEntrepriseController::class, 'index']);
  Route::get('/show/{id}', [VisitesEntrepriseController::class, 'show']);
  Route::put('/{id}', [VisitesEntrepriseController::class, 'update']);
  Route::delete('/delete/{id}', [VisitesEntrepriseController::class, 'destroy']);
});

Route::group(['namespace' => 'Api', 'prefix' => 'seminaire_ji_secteur'], function () {
  Route::post('/', [SeminaireJISecteurController::class, 'store']);
  Route::get('/all', [SeminaireJISecteurController::class, 'index']);
  Route::get('/show/{id}', [SeminaireJISecteurController::class, 'show']);
  Route::put('/{id}', [SeminaireJISecteurController::class, 'update']);
  Route::delete('/delete/{id}', [SeminaireJISecteurController::class, 'destroy']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'salon_sectoriel'], function () {
  Route::post('/', [SalonSectorielController::class, 'store']);
  Route::get('/all', [SalonSectorielController::class, 'index']);
  Route::get('/show/{id}', [SalonSectorielController::class, 'show']);
  Route::put('/{id}', [SalonSectorielController::class, 'update']);
  Route::delete('/delete/{id}', [SalonSectorielController::class, 'destroy']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'demarchage_direct'], function () {
  Route::post('/', [DemarchageDirectController::class, 'store']);
  Route::get('/all', [DemarchageDirectController::class, 'index']);
  Route::get('/show/{id}', [DemarchageDirectController::class, 'show']);
  Route::put('/{id}', [DemarchageDirectController::class, 'update']);
  Route::delete('/delete/{id}', [DemarchageDirectController::class, 'destroy']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'tasks'], function () {
  Route::get('/calendar', [TaskController::class, 'getCalendarTasks'])->middleware('auth:api');
  Route::post('/{task}/move', [TaskController::class, 'moveTask'])->middleware('auth:api');
  Route::patch('/{task}/status', [TaskController::class, 'updateStatus'])->middleware('auth:api');
  Route::get('/myTasks', [TaskController::class, 'getMyTasks'])->middleware('auth:api');
  Route::get('/dashboard/stats', [TaskController::class, 'getDashboardStats'])->middleware('auth:api');
  Route::post('/', [TaskController::class, 'store'])->middleware('auth:api');
  Route::get('/all', [TaskController::class, 'index'])->middleware('auth:api');
  Route::get('/show/{id}', [TaskController::class, 'show'])->middleware('auth:api');
  Route::put('/{id}', [TaskController::class, 'update'])->middleware('auth:api');
  Route::delete('/delete/{id}', [TaskController::class, 'destroy'])->middleware('auth:api');
  Route::get('my-tasks', [TaskController::class, 'getUserTasks'])->middleware('auth:api');
  // Route::get('/pipeline', [TaskController::class, 'getTasksByPipelineStage'])->middleware('auth:api');
  // Route::get('/by-stage', [TaskController::class, 'getTasksByPipelineStage'])->middleware('auth:api');
  Route::get('/', [TaskController::class, 'index'])->middleware('auth:api');
  // Route::get('/pipeline/{entityType}/{entityId}/tasks', [TaskController::class, 'getAllPipelineTasks'])->middleware('auth:api');
  // Route::get('/pipeline/{entityType}/{entityId}/stages/{stageId}/tasks', [TaskController::class, 'getPipelineTasks'])->middleware('auth:api');

  Route::get('/pipeline/{entityType}/{entityId}/tasks', [TaskController::class, 'getAllPipelineTasks']);
  Route::get('/pipeline/{entityType}/{entityId}/stages/{stageId}/tasks', [TaskController::class, 'getPipelineTasks']);

  // Route::post('/{entityType}/{entityId}/{stageId}', [TaskController::class, 'createPipelineTask'])->middleware('auth:api');
  // Route::get('/{entityType}/{entityId}/{stageId}', [TaskController::class, 'getPipelineTasks'])->middleware('auth:api');
});



Route::group(['namespace' => 'Api', 'prefix' => 'projects'], function () {
  Route::get('/total-blocked-projects', [ProjectController::class, 'totalBlockedProjects']); // Somme des projets bloqués
  Route::get('/total-in-production-projects', [ProjectController::class, 'totalInProductionProjects']); // Somme des projets en production
  Route::get('/total-in-progress-projects', [ProjectController::class, 'totalInProgressProjects']); // Somme des projets en cours
  Route::get('/total-idea-projects', [ProjectController::class, 'totalIdeaProjects']); // Somme des projets en idée
  Route::get('/total-jobs', [ProjectController::class, 'totalJobs']); // Nombre total d'emplois
  Route::get('/investment-by-sector', [ProjectController::class, 'investmentBySector']); // Montant total d'investissement par secteur
  Route::get('/projects-by-status', [ProjectController::class, 'projectsByStatus']); // Nombre de projets par statut
  Route::get('/jobs-by-sector', [ProjectController::class, 'jobsBySector']); // Nombre total d'emplois par secteur
  Route::get('/projects-by-month', [ProjectController::class, 'projectsByMonth']); // Nombre de projets par mois
  Route::get('/delayed-projects', [ProjectController::class, 'delayedProjects']); // Projets en retard
  Route::get('/average-progression', [ProjectController::class, 'averageProgression']); // Progression moyenne des projets
  Route::get('/average-investment', [ProjectController::class, 'averageInvestment']); // Montant moyen d'investissement par projet
  Route::get('/projects-by-year', [ProjectController::class, 'projectsByYear']); // Nombre de projets par année
  Route::get('/projects-by-responsable', [ProjectController::class, 'projectsByResponsable']); // Nombre de projets par responsable
  Route::get('/high-investment-projects', [ProjectController::class, 'highInvestmentProjects']); // Projets avec des investissements élevés
  Route::get('/hierarchical-projects-by-sector', [ProjectController::class, 'hierarchicalProjectsBySector']);
  Route::get('/pipeline-progression', [ProjectController::class, 'pipelineProgression']);
  Route::get('/investment-by-region', [ProjectController::class, 'investmentByRegion']);
  
  Route::get('/', [ProjectController::class, 'index']);
  Route::post('/', [ProjectController::class, 'store']);
  Route::get('/{id}', [ProjectController::class, 'show']);
  Route::put('/{id}', [ProjectController::class, 'update']);
  Route::delete('/{id}', [ProjectController::class, 'destroy']);
  Route::patch('/{id}/status', [ProjectController::class, 'changeStatus']);
  Route::get('/secteur/{secteurId}', [ProjectController::class, 'getBySecteur']);
  Route::get('/stats', [ProjectController::class, 'stats']);
  Route::post('/{id}/pipeline/initialize', [ProjectController::class, 'initializePipeline']);
  Route::post('/{id}/pipeline/advance', [ProjectController::class, 'advanceStage']);
  Route::patch('/{id}/pipeline/stage', [ProjectController::class, 'updatePipelineStage']);
  Route::get('/{id}/pipeline', [ProjectController::class, 'getPipelineStatus']);
  Route::post('/from-investisseur', [ProjectController::class, 'createFromInvestisseur']);
  Route::get('/investisseur/{investisseurId}/data', [ProjectController::class, 'getInvestisseurDataForProject']);
  Route::post('/{id}/finalize-pipeline', [ProjectController::class, 'finalizePipelineProgression']);
});



// Routes pour les types de pipeline et étapes
Route::group(['namespace' => 'Api', 'prefix' => 'pipeline'], function () {
  // Types de pipeline
  Route::get('/types/all', [ProjectPipelineTypeController::class, 'index']);
  Route::get('/types/show/{id}', [ProjectPipelineTypeController::class, 'show']);
  Route::post('/types', [ProjectPipelineTypeController::class, 'store']);
  Route::put('/types/{id}', [ProjectPipelineTypeController::class, 'update']);
  Route::delete('/types/delete/{id}', [ProjectPipelineTypeController::class, 'destroy']);
});
// Routes pour les statistiques/dashboard
Route::group(['namespace' => 'Api', 'prefix' => 'stats'], function () {
  Route::get('/projects-by-status', [StatsController::class, 'projectsByStatus']);
  // Route::get('/projects-by-sector', [StatsController::class, 'projectsBySector']);
  Route::get('/investment-by-region', [StatsController::class, 'investmentByRegion']);
  // Route::get('/jobs-created', [StatsController::class, 'jobsCreated']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'contacts'], function () {
  Route::get('/', [ProjectContactController::class, 'index']);
  Route::post('/', [ProjectContactController::class, 'store']);
  Route::get('/{contact}', [ProjectContactController::class, 'show']);
  Route::put('/{contact}', [ProjectContactController::class, 'update']);
  Route::delete('/{contact}', [ProjectContactController::class, 'destroy']);
  Route::put('/{contact}/set-primary', [ProjectContactController::class, 'setPrimary']);
  Route::get('/project/{project}', [ProjectContactController::class, 'contactsByProject']);
});

Route::group(['namespace' => 'Api', 'prefix' => 'invites'], function () {
  Route::get('/stats', [InviteController::class, 'stats']);
  Route::get('/dashboard', [InviteController::class, 'dashboard']);
  
  // Charts individuels
  Route::get('/charts/status', [InviteController::class, 'chartByStatus']);
  Route::get('/charts/potentiel', [InviteController::class, 'chartByPotentiel']);
  Route::get('/charts/evolution', [InviteController::class, 'chartEvolutionMensuelle']);
  Route::get('/charts/pays', [InviteController::class, 'chartByPays']);
  Route::get('/charts/secteur', [InviteController::class, 'chartBySecteur']);
  Route::get('/charts/pipeline', [InviteController::class, 'chartPipelineProgression']);
  Route::get('/charts/conversion', [InviteController::class, 'chartConversionRate']);
  Route::get('/charts/type', [InviteController::class, 'chartByType']);
  Route::get('/charts/entreprises', [InviteController::class, 'chartTopEntreprises']);
  Route::get('/charts/heatmap', [InviteController::class, 'chartHeatmapCreation']);


  Route::get('/invites-by-country', [InviteController::class, 'invitesByCountry']);

  Route::get('/', [InviteController::class, 'index']);
  Route::post('/', [InviteController::class, 'store']);
  Route::get('/{id}', [InviteController::class, 'show']);
  Route::put('/{id}', [InviteController::class, 'update']);
  Route::patch('/{id}/status', [InviteController::class, 'updateStatus']);
  Route::delete('/{id}', [InviteController::class, 'destroy']);
  Route::get('/entreprise/{entrepriseId}', [InviteController::class, 'getByEntreprise']);
  Route::post('{id}/send', [InviteController::class, 'sendInvitation']);
  Route::get('confirm/{token}', [InviteController::class, 'confirm'])->name('invitations.confirm');
  Route::get('decline/{token}', [InviteController::class, 'decline'])->name('invitations.decline');
  Route::post('{id}/pipeline/initialize', [InviteController::class, 'initializePipeline']);
  Route::post('{id}/pipeline/advance', [InviteController::class, 'advanceStage'])->middleware('auth:api');
  Route::post('{id}/convert-to-prospect', [InviteController::class, 'convertToProspect']);
  Route::get('{id}/pipeline', [InviteController::class, 'getPipelineStatus'])->middleware('auth:api');
  // Route::get('{id}/pipeline/tasks', [InviteController::class, 'getAllPipelineTasks'])->middleware('auth:api');
  // Route::get('{id}/pipeline/stage/{stageId}/tasks', [InviteController::class, 'getPipelineStageTasks'])->middleware('auth:api');
  // Route::post('/{id}/pipeline/stage/{stageId}/tasks', [InviteController::class, 'createPipelineStageTask'])->middleware('auth:api');
  Route::get('/{id}/progression', [InviteController::class, 'getProgression']);
});


Route::group(['namespace' => 'Api', 'prefix' => 'pipeline-tasks'], function () {
  Route::post('/{entityType}/{entityId}/{stageId}', [TaskController::class, 'createPipelineTask'])->middleware('auth:api');
  Route::get('/{entityType}/{entityId}/{stageId}', [TaskController::class, 'getPipelineTasks'])->middleware('auth:api');
  Route::get('/{entityType}/{entityId}', [TaskController::class, 'getAllPipelineTasks']);
  Route::put('/{taskId}', [TaskController::class, 'updatePipelineTask']);
  Route::patch('/{taskId}/status', [TaskController::class, 'updatePipelineTaskStatus'])->middleware('auth:api');
  Route::patch('/{taskId}/move/{newStageId}', [TaskController::class, 'moveTaskToStage']);
  Route::delete('/{taskId}', [TaskController::class, 'deletePipelineTask']);
  Route::get('/{taskId}', [TaskController::class, 'showPipelineTask']);
});

Route::group(['namespace' => 'Api', 'prefix' => 'blockages'], function () {
  Route::get('/all', [BlockageController::class, 'indexadmin']); 
  Route::get('/', [BlockageController::class, 'index']);
  Route::get('/{blockage}', [BlockageController::class, 'show']);
  Route::post('/', [BlockageController::class, 'store'])->middleware('auth:api', 'role:admin');
  Route::put('/{blockage}', [BlockageController::class, 'update']);
  Route::post('/{blockage}/resolve', [BlockageController::class, 'resolve'])->middleware('auth:api', 'role:admin');
  Route::delete('/{blockage}', [BlockageController::class, 'destroy']);
  Route::post('/{blockage}/escalate', [BlockageController::class, 'escalate']);
  // Route::get('/by-stage', [BlockageController::class, 'getByStage']);
  Route::get('/{entityType}/{entityId}/stage/{stageId}', [BlockageController::class, 'getBlockages']);
});



Route::group(['namespace' => 'Api', 'prefix' => 'entreprises'], function () {
  Route::get('/', [EntrepriseController::class, 'index']);
  Route::post('/', [EntrepriseController::class, 'store']);
  Route::get('/{id}', [EntrepriseController::class, 'show']);
  Route::put('/{id}', [EntrepriseController::class, 'update']);
  Route::delete('/{id}', [EntrepriseController::class, 'destroy']);
  Route::patch('/{id}/pipeline-stage', [EntrepriseController::class, 'updatePipelineStage']);
  Route::get('/search/quick', [EntrepriseController::class, 'search']);
  Route::get('/dashboard/stats', [EntrepriseController::class, 'stats']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'actions'], function () {
  Route::get('/actions-treemap-data', [ActionController::class, 'getActionsTreemapData']);

  Route::get('/', [ActionController::class, 'index']);
  Route::post('/', [ActionController::class, 'store']);
  Route::get('/{id}', [ActionController::class, 'show']);
  Route::put('/{id}', [ActionController::class, 'update']);
  Route::patch('/{id}/status', [ActionController::class, 'updateStatus']);
  Route::delete('/{id}', [ActionController::class, 'destroy']);
  Route::get('/entreprise/{entrepriseId}', [ActionController::class, 'getByEntreprise']);
  Route::get('/calendar/events', [ActionController::class, 'calendar']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'etapes'], function () {
  Route::get('/', [EtapeController::class, 'index']);
  Route::post('/', [EtapeController::class, 'store']);
  Route::get('/{id}', [EtapeController::class, 'show']);
  Route::put('/{id}', [EtapeController::class, 'update']);
  Route::delete('/{id}', [EtapeController::class, 'destroy']);
  Route::get('/action/{actionId}', [EtapeController::class, 'getByAction']);
  Route::put('/reorder', [EtapeController::class, 'reorder']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'contacts'], function () {
  Route::get('/', [ContactController::class, 'index']);
  Route::post('/', [ContactController::class, 'store']);
  Route::get('/{id}', [ContactController::class, 'show']);
  Route::put('/{id}', [ContactController::class, 'update']);
  Route::delete('/{id}', [ContactController::class, 'destroy']);
  Route::put('/{id}/set-primary', [ContactController::class, 'setPrimary']);
  Route::get('/entreprise/{entrepriseId}', [ContactController::class, 'getByEntreprise']);
  Route::get('/search/quick', [ContactController::class, 'search']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'prospects'], function () {
  // Routes spécifiques AVANT les routes avec paramètres dynamiques


  Route::get('/', [ProspectController::class, 'index']);
  Route::post('/', [ProspectController::class, 'store']);
  Route::get('/{id}', [ProspectController::class, 'show']);
  Route::put('/{id}', [ProspectController::class, 'update']);
  Route::delete('/{id}', [ProspectController::class, 'destroy']);
  Route::patch('/{id}/status', [ProspectController::class, 'updateStatus']);

  Route::get('/stats', [ProspectController::class, 'stats']);
  Route::get('/entreprise/{entrepriseId}', [ProspectController::class, 'getByEntreprise']);

  // Routes CRUD de base

  // Routes du pipeline AVANT show/{id} pour éviter les conflits
  Route::post('/{id}/pipeline/initialize', [ProspectController::class, 'initializePipeline']);
  Route::post('/{id}/pipeline/advance', [ProspectController::class, 'advanceStage']);
  Route::get('/{id}/pipeline', [ProspectController::class, 'getPipelineStatus']);
  Route::post('/{id}/convert-to-investor', [ProspectController::class, 'convertToInvestor'])->middleware('auth:api');
  Route::get('/{id}/investor-data', [ProspectController::class, 'getInvestorDataForConversion']);

  // Route::get('/{id}/pipeline/stage/{stageId}/tasks', [ProspectController::class, 'getPipelineStageTasks'])->middleware('auth:api');
  // Route::post('/{id}/pipeline/stage/{stageId}/tasks', [ProspectController::class, 'createPipelineStageTask'])->middleware('auth:api');
  // Route::get('/{id}/pipeline/tasks', [ProspectController::class, 'getAllPipelineTasks'])->middleware('auth:api');   
});
Route::group(['namespace' => 'Api', 'prefix' => 'investisseurs'], function () {
  Route::get('/', [InvestisseurController::class, 'index']);
  Route::post('/', [InvestisseurController::class, 'store']);
  Route::get('/{id}', [InvestisseurController::class, 'show']);
  Route::put('/{id}', [InvestisseurController::class, 'update']);
  Route::delete('/{id}', [InvestisseurController::class, 'destroy']);
  Route::patch('/{id}/status', [InvestisseurController::class, 'updateStatus']);
  Route::get('/entreprise/{entrepriseId}', [InvestisseurController::class, 'getByEntreprise']);
  Route::get('/stats', [InvestisseurController::class, 'stats']);
  Route::post('/{id}/pipeline/initialize', [InvestisseurController::class, 'initializePipeline']);
  Route::post('/{id}/pipeline/advance', [InvestisseurController::class, 'advanceStage'])->middleware('auth:api');
  Route::get('/{id}/pipeline', [InvestisseurController::class, 'getPipelineStatus']);
  Route::post('/{id}/convert-to-project', [InvestisseurController::class, 'convertToProject']);
});
Route::group(['namespace' => 'Api', 'prefix' => 'pipeline-stages'], function () {
  // Routes génériques pour tous les types d'entités
  Route::get('/{entityType}', [PipelineStageController::class, 'index'])->middleware('auth:api');
  Route::get('/{entityType}/{id}', [PipelineStageController::class, 'show'])->middleware('auth:api');
  Route::get('/{entityType}/{entityId}/stage/{stageId}', [PipelineStageController::class, 'getStageDetails']);

  // Routes protégées par le rôle admin
  Route::middleware('role:admin')->group(function () {
    Route::post('/{entityType}', [PipelineStageController::class, 'store'])->middleware('auth:api');
    Route::put('/{entityType}/{id}', [PipelineStageController::class, 'update'])->middleware('auth:api');
    Route::delete('/{entityType}/{id}', [PipelineStageController::class, 'destroy'])->middleware('auth:api');
    Route::post('/{entityType}/reorder', [PipelineStageController::class, 'reorder'])->middleware('auth:api');
  });
});


Route::middleware('auth:api')->group(function () {
  Route::get('user', [AuthenticationController::class, 'user']);
});
