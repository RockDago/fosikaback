<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\AuditLogger;

class EnseignantController extends Controller
{
    /**
     * Mapping entre corps et catégorie (titre)
     */
    private function getCategoriFromCorps($corps)
    {
        $mapping = [
            'AES' => 'ASSISTANT D\'ENSEIGNEMENT SUPERIEUR',
            'MC' => 'MAÎTRE DE CONFÉRENCES D\'ENSEIGNEMENT SUPERIEUR',
            'PES' => 'PROFESSEUR D\'ENSEIGNEMENT SUPERIEUR',
            'PE' => 'PROFESSEUR ÉMÉRITE',
            'PT' => 'PROFESSEUR TITULAIRE',
        ];

        return $mapping[strtoupper($corps)] ?? null;
    }

    /**
     * Récupérer tous les enseignants avec filtres et pagination
     */
    public function index(Request $request)
    {
        $query = Enseignant::with(['universite', 'etablissement']);

        // Filtres
        if ($request->has('universite_id')) {
            $query->where('universite_id', $request->universite_id);
        }

        if ($request->has('etablissement_id')) {
            $query->where('etablissement_id', $request->etablissement_id);
        }

        if ($request->has('corps')) {
            $query->where('corps', $request->corps);
        }

        if ($request->has('categorie')) {
            $query->where('categorie', $request->categorie);
        }

        if ($request->has('diplome')) {
            $query->where('diplome', $request->diplome);
        }

        if ($request->has('sexe')) {
            $query->where('sexe', $request->sexe);
        }

        // Recherche avancée dans MULTIPLES champs
        if ($request->has('search') && $request->search != '') {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('nom', 'like', '%' . $searchTerm . '%')
                  ->orWhere('im', 'like', '%' . $searchTerm . '%')
                  ->orWhere('diplome', 'like', '%' . $searchTerm . '%')
                  ->orWhere('specialite', 'like', '%' . $searchTerm . '%')
                  ->orWhere('corps', 'like', '%' . $searchTerm . '%')
                  ->orWhere('categorie', 'like', '%' . $searchTerm . '%');
            });
        }

        // Tri par défaut par nom
        $sortBy = $request->get('sort_by', 'nom');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 100);
        $enseignants = $query->paginate($perPage);

        return response()->json($enseignants);
    }

    /**
     * Récupérer TOUS les enseignants sans pagination (pour export/filtrage côté client)
     */
    public function getAll(Request $request)
    {
        $query = Enseignant::with(['universite', 'etablissement']);

        // Filtres de base
        if ($request->has('universite_id')) {
            $query->where('universite_id', $request->universite_id);
        }

        if ($request->has('etablissement_id')) {
            $query->where('etablissement_id', $request->etablissement_id);
        }

        if ($request->has('corps')) {
            $query->where('corps', $request->corps);
        }

        if ($request->has('categorie')) {
            $query->where('categorie', $request->categorie);
        }

        // Tri par défaut
        $query->orderBy('nom', 'asc');

        return response()->json($query->get());
    }

    /**
     * Récupérer un enseignant spécifique
     */
    public function show($id)
    {
        $enseignant = Enseignant::with(['universite', 'etablissement'])->findOrFail($id);
        return response()->json($enseignant);
    }

    /**
     * Créer un nouvel enseignant
     */
    public function store(Request $request)
    {
        Log::info('Tentative de création enseignant:', $request->all());

        $validator = Validator::make($request->all(), [
            'universite_id' => 'required|integer|exists:universites,id',
            'etablissement_id' => 'required|integer|exists:etablissements,id',
            'nom' => 'required|string|max:255',
            'sexe' => 'required|in:M,F',
            'im' => 'required|string|size:6|unique:enseignants,im',
            'date_naissance' => 'required|date|before:today',
            'corps' => 'required|string|in:AES,MC,PE,PES,PT',
            'diplome' => 'required|string|max:255',
            'specialite' => 'required|string|max:255',
            'categorie' => 'nullable|string|max:255'
        ], [
            'universite_id.required' => 'L\'université est obligatoire.',
            'universite_id.exists' => 'L\'université sélectionnée n\'existe pas.',
            'etablissement_id.required' => 'L\'établissement est obligatoire.',
            'etablissement_id.exists' => 'L\'établissement sélectionné n\'existe pas.',
            'nom.required' => 'Le nom est obligatoire.',
            'nom.max' => 'Le nom ne doit pas dépasser 255 caractères.',
            'sexe.required' => 'Le sexe est obligatoire.',
            'sexe.in' => 'Le sexe doit être M ou F.',
            'im.required' => 'L\'IM est obligatoire.',
            'im.size' => 'L\'IM doit comporter exactement 6 chiffres.',
            'im.unique' => 'Cet IM existe déjà dans la base de données.',
            'date_naissance.required' => 'La date de naissance est obligatoire.',
            'date_naissance.date' => 'La date de naissance doit être une date valide.',
            'date_naissance.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'corps.required' => 'Le corps est obligatoire.',
            'corps.in' => 'Le corps doit être AES, MC, PE, PES ou PT.',
            'diplome.required' => 'Le diplôme est obligatoire.',
            'diplome.max' => 'Le diplôme ne doit pas dépasser 255 caractères.',
            'specialite.required' => 'La spécialité est obligatoire.',
            'specialite.max' => 'La spécialité ne doit pas dépasser 255 caractères.',
        ]);

        if ($validator->fails()) {
            Log::error('Erreur de validation enseignant:', $validator->errors()->toArray());
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $corps = strtoupper(trim($request->corps));
            
            // ✅ CORRECTION: Si catégorie n'est pas fournie ou vide, déduire du corps
            $categorie = $request->categorie;
            if (empty($categorie)) {
                $categorie = $this->getCategoriFromCorps($corps);
            }

            $enseignant = Enseignant::create([
                'universite_id' => $request->universite_id,
                'etablissement_id' => $request->etablissement_id,
                'nom' => trim($request->nom),
                'sexe' => strtoupper($request->sexe),
                'im' => trim($request->im),
                'date_naissance' => $request->date_naissance,
                'corps' => $corps,
                'diplome' => trim($request->diplome),
                'specialite' => trim($request->specialite),
                'categorie' => trim($categorie)
            ]);

            $enseignant->load(['universite', 'etablissement']);

            DB::commit();

            Log::info('Enseignant créé avec succès:', ['id' => $enseignant->id, 'nom' => $enseignant->nom]);
            AuditLogger::logCreation(
                $request->user()->email ?? 'system',
                'Enseignant',
                "Creation de l'enseignant {$enseignant->nom} (ID: {$enseignant->id})"
            );

            return response()->json([
                'message' => 'Enseignant créé avec succès',
                'data' => $enseignant
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création enseignant: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la création de l\'enseignant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un enseignant
     */
    public function update(Request $request, $id)
    {
        $enseignant = Enseignant::findOrFail($id);

        Log::info('Tentative de modification enseignant:', ['id' => $id, 'data' => $request->all()]);

        $validator = Validator::make($request->all(), [
            'universite_id' => 'sometimes|integer|exists:universites,id',
            'etablissement_id' => 'sometimes|integer|exists:etablissements,id',
            'nom' => 'sometimes|string|max:255',
            'sexe' => 'sometimes|in:M,F',
            'im' => 'sometimes|string|size:6|unique:enseignants,im,' . $id,
            'date_naissance' => 'sometimes|date|before:today',
            'corps' => 'sometimes|string|in:AES,MC,PE,PES,PT',
            'diplome' => 'sometimes|string|max:255',
            'specialite' => 'sometimes|string|max:255',
            'categorie' => 'nullable|string|max:255'
        ], [
            'im.unique' => 'Cet IM existe déjà dans la base de données.',
            'im.size' => 'L\'IM doit comporter exactement 6 chiffres.',
            'date_naissance.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'corps.in' => 'Le corps doit être AES, MC, PE, PES ou PT.',
        ]);

        if ($validator->fails()) {
            Log::error('Erreur de validation modification:', $validator->errors()->toArray());
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $dataToUpdate = [];
            
            if ($request->has('nom')) {
                $dataToUpdate['nom'] = trim($request->nom);
            }
            if ($request->has('sexe')) {
                $dataToUpdate['sexe'] = strtoupper($request->sexe);
            }
            if ($request->has('im')) {
                $dataToUpdate['im'] = trim($request->im);
            }
            if ($request->has('date_naissance')) {
                $dataToUpdate['date_naissance'] = $request->date_naissance;
            }
            
            // ✅ CORRECTION: Synchroniser automatiquement categorie quand corps change
            if ($request->has('corps')) {
                $corps = strtoupper(trim($request->corps));
                $dataToUpdate['corps'] = $corps;
                
                // Si catégorie n'est pas fournie, la déduire du nouveau corps
                if (!$request->has('categorie') || empty($request->categorie)) {
                    $dataToUpdate['categorie'] = $this->getCategoriFromCorps($corps);
                }
            }
            
            // Si catégorie est fournie explicitement, l'utiliser
            if ($request->has('categorie') && !empty($request->categorie)) {
                $dataToUpdate['categorie'] = trim($request->categorie);
            }
            
            if ($request->has('diplome')) {
                $dataToUpdate['diplome'] = trim($request->diplome);
            }
            if ($request->has('specialite')) {
                $dataToUpdate['specialite'] = trim($request->specialite);
            }
            if ($request->has('universite_id')) {
                $dataToUpdate['universite_id'] = $request->universite_id;
            }
            if ($request->has('etablissement_id')) {
                $dataToUpdate['etablissement_id'] = $request->etablissement_id;
            }

            $enseignant->update($dataToUpdate);
            $enseignant->load(['universite', 'etablissement']);

            DB::commit();

            Log::info('Enseignant modifié avec succès:', [
                'id' => $id, 
                'corps' => $dataToUpdate['corps'] ?? 'non modifié',
                'categorie' => $dataToUpdate['categorie'] ?? 'non modifié'
            ]);
            AuditLogger::logModification(
                $request->user()->email ?? 'system',
                'Enseignant',
                "Modification de l'enseignant {$enseignant->nom} (ID: {$enseignant->id})"
            );

            return response()->json([
                'message' => 'Enseignant mis à jour avec succès',
                'data' => $enseignant
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur modification enseignant: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Erreur lors de la modification de l\'enseignant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un enseignant
     */
    public function destroy($id)
    {
        try {
            $enseignant = Enseignant::findOrFail($id);
            $nom = $enseignant->nom;
            
            $enseignant->delete();

            Log::info('Enseignant supprimé avec succès:', ['id' => $id, 'nom' => $nom]);
            AuditLogger::logSuppression(
                request()->user()->email ?? 'system',
                'Enseignant',
                "Suppression de l'enseignant {$nom} (ID: {$id})"
            );

            return response()->json([
                'message' => 'Enseignant supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur suppression enseignant: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Erreur lors de la suppression de l\'enseignant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques par établissement
     */
    public function statistiques(Request $request)
    {
        $universiteId = $request->get('universite_id');

        $stats = Enseignant::with('etablissement')
            ->when($universiteId, function($query) use ($universiteId) {
                return $query->where('universite_id', $universiteId);
            })
            ->selectRaw("
                etablissement_id,
                COUNT(*) as total,
                SUM(CASE WHEN corps = 'AES' THEN 1 ELSE 0 END) as AES,
                SUM(CASE WHEN corps = 'MC' THEN 1 ELSE 0 END) as MC,
                SUM(CASE WHEN corps = 'PES' THEN 1 ELSE 0 END) as PES,
                SUM(CASE WHEN corps = 'PT' THEN 1 ELSE 0 END) as PT,
                SUM(CASE WHEN corps = 'PE' THEN 1 ELSE 0 END) as PE,
                SUM(CASE WHEN sexe = 'F' THEN 1 ELSE 0 END) as F,
                SUM(CASE WHEN sexe = 'M' THEN 1 ELSE 0 END) as M
            ")
            ->groupBy('etablissement_id')
            ->get();

        return response()->json($stats);
    }

    /**
     * Statistiques détaillées par établissement
     */
    public function statistiquesParEtablissement(Request $request)
    {
        $universiteId = $request->get('universite_id');

        $stats = Enseignant::with('etablissement')
            ->when($universiteId, function($query) use ($universiteId) {
                return $query->where('universite_id', $universiteId);
            })
            ->selectRaw("
                etablissement_id,
                COUNT(*) as total,
                SUM(CASE WHEN corps = 'AES' THEN 1 ELSE 0 END) as AES,
                SUM(CASE WHEN corps = 'MC' THEN 1 ELSE 0 END) as MC,
                SUM(CASE WHEN corps = 'PES' THEN 1 ELSE 0 END) as PES,
                SUM(CASE WHEN corps = 'PT' THEN 1 ELSE 0 END) as PT,
                SUM(CASE WHEN corps = 'PE' THEN 1 ELSE 0 END) as PE,
                SUM(CASE WHEN sexe = 'F' THEN 1 ELSE 0 END) as F,
                SUM(CASE WHEN sexe = 'M' THEN 1 ELSE 0 END) as M
            ")
            ->groupBy('etablissement_id')
            ->get()
            ->map(function($stat) {
                $stat->etablissement_nom = $stat->etablissement->nom ?? 'N/A';
                return $stat;
            });

        return response()->json($stats);
    }

    /**
     * Recherche globale (utilisé pour la vue publique)
     */
    public function searchGlobal(Request $request)
    {
        $query = Enseignant::with(['universite', 'etablissement']);
        
        // Filtres optionnels
        if ($request->has('universite_id')) {
            $query->where('universite_id', $request->universite_id);
        }
        
        if ($request->has('etablissement_id')) {
            $query->where('etablissement_id', $request->etablissement_id);
        }
        
        if ($request->has('corps')) {
            $query->where('corps', $request->corps);
        }
        
        if ($request->has('categorie')) {
            $query->where('categorie', $request->categorie);
        }
        
        if ($request->has('diplome')) {
            $query->where('diplome', $request->diplome);
        }
        
        // Recherche multi-champs insensible à la casse
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = strtolower($request->search);
            $query->where(function($q) use ($searchTerm) {
                $q->whereRaw('LOWER(nom) LIKE ?', ['%' . $searchTerm . '%'])
                  ->orWhereRaw('LOWER(im) LIKE ?', ['%' . $searchTerm . '%'])
                  ->orWhereRaw('LOWER(diplome) LIKE ?', ['%' . $searchTerm . '%'])
                  ->orWhereRaw('LOWER(specialite) LIKE ?', ['%' . $searchTerm . '%'])
                  ->orWhereRaw('LOWER(corps) LIKE ?', ['%' . $searchTerm . '%'])
                  ->orWhereRaw('LOWER(categorie) LIKE ?', ['%' . $searchTerm . '%']);
            });
        }
        
        // Tri
        $query->orderBy('nom', 'asc');
        
        // Pagination avec plus d'éléments pour la vue publique
        $perPage = $request->get('per_page', 1000);
        return $query->paginate($perPage);
    }

    /**
     * Compter le nombre total d'enseignants (pour statistiques)
     */
    public function count(Request $request)
    {
        $query = Enseignant::query();
        
        if ($request->has('universite_id')) {
            $query->where('universite_id', $request->universite_id);
        }
        
        if ($request->has('etablissement_id')) {
            $query->where('etablissement_id', $request->etablissement_id);
        }
        
        if ($request->has('corps')) {
            $query->where('corps', $request->corps);
        }
        
        if ($request->has('categorie')) {
            $query->where('categorie', $request->categorie);
        }
        
        $count = $query->count();
        
        return response()->json([
            'count' => $count,
            'filters' => $request->all()
        ]);
    }

    /**
     * Récupérer les corps et catégories disponibles
     */
    public function getMetadata(Request $request)
    {
        $query = Enseignant::query();
        
        if ($request->has('universite_id')) {
            $query->where('universite_id', $request->universite_id);
        }
        
        $corps = $query->distinct()->pluck('corps')->filter()->values();
        $categories = $query->distinct()->pluck('categorie')->filter()->values();
        $diplomes = $query->distinct()->pluck('diplome')->filter()->values();
        
        return response()->json([
            'corps' => $corps,
            'categories' => $categories,
            'diplomes' => $diplomes,
            'total' => $query->count()
        ]);
    }

    /**
     * Import depuis Excel/CSV
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        // Logique d'import à implémenter avec maatwebsite/excel
        return response()->json([
            'message' => 'Import en cours de développement'
        ]);
    }

    /**
     * Export vers Excel
     */
    public function export(Request $request)
    {
        // Logique d'export à implémenter
        return response()->json([
            'message' => 'Export disponible prochainement'
        ]);
    }
}
