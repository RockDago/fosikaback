<?php

namespace App\Http\Controllers;

use App\Models\Universite;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class UniversiteController extends Controller
{
    public function index()
    {
        return response()->json(Universite::with('etablissements')->get());
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
