<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\PasswordResetRequest;
use App\Models\UserAuditLog;
use App\Mail\WelcomeUserMail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Lista todos os usuários com paginação e filtros
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('roleRelation:id,name,display_name,color,icon');

        // Filtro por busca (nome ou email)
        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtro por role (legado) ou role_id (novo sistema)
        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->input('role_id'));
        }

        // Filtro por status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filtro por usuários que precisam definir senha
        if ($request->filled('needs_password')) {
            $query->where('password_reset_required', true);
        }

        // Ordenação
        $sortBy = $request->input('sortBy', 'created_at');
        $orderBy = $request->input('orderBy', 'desc');
        $query->orderBy($sortBy, $orderBy);

        // Paginação
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage == -1) {
            $users = $query->get();
            $total = $users->count();
        } else {
            $paginated = $query->paginate($perPage);
            $users = $paginated->items();
            $total = $paginated->total();
        }

        return response()->json([
            'users' => $users,
            'totalUsers' => $total,
        ]);
    }

    /**
     * Estatísticas de usuários
     */
    public function statistics(): JsonResponse
    {
        $total = User::count();
        $admins = User::where('role', 'admin')->count();
        $users = User::where('role', 'user')->count();
        $active = User::where('status', 'active')->count();
        $inactive = User::where('status', 'inactive')->count();
        $pending = User::where('status', 'pending')->count();
        $pendingPasswordReset = User::where('password_reset_required', true)->count();

        // Usuários criados esta semana
        $thisWeek = User::where('created_at', '>=', now()->startOfWeek())->count();
        $lastWeek = User::whereBetween('created_at', [
            now()->subWeek()->startOfWeek(),
            now()->subWeek()->endOfWeek()
        ])->count();

        $weekChange = $lastWeek > 0
            ? round((($thisWeek - $lastWeek) / $lastWeek) * 100)
            : ($thisWeek > 0 ? 100 : 0);

        return response()->json([
            'total' => $total,
            'admins' => $admins,
            'users' => $users,
            'active' => $active,
            'inactive' => $inactive,
            'pending' => $pending,
            'pendingPasswordReset' => $pendingPasswordReset,
            'thisWeek' => $thisWeek,
            'weekChange' => $weekChange,
        ]);
    }

    /**
     * Exibe um usuário específico
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Cria um novo usuário (sem senha - usuário cria no primeiro acesso)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'role_id' => ['required', 'exists:roles,id'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'pending'])],
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
            'role_id.required' => 'O perfil é obrigatório.',
            'role_id.exists' => 'Perfil inválido.',
        ]);

        // Buscar role para auditoria
        $role = Role::find($validated['role_id']);

        // Gerar token único para primeiro acesso
        $setupToken = Str::random(64);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make(Str::random(32)), // Senha aleatória temporária
            'role' => 'user', // Legado - manter para compatibilidade
            'role_id' => $validated['role_id'],
            'status' => $validated['status'] ?? 'active',
            'password_reset_required' => true, // Forçar criação de senha no primeiro acesso
            'setup_token' => $setupToken,
        ]);

        // Registrar auditoria
        UserAuditLog::log(
            $user->id,
            UserAuditLog::ACTION_CREATED,
            auth()->id(),
            null,
            null,
            [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role->display_name,
                'status' => $user->status,
            ]
        );

        // Enviar email de boas-vindas com link para configurar senha
        $setupUrl = url("/configurar-senha/{$setupToken}");
        $emailSent = false;
        $emailError = null;

        try {
            Mail::to($user->email)->send(new WelcomeUserMail($user, $setupUrl));
            $emailSent = true;
            Log::info('Email de boas-vindas enviado', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            $emailError = $e->getMessage();
            Log::error('Falha ao enviar email de boas-vindas', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $emailError,
            ]);
        }

        return response()->json([
            'message' => $emailSent
                ? 'Usuario criado com sucesso. Um email foi enviado para ' . $user->email . ' com as instrucoes de acesso.'
                : 'Usuario criado com sucesso. Nao foi possivel enviar o email automaticamente. Copie o link abaixo e envie manualmente.',
            'user' => $user->load('roleRelation'),
            'setupUrl' => $setupUrl,
            'emailSent' => $emailSent,
            'emailError' => $emailError,
        ], 201);
    }

    /**
     * Atualiza um usuário existente
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', Password::defaults()],
            'role' => ['sometimes', 'string', Rule::in(['admin', 'user'])],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive', 'pending'])],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ], [
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
            'role.in' => 'Perfil inválido.',
        ]);

        $reason = $validated['reason'] ?? null;
        unset($validated['reason']);

        // Capturar valores antigos para auditoria
        $oldValues = $user->only(['name', 'email', 'role', 'status']);

        // Verificar mudanças específicas para auditoria detalhada
        if (isset($validated['role']) && $validated['role'] !== $user->role) {
            UserAuditLog::log(
                $user->id,
                UserAuditLog::ACTION_ROLE_CHANGED,
                auth()->id(),
                'role',
                $user->role,
                $validated['role'],
                $reason
            );
        }

        if (isset($validated['status']) && $validated['status'] !== $user->status) {
            UserAuditLog::log(
                $user->id,
                UserAuditLog::ACTION_STATUS_CHANGED,
                auth()->id(),
                'status',
                $user->status,
                $validated['status'],
                $reason
            );
        }

        // Remove password se estiver vazio
        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
            $validated['password_changed_at'] = now();
            $validated['password_reset_required'] = false;

            UserAuditLog::log(
                $user->id,
                UserAuditLog::ACTION_PASSWORD_CHANGED,
                auth()->id(),
                'password',
                null,
                null,
                $reason ?? 'Senha alterada pelo administrador'
            );
        }

        // Registrar alteração geral (se houver mudanças além de role/status/password)
        $fieldsToCheck = ['name', 'email'];
        $generalChanges = [];
        foreach ($fieldsToCheck as $field) {
            if (isset($validated[$field]) && $validated[$field] !== $user->$field) {
                $generalChanges[$field] = [
                    'old' => $user->$field,
                    'new' => $validated[$field],
                ];
            }
        }

        if (!empty($generalChanges)) {
            UserAuditLog::log(
                $user->id,
                UserAuditLog::ACTION_UPDATED,
                auth()->id(),
                implode(', ', array_keys($generalChanges)),
                json_encode(array_column($generalChanges, 'old')),
                json_encode(array_column($generalChanges, 'new')),
                $reason
            );
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Usuário atualizado com sucesso.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Remove um usuário
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Não permite excluir o próprio usuário
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'Você não pode excluir sua própria conta.',
            ], 403);
        }

        $reason = $request->input('reason');

        // Registrar auditoria antes de deletar
        UserAuditLog::log(
            $user->id,
            UserAuditLog::ACTION_DELETED,
            auth()->id(),
            null,
            [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
            ],
            null,
            $reason
        );

        $user->delete();

        return response()->json([
            'message' => 'Usuário excluído com sucesso.',
        ]);
    }

    /**
     * Reseta a senha de um usuário (força troca no próximo login)
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        // Não permite resetar a própria senha por aqui
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'Use a opção "Alterar Senha" para modificar sua própria senha.',
            ], 403);
        }

        $validated = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        // Gera uma senha temporária aleatória
        $temporaryPassword = Str::random(12);

        $user->update([
            'password' => Hash::make($temporaryPassword),
            'password_reset_required' => true,
            'password_changed_at' => now(),
        ]);

        // Registrar auditoria
        UserAuditLog::log(
            $user->id,
            UserAuditLog::ACTION_PASSWORD_RESET,
            auth()->id(),
            'password',
            null,
            null,
            $validated['reason'] ?? 'Senha resetada pelo administrador'
        );

        return response()->json([
            'message' => 'Senha resetada com sucesso. O usuário precisará criar uma nova senha no próximo login.',
            'temporaryPassword' => $temporaryPassword,
        ]);
    }

    /**
     * Reenvia o email de configuracao de senha para o usuario
     */
    public function resendSetupEmail(User $user): JsonResponse
    {
        // Verificar se o usuario ainda precisa configurar senha
        if (!$user->password_reset_required) {
            return response()->json([
                'message' => 'Este usuario ja configurou sua senha.',
            ], 400);
        }

        // Gerar novo token se nao existir
        if (empty($user->setup_token)) {
            $user->setup_token = Str::random(64);
            $user->save();
        }

        $setupUrl = url("/configurar-senha/{$user->setup_token}");

        try {
            Mail::to($user->email)->send(new WelcomeUserMail($user, $setupUrl));

            Log::info('Email de configuracao reenviado', [
                'user_id' => $user->id,
                'email' => $user->email,
                'performed_by' => auth()->id(),
            ]);

            // Registrar auditoria
            UserAuditLog::log(
                $user->id,
                UserAuditLog::ACTION_UPDATED,
                auth()->id(),
                'setup_email',
                null,
                'Email reenviado',
                'Email de configuracao de senha reenviado'
            );

            return response()->json([
                'message' => 'Email reenviado com sucesso para ' . $user->email,
                'setupUrl' => $setupUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('Falha ao reenviar email de configuracao', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Falha ao enviar email. Copie o link abaixo e envie manualmente.',
                'setupUrl' => $setupUrl,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Histórico de auditoria de um usuário
     */
    public function auditLogs(Request $request, User $user): JsonResponse
    {
        $perPage = $request->input('itemsPerPage', 10);

        $query = UserAuditLog::where('user_id', $user->id)
            ->with('performer:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($perPage == -1) {
            $logs = $query->get();
            $total = $logs->count();
        } else {
            $paginated = $query->paginate($perPage);
            $logs = $paginated->items();
            $total = $paginated->total();
        }

        // Adicionar atributos computados
        $logs = collect($logs)->map(function ($log) {
            $log->action_description = $log->action_description;
            $log->action_color = $log->action_color;
            $log->action_icon = $log->action_icon;
            return $log;
        });

        return response()->json([
            'logs' => $logs,
            'totalLogs' => $total,
        ]);
    }

    /**
     * Histórico de auditoria geral (todos os usuários)
     */
    public function allAuditLogs(Request $request): JsonResponse
    {
        $perPage = $request->input('itemsPerPage', 20);

        $query = UserAuditLog::with(['user:id,name,email', 'performer:id,name,email'])
            ->orderBy('created_at', 'desc');

        // Filtro por ação
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        // Filtro por usuário afetado
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Filtro por quem executou
        if ($request->filled('performed_by')) {
            $query->where('performed_by', $request->input('performed_by'));
        }

        // Filtro por data
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($perPage == -1) {
            $logs = $query->get();
            $total = $logs->count();
        } else {
            $paginated = $query->paginate($perPage);
            $logs = $paginated->items();
            $total = $paginated->total();
        }

        // Adicionar atributos computados
        $logs = collect($logs)->map(function ($log) {
            $log->action_description = $log->action_description;
            $log->action_color = $log->action_color;
            $log->action_icon = $log->action_icon;
            return $log;
        });

        return response()->json([
            'logs' => $logs,
            'totalLogs' => $total,
        ]);
    }

    /**
     * Lista as permissões disponíveis
     */
    public function permissions(): JsonResponse
    {
        $permissions = [
            [
                'id' => 'users',
                'name' => 'Usuários',
                'actions' => ['read', 'create', 'update', 'delete'],
            ],
            [
                'id' => 'transportes',
                'name' => 'Transportadores',
                'actions' => ['read', 'create', 'update', 'delete'],
            ],
            [
                'id' => 'pacotes',
                'name' => 'Pacotes',
                'actions' => ['read', 'create', 'update', 'delete'],
            ],
            [
                'id' => 'rotas',
                'name' => 'Rotas',
                'actions' => ['read', 'create', 'update', 'delete'],
            ],
            [
                'id' => 'compra_viagem',
                'name' => 'Compra de Viagem',
                'actions' => ['read', 'create'],
            ],
            [
                'id' => 'vpo',
                'name' => 'VPO / NDD Cargo',
                'actions' => ['read', 'create'],
            ],
            [
                'id' => 'pracas_pedagio',
                'name' => 'Praças de Pedágio',
                'actions' => ['read', 'create', 'update', 'delete'],
            ],
            [
                'id' => 'relatorios',
                'name' => 'Relatórios',
                'actions' => ['read', 'export'],
            ],
        ];

        return response()->json([
            'permissions' => $permissions,
        ]);
    }

    /**
     * Roles disponíveis no sistema
     */
    public function roles(): JsonResponse
    {
        $roles = [
            [
                'id' => 'admin',
                'name' => 'Administrador',
                'description' => 'Acesso total ao sistema',
                'color' => 'primary',
                'icon' => 'tabler-crown',
            ],
            [
                'id' => 'user',
                'name' => 'Usuário',
                'description' => 'Acesso padrão ao sistema',
                'color' => 'info',
                'icon' => 'tabler-user',
            ],
        ];

        return response()->json([
            'roles' => $roles,
        ]);
    }
}
