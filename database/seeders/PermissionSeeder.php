<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Permissões do sistema organizadas por módulo
     */
    private array $permissions = [
        // Grupo: Administração
        'usuarios' => [
            'group' => 'Administração',
            'display' => 'Usuários',
            'actions' => [
                'view' => 'Visualizar usuários',
                'create' => 'Criar usuários',
                'edit' => 'Editar usuários',
                'delete' => 'Excluir usuários',
                'reset_password' => 'Resetar senha de usuários',
                'manage_roles' => 'Gerenciar perfis de usuários',
            ],
        ],
        'perfis' => [
            'group' => 'Administração',
            'display' => 'Perfis e Permissões',
            'actions' => [
                'view' => 'Visualizar perfis',
                'create' => 'Criar perfis',
                'edit' => 'Editar perfis',
                'delete' => 'Excluir perfis',
                'manage_permissions' => 'Gerenciar permissões de perfis',
            ],
        ],
        'auditoria' => [
            'group' => 'Administração',
            'display' => 'Auditoria',
            'actions' => [
                'view' => 'Visualizar logs de auditoria',
                'export' => 'Exportar logs de auditoria',
            ],
        ],

        // Grupo: Cadastros
        'transportadores' => [
            'group' => 'Cadastros',
            'display' => 'Transportadores',
            'actions' => [
                'view' => 'Visualizar transportadores',
                'view_details' => 'Visualizar detalhes do transportador',
                'export' => 'Exportar lista de transportadores',
            ],
        ],
        'motoristas' => [
            'group' => 'Cadastros',
            'display' => 'Motoristas',
            'actions' => [
                'view' => 'Visualizar motoristas',
                'view_details' => 'Visualizar detalhes do motorista',
            ],
        ],
        'veiculos' => [
            'group' => 'Cadastros',
            'display' => 'Veículos',
            'actions' => [
                'view' => 'Visualizar veículos',
                'view_details' => 'Visualizar detalhes do veículo',
                'validate_semparar' => 'Validar veículo no SemParar',
            ],
        ],

        // Grupo: Operações
        'pacotes' => [
            'group' => 'Operações',
            'display' => 'Pacotes',
            'actions' => [
                'view' => 'Visualizar pacotes',
                'view_details' => 'Visualizar detalhes do pacote',
                'view_itinerary' => 'Visualizar itinerário',
                'export' => 'Exportar lista de pacotes',
            ],
        ],
        'rotas_padrao' => [
            'group' => 'Operações',
            'display' => 'Rotas Padrão',
            'actions' => [
                'view' => 'Visualizar rotas padrão',
                'create' => 'Criar rotas padrão',
                'edit' => 'Editar rotas padrão',
                'delete' => 'Excluir rotas padrão',
                'manage_municipios' => 'Gerenciar municípios da rota',
            ],
        ],
        'pracas_pedagio' => [
            'group' => 'Operações',
            'display' => 'Praças de Pedágio',
            'actions' => [
                'view' => 'Visualizar praças de pedágio',
                'import' => 'Importar praças de pedágio',
                'export' => 'Exportar praças de pedágio',
            ],
        ],

        // Grupo: Vale Pedágio
        'compra_viagem' => [
            'group' => 'Vale Pedágio',
            'display' => 'Compra de Viagem (SemParar)',
            'actions' => [
                'view' => 'Visualizar compras de viagem',
                'create' => 'Realizar compra de viagem',
                'cancel' => 'Cancelar viagem',
                'view_receipt' => 'Visualizar recibo',
                'generate_pdf' => 'Gerar PDF do recibo',
            ],
        ],
        'vpo_emissao' => [
            'group' => 'Vale Pedágio',
            'display' => 'Emissão VPO (NDD Cargo)',
            'actions' => [
                'view' => 'Visualizar emissões VPO',
                'create' => 'Emitir VPO',
                'validate' => 'Validar dados para emissão',
            ],
        ],

        // Grupo: Relatórios
        'relatorios' => [
            'group' => 'Relatórios',
            'display' => 'Relatórios',
            'actions' => [
                'view_dashboard' => 'Visualizar dashboard',
                'view_reports' => 'Visualizar relatórios',
                'export_reports' => 'Exportar relatórios',
            ],
        ],

        // Grupo: Configurações
        'configuracoes' => [
            'group' => 'Configurações',
            'display' => 'Configurações do Sistema',
            'actions' => [
                'view' => 'Visualizar configurações',
                'edit' => 'Editar configurações',
                'test_connections' => 'Testar conexões',
            ],
        ],
    ];

    /**
     * Perfis padrão do sistema
     */
    private array $roles = [
        [
            'name' => 'admin',
            'display_name' => 'Administrador',
            'description' => 'Acesso total ao sistema. Pode gerenciar usuários, perfis e todas as funcionalidades.',
            'color' => 'primary',
            'icon' => 'tabler-crown',
            'is_system' => true,
            'permissions' => '*', // Todas as permissões
        ],
        [
            'name' => 'operador',
            'display_name' => 'Operador',
            'description' => 'Acesso às operações diárias. Pode realizar compras de viagem e emissão de VPO.',
            'color' => 'success',
            'icon' => 'tabler-user-check',
            'is_system' => false,
            'permissions' => [
                'transportadores.view',
                'transportadores.view_details',
                'motoristas.view',
                'motoristas.view_details',
                'veiculos.view',
                'veiculos.view_details',
                'veiculos.validate_semparar',
                'pacotes.view',
                'pacotes.view_details',
                'pacotes.view_itinerary',
                'rotas_padrao.view',
                'pracas_pedagio.view',
                'compra_viagem.view',
                'compra_viagem.create',
                'compra_viagem.view_receipt',
                'compra_viagem.generate_pdf',
                'vpo_emissao.view',
                'vpo_emissao.create',
                'vpo_emissao.validate',
                'relatorios.view_dashboard',
            ],
        ],
        [
            'name' => 'visualizador',
            'display_name' => 'Visualizador',
            'description' => 'Acesso apenas para visualização. Não pode realizar alterações.',
            'color' => 'info',
            'icon' => 'tabler-eye',
            'is_system' => false,
            'permissions' => [
                'transportadores.view',
                'motoristas.view',
                'veiculos.view',
                'pacotes.view',
                'pacotes.view_itinerary',
                'rotas_padrao.view',
                'pracas_pedagio.view',
                'compra_viagem.view',
                'vpo_emissao.view',
                'relatorios.view_dashboard',
            ],
        ],
    ];

    public function run(): void
    {
        $this->createPermissions();
        $this->createRoles();
    }

    private function createPermissions(): void
    {
        $sortOrder = 0;

        foreach ($this->permissions as $module => $config) {
            foreach ($config['actions'] as $action => $displayName) {
                Permission::updateOrCreate(
                    ['name' => "{$module}.{$action}"],
                    [
                        'module' => $module,
                        'action' => $action,
                        'display_name' => $displayName,
                        'description' => "Permissão para {$displayName} em {$config['display']}",
                        'group' => $config['group'],
                        'sort_order' => $sortOrder++,
                        'is_active' => true,
                    ]
                );
            }
        }

        $this->command->info("Criadas/atualizadas {$sortOrder} permissões.");
    }

    private function createRoles(): void
    {
        foreach ($this->roles as $roleData) {
            $permissions = $roleData['permissions'];
            unset($roleData['permissions']);

            $role = Role::updateOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );

            // Sincroniza permissões
            if ($permissions === '*') {
                // Todas as permissões
                $permissionIds = Permission::where('is_active', true)->pluck('id')->toArray();
            } else {
                // Permissões específicas
                $permissionIds = Permission::whereIn('name', $permissions)->pluck('id')->toArray();
            }

            $role->permissions()->sync($permissionIds);

            $this->command->info("Perfil '{$role->display_name}' criado/atualizado com " . count($permissionIds) . " permissões.");
        }
    }
}
