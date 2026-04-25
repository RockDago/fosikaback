<?php

namespace App\Http\Controllers;

use App\Models\Universite;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class UniversiteController extends Controller
{
    public function index()
    {
        try {
            $universites = Universite::with('etablissements')->get();
            
            \Log::info('Universites API called', [
                'count' => $universites->count(),
                'first_item' => $universites->first(),
                'user_agent' => request()->userAgent(),
                'ip' => request()->ip(),
            ]);
            
            return response()->json($universites);
        } catch (\Exception $e) {
            \Log::error('Error in UniversiteController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Erreur lors de la récupération des universités',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getAll()
    {
        return response()->json(
            Universite::with('etablissements')
                ->orderBy('nom')
                ->get()
        );
    }

    public function show($id)
    {
        return response()->json(Universite::with('etablissements')->findOrFail($id));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'province' => 'required|string',
            'code' => 'required|string|max:10',
        ]);

        $universite = Universite::create($validated);

        AuditLogger::logCreation(
            $request->user()->email ?? 'system',
            'Universite',
            "Creation de l'universite {$universite->nom} (ID: {$universite->id})"
        );

        return response()->json($universite, 201);
    }

    public function update(Request $request, $id)
    {
        $universite = Universite::findOrFail($id);

        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'province' => 'sometimes|string',
            'code' => 'sometimes|string|max:10',
        ]);

        $universite->update($validated);

        AuditLogger::logModification(
            $request->user()->email ?? 'system',
            'Universite',
            "Modification de l'universite {$universite->nom} (ID: {$universite->id})"
        );

        return response()->json($universite);
    }

    public function destroy(Request $request, $id)
    {
        $universite = Universite::findOrFail($id);
        $nom = $universite->nom;
        $universite->delete();

        AuditLogger::logSuppression(
            $request->user()->email ?? 'system',
            'Universite',
            "Suppression de l'universite {$nom} (ID: {$id})"
        );

        return response()->json(['message' => 'Universite supprimee avec succes']);
    }
}
