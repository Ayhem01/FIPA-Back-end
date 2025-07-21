<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Token;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;




class AuthenticationController extends Controller
{
    public function register(Request $request)
    {
        // Validation de l'email
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users',
        ]);

        // Générer un mot de passe aléatoire
        $randomPassword = Str::random(10);

        // Créer un nouvel utilisateur avec le mot de passe généré
        $user = new User([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($randomPassword),
        ]);
        $user->save();

        // Envoyer un e-mail contenant le mot de passe généré
        Mail::raw("Bonjour {$user->name},\n\nVotre compte a été créé avec succès. Voici votre mot de passe : {$randomPassword}\n\nVeuillez le conserver en lieu sûr.", function ($message) use ($user) {
            $message->to($user->email)
                ->subject('Votre compte a été créé');
        });

        return response()->json([
            'message' => 'Utilisateur créé avec succès. Le mot de passe a été envoyé par e-mail.',
        ], 201);
    }


    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'remember_me' => 'boolean'
        ]);

        $credentials = $request->only(['email', 'password']);

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Identifiants incorrects'], 401);
        }

        $user = auth()->user();

        // Vérifier si 2FA est activé pour cet utilisateur
        if ($user->two_factor_enabled && $user->google2fa_secret) {
            // Générer un token temporaire avec scope limité '2fa-temp'
            $tempToken = $user->createToken(
                'Temporary 2FA Token',
                ['2fa-temp'],
                now()->addMinutes(5)
            )->accessToken;

            return response()->json([
                'message' => 'Authentification à deux facteurs requise',
                'temp_token' => $tempToken,
                'requires_2fa' => true,
                'user_email' => $user->email,
            ], 200);
        }

        // Si 2FA n'est pas activé, générer un token avec accès complet
        $accessToken = $user->createToken('Full Access Token', ['full-access'])->accessToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::now()->addWeeks(1)->toDateTimeString(),
            'requires_2fa' => false,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }

    public function verifyLogin2FA(Request $request)
    {
        $request->validate([
            'code' => 'required|numeric',
        ]);

        // Récupérer l'utilisateur authentifié
        $user = auth()->guard('api')->user();

        if (!$user) {
            return response()->json(['message' => 'Non autorisé'], 401);
        }

        // Vérifier que le token possède le scope 2fa-temp
        if (!$request->user()->tokenCan('2fa-temp')) {
            return response()->json([
                'message' => 'Ce token n\'est pas autorisé pour cette opération',
                'error' => 'scope_invalid'
            ], 403);
        }

        // Vérifier que l'utilisateur a activé 2FA
        if (!$user->two_factor_enabled || !$user->google2fa_secret) {
            return response()->json(['message' => '2FA n\'est pas activé pour cet utilisateur'], 400);
        }

        // Initialiser TwoFactorAuth
        $qrCodeProvider = new BaconQrCodeProvider();
        $tfa = new TwoFactorAuth($qrCodeProvider, 'FIPA');

        // Vérifier le code 2FA avec une fenêtre de validation élargie (2 périodes)
        $valid = $tfa->verifyCode($user->google2fa_secret, $request->input('code'), 4);

        if (!$valid) {
            return response()->json(['message' => 'Code 2FA invalide'], 401);
        }

        // Code valide, révoquer le token temporaire
        $currentToken = $user->token();
        $currentToken->revoke();

        // Générer un nouveau token avec accès complet
        $accessToken = $user->createToken(
            'Full Access Token',
            ['full-access']
        )->accessToken;

        return response()->json([
            'message' => 'Authentification réussie',
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::now()->addWeeks(1)->toDateTimeString(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'two_factor_enabled' => $user->two_factor_enabled
            ]
        ]);
    }
    public function destroy(Request $request)
    {
        try {
            // Récupérer l'utilisateur authentifié
            $user = Auth::guard('api')->user();

            // Vérifier si l'utilisateur est bien authentifié
            if (!$user) {
                return response()->json(['error' => 'Utilisateur non authentifié'], 401);
            }

            // Récupérer et révoquer le token actuel uniquement
            $user->token()->revoke();

            return response()->json([
                'message' => 'Déconnexion réussie'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue lors de la déconnexion',
                'exception' => $e->getMessage() // Ajouter le message d'exception pour plus de détails
            ], 500);
        }
    }


    public function getCurrentUser(Request $request)
{
    $user = $request->user();
    
    // Ajouter des informations sur le rôle
    $user->is_admin = $user->hasRole('admin');
    $user->role = $user->roles()->first() ? $user->roles()->first()->name : null;
    $user->roles = $user->roles()->pluck('name')->toArray();
    
    return response()->json([
        'user' => $user
    ]);
}


    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'L\'ancien mot de passe est incorrect.'
            ], 400);
        }

        $user->password = bcrypt($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Mot de passe mis à jour avec succès.'
        ], 200);
    }
    public function forgotPassword(Request $request)
    {
        // Validation de l'email
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        // Envoi du lien de réinitialisation
        $status = Password::sendResetLink(
            $request->only('email'),
            function ($user, $token) {
                $frontendUrl = "http://localhost:3000/reset-password?token=" . $token . "&email=" . urlencode($user->email);
                $user->sendPasswordResetNotification($frontendUrl);
            }
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Un lien de réinitialisation du mot de passe a été envoyé à votre adresse e-mail.'
            ], 200);
        }

        return response()->json([
            'message' => 'Une erreur est survenue lors de l\'envoi du lien de réinitialisation.'
        ], 500);
    }
    public function resetPassword(Request $request)
    {
        // Validation des données
        $request->validate([
            'token' => 'required',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Réinitialisation du mot de passe
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        // Vérification du statut et réponse
        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Votre mot de passe a été réinitialisé avec succès.'
            ], 200);
        }

        return response()->json([
            'message' => 'Le lien de réinitialisation est invalide ou expiré.'
        ], 400);
    }


    public function enable2FA(Request $request)
    {
        $user = auth()->user();

        // Initialiser BaconQrCodeProvider
        $qrCodeProvider = new BaconQrCodeProvider();

        // Créer l'instance TwoFactorAuth avec le provider (ordre correct des paramètres)
        $tfa = new TwoFactorAuth($qrCodeProvider, 'FIPA');

        // Toujours générer un nouveau secret pour chaque demande d'activation
        // Supprimer cette condition pour assurer l'unicité pour chaque utilisateur
        $secret = $tfa->createSecret();

        // Enregistrer le secret dans la base de données
        $user->update([
            'temp_2fa_secret' => $secret
        ]);

        // IMPORTANT: Rafraîchir l'utilisateur pour s'assurer que les données sont à jour
        $user->refresh();



        // Générer le QR code avec le secret actualisé
        $qrCodeUrl = $tfa->getQRCodeImageAsDataUri($user->name, $secret);

        return response()->json([
            'qr' => $qrCodeUrl,
            'secret' => $secret
        ]);
    }

    public function verify2FA(Request $request)
    {
        $request->validate([
            'code' => 'required|numeric',
        ]);

        $user = auth()->user();
        $secret = $user->temp_2fa_secret;


        if (!$secret) {
            return response()->json(['message' => 'Le secret 2FA est introuvable.'], 400);
        }

        $qrCodeProvider = new BaconQrCodeProvider();
        $tfa = new TwoFactorAuth($qrCodeProvider, 'FIPA');

        // Vérifier le code avec une fenêtre de validation élargie (2 périodes)
        $valid = $tfa->verifyCode($secret, $request->input('code'), 2);

        if ($valid) {
            // Enregistrer le secret définitif et nettoyer le secret temporaire
            $user->update([
                'google2fa_secret' => $secret,
                'temp_2fa_secret' => null,
                'two_factor_enabled' => true
            ]);

            // IMPORTANT: Actualiser l'utilisateur après la mise à jour
            $user->refresh();

            return response()->json(['message' => '2FA activé avec succès.']);
        }

        return response()->json(['message' => 'Le code 2FA est invalide.', 'two_factor_enabled' => true], 401);
    }
    public function twoFactorStatus(Request $request)
    {
        $user = auth()->user();

        return response()->json([
            'enabled' => (bool)$user->two_factor_enabled && !empty($user->google2fa_secret),
            'setup_incomplete' => !empty($user->temp_2fa_secret) && empty($user->google2fa_secret)
        ]);
    }
    public function disable2FA(Request $request)
    {
        $user = auth()->user();

        // Vérifier si 2FA est activé
        if (!$user->two_factor_enabled) {
            return response()->json(['message' => '2FA n\'est pas activé.'], 400);
        }

        // Désactiver 2FA
        $user->update([
            'google2fa_secret' => null,
            'two_factor_enabled' => false
        ]);

        return response()->json(['message' => '2FA désactivé avec succès.']);
    }

    /**
     * Récupérer la liste de tous les utilisateurs
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    // public function getAllUsers(Request $request)
    // {
    //     try {
    //         // Vérification des autorisations (à adapter selon votre logique d'autorisation)
    //         // Utilisation d'une vérification simple basée sur l'authentification
    //         if (!auth()->user()) {
    //             return response()->json([
    //                 'message' => 'Vous n\'êtes pas autorisé à accéder à cette ressource'
    //             ], 403);
    //         }

    //         // Note: Si vous avez besoin d'autorisations plus détaillées, installez le package:
    //         // composer require spatie/laravel-permission

    //         $query = User::query();

    //         // Filtres
    //         if ($request->has('search')) {
    //             $searchTerm = $request->search;
    //             $query->where(function($q) use ($searchTerm) {
    //                 $q->where('name', 'LIKE', "%{$searchTerm}%")
    //                   ->orWhere('email', 'LIKE', "%{$searchTerm}%");
    //             });
    //         }

    //         if ($request->has('two_factor_enabled')) {
    //             $query->where('two_factor_enabled', $request->two_factor_enabled == 1);
    //         }

    //         // Tri
    //         $sortBy = $request->input('sort_by', 'created_at');
    //         $sortDirection = $request->input('sort_direction', 'desc');

    //         // Liste des colonnes autorisées pour le tri
    //         $allowedSortColumns = ['id', 'name', 'email', 'created_at', 'updated_at'];

    //         if (in_array($sortBy, $allowedSortColumns)) {
    //             $query->orderBy($sortBy, $sortDirection === 'asc' ? 'asc' : 'desc');
    //         }

    //         // Pagination
    //         $perPage = $request->input('per_page', 15);
    //         $users = $query->paginate($perPage);

    //         // Exclure les informations sensibles
    //         $users->getCollection()->transform(function ($user) {
    //             return [
    //                 'id' => $user->id,
    //                 'name' => $user->name,
    //                 'email' => $user->email,
    //                 'two_factor_enabled' => (bool)$user->two_factor_enabled,
    //                 'created_at' => $user->created_at,
    //                 'updated_at' => $user->updated_at,
    //                 // Ajouter d'autres champs selon vos besoins
    //             ];
    //         });

    //         return response()->json([
    //             'status' => 'success',
    //             'data' => $users,
    //             'message' => 'Liste des utilisateurs récupérée avec succès'
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Une erreur est survenue lors de la récupération des utilisateurs',
    //             'error' => config('app.debug') ? $e->getMessage() : null
    //         ], 500);
    //     }
    // }
    public function getAllUsers()
    {
        // Récupérer tous les utilisateurs avec les informations nécessaires
        $users = User::select('id', 'name', 'email')->get();

        return response()->json($users);
    }
}
