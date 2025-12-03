<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            // Validar integridade de role (não usar fallback silencioso)
            if (!$user->role || !in_array($user->role, ['admin', 'user'], true)) {
                \Log::error('Usuário com role inválido ou nulo detectado', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Erro de integridade de dados do usuário. Contate o administrador.'
                ], 500);
            }

            // CORREÇÃO #1: Logging de login bem-sucedido (LGPD Art. 46)
            \Log::info('Login bem-sucedido', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String()
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'accessToken' => $token,
                'userData' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'avatar' => null,
                ],
                'userAbilityRules' => [
                    [
                        'action' => 'manage',
                        'subject' => 'all'
                    ]
                ]
            ]);
        }

        // CORREÇÃO #1: Logging de tentativa falhada (CRÍTICO para detecção de brute force)
        \Log::warning('Tentativa de login falhada', [
            'email' => $request->input('email'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Credenciais inválidas'
        ], 401);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout realizado com sucesso'
        ]);
    }

    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()
        ]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[a-z]/',      // Pelo menos uma letra minúscula
                'regex:/[A-Z]/',      // Pelo menos uma letra maiúscula
                'regex:/[0-9]/',      // Pelo menos um número
                'regex:/[@$!%*#?&]/', // Pelo menos um caractere especial
            ],
            'password_confirmation' => 'required|string|min:8',
        ], [
            // CORREÇÃO #9: Mensagens customizadas mais claras
            'password.regex' => 'A senha deve conter: 1 letra minúscula, 1 maiúscula, 1 número e 1 caractere especial (@$!%*#?&)',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres',
            'password.confirmed' => 'As senhas não correspondem',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        // CORREÇÃO #5: Removido double-check redundante
        // Laravel 'confirmed' rule já valida que password === password_confirmation
        // Se validator passou, senhas já são iguais!

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
        ]);

        // CORREÇÃO #4: Logging de novo registro (LGPD Art. 46 compliance)
        Log::info('Novo usuário registrado', [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Usuário criado com sucesso',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ], 201);
    }
}