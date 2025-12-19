<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Lista todos os perfis com suas permissões
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::withCount(['users', 'permissions']);

        // Filtro por busca
        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        // Filtro por status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Ordenação
        $sortBy = $request->input('sortBy', 'name');
        $orderBy = $request->input('orderBy', 'asc');
        $query->orderBy($sortBy, $orderBy);

        // Paginação
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage == -1) {
            $roles = $query->get();
            $total = $roles->count();
        } else {
            $paginated = $query->paginate($perPage);
            $roles = $paginated->items();
            $total = $paginated->total();
        }

        return response()->json([
            'roles' => $roles,
            'totalRoles' => $total,
        ]);
    }

    /**
     * Exibe um perfil específico com suas permissões
     */
    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');

        return response()->json([
            'role' => $role,
            'permissions' => $role->permissions->pluck('name'),
        ]);
    }

    /**
     * Cria um novo perfil
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:roles,name', 'regex:/^[a-z_]+$/'],
            'display_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['sometimes', 'string', 'max:20'],
            'icon' => ['sometimes', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ], [
            'name.required' => 'O identificador é obrigatório.',
            'name.unique' => 'Este identificador já está em uso.',
            'name.regex' => 'O identificador deve conter apenas letras minúsculas e underline.',
            'display_name.required' => 'O nome de exibição é obrigatório.',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? 'primary',
            'icon' => $validated['icon'] ?? 'tabler-user',
            'is_active' => $validated['is_active'] ?? true,
            'is_system' => false,
        ]);

        // Sincronizar permissões
        if (isset($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return response()->json([
            'message' => 'Perfil criado com sucesso.',
            'role' => $role->load('permissions'),
        ], 201);
    }

    /**
     * Atualiza um perfil existente
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        // Não permitir editar perfis do sistema (exceto permissões)
        if ($role->is_system && $request->hasAny(['name', 'is_active'])) {
            return response()->json([
                'message' => 'Não é possível alterar o identificador ou desativar perfis do sistema.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:50', Rule::unique('roles')->ignore($role->id), 'regex:/^[a-z_]+$/'],
            'display_name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['sometimes', 'string', 'max:20'],
            'icon' => ['sometimes', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ], [
            'name.unique' => 'Este identificador já está em uso.',
            'name.regex' => 'O identificador deve conter apenas letras minúsculas e underline.',
        ]);

        // Não permitir alterar campos protegidos de perfis do sistema
        if ($role->is_system) {
            unset($validated['name'], $validated['is_active']);
        }

        // Atualiza apenas os campos que não são permissões
        $permissions = $validated['permissions'] ?? null;
        unset($validated['permissions']);

        if (!empty($validated)) {
            $role->update($validated);
        }

        // Sincronizar permissões
        if ($permissions !== null) {
            $role->permissions()->sync($permissions);
        }

        return response()->json([
            'message' => 'Perfil atualizado com sucesso.',
            'role' => $role->fresh()->load('permissions'),
        ]);
    }

    /**
     * Remove um perfil
     */
    public function destroy(Role $role): JsonResponse
    {
        // Não permitir excluir perfis do sistema
        if ($role->is_system) {
            return response()->json([
                'message' => 'Não é possível excluir perfis do sistema.',
            ], 403);
        }

        // Verificar se há usuários usando este perfil
        $usersCount = $role->users()->count();
        if ($usersCount > 0) {
            return response()->json([
                'message' => "Não é possível excluir este perfil. Existem {$usersCount} usuário(s) associado(s).",
            ], 409);
        }

        $role->permissions()->detach();
        $role->delete();

        return response()->json([
            'message' => 'Perfil excluído com sucesso.',
        ]);
    }

    /**
     * Sincroniza as permissões de um perfil
     */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role->permissions()->sync($validated['permissions']);

        return response()->json([
            'message' => 'Permissões atualizadas com sucesso.',
            'role' => $role->fresh()->load('permissions'),
        ]);
    }

    /**
     * Lista todas as permissões disponíveis (agrupadas por módulo)
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::where('is_active', true)
            ->orderBy('group')
            ->orderBy('sort_order')
            ->get();

        // Agrupar por grupo
        $grouped = $permissions->groupBy('group')->map(function ($group, $groupName) {
            // Dentro de cada grupo, agrupar por módulo
            $modules = $group->groupBy('module')->map(function ($modulePerms, $moduleName) {
                return [
                    'module' => $moduleName,
                    'display' => $modulePerms->first()->display_name_module ?? ucfirst($moduleName),
                    'permissions' => $modulePerms->map(function ($perm) {
                        return [
                            'id' => $perm->id,
                            'name' => $perm->name,
                            'action' => $perm->action,
                            'display_name' => $perm->display_name,
                            'description' => $perm->description,
                        ];
                    })->values(),
                ];
            })->values();

            return [
                'group' => $groupName,
                'modules' => $modules,
            ];
        })->values();

        return response()->json([
            'groups' => $grouped,
            'allPermissions' => $permissions,
        ]);
    }

    /**
     * Estatísticas dos perfis
     */
    public function statistics(): JsonResponse
    {
        $total = Role::count();
        $active = Role::where('is_active', true)->count();
        $system = Role::where('is_system', true)->count();
        $custom = Role::where('is_system', false)->count();

        // Perfis com mais usuários
        $topRoles = Role::withCount('users')
            ->orderByDesc('users_count')
            ->limit(5)
            ->get(['id', 'name', 'display_name', 'color', 'icon']);

        return response()->json([
            'total' => $total,
            'active' => $active,
            'system' => $system,
            'custom' => $custom,
            'topRoles' => $topRoles,
        ]);
    }
}
