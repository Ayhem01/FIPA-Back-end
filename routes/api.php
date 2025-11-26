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
use App\Http\Controllers\Api\UserController;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;


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
Route::get('/check', function (Request $request) {
    $user = $request->user('api'); // Passport guard
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
    return [
        'ok' => true,
        'id' => $user->id,
        'email' => $user->email,
        'roles' => method_exists($user,'getRoleNames') ? $user->getRoleNames() : [],
    ];
})->middleware('auth:api');

Route::prefix('auth')->group(function () {
    // Public
    Route::post('login', [AuthenticationController::class, 'login']);
    Route::post('forgot-password', [AuthenticationController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthenticationController::class, 'resetPassword']);

    // Admin only
    Route::post('register', [AuthenticationController::class, 'register'])
        ->middleware(['auth:api', 'role:admin']);
    Route::get('users', [AuthenticationController::class, 'getAllUsers'])
        ->middleware(['auth:api', 'role:admin|responsable fipa']);

    // Authentifié: admin ou responsable fipa
    Route::middleware(['auth:api', 'role:admin|responsable fipa'])->group(function () {
        Route::get('logout', [AuthenticationController::class, 'destroy']);
        Route::post('change-password', [AuthenticationController::class, 'changePassword']);
        Route::post('verify2fa', [AuthenticationController::class, 'verify2FA']);
        Route::post('enable2fa', [AuthenticationController::class, 'enable2FA']);
        Route::get('server-time', [AuthenticationController::class, 'getServerTime']);
        Route::get('two-factor-status', [AuthenticationController::class, 'twoFactorStatus']);
        Route::post('disable2fa', [AuthenticationController::class, 'disable2FA']);
        Route::get('user', [AuthenticationController::class, 'getCurrentUser']);
    });

    // Étape 2FA du login (token avec scope 2fa-temp)
    Route::post('verify-login-2fa', [AuthenticationController::class, 'verifyLogin2FA'])
        ->middleware(['auth:api', 'scope:2fa-temp']);
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

Route::get('/users/all-with-roles', [UserController::class, 'index2'])
    ->middleware(['auth:api','role:responsable fipa']);

Route::prefix('tasks')
    ->middleware(['auth:api', 'role:admin|responsable fipa'])
    ->group(function () {
        // Route::get('/', [TaskController::class, 'index']);
        Route::get('/all', [TaskController::class, 'index']);
        Route::get('/show/{id}', [TaskController::class, 'show']);

        Route::post('/', [TaskController::class, 'store']);
        Route::put('/{id}', [TaskController::class, 'update']);
        Route::delete('/delete/{id}', [TaskController::class, 'destroy']);

        Route::get('/calendar', [TaskController::class, 'getCalendarTasks']);
        Route::get('/dashboard/stats', [TaskController::class, 'getDashboardStats']);
        Route::get('/myTasks', [TaskController::class, 'getMyTasks']);
        Route::get('/my-tasks', [TaskController::class, 'getUserTasks']);

        Route::post('/{task}/move', [TaskController::class, 'moveTask']);
        Route::patch('/{task}/status', [TaskController::class, 'updateStatus']);

        Route::get('/pipeline/{entityType}/{entityId}/tasks', [TaskController::class, 'getAllPipelineTasks']);
        Route::get('/pipeline/{entityType}/{entityId}/stages/{stageId}/tasks', [TaskController::class, 'getPipelineTasks']);
    });



Route::prefix('projects')
    ->middleware(['auth:api', 'role:admin|responsable fipa'])
    ->group(function () {
        // Analytics
        Route::get('/total-blocked-projects', [ProjectController::class, 'totalBlockedProjects']);
        Route::get('/total-in-production-projects', [ProjectController::class, 'totalInProductionProjects']);
        Route::get('/total-in-progress-projects', [ProjectController::class, 'totalInProgressProjects']);
        Route::get('/total-idea-projects', [ProjectController::class, 'totalIdeaProjects']);
        Route::get('/total-jobs', [ProjectController::class, 'totalJobs']);
        Route::get('/investment-by-sector', [ProjectController::class, 'investmentBySector']);
        Route::get('/projects-by-status', [ProjectController::class, 'projectsByStatus']);
        Route::get('/jobs-by-sector', [ProjectController::class, 'jobsBySector']);
        Route::get('/projects-by-month', [ProjectController::class, 'projectsByMonth']);
        Route::get('/delayed-projects', [ProjectController::class, 'delayedProjects']);
        Route::get('/average-progression', [ProjectController::class, 'averageProgression']);
        Route::get('/average-investment', [ProjectController::class, 'averageInvestment']);
        Route::get('/projects-by-year', [ProjectController::class, 'projectsByYear']);
        Route::get('/projects-by-responsable', [ProjectController::class, 'projectsByResponsable']);
        Route::get('/high-investment-projects', [ProjectController::class, 'highInvestmentProjects']);
        Route::get('/hierarchical-projects-by-sector', [ProjectController::class, 'hierarchicalProjectsBySector']);
        Route::get('/pipeline-progression', [ProjectController::class, 'pipelineProgression']);
        Route::get('/investment-by-region', [ProjectController::class, 'investmentByRegion']);

        // CRUD
        Route::get('/', [ProjectController::class, 'index']);
        Route::post('/', [ProjectController::class, 'store']);
        Route::get('/{id}', [ProjectController::class, 'show']);
        Route::put('/{id}', [ProjectController::class, 'update']);
        Route::delete('/{id}', [ProjectController::class, 'destroy']);

        // Status & Pipeline
        Route::patch('/{id}/status', [ProjectController::class, 'changeStatus']);
        Route::post('/{id}/pipeline/initialize', [ProjectController::class, 'initializePipeline']);
        Route::post('/{id}/pipeline/advance', [ProjectController::class, 'advanceStage']);
        Route::patch('/{id}/pipeline/stage', [ProjectController::class, 'updatePipelineStage']);
        Route::get('/{id}/pipeline', [ProjectController::class, 'getPipelineStatus']);
        Route::post('/{id}/finalize-pipeline', [ProjectController::class, 'finalizePipelineProgression']);

        // Liaison investisseur
        Route::post('/from-investisseur', [ProjectController::class, 'createFromInvestisseur']);
        Route::get('/investisseur/{investisseurId}/data', [ProjectController::class, 'getInvestisseurDataForProject']);

        // Filtres
        Route::get('/secteur/{secteurId}', [ProjectController::class, 'getBySecteur']);
        Route::get('/stats', [ProjectController::class, 'stats']);
    });



// Routes pour les types de pipeline et étapes
Route::prefix('pipeline')->group(function () {
    // Lecture protégée
    Route::middleware(['auth:api','role:admin|responsable fipa'])->group(function () {
        Route::get('/types/all', [ProjectPipelineTypeController::class, 'index']);
        Route::get('/types/show/{id}', [ProjectPipelineTypeController::class, 'show']);
    });

    // Gestion (admin only)
    Route::middleware(['auth:api','role:admin'])->group(function () {
        Route::post('/types', [ProjectPipelineTypeController::class, 'store']);
        Route::put('/types/{id}', [ProjectPipelineTypeController::class, 'update']);
        Route::delete('/types/delete/{id}', [ProjectPipelineTypeController::class, 'destroy']);
    });
});

// Stats (admin|responsable fipa)
Route::prefix('stats')
    ->middleware(['auth:api','role:admin|responsable fipa'])
    ->group(function () {
        Route::get('/projects-by-status', [StatsController::class, 'projectsByStatus']);
        // Route::get('/projects-by-sector', [StatsController::class, 'projectsBySector']);
        Route::get('/investment-by-region', [StatsController::class, 'investmentByRegion']);
        // Route::get('/jobs-created', [StatsController::class, 'jobsCreated']);
    });
// Route::group(['namespace' => 'Api', 'prefix' => 'contacts'], function () {
//   Route::get('/', [ProjectContactController::class, 'index']);
//   Route::post('/', [ProjectContactController::class, 'store']);
//   Route::get('/{contact}', [ProjectContactController::class, 'show']);
//   Route::put('/{contact}', [ProjectContactController::class, 'update']);
//   Route::delete('/{contact}', [ProjectContactController::class, 'destroy']);
//   Route::put('/{contact}/set-primary', [ProjectContactController::class, 'setPrimary']);
//   Route::get('/project/{project}', [ProjectContactController::class, 'contactsByProject']);
// });

Route::prefix('invites')->group(function () {
    // Public (liens email)
    Route::get('confirm/{token}', [InviteController::class, 'confirm'])->name('invitations.confirm');
    Route::get('decline/{token}', [InviteController::class, 'decline'])->name('invitations.decline');

    // Authentifié: admin ou responsable fipa
    Route::middleware(['auth:api', 'role:admin|responsable fipa'])->group(function () {
        // Stats / Dashboard / Charts
        Route::get('/stats', [InviteController::class, 'stats']);
        Route::get('/dashboard', [InviteController::class, 'dashboard']);
        Route::get('/charts/status', [InviteController::class, 'chartByStatus']);
        Route::get('/charts/potentiel', [InviteController::class, 'chartByPotentiel']);
        Route::get('/charts/evolution', [InviteController::class, 'chartEvolutionMensuelle']);
        Route::get('/charts/pays', [InviteController::class, 'chartByPays']);
        Route::get('/charts/secteur', [InviteController::class, 'chartBySecteur']);
        Route::get('/charts/conversion', [InviteController::class, 'chartConversionRate']);
        Route::get('/charts/type', [InviteController::class, 'chartByType']);
        Route::get('/invites-by-country', [InviteController::class, 'invitesByCountry']);

        // Pipeline/Conversion (spécifiques avant /{id})
        Route::post('{id}/send', [InviteController::class, 'sendInvitation']);
        Route::post('{id}/pipeline/initialize', [InviteController::class, 'initializePipeline']);
        Route::post('{id}/pipeline/advance', [InviteController::class, 'advanceStage']);
        Route::post('{id}/convert-to-prospect', [InviteController::class, 'convertToProspect']);
        Route::get('{id}/pipeline', [InviteController::class, 'getPipelineStatus']);
        Route::get('/{id}/progression', [InviteController::class, 'getProgression']);

        // CRUD
        Route::get('/', [InviteController::class, 'index']);
        Route::post('/', [InviteController::class, 'store']);
        Route::put('/{id}', [InviteController::class, 'update']);
        Route::patch('/{id}/status', [InviteController::class, 'updateStatus']);
        Route::delete('/{id}', [InviteController::class, 'destroy']);
        Route::get('/entreprise/{entrepriseId}', [InviteController::class, 'getByEntreprise']);
        Route::get('/{id}', [InviteController::class, 'show']); // garder après les routes + spécifiques
    });
});


Route::prefix('pipeline-tasks')
    ->middleware(['auth:api', 'role:admin|responsable fipa'])
    ->group(function () {
        // Par entité/pipeline (routes plus spécifiques d’abord)
        Route::post('/{entityType}/{entityId}/{stageId}', [TaskController::class, 'createPipelineTask']);
        Route::get('/{entityType}/{entityId}/{stageId}', [TaskController::class, 'getPipelineTasks']);
        Route::get('/{entityType}/{entityId}', [TaskController::class, 'getAllPipelineTasks']);

        // Par tâche (CRUD/ops)
        Route::put('/{taskId}', [TaskController::class, 'updatePipelineTask']);
        Route::patch('/{taskId}/status', [TaskController::class, 'updatePipelineTaskStatus']);
        Route::patch('/{taskId}/move/{newStageId}', [TaskController::class, 'moveTaskToStage']);
        Route::delete('/{taskId}', [TaskController::class, 'deletePipelineTask']);
        Route::get('/{taskId}', [TaskController::class, 'showPipelineTask']);
    });

Route::prefix('blockages')->group(function () {
    // Admin first (pour que /all passe avant /{blockage})
    Route::middleware(['auth:api','role:admin|responsable fipa'])->group(function () {
        Route::get('/all', [BlockageController::class, 'indexadmin']);
        Route::post('/', [BlockageController::class, 'store']);
        Route::put('/{blockage}', [BlockageController::class, 'update'])->whereNumber('blockage');
        Route::post('/{blockage}/resolve', [BlockageController::class, 'resolve'])->whereNumber('blockage');
        Route::post('/{blockage}/escalate', [BlockageController::class, 'escalate'])->whereNumber('blockage');
        Route::delete('/{blockage}', [BlockageController::class, 'destroy'])->whereNumber('blockage');
        Route::get('/{entityType}/{entityId}/stage/{stageId}', [BlockageController::class, 'getBlockages']);
        Route::get('/', [BlockageController::class, 'index']);
        Route::get('/{blockage}', [BlockageController::class, 'show'])->whereNumber('blockage');
    });
  });



Route::prefix('entreprises')->group(function () {
    // Lecture (auth:api, admin ou responsable fipa)
    Route::middleware(['auth:api','role:admin|responsable fipa'])->group(function () {
        // Spécifiques avant génériques
        Route::get('/search/quick', [EntrepriseController::class, 'search']);
        Route::get('/dashboard/stats', [EntrepriseController::class, 'stats']);

        Route::get('/', [EntrepriseController::class, 'index']);
        Route::get('/{id}', [EntrepriseController::class, 'show']);
    });

    // Écriture (admin only)
    Route::middleware(['auth:api','role:admin'])->group(function () {
        Route::post('/', [EntrepriseController::class, 'store']);
        Route::put('/{id}', [EntrepriseController::class, 'update']);
        Route::delete('/{id}', [EntrepriseController::class, 'destroy']);
        Route::patch('/{id}/pipeline-stage', [EntrepriseController::class, 'updatePipelineStage']);
    });
});
Route::prefix('actions')->group(function () {
    // Lecture (auth:api, admin|responsable fipa)
    Route::middleware(['auth:api','role:admin|responsable fipa'])->group(function () {
        Route::get('/actions-treemap-data', [ActionController::class, 'getActionsTreemapData']);
        Route::get('/calendar/events', [ActionController::class, 'calendar']);
        Route::get('/entreprise/{entrepriseId}', [ActionController::class, 'getByEntreprise']);

        Route::get('/', [ActionController::class, 'index']);
        Route::get('/{id}', [ActionController::class, 'show']);
    });

    // Écriture (admin only)
    Route::middleware(['auth:api','role:admin'])->group(function () {
        Route::post('/', [ActionController::class, 'store']);
        Route::put('/{id}', [ActionController::class, 'update']);
        Route::patch('/{id}/status', [ActionController::class, 'updateStatus']);
        Route::delete('/{id}', [ActionController::class, 'destroy']);
    });
});
Route::prefix('etapes')->group(function () {
    // Lecture (auth:api, admin|responsable fipa)
    Route::middleware(['auth:api','role:admin|responsable fipa'])->group(function () {
        Route::get('/action/{actionId}', [EtapeController::class, 'getByAction']); // spécifique d'abord
        Route::get('/', [EtapeController::class, 'index']);
        Route::get('/{id}', [EtapeController::class, 'show']);
    });

    // Écriture (admin only)
    Route::middleware(['auth:api','role:admin'])->group(function () {
        Route::post('/', [EtapeController::class, 'store']);
        Route::put('/reorder', [EtapeController::class, 'reorder']); // avant /{id}
        Route::put('/{id}', [EtapeController::class, 'update']);
        Route::delete('/{id}', [EtapeController::class, 'destroy']);
    });
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
Route::prefix('prospects')->group(function () {
  Route::middleware(['auth:api','role:admin|responsable fipa'])->group(function () {  // Routes spécifiques AVANT les routes avec paramètres dynamiques
  Route::get('/conversion-time', [ProspectController::class, 'chartConversionTimeAnalysis']);
  Route::get('/status', [ProspectController::class, 'chartByStatus']);
  // Route::get('/sector', [ProspectController::class, 'chartBySector']);
  // Route::get('/country', [ProspectController::class, 'chartByCountry']);
  Route::get('/evolution', [ProspectController::class, 'chartEvolutionMensuelle']);
  // Route::get('/pipeline', [ProspectController::class, 'chartPipelineProgression']);
  Route::get('/conversion', [ProspectController::class, 'chartConversionRate']);
  Route::get('/responsable', [ProspectController::class, 'chartByResponsable']);
  Route::get('/value-analysis', [ProspectController::class, 'chartValueAnalysis']);


  Route::get('/', [ProspectController::class, 'index']);
  Route::post('/', [ProspectController::class, 'store']);
  Route::get('/{id}', [ProspectController::class, 'show']);
  Route::get('/{id}/on-chain', [ProspectController::class, 'showOnChain']);
  Route::put('/{id}', [ProspectController::class, 'update']);
  Route::delete('/{id}', [ProspectController::class, 'destroy']);
  Route::patch('/{id}/status', [ProspectController::class, 'updateStatus']);
  Route::get('/{id}/on-chain-tasks', [ProspectController::class, 'showOnChainTasks']);
  Route::get('/{prospectId}/on-chain-tasks/stage/{stageId}', [ProspectController::class, 'showOnChainStageTasks']);
  Route::get('/{id}/on-chain-progress', [ProspectController::class, 'showOnChainProgress']);
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

});
  Route::prefix('investisseurs')->group(function () {
    // Lecture (auth:api, admin|responsable fipa)
    Route::middleware(['auth:api','role:admin|responsable fipa'])->group(function () {
  Route::get('/stats/global', [InvestisseurController::class, 'statsGlobal']);
  Route::get('/dashboard/complete', [InvestisseurController::class, 'dashboard']);
  Route::get('/status', [InvestisseurController::class, 'chartByStatus']);
  Route::get('/pipeline', [InvestisseurController::class, 'chartPipelineProgression']);
  Route::get('/evolution', [InvestisseurController::class, 'chartEvolutionMensuelle']);
  Route::get('/investment-analysis', [InvestisseurController::class, 'chartInvestmentAnalysis']);
  Route::get('/sector', [InvestisseurController::class, 'chartBySector']);
  Route::get('/country', [InvestisseurController::class, 'chartByCountry']);
  Route::get('/responsable', [InvestisseurController::class, 'chartByResponsable']);
  Route::get('/conversion-funnel', [InvestisseurController::class, 'chartConversionFunnel']);
  Route::get('/roi-analysis', [InvestisseurController::class, 'chartROIAnalysis']);
  Route::get('/activity-heatmap', [InvestisseurController::class, 'chartActivityHeatmap']);


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
});
Route::prefix('pipeline-stages')->group(function () {
    // Lecture (auth:api, admin|responsable fipa)
    Route::middleware(['auth:api','role:admin|responsable fipa'])->group(function () {
        // spécifique avant générique
        Route::get('/{entityType}/{entityId}/stage/{stageId}', [PipelineStageController::class, 'getStageDetails']);
        Route::get('/{entityType}', [PipelineStageController::class, 'index']);
        Route::get('/{entityType}/{id}', [PipelineStageController::class, 'show']);
    });

    // Écriture (admin only)
    Route::middleware(['auth:api','role:admin'])->group(function () {
        Route::post('/{entityType}', [PipelineStageController::class, 'store']);
        Route::put('/{entityType}/{id}', [PipelineStageController::class, 'update']);
        Route::delete('/{entityType}/{id}', [PipelineStageController::class, 'destroy']);
        Route::post('/{entityType}/reorder', [PipelineStageController::class, 'reorder']);
    });
});

Route::prefix('users')->group(function () {
    // Profil (authentifié)
    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [UserController::class, 'me']);
        Route::put('/me/profile', [UserController::class, 'updateProfile']);
        Route::get('/me/actions', [UserController::class, 'myActions']);
        Route::get('/me/actions/stats', [UserController::class, 'myActionsStats']);
    });

    // Administration (admin only)
    Route::middleware(['auth:api', 'role:admin'])->group(function () {
        Route::get('/all', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        Route::post('/{id}/roles', [UserController::class, 'assignRoles']);
        Route::post('/{id}/permissions', [UserController::class, 'assignPermissions']);
    });
});


Route::middleware('auth:api')->group(function () {
  Route::get('user', [AuthenticationController::class, 'user']);
});
