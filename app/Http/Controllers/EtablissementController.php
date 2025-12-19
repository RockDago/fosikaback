<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Etablissement;
use Illuminate\Http\Request;

class EtablissementController extends Controller
{
    public function index(Request $request)
    {
        $query = Etablissement::with('universite');

        if ($request->has('universite_id')) {
            $query->where('universite_id', $request->universite_id);
        }

        $etablissements = $query->get();
        return response()->json($etablissements);
    }

    public function show($id)
    {
        $etablissement = Etablissement::with('universite')->findOrFail($id);
        return response()->json($etablissement);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'universite_id' => 'required|exists:universites,id',
            'nom' => 'required|string|max:255'
        ]);

        $etablissement = Etablissement::create($validated);
        return response()->json($etablissement, 201);
    }

    public function update(Request $request, $id)
    {
        $etablissement = Etablissement::findOrFail($id);

        $validated = $request->validate([
            'universite_id' => 'sometimes|exists:universites,id',
            'nom' => 'sometimes|string|max:255'
        ]);

        $etablissement->update($validated);
        return response()->json($etablissement);
    }

    public function destroy($id)
    {
        $etablissement = Etablissement::findOrFail($id);
        $etablissement->delete();
        return response()->json(['message' => 'Établissement supprimé avec succès']);
    }
}
