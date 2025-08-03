<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseController
{
    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20|unique:users',
            'address' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'address' => $request->address,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->sendResponse([
            'user' => $user,
            'token' => $token,
        ], 'Usuario registrado exitosamente');
    }

    /**
     * Login user.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email_or_phone' => 'required|string',
            'password' => 'required',
        ]);

        $emailOrPhone = $request->email_or_phone;
        
        // Determinar si es email o teléfono
        $isEmail = filter_var($emailOrPhone, FILTER_VALIDATE_EMAIL);
        
        if ($isEmail) {
            // Buscar por email
            $user = User::where('email', $emailOrPhone)->first();
        } else {
            // Buscar por teléfono
            $user = User::where('phone', $emailOrPhone)->first();
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email_or_phone' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Crear token para API
        $token = $user->createToken('auth_token')->plainTextToken;
        $user->roles;
        $user->permissions;
        return $this->sendResponse([
            'user' => $user,            
            'token' => $token,
        ], 'Inicio de sesión exitoso');
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->sendResponse([], 'Sesión cerrada exitosamente');
    }

    /**
     * Get authenticated user.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('roles', 'permissions');

        return $this->sendResponse($user);
    }

    /**
     * Check if email or phone exists.
     */
    public function checkExists(Request $request)
    {
        $request->validate([
            'email_or_phone' => 'required|string',
        ]);

        $emailOrPhone = $request->email_or_phone;
        $isEmail = filter_var($emailOrPhone, FILTER_VALIDATE_EMAIL);
        
        if ($isEmail) {
            $exists = User::where('email', $emailOrPhone)->exists();
        } else {
            $exists = User::where('phone', $emailOrPhone)->exists();
        }

        return $this->sendResponse([
            'exists' => $exists,
            'type' => $isEmail ? 'email' : 'phone'
        ]);
    }
} 