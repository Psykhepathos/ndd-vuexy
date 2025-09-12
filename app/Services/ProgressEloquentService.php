<?php

namespace App\Services;

use App\Models\Progress\Transporte;
use App\Models\Progress\Motorista;
use App\Models\Progress\Veiculo;
use App\Models\Progress\Pedagio;
use App\Models\Progress\Ciot;
use Illuminate\Support\Facades\Log;
use Exception;

class ProgressEloquentService
{
    /**
     * Busca transportes paginados usando Eloquent
     */
    public function getTransportesPaginated(array $filters): array
    {
        try {
            Log::info('Buscando transportes com Eloquent', ['filters' => $filters]);

            $query = Transporte::query();
            
            // Aplicar filtros usando scopes
            if (!empty($filters['search'])) {
                $query->buscar($filters['search']);
            }
            
            if (!empty($filters['tipo']) && $filters['tipo'] !== 'todos') {
                if ($filters['tipo'] === 'autonomo') {
                    $query->autonomos();
                } elseif ($filters['tipo'] === 'empresa') {
                    $query->empresas();
                }
            }
            
            if (!empty($filters['natureza'])) {
                $query->porNatureza($filters['natureza']);
            }
            
            if (isset($filters['ativo']) && $filters['ativo'] !== null) {
                if ($filters['ativo'] === 'true') {
                    $query->ativos();
                } else {
                    $query->inativos();
                }
            }
            
            // Campos essenciais para a interface
            $query->select([
                'codtrn', 'nomtrn', 'flgautonomo', 'natcam', 'tipcam', 
                'codcnpjcpf', 'numpla', 'numtel', 'dddtel', 'flgati', 'indcd'
            ]);
            
            // Paginação
            $page = $filters['page'] ?? 1;
            $perPage = $filters['per_page'] ?? 10;
            
            $transportes = $query->paginate($perPage, ['*'], 'page', $page);
            
            return [
                'success' => true,
                'data' => [
                    'results' => $transportes->items(),
                    'total' => count($transportes->items()),
                    'filters_applied' => $filters
                ],
                'pagination' => [
                    'current_page' => $transportes->currentPage(),
                    'per_page' => $transportes->perPage(),
                    'total' => $transportes->total(),
                    'last_page' => $transportes->lastPage(),
                    'from' => $transportes->firstItem(),
                    'to' => $transportes->lastItem(),
                    'has_more_pages' => $transportes->hasMorePages()
                ]
            ];
            
        } catch (Exception $e) {
            Log::error('Erro na busca Eloquent de transportes', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            
            return [
                'success' => false,
                'error' => 'Erro na busca de transportes: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Busca transporte por ID usando Eloquent
     */
    public function getTransporteById($id): array
    {
        try {
            Log::info('Buscando transporte por ID com Eloquent', ['id' => $id]);
            
            $transporte = Transporte::with(['veiculos', 'motoristas'])
                ->find($id);
                
            if (!$transporte) {
                return [
                    'success' => false,
                    'message' => 'Transporte não encontrado',
                    'data' => null
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Transporte encontrado',
                'data' => $transporte
            ];
            
        } catch (Exception $e) {
            Log::error('Erro na busca Eloquent de transporte por ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Erro na busca de transporte: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtém estatísticas dos transportes usando Eloquent
     */
    public function getTransportesStatistics(): array
    {
        try {
            Log::info('Obtendo estatísticas com Eloquent');
            
            $stats = [
                'total' => Transporte::count(),
                'autonomos' => Transporte::autonomos()->count(),
                'empresas' => Transporte::empresas()->count(),
                'ativos' => Transporte::ativos()->count(),
                'inativos' => Transporte::inativos()->count(),
                'com_cd' => Transporte::comCD()->count(),
            ];
            
            return [
                'success' => true,
                'message' => 'Estatísticas obtidas com sucesso',
                'data' => $stats
            ];
            
        } catch (Exception $e) {
            Log::error('Erro ao obter estatísticas Eloquent', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Erro ao obter estatísticas: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Busca motoristas paginados usando Eloquent
     */
    public function getMotoristasPaginated(array $filters): array
    {
        try {
            Log::info('Buscando motoristas com Eloquent', ['filters' => $filters]);

            $query = Motorista::query();
            
            // Aplicar filtros
            if (!empty($filters['search'])) {
                $query->buscar($filters['search']);
            }
            
            if (!empty($filters['tipo']) && $filters['tipo'] !== 'todos') {
                if ($filters['tipo'] === 'autonomo') {
                    $query->autonomos();
                } elseif ($filters['tipo'] === 'funcionario') {
                    $query->funcionarios();
                }
            }
            
            if (isset($filters['ativo']) && $filters['ativo'] !== null) {
                if ($filters['ativo'] === 'true') {
                    $query->ativos();
                } else {
                    $query->inativos();
                }
            }
            
            if (!empty($filters['cnh_status'])) {
                match($filters['cnh_status']) {
                    'valida' => $query->comCnhValida(),
                    'vencida' => $query->comCnhVencida(),
                    'proxima_vencimento' => $query->comCnhProximaVencimento(),
                    default => null
                };
            }
            
            // Paginação
            $page = $filters['page'] ?? 1;
            $perPage = $filters['per_page'] ?? 10;
            
            $motoristas = $query->paginate($perPage, ['*'], 'page', $page);
            
            return [
                'success' => true,
                'data' => [
                    'results' => $motoristas->items(),
                    'total' => count($motoristas->items()),
                    'filters_applied' => $filters
                ],
                'pagination' => [
                    'current_page' => $motoristas->currentPage(),
                    'per_page' => $motoristas->perPage(),
                    'total' => $motoristas->total(),
                    'last_page' => $motoristas->lastPage(),
                    'from' => $motoristas->firstItem(),
                    'to' => $motoristas->lastItem(),
                    'has_more_pages' => $motoristas->hasMorePages()
                ]
            ];
            
        } catch (Exception $e) {
            Log::error('Erro na busca Eloquent de motoristas', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            
            return [
                'success' => false,
                'error' => 'Erro na busca de motoristas: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Busca veículos por transportador
     */
    public function getVeiculosPorTransportador($codigoTransportador): array
    {
        try {
            $veiculos = Veiculo::doTransportador($codigoTransportador)
                ->ativos()
                ->get();
                
            return [
                'success' => true,
                'data' => $veiculos
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao buscar veículos: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Busca pedágios por filtros
     */
    public function getPedagios(array $filters): array
    {
        try {
            $query = Pedagio::query()->ativos();
            
            if (!empty($filters['rodovia'])) {
                $query->porRodovia($filters['rodovia']);
            }
            
            if (!empty($filters['uf'])) {
                $query->porUf($filters['uf']);
            }
            
            if (!empty($filters['cidade'])) {
                $query->porCidade($filters['cidade']);
            }
            
            $pedagios = $query->get();
            
            return [
                'success' => true,
                'data' => $pedagios
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao buscar pedágios: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Busca CIOTs com relacionamentos
     */
    public function getCiots(array $filters): array
    {
        try {
            $query = Ciot::with(['transportador', 'motorista']);
            
            if (!empty($filters['transportador'])) {
                $query->doTransportador($filters['transportador']);
            }
            
            if (!empty($filters['motorista'])) {
                $query->doMotorista($filters['motorista']);
            }
            
            if (!empty($filters['status'])) {
                $query->porStatus($filters['status']);
            }
            
            if (!empty($filters['origem'])) {
                $query->porRota($filters['origem']);
            }
            
            $ciots = $query->orderBy('datcri', 'desc')->get();
            
            return [
                'success' => true,
                'data' => $ciots
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao buscar CIOTs: ' . $e->getMessage()
            ];
        }
    }
}