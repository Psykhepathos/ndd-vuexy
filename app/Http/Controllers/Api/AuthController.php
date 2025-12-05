<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // CORREÇÃO BUG #1: Rate limiting para prevenir brute force (5 tentativas por minuto)
        $key = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            Log::warning('Rate limit excedido em login', [
                'ip' => $request->ip(),
                'email' => $request->input('email'),
                'retry_after' => $seconds,
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String()
            ]);

            return response()->json([
                'success' => false,
                'message' => "Muitas tentativas de login. Tente novamente em {$seconds} segundos.",
                'retry_after' => $seconds
            ], 429);
        }

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

        // Incrementar contador de tentativas
        RateLimiter::hit($key, 60);

        if (Auth::attempt($credentials)) {
            // CORREÇÃO BUG #1: Limpar contador de rate limit após login bem-sucedido
            RateLimiter::clear($key);

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
        // CORREÇÃO BUG #2: Null-safe operator para evitar erro se token não existir
        $request->user()?->currentAccessToken()?->delete();

        // LGPD logging
        Log::info('Logout realizado', [
            'user_id' => $request->user()?->id,
            'email' => $request->user()?->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);

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

    /**
     * CORREÇÃO BUG #3: Endpoint de registro público
     *
     * ⚠️ ATENÇÃO SEGURANÇA:
     * Este endpoint está PÚBLICO por design para permitir auto-registro.
     *
     * Considerações:
     * - Para produção, considere adicionar email verification (Laravel MustVerifyEmail)
     * - Ou desabilitar este endpoint e criar usuários apenas via admin
     * - Ou adicionar CAPTCHA para prevenir spam de bots
     *
     * Para desabilitar registro público, comente a rota em routes/api.php:
     * // Route::post('/auth/register', [AuthController::class, 'register']);
     */
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

        // CORREÇÃO BUG #4: Role configurável via config, default 'user' (seguro)
        // Para permitir criação de admins, altere em config/auth.php:
        // 'default_registration_role' => 'admin' (NÃO RECOMENDADO EM PRODUÇÃO!)
        $defaultRole = config('auth.default_registration_role', 'user');

        // Security: Nunca permitir role 'admin' em registro público
        if ($defaultRole === 'admin') {
            Log::warning('Tentativa de criar usuário admin via registro público bloqueada', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String()
            ]);

            $defaultRole = 'user'; // Force user role for security
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $defaultRole,
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