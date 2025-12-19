<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Universite;
use Illuminate\Http\Request;

class UniversiteController extends Controller
{
    public function index()
    {
        $universites = Universite::with('etablissements')->get();
        return response()->json($universites);
    }

    public function show($id)
    {
        $universite = Universite::with('etablissements')->findOrFail($id);
        return response()->json($universite);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'province' => 'required|string',
            'code' => 'required|string|max:10'
        ]);

        $universite = Universite::create($validated);
        return response()->json($universite, 201);
    }

    public function update(Request $request, $id)
    {
        $universite = Universite::findOrFail($id);

        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'province' => 'sometimes|string',
            'code' => 'sometimes|string|max:10'
        ]);

        $universite->update($validated);
        return response()->json($universite);
    }

    public function destroy($id)
    {
        $universite = Universite::findOrFail($id);
        $universite->delete();
        return response()->json(['message' => 'Université supprimée avec succès']);
    }
}
