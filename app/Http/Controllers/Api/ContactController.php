<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Entreprise;
use App\Http\Requests\ContactRequest;
use App\Exceptions\ContactExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    /**
     * Liste des contacts avec filtres possibles
     */
    public function index(Request $request)
    {
        try {
            $query = Contact::query()->with(['entreprise', 'proprietaire']);
            
            // Filtres
            if ($request->has('entreprise_id')) {
                $query->where('entreprise_id', $request->entreprise_id);
            }
            
            if ($request->has('nom')) {
                $query->where(function($q) use ($request) {
                    $q->where('nom', 'like', '%' . $request->nom . '%')
                      ->orWhere('prenom', 'like', '%' . $request->nom . '%');
                });
            }
            
            if ($request->has('email')) {
                $query->where('email', 'like', '%' . $request->email . '%');
            }
            
            if ($request->has('fonction')) {
                $query->where('fonction', 'like', '%' . $request->fonction . '%');
            }
            
            if ($request->has('est_principal')) {
                $query->where('est_principal', $request->est_principal === 'true' || $request->est_principal === '1');
            }
            
            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }
            
            // Tri
            $sortField = $request->sort_by ?? 'nom';
            $sortDirection = $request->sort_direction ?? 'asc';
            $contacts = $query->orderBy($sortField, $sortDirection)
                             ->paginate($request->per_page ?? 15);
            
            return response()->json([
                'success' => true,
                'data' => $contacts
            ]);
            
        } catch (\Exception $e) {
            return ContactExceptionHandler::handle($e);
        }
    }

    /**
     * Afficher un contact spécifique
     */
    public function show($id)
    {
        try {
            $contact = Contact::with(['entreprise', 'proprietaire', 'invitations'])
                            ->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $contact
            ]);
            
        } catch (\Exception $e) {
            return ContactExceptionHandler::handle($e);
        }
    }

    /**
     * Créer un nouveau contact
     */
    public function store(ContactRequest $request)
    {
        try {
            DB::beginTransaction();
            
            $data = $request->validated();
            
            // Si le contact est défini comme principal, désactiver les autres contacts principaux
            if (isset($data['est_principal']) && $data['est_principal']) {
                Contact::where('entreprise_id', $data['entreprise_id'])
                      ->where('est_principal', true)
                      ->update(['est_principal' => false]);
            }
            
            $contact = Contact::create($data);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Contact créé avec succès',
                'data' => $contact
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return ContactExceptionHandler::handle($e);
        }
    }

    /**
     * Mettre à jour un contact
     */
    public function update(ContactRequest $request, $id)
    {
        try {
            DB::beginTransaction();
            
            $contact = Contact::findOrFail($id);
            $data = $request->validated();
            
            // Si le contact est défini comme principal, désactiver les autres contacts principaux
            if (isset($data['est_principal']) && $data['est_principal'] && !$contact->est_principal) {
                Contact::where('entreprise_id', $contact->entreprise_id)
                      ->where('id', '!=', $contact->id)
                      ->where('est_principal', true)
                      ->update(['est_principal' => false]);
            }
            
            $contact->update($data);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Contact mis à jour avec succès',
                'data' => $contact
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return ContactExceptionHandler::handle($e);
        }
    }

    /**
     * Définir un contact comme contact principal
     */
    public function setPrimary($id)
    {
        try {
            DB::beginTransaction();
            
            $contact = Contact::findOrFail($id);
            
            // Désactiver tous les contacts principaux de cette entreprise
            Contact::where('entreprise_id', $contact->entreprise_id)
                  ->where('est_principal', true)
                  ->update(['est_principal' => false]);
            
            // Définir ce contact comme principal
            $contact->est_principal = true;
            $contact->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Contact défini comme principal',
                'data' => $contact
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return ContactExceptionHandler::handle($e);
        }
    }

    /**
     * Supprimer un contact
     */
    public function destroy($id)
    {
        try {
            $contact = Contact::findOrFail($id);
            
            // Vérifier si des invitations sont associées à ce contact
            $invitationsCount = $contact->invitations()->count();
            if ($invitationsCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer ce contact car il est associé à ' . $invitationsCount . ' invitation(s)'
                ], 409);
            }
            
            $contact->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Contact supprimé avec succès'
            ]);
            
        } catch (\Exception $e) {
            return ContactExceptionHandler::handle($e);
        }
    }

    /**
     * Liste des contacts pour une entreprise spécifique
     */
    public function getByEntreprise($entrepriseId)
    {
        try {
            $entreprise = Entreprise::findOrFail($entrepriseId);
            
            $contacts = $entreprise->contacts()
                                  ->with('proprietaire')
                                  ->orderBy('est_principal', 'desc')
                                  ->orderBy('nom')
                                  ->get();
            
            return response()->json([
                'success' => true,
                'data' => $contacts
            ]);
            
        } catch (\Exception $e) {
            return ContactExceptionHandler::handle($e);
        }
    }

    /**
     * Recherche rapide de contacts
     */
    public function search(Request $request)
    {
        try {
            $term = $request->term;
            $limit = $request->limit ?? 10;
            
            if (empty($term)) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }
            
            $contacts = Contact::where(function($q) use ($term) {
                    $q->where('nom', 'like', '%' . $term . '%')
                      ->orWhere('prenom', 'like', '%' . $term . '%')
                      ->orWhere('email', 'like', '%' . $term . '%');
                })
                ->with('entreprise:id,nom')
                ->select('id', 'nom', 'prenom', 'email', 'fonction', 'entreprise_id')
                ->limit($limit)
                ->get();
                
            return response()->json([
                'success' => true,
                'data' => $contacts
            ]);
            
        } catch (\Exception $e) {
            return ContactExceptionHandler::handle($e);
        }
    }
}