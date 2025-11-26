<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    // app/Http/Controllers/AuthController.php
public function login(Request $request)
{
    $credentials = $request->only('email', 'password');
    
    if (Auth::attempt($credentials)) {
        $user = Auth::user();
        $token = $user->createToken('admin-token')->plainTextToken;
        
        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user
        ]);
    }
    
    return response()->json([
        'success' => false,
        'message' => 'Identifiants invalides'
    ], 401);
}
}
