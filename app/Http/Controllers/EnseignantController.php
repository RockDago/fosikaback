<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Enseignant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EnseignantController extends Controller
{
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

        // Recherche par nom
        if ($request->has('search')) {
            $query->where('nom', 'like', '%' . $request->search . '%');
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $enseignants = $query->paginate($perPage);

        return response()->json($enseignants);
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
        $validator = Validator::make($request->all(), [
            'universite_id' => 'required|exists:universites,id',
            'etablissement_id' => 'required|exists:etablissements,id',
            'nom' => 'required|string|max:255',
            'sexe' => 'required|in:M,F',
            'im' => 'required|string|size:6',
            'date_naissance' => 'required|date',
            'corps' => 'required|string',
            'diplome' => 'required|string',
            'specialite' => 'required|string',
            'categorie' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $enseignant = Enseignant::create($request->all());
        $enseignant->load(['universite', 'etablissement']);

        return response()->json([
            'message' => 'Enseignant créé avec succès',
            'data' => $enseignant
        ], 201);
    }

    /**
     * Mettre à jour un enseignant
     */
    public function update(Request $request, $id)
    {
        $enseignant = Enseignant::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'universite_id' => 'sometimes|exists:universites,id',
            'etablissement_id' => 'sometimes|exists:etablissements,id',
            'nom' => 'sometimes|string|max:255',
            'sexe' => 'sometimes|in:M,F',
            'im' => 'sometimes|string|size:6',
            'date_naissance' => 'sometimes|date',
            'corps' => 'sometimes|string',
            'diplome' => 'sometimes|string',
            'specialite' => 'sometimes|string',
            'categorie' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $enseignant->update($request->all());
        $enseignant->load(['universite', 'etablissement']);

        return response()->json([
            'message' => 'Enseignant mis à jour avec succès',
            'data' => $enseignant
        ]);
    }

    /**
     * Supprimer un enseignant
     */
    public function destroy($id)
    {
        $enseignant = Enseignant::findOrFail($id);
        $enseignant->delete();

        return response()->json([
            'message' => 'Enseignant supprimé avec succès'
        ]);
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
            ->selectRaw('
                etablissement_id,
                COUNT(*) as total,
                SUM(CASE WHEN corps = "AES" THEN 1 ELSE 0 END) as AES,
                SUM(CASE WHEN corps = "MC" THEN 1 ELSE 0 END) as MC,
                SUM(CASE WHEN corps = "PES" THEN 1 ELSE 0 END) as PES,
                SUM(CASE WHEN corps = "PT" THEN 1 ELSE 0 END) as PT,
                SUM(CASE WHEN sexe = "F" THEN 1 ELSE 0 END) as F,
                SUM(CASE WHEN sexe = "M" THEN 1 ELSE 0 END) as M
            ')
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
            ->selectRaw('
            etablissement_id,
            COUNT(*) as total,
            SUM(CASE WHEN corps = "AES" THEN 1 ELSE 0 END) as AES,
            SUM(CASE WHEN corps = "MC" THEN 1 ELSE 0 END) as MC,
            SUM(CASE WHEN corps = "PES" THEN 1 ELSE 0 END) as PES,
            SUM(CASE WHEN corps = "PT" THEN 1 ELSE 0 END) as PT,
            SUM(CASE WHEN corps = "PE" THEN 1 ELSE 0 END) as PE,
            SUM(CASE WHEN sexe = "F" THEN 1 ELSE 0 END) as F,
            SUM(CASE WHEN sexe = "M" THEN 1 ELSE 0 END) as M
        ')
            ->groupBy('etablissement_id')
            ->get()
            ->map(function($stat) {
                $stat->etablissement_nom = $stat->etablissement->nom ?? 'N/A';
                return $stat;
            });

        return response()->json($stats);
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
