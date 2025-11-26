<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage; // AJOUTER CETTE LIGNE
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    // Liste paginée des utilisateurs + recherche
    public function index(Request $request)
    {
        $query = User::query();

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 15);
        $users = $query->latest()->paginate($perPage);

        // Ajouter rôles/permissions à la réponse
        $users->getCollection()->transform(function ($user) {
            $user->roles_list = $user->getRoleNames();
            $user->permissions_list = $user->getAllPermissions()->pluck('name');
            return $user;
        });

        return response()->json($users);
    }
    public function index2(Request $request)
{
    $users = \App\Models\User::with('roles:id,name')->paginate(15);
    $users->getCollection()->transform(function ($user) {
        $user->roles_list = $user->getRoleNames(); // ["admin", ...]
        return $user;
    });
    return response()->json($users);
}

    // Détail utilisateur
    public function show($id)
    {
        $user = User::findOrFail($id);
        $user->roles_list = $user->getRoleNames();
        $user->permissions_list = $user->getAllPermissions()->pluck('name');

        return response()->json($user);
    }

    // Création utilisateur + rôles/permissions optionnels
    // public function store(Request $request)
    // {
    //     $data = $request->validate([
    //         'name'                  => ['required', 'string', 'max:255'],
    //         'email'                 => ['required', 'email', 'max:255', 'unique:users,email'],
    //         'password'              => ['required', 'string', 'min:8'],
    //         'phone'                 => ['nullable', 'string', 'max:20'],
    //         'position'              => ['nullable', 'string', 'max:100'],
    //         'address'               => ['nullable', 'string', 'max:255'],
    //         'birth_date'            => ['nullable', 'date'],
    //         'gender'                => ['nullable', 'in:male,female,other'],
    //         'two_factor_enabled'    => ['nullable', 'boolean'],
    //         'roles'                 => ['nullable', 'array'],
    //         'roles.*'               => ['string', Rule::exists('roles', 'name')],
    //         'permissions'           => ['nullable', 'array'],
    //         'permissions.*'         => ['string', Rule::exists('permissions', 'name')],
    //     ]);

    //     $data['password'] = Hash::make($data['password']);

    //     $user = User::create($data);

    //     if (!empty($data['roles'])) {
    //         $user->syncRoles($data['roles']);
    //     }

    //     if (!empty($data['permissions'])) {
    //         $user->syncPermissions($data['permissions']);
    //     }

    //     $user->roles_list = $user->getRoleNames();
    //     $user->permissions_list = $user->getAllPermissions()->pluck('name');

    //     return response()->json(['message' => 'Utilisateur créé', 'user' => $user], 201);
    // }

    // // Mise à jour utilisateur + rôles/permissions optionnels
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'               => ['sometimes', 'string', 'max:255'],
            'email'              => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password'           => ['sometimes', 'nullable', 'string', 'min:8'],
            'phone'              => ['nullable', 'string', 'max:20'],
            'position'           => ['nullable', 'string', 'max:100'],
            'address'            => ['nullable', 'string', 'max:255'],
            'birth_date'         => ['nullable', 'date'],
            'gender'             => ['nullable', 'in:male,female,other'],
            'two_factor_enabled' => ['nullable', 'boolean'],
            'roles'              => ['nullable', 'array'],
            'roles.*'            => ['string', Rule::exists('roles', 'name')],
            'permissions'        => ['nullable', 'array'],
            'permissions.*'      => ['string', Rule::exists('permissions', 'name')],
        ]);

        // Mise à jour du mot de passe si fourni
        if (array_key_exists('password', $data)) {
            if ($data['password']) {
                $user->password = Hash::make($data['password']);
            }
            unset($data['password']);
        }

        $user->update($data);

        if ($request->has('roles')) {
            $user->syncRoles($data['roles'] ?? []);
        }
        if ($request->has('permissions')) {
            $user->syncPermissions($data['permissions'] ?? []);
        }

        $user->roles_list = $user->getRoleNames();
        $user->permissions_list = $user->getAllPermissions()->pluck('name');

        return response()->json(['message' => 'Utilisateur mis à jour', 'user' => $user]);
    }

    // Suppression utilisateur
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Empêcher la suppression de soi-même
        if (auth('api')->id() === $user->id) {
            return response()->json(['message' => "Impossible de supprimer votre propre compte."], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé']);
    }

    // Récupérer l'utilisateur authentifié
  public function me(Request $request)
{
    $user = $request->user('api') ?? auth('api')->user();

    if (!$user) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    $user->roles_list = $user->getRoleNames();
    $user->permissions_list = $user->getAllPermissions()->pluck('name');

    return response()->json($user);
}

    // Mise à jour du profil (infos personnelles + photo)
    public function updateProfile(Request $request)
{
    try {
        $user = $request->user();
        
        // Vérifier que l'utilisateur est authentifié
        if (!$user) {
            return response()->json([
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'position'    => ['nullable', 'string', 'max:100'],
            'address'     => ['nullable', 'string', 'max:255'],
            'birth_date'  => ['nullable', 'date'],
            'gender'      => ['nullable', 'in:male,female,other'],
            'photo'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        // Gestion de l'upload de photo
        if ($request->hasFile('photo')) {
            try {
                // Supprimer l'ancienne photo si elle existe
                if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                    Storage::disk('public')->delete($user->photo);
                }
                
                $path = $request->file('photo')->store('profile_photos', 'public');
                $data['photo'] = $path;
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Erreur lors du téléchargement de la photo',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        // Mise à jour de l'utilisateur
        $user->update($data);

        // Recharger l'utilisateur pour avoir les données fraîches
        $user->refresh();

        // Ajouter les rôles et permissions
        $user->roles_list = $user->getRoleNames();
        $user->permissions_list = $user->getAllPermissions()->pluck('name');

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'user' => $user
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Erreur de validation',
            'errors' => $e->errors()
        ], 422);
        
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Erreur lors de la mise à jour du profil',
            'error' => $e->getMessage()
        ], 500);
    }
}

    
   

   

    // Assigner des rôles
    public function assignRoles(Request $request, $id)
    {
        $data = $request->validate([
            'roles'   => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        $user = User::findOrFail($id);
        $user->syncRoles($data['roles']);

        return response()->json(['message' => 'Rôles assignés', 'roles' => $user->getRoleNames()]);
    }

    // Assigner des permissions
    public function assignPermissions(Request $request, $id)
    {
        $data = $request->validate([
            'permissions'   => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        $user = User::findOrFail($id);
        $user->syncPermissions($data['permissions']);

        return response()->json(['message' => 'Permissions assignées', 'permissions' => $user->getAllPermissions()->pluck('name')]);
    }
   
/**
 * Récupérer les actions assignées à l'utilisateur connecté
 */

public function myActions(Request $request)
{
    $auth = $request->user('api') ?? auth('api')->user();
    if (!$auth) {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    // Cible: par défaut l'utilisateur connecté
    $targetUserId = $auth->id;

    // Admin|responsable fipa peuvent filtrer par un autre user via ?user_id=
    if ($request->filled('user_id') && method_exists($auth, 'hasRole') && $auth->hasRole(['admin', 'responsable fipa'])) {
        $targetUserId = (int) $request->input('user_id');
        if (!User::whereKey($targetUserId)->exists()) {
            return response()->json(['message' => 'Utilisateur cible introuvable'], 404);
        }
    }

    $query = Action::query()
        ->where('responsable_id', $targetUserId)
        ->with(['responsable:id,name,email']);

    // Filtres
    if ($request->filled('statut')) {
        $statuts = Arr::wrap($request->input('statut'));
        $query->whereIn('statut', $statuts);
    }
    if ($request->filled('type')) {
        $types = Arr::wrap($request->input('type'));
        $query->whereIn('type', $types);
    }
    if ($request->filled('date_from')) {
        $query->whereDate('date_debut', '>=', $request->date_from);
    }
    if ($request->filled('date_to')) {
        $query->whereDate('date_debut', '<=', $request->date_to);
    }
    if ($request->filled('periode')) {
        $now = now();
        switch ($request->periode) {
            case 'a_venir':
                $query->where('date_debut', '>=', $now)
                      ->whereNotIn('statut', ['terminee', 'annulee']);
                break;
            case 'passees':
                $query->where(function ($q) use ($now) {
                    $q->where('date_debut', '<', $now)
                      ->orWhere('statut', 'terminee');
                });
                break;
            case 'semaine':
                $query->whereBetween('date_debut', [$now, $now->copy()->addDays(7)]);
                break;
            case 'mois':
                $query->whereBetween('date_debut', [$now, $now->copy()->addMonth()]);
                break;
        }
    }
    if ($search = $request->get('q')) {
        $query->where(function ($q) use ($search) {
            $q->where('titre', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // Tri + pagination
    $allowedSorts = ['date_debut', 'created_at', 'updated_at', 'statut', 'type', 'titre'];
    $sortField = in_array($request->get('sort_by'), $allowedSorts) ? $request->get('sort_by') : 'date_debut';
    $sortDirection = strtolower($request->get('sort_direction', 'asc')) === 'desc' ? 'desc' : 'asc';
    $perPage = (int) $request->get('per_page', 15);

    $actions = $query->orderBy($sortField, $sortDirection)->paginate($perPage);

    return response()->json([
        'success' => true,
        'data' => $actions,
        'target_user' => [
            'id' => $targetUserId,
        ],
    ]);
}



/**
 * Statistiques des actions de l'utilisateur connecté
 */
public function myActionsStats(Request $request)
{
    try {
        $user = auth('api')->user() ?? auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $actionsQuery = Action::where('responsable_id', $user->id);

        // 🧮 Statistiques générales
        $stats = [
            'total_actions' => (clone $actionsQuery)->count(),
            'actions_a_venir' => (clone $actionsQuery)
                ->where('date_debut', '>=', now())
                ->whereNotIn('statut', ['terminee', 'annulee'])
                ->count(),
            'actions_passees' => (clone $actionsQuery)
                ->where('date_debut', '<', now())
                ->orWhere('statut', 'terminee')
                ->count(),
            'actions_par_statut' => (clone $actionsQuery)
                ->selectRaw('statut, COUNT(*) as count')
                ->groupBy('statut')
                ->pluck('count', 'statut'),
            'actions_par_type' => (clone $actionsQuery)
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
            'total_invites' => DB::table('invites')
                ->join('actions', 'invites.action_id', '=', 'actions.id')
                ->where('actions.responsable_id', $user->id)
                ->count(),
            'invites_confirmes' => DB::table('invites')
                ->join('actions', 'invites.action_id', '=', 'actions.id')
                ->where('actions.responsable_id', $user->id)
                ->whereIn('invites.statut', ['confirmee', 'participation_confirmee'])
                ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des statistiques',
            'error' => $e->getMessage()
        ], 500);
    }
}


}