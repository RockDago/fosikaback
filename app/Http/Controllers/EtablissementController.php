<?php

namespace App\Http\Controllers;

use App\Models\Etablissement;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class EtablissementController extends Controller
{
    public function index(Request $request)
    {
        $query = Etablissement::with('universite');

        if ($request->filled('universite_id')) {
            $query->where('universite_id', $request->universite_id);
        }

        return response()->json($query->get());
    }

    public function show($id)
    {
        return response()->json(Etablissement::with('universite')->findOrFail($id));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'universite_id' => 'required|exists:universites,id',
            'nom' => 'required|string|max:255',
        ]);

        $etablissement = Etablissement::create($validated);

        AuditLogger::logCreation(
            $request->user()->email ?? 'system',
            'Etablissement',
            "Creation de l'etablissement {$etablissement->nom} (ID: {$etablissement->id})"
        );

        return response()->json($etablissement, 201);
    }

    public function update(Request $request, $id)
    {
        $etablissement = Etablissement::findOrFail($id);

        $validated = $request->validate([
            'universite_id' => 'sometimes|exists:universites,id',
            'nom' => 'sometimes|string|max:255',
        ]);

        $etablissement->update($validated);

        AuditLogger::logModification(
            $request->user()->email ?? 'system',
            'Etablissement',
            "Modification de l'etablissement {$etablissement->nom} (ID: {$etablissement->id})"
        );

        return response()->json($etablissement);
    }

    public function destroy(Request $request, $id)
    {
        $etablissement = Etablissement::findOrFail($id);
        $nom = $etablissement->nom;
        $etablissement->delete();

        AuditLogger::logSuppression(
            $request->user()->email ?? 'system',
            'Etablissement',
            "Suppression de l'etablissement {$nom} (ID: {$id})"
        );

        return response()->json(['message' => 'Etablissement supprime avec succes']);
    }
}
