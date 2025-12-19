<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Validation\Rules\Password;

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
            'remember' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember', false);

        // Incrementar contador de tentativas
        RateLimiter::hit($key, 60);

        if (Auth::attempt($credentials, $remember)) {
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

            // Registrar auditoria de login
            UserAuditLog::log(
                $user->id,
                UserAuditLog::ACTION_LOGIN,
                $user->id
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            // Verificar se precisa trocar a senha
            if ($user->password_reset_required) {
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
                    ],
                    'passwordResetRequired' => true,
                ]);
            }

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

        // Registrar auditoria de login falho (se usuário existir)
        $user = User::where('email', $request->input('email'))->first();
        if ($user) {
            UserAuditLog::log(
                $user->id,
                UserAuditLog::ACTION_LOGIN_FAILED,
                null,
                null,
                null,
                null,
                'Credenciais inválidas'
            );
        }

        return response()->json([
            'success' => false,
            'message' => 'Credenciais inválidas'
        ], 401);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        // Registrar auditoria de logout
        if ($user) {
            UserAuditLog::log(
                $user->id,
                UserAuditLog::ACTION_LOGOUT,
                $user->id
            );
        }

        // CORREÇÃO BUG #2: Null-safe operator para evitar erro se token não existir
        $request->user()?->currentAccessToken()?->delete();

        // LGPD logging
        Log::info('Logout realizado', [
            'user_id' => $user?->id,
            'email' => $user?->email,
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

    /**
     * Altera a senha do usuário (usado após reset obrigatório)
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não autenticado'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => ['required_without:is_reset', 'string'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                Password::defaults(),
            ],
            'password_confirmation' => ['required', 'string'],
            'is_reset' => ['sometimes', 'boolean'],
        ], [
            'password.min' => 'A nova senha deve ter no mínimo 8 caracteres',
            'password.confirmed' => 'As senhas não correspondem',
            'current_password.required_without' => 'A senha atual é obrigatória',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $isResetFlow = $request->input('is_reset', false) && $user->password_reset_required;

        // Se não for fluxo de reset, verificar senha atual
        if (!$isResetFlow) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Senha atual incorreta'
                ], 422);
            }
        }

        // Não permitir usar a mesma senha
        if (Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'A nova senha não pode ser igual à senha anterior'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'password_reset_required' => false,
            'password_changed_at' => now(),
        ]);

        // Registrar auditoria
        UserAuditLog::log(
            $user->id,
            UserAuditLog::ACTION_PASSWORD_CHANGED,
            $user->id,
            'password',
            null,
            null,
            $isResetFlow ? 'Senha alterada após reset obrigatório' : 'Senha alterada pelo próprio usuário'
        );

        Log::info('Senha alterada com sucesso', [
            'user_id' => $user->id,
            'email' => $user->email,
            'is_reset_flow' => $isResetFlow,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Senha alterada com sucesso'
        ]);
    }

    /**
     * Configura senha no primeiro acesso (via token)
     */
    public function setupPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'size:64'],
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
            'password_confirmation' => ['required', 'string'],
        ], [
            'token.required' => 'Token de configuração é obrigatório.',
            'token.size' => 'Token de configuração inválido.',
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'password.confirmed' => 'As senhas não correspondem.',
            'password.regex' => 'A senha deve conter: 1 letra minúscula, 1 maiúscula, 1 número e 1 caractere especial (@$!%*#?&)',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        // Buscar usuário pelo token
        $user = User::where('setup_token', $request->token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido ou expirado.'
            ], 404);
        }

        // Verificar se token expirou (24 horas)
        if ($user->setup_token_expires_at && $user->setup_token_expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Token expirado. Solicite um novo link ao administrador.'
            ], 410);
        }

        // Configurar senha
        $user->update([
            'password' => Hash::make($request->password),
            'password_reset_required' => false,
            'password_changed_at' => now(),
            'setup_token' => null,
            'setup_token_expires_at' => null,
        ]);

        // Registrar auditoria
        UserAuditLog::log(
            $user->id,
            UserAuditLog::ACTION_PASSWORD_CHANGED,
            $user->id,
            'password',
            null,
            null,
            'Senha configurada no primeiro acesso'
        );

        Log::info('Senha configurada no primeiro acesso', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);

        // Criar token de autenticação
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Senha configurada com sucesso!',
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

    /**
     * Verifica se token de setup é válido
     */
    public function verifySetupToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'size:64'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'Token inválido.'
            ], 422);
        }

        $user = User::where('setup_token', $request->token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'Token não encontrado.'
            ], 404);
        }

        if ($user->setup_token_expires_at && $user->setup_token_expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'Token expirado.',
                'expired' => true
            ], 410);
        }

        return response()->json([
            'success' => true,
            'valid' => true,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }
}