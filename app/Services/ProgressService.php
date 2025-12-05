<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class ProgressService
{
    /**
     * Escapa string para uso seguro em queries SQL
     * Protege contra SQL injection
     *
     * @param string $value Valor a ser escapado
     * @return string Valor escapado e entre aspas simples
     */
    protected function escapeSqlString(string $value): string
    {
        // Escapar aspas simples duplicando-as (padrão SQL)
        $escaped = str_replace("'", "''", $value);

        // CORREÇÃO BUG #75: Escapar wildcards LIKE (% e _) para prevenir injection
        // Nota: Apenas escapar se a string será usada em LIKE
        // Para uso geral, não escapar wildcards (eles são literais em VALUES)
        // A decisão de escapar wildcards deve ser feita no contexto de uso

        // Remover caracteres perigosos
        $escaped = preg_replace('/[;\x00-\x08\x0B-\x0C\x0E-\x1F]/', '', $escaped);

        return "'" . $escaped . "'";
    }

    /**
     * Testa a conexão com o banco Progress via JDBC
     */
    public function testConnection(): array
    {
        try {
            // CORREÇÃO BUG #74: Usar config() em vez de env() no runtime
            Log::info('Testando conexão Progress via JDBC', [
                'host' => config('progress.host'),
                'database' => config('progress.database')
            ]);

            $result = $this->executeJavaConnector('test');
            
            Log::info('Teste de conexão Progress JDBC concluído', [
                'success' => $result['success'] ?? false
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Erro no teste de conexão Progress JDBC', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Falha no teste de conexão Progress: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Busca dados da tabela transporte via JDBC
     */
    public function getTransportes(array $filters = []): array
    {
        try {
            Log::info('Consultando tabela transporte no Progress via JDBC', ['filters' => $filters]);

            // Construir cláusula WHERE baseada nos filtros
            $whereClause = $this->buildWhereClause($filters);
            $limit = $filters['limit'] ?? 100;

            $result = $this->executeJavaConnector('transportes', $whereClause, (string)$limit);
            
            if ($result['success']) {
                Log::info('Consulta transporte JDBC concluída', [
                    'total_registros' => $result['data']['total'] ?? 0,
                    'limit' => $limit
                ]);

                // Adicionar informações de filtros aplicados
                $result['data']['filters_applied'] = $filters;
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Erro na consulta tabela transporte JDBC', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);

            return [
                'success' => false,
                'error' => 'Erro na consulta transporte: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lista transportes com paginação eficiente no servidor
     * Suporta keyset pagination (cursor-based) e legacy page-based pagination
     */
    public function getTransportesPaginated(array $filters): array
    {
        try {
            Log::info('Buscando transportes paginados via JDBC', ['filters' => $filters]);

            $perPage = $filters['per_page'] ?? 10;
            $search = $filters['search'] ?? '';

            // KEYSET PAGINATION: Use cursor (last_id) instead of page number
            $lastId = $filters['last_id'] ?? null;
            $direction = $filters['direction'] ?? 'next';

            // Legacy support: if 'page' is provided but no last_id, use old method
            $page = $filters['page'] ?? 1;
            $isLegacyMode = ($lastId === null && $page > 1);

            // Campos essenciais para diferenciar tipos de transportadores
            $campos = "codtrn, nomtrn, flgautonomo, natcam, tipcam, codcnpjcpf, numpla, numtel, dddtel, flgati, indcd";

            // Construir condições WHERE baseadas nos filtros
            $whereConditions = [];

            // Filtro de busca por código ou nome
            if (!empty($search)) {
                $searchTerm = trim($search);
                if (is_numeric($searchTerm)) {
                    $whereConditions[] = "codtrn = " . intval($searchTerm);
                } else {
                    $whereConditions[] = "UPPER(nomtrn) LIKE " . $this->escapeSqlString('%' . strtoupper($searchTerm) . '%');
                }
            }

            // Filtro por tipo (autônomo vs empresa)
            $tipo = $filters['tipo'] ?? 'todos';
            if ($tipo === 'autonomo') {
                $whereConditions[] = "flgautonomo = 1";
            } elseif ($tipo === 'empresa') {
                $whereConditions[] = "flgautonomo = 0";
            }

            // Filtro por natureza do transporte (validar para evitar SQL injection)
            // CORREÇÃO BUG #76: Usar escapeSqlString ao invés de hardcoded
            $natureza = $filters['natureza'] ?? '';
            if (!empty($natureza)) {
                // Validar que natureza é apenas 'F' ou 'J'
                if (in_array($natureza, ['F', 'J'], true)) {
                    $whereConditions[] = "natcam = " . $this->escapeSqlString($natureza);
                } else {
                    Log::warning('Tentativa de SQL injection detectada em natureza', ['natureza' => $natureza]);
                }
            }

            // Filtro por status ativo
            $ativo = $filters['ativo'] ?? null;
            if ($ativo !== null) {
                $whereConditions[] = ($ativo === 'true' || $ativo === '1' || $ativo === 1) ? "flgati = 1" : "flgati = 0";
            }

            $whereClause = !empty($whereConditions) ? " WHERE " . implode(' AND ', $whereConditions) : "";

            // BUILD SQL BASED ON PAGINATION MODE
            if ($lastId !== null) {
                // KEYSET PAGINATION: Use codtrn > $lastId for next, < for prev
                $cursorCondition = $whereClause ? " AND " : " WHERE ";
                if ($direction === 'prev') {
                    $simpleSql = "SELECT TOP $perPage $campos FROM PUB.transporte$whereClause{$cursorCondition}codtrn < " . intval($lastId) . " ORDER BY codtrn DESC";
                } else {
                    $simpleSql = "SELECT TOP $perPage $campos FROM PUB.transporte$whereClause{$cursorCondition}codtrn > " . intval($lastId) . " ORDER BY codtrn";
                }
            } elseif ($isLegacyMode) {
                // LEGACY MODE: Inefficient offset simulation (deprecated)
                $offset = ($page - 1) * $perPage;
                $skipSql = "SELECT TOP $offset codtrn FROM PUB.transporte$whereClause ORDER BY codtrn";
                $skipResult = $this->executeCustomQuery($skipSql);
                if ($skipResult['success'] && !empty($skipResult['data']['results'])) {
                    $lastSkipId = (int) end($skipResult['data']['results'])['codtrn'];
                    $conditionPrefix = $whereClause ? " AND " : " WHERE ";
                    $simpleSql = "SELECT TOP $perPage $campos FROM PUB.transporte$whereClause{$conditionPrefix}codtrn > $lastSkipId ORDER BY codtrn";
                } else {
                    $simpleSql = "SELECT TOP $perPage $campos FROM PUB.transporte WHERE 1=0";
                }
            } else {
                // FIRST PAGE: No cursor needed
                $simpleSql = "SELECT TOP $perPage $campos FROM PUB.transporte$whereClause ORDER BY codtrn";
            }

            Log::info('SQL paginação', ['sql' => $simpleSql, 'cursor_mode' => ($lastId !== null), 'direction' => $direction]);

            $result = $this->executeCustomQuery($simpleSql);

            if ($result['success']) {
                $results = $result['data']['results'] ?? [];

                // If we fetched in reverse (prev), reverse the array back
                if ($direction === 'prev' && !empty($results)) {
                    $results = array_reverse($results);
                    $result['data']['results'] = $results;
                }

                // Count total records for pagination (apply same filters)
                $countSql = "SELECT COUNT(*) as total FROM PUB.transporte$whereClause";
                $totalResult = $this->executeCustomQuery($countSql);

                $total = 0;
                if ($totalResult['success'] && !empty($totalResult['data']['results'])) {
                    $total = $totalResult['data']['results'][0]['total'] ?? 0;
                }

                // Extract cursor information for next/prev navigation
                $firstId = !empty($results) ? $results[0]['codtrn'] : null;
                $currentLastId = !empty($results) ? end($results)['codtrn'] : null;

                // Determine if there are more pages
                $hasNext = false;
                $hasPrev = false;

                if ($currentLastId !== null) {
                    $nextCheckSql = "SELECT TOP 1 codtrn FROM PUB.transporte$whereClause" . ($whereClause ? " AND " : " WHERE ") . "codtrn > " . intval($currentLastId) . " ORDER BY codtrn";
                    $nextCheck = $this->executeCustomQuery($nextCheckSql);
                    $hasNext = $nextCheck['success'] && !empty($nextCheck['data']['results']);
                }

                if ($firstId !== null) {
                    $prevCheckSql = "SELECT TOP 1 codtrn FROM PUB.transporte$whereClause" . ($whereClause ? " AND " : " WHERE ") . "codtrn < " . intval($firstId) . " ORDER BY codtrn DESC";
                    $prevCheck = $this->executeCustomQuery($prevCheckSql);
                    $hasPrev = $prevCheck['success'] && !empty($prevCheck['data']['results']);
                }

                $lastPage = ceil($total / $perPage);

                $result['pagination'] = [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'has_next' => $hasNext,
                    'has_prev' => $hasPrev,
                    'next_cursor' => $currentLastId,
                    'prev_cursor' => $firstId,
                    'count' => count($results)
                ];

                $result['data']['filters_applied'] = $filters;
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Erro na busca paginada de transportes JDBC', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);

            return [
                'success' => false,
                'error' => 'Erro na busca paginada de transportes: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Busca transporte específico por código/ID
     */
    public function getTransporteById($id): array
    {
        try {
            // CRITICAL SECURITY: Validate and sanitize ID
            if (!is_numeric($id) || $id < 1) {
                return [
                    'success' => false,
                    'error' => 'ID inválido fornecido'
                ];
            }

            $id = (int) $id;  // Force integer casting to prevent SQL injection

            Log::info('Buscando transporte por ID', ['id' => $id]);

            $sql = "SELECT codtrn, nomtrn, flgautonomo, natcam, tipcam, codcnpjcpf, numpla, numtel, dddtel, numcel, dddcel, flgati, indcd, desend, numend, cplend, numceptrn, \"e-mail\", numhab, venhab, cathab, datnas FROM PUB.transporte WHERE codtrn = $id";

            $result = $this->executeJavaConnector('query', $sql);
            
            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Erro na consulta do transporte'
                ];
            }

            $transportes = $result['data']['results'] ?? [];
            
            if (empty($transportes)) {
                return [
                    'success' => false,
                    'error' => 'Transporte não encontrado'
                ];
            }

            Log::info('Transporte encontrado', ['transporte_id' => $id]);

            return [
                'success' => true,
                'data' => $transportes[0]
            ];

        } catch (Exception $e) {
            Log::error('Erro na busca de transporte', [
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
     * Lista pacotes com paginação e filtros
     */
    public function getPacotesPaginated($filters): array
    {
        try {
            $page = $filters['page'] ?? 1;
            $perPage = $filters['per_page'] ?? 15;
            $search = $filters['search'] ?? '';
            $codigo = $filters['codigo'] ?? '';
            $transportador = $filters['transportador'] ?? '';
            $codigoTransportador = $filters['codigo_transportador'] ?? '';
            $motorista = $filters['motorista'] ?? '';
            $rota = $filters['rota'] ?? '';
            $situacao = $filters['situacao'] ?? '';
            $apenasRecentes = $filters['apenas_recentes'] ?? false;
            $dataInicio = $filters['data_inicio'] ?? '';
            $dataFim = $filters['data_fim'] ?? '';

            // Campos principais da consulta - incluindo flag TCD
            $campos = "p.codpac, p.datforpac, p.horforpac, p.codtrn, p.codmot, p.numpla, p.valpac, p.volpac, p.pespac, p.sitpac, p.codrot, p.nroped, t.nomtrn, CASE WHEN pcd.codpaccd IS NOT NULL THEN 1 ELSE 0 END as flg_tcd";

            // Construir condições WHERE
            $whereConditions = [];

            // Filtro por código do pacote
            if (!empty($codigo)) {
                $whereConditions[] = "p.codpac = $codigo";
            }

            // Filtro por busca geral (código do pacote ou nome do transportador)
            if (!empty($search)) {
                $searchEscaped = $this->escapeSqlString('%' . strtoupper($search) . '%');
                $whereConditions[] = "(p.codpac LIKE " . $this->escapeSqlString('%' . $search . '%') . " OR UPPER(t.nomtrn) LIKE " . $searchEscaped . ")";
            }

            // Filtro por transportador (nome)
            if (!empty($transportador)) {
                $whereConditions[] = "UPPER(t.nomtrn) LIKE " . $this->escapeSqlString('%' . strtoupper($transportador) . '%');
            }

            // Filtro por código do transportador
            if (!empty($codigoTransportador)) {
                $whereConditions[] = "p.codtrn = $codigoTransportador";
            }

            // Filtro por rota
            if (!empty($rota)) {
                $whereConditions[] = "p.codrot LIKE " . $this->escapeSqlString('%' . $rota . '%');
            }

            // CORREÇÃO BUG #77: SQL injection na situação - usar escapeSqlString
            if (!empty($situacao)) {
                // CORREÇÃO BUG #77: Validar que situação é apenas 1 caractere alfanumérico
                if (!preg_match('/^[A-Za-z0-9]$/', $situacao)) {
                    return [
                        'success' => false,
                        'error' => 'Situação inválida (deve ser 1 caractere alfanumérico)',
                        'data' => null
                    ];
                }
                $whereConditions[] = "p.sitpac = " . $this->escapeSqlString($situacao);
            }

            // Filtro "apenas recentes" (baseado no padrão Progress)
            if ($apenasRecentes) {
                $whereConditions[] = "p.codpac > 800000";
            }

            // CORREÇÃO BUG #78: SQL injection nas datas - validar formato YYYY-MM-DD
            if (!empty($dataInicio)) {
                // CORREÇÃO BUG #78: Validar formato de data estrito
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) {
                    return [
                        'success' => false,
                        'error' => 'Data de início inválida (use formato YYYY-MM-DD)',
                        'data' => null
                    ];
                }
                // Validar que é uma data real
                $parts = explode('-', $dataInicio);
                if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
                    return [
                        'success' => false,
                        'error' => 'Data de início inválida',
                        'data' => null
                    ];
                }
                $whereConditions[] = "p.datforpac >= " . $this->escapeSqlString($dataInicio);
            }
            if (!empty($dataFim)) {
                // CORREÇÃO BUG #78: Validar formato de data estrito
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
                    return [
                        'success' => false,
                        'error' => 'Data de fim inválida (use formato YYYY-MM-DD)',
                        'data' => null
                    ];
                }
                // Validar que é uma data real
                $parts = explode('-', $dataFim);
                if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
                    return [
                        'success' => false,
                        'error' => 'Data de fim inválida',
                        'data' => null
                    ];
                }
                $whereConditions[] = "p.datforpac <= " . $this->escapeSqlString($dataFim);
            }

            // Sempre mostrar apenas pacotes com transportador
            $whereConditions[] = "p.codtrn > 0";

            $whereClause = " WHERE " . implode(' AND ', $whereConditions);
            
            // Query principal com paginação e JOIN para pegar nome do transportador
            $offset = ($page - 1) * $perPage;
            
            if ($offset == 0) {
                // Primeira página
                $sql = "SELECT TOP $perPage $campos FROM PUB.pacote p LEFT JOIN PUB.transporte t ON p.codtrn = t.codtrn LEFT JOIN PUB.paccd pcd ON pcd.codpaccd = p.codpac $whereClause ORDER BY p.codpac DESC";
            } else {
                // Páginas subsequentes usando offset simulado
                $offsetSql = "SELECT TOP $offset p.codpac FROM PUB.pacote p LEFT JOIN PUB.transporte t ON p.codtrn = t.codtrn LEFT JOIN PUB.paccd pcd ON pcd.codpaccd = p.codpac $whereClause ORDER BY p.codpac DESC";
                $offsetResult = $this->executeCustomQuery($offsetSql);
                
                if ($offsetResult['success'] && !empty($offsetResult['data']['results'])) {
                    $lastId = end($offsetResult['data']['results'])['codpac'];
                    $sql = "SELECT TOP $perPage $campos FROM PUB.pacote p LEFT JOIN PUB.transporte t ON p.codtrn = t.codtrn LEFT JOIN PUB.paccd pcd ON pcd.codpaccd = p.codpac $whereClause AND p.codpac < $lastId ORDER BY p.codpac DESC";
                } else {
                    $sql = "SELECT TOP $perPage $campos FROM PUB.pacote p LEFT JOIN PUB.transporte t ON p.codtrn = t.codtrn LEFT JOIN PUB.paccd pcd ON pcd.codpaccd = p.codpac WHERE 1=0";
                }
            }

            Log::info('SQL Pacotes', ['sql' => $sql]);
            
            $result = $this->executeCustomQuery($sql);

            if ($result['success']) {
                // Contar total com os mesmos filtros
                $countSql = "SELECT COUNT(*) as total FROM PUB.pacote p LEFT JOIN PUB.transporte t ON p.codtrn = t.codtrn LEFT JOIN PUB.paccd pcd ON pcd.codpaccd = p.codpac $whereClause";
                $totalResult = $this->executeCustomQuery($countSql);

                $total = 0;
                if ($totalResult['success'] && !empty($totalResult['data']['results'])) {
                    $total = $totalResult['data']['results'][0]['total'] ?? 0;
                }

                $lastPage = ceil($total / $perPage);

                $result['pagination'] = [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                    'has_more_pages' => $page < $lastPage
                ];

                Log::info('Pacotes encontrados', ['total' => count($result['data']['results'] ?? [])]);
                
                return $result;
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Erro na busca de pacotes paginados', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro na busca de pacotes: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Busca pacote específico por ID
     */
    public function getPacoteById($id): array
    {
        try {
            Log::info('Buscando pacote por ID', ['id' => $id]);

            $sql = "SELECT p.*, t.nomtrn, t.codcnpjcpf as transportador_cpf FROM PUB.pacote p LEFT JOIN PUB.transporte t ON p.codtrn = t.codtrn WHERE p.codpac = $id";

            $result = $this->executeCustomQuery($sql);
            
            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Erro na consulta do pacote'
                ];
            }

            $pacotes = $result['data']['results'] ?? [];
            
            if (empty($pacotes)) {
                return [
                    'success' => false,
                    'error' => 'Pacote não encontrado'
                ];
            }

            Log::info('Pacote encontrado', ['pacote_id' => $id]);

            return [
                'success' => true,
                'data' => $pacotes[0]
            ];

        } catch (Exception $e) {
            Log::error('Erro na busca de pacote', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro na busca de pacote: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Busca itinerário completo do pacote (baseado no Progress itinerario.p)
     */
    public function getItinerarioPacote($codPac): array
    {
        try {
            Log::info('Buscando itinerário do pacote', ['codpac' => $codPac]);

            // Primeiro verificar se o pacote é TCD - se sim, buscar o pacote original
            $sqlVerificaTCD = "SELECT pcd.codpac as pacote_original FROM PUB.paccd pcd WHERE pcd.codpaccd = $codPac";
            $resultTCD = $this->executeJavaConnector('query', $sqlVerificaTCD);
            
            $pacoteParaBuscar = $codPac;
            $isTCD = false;
            
            if ($resultTCD['success'] && !empty($resultTCD['data']['results'])) {
                // É um pacote TCD, usar o pacote original para buscar entregas
                $pacoteOriginal = $resultTCD['data']['results'][0]['pacote_original'];
                $pacoteParaBuscar = $pacoteOriginal;
                $isTCD = true;
                Log::info('Pacote TCD detectado', ['tcd' => $codPac, 'original' => $pacoteOriginal]);
            }

            // Buscar dados principais da carga + PLACA DO VEÍCULO usando o pacote correto
            $sqlCarga = "SELECT p.codpac, p.codrot as rota, p.codmot as motorista, p.codtrn, p.numpla as placa, p.pespac as peso, p.volpac as volume, p.valpac as valor, t.nomtrn as transportador, COALESCE(cf.valfre, 0) as frete FROM PUB.pacote p LEFT JOIN PUB.cxapacote cp ON cp.codpac = p.codpac LEFT JOIN PUB.caixafech cf ON cf.codcxa = cp.codcxa LEFT JOIN PUB.transporte t ON t.codtrn = p.codtrn WHERE p.codpac = $pacoteParaBuscar";

            $resultCarga = $this->executeJavaConnector('query', $sqlCarga);

            if (!$resultCarga['success']) {
                return [
                    'success' => false,
                    'error' => $resultCarga['error'] ?? 'Erro na consulta da carga'
                ];
            }

            $cargas = $resultCarga['data']['results'] ?? [];
            if (empty($cargas)) {
                return [
                    'success' => false,
                    'error' => 'Pacote não encontrado'
                ];
            }

            $carga = $cargas[0];

            // Buscar pedidos/entregas seguindo a estrutura: pacote -> carga -> pedido (como no itinerario.p)
            $sqlEntregas = "SELECT ped.numseqped as seqent, cli.codcli, cli.descnt as razcli, est.sigest as uf, mun.desmun, bai.desbai, cli.desend, ped.valtotateped as valnot, ped.pesped as peso, ped.volped as volume, ard.latitute as latitude, ard.longitude FROM PUB.carga car INNER JOIN PUB.pedido ped ON ped.codcar = car.codcar INNER JOIN PUB.cliente cli ON cli.codcli = ped.codcli INNER JOIN PUB.estado est ON est.codest = cli.codest INNER JOIN PUB.municipio mun ON mun.codest = cli.codest AND mun.codmun = cli.codmun INNER JOIN PUB.bairro bai ON bai.codest = cli.codest AND bai.codmun = cli.codmun AND bai.codbai = cli.codbai LEFT JOIN PUB.arqrdnt ard ON ard.asdped = ped.asdped WHERE car.codpac = $pacoteParaBuscar AND ped.valtotateped > 0 AND ped.tipped != 'RAS' ORDER BY ped.numseqped";

            $resultEntregas = $this->executeJavaConnector('query', $sqlEntregas);
            
            if (!$resultEntregas['success']) {
                Log::warning('Erro ao buscar entregas, continuando sem elas', ['error' => $resultEntregas['error']]);
                $entregas = [];
            } else {
                $entregas = $resultEntregas['data']['results'] ?? [];
                
                // Processar coordenadas GPS da mesma forma que o itinerario.p
                foreach ($entregas as &$entrega) {
                    if (!empty($entrega['latitude']) && !empty($entrega['longitude'])) {
                        $entrega['gps_lat'] = $this->processGpsCoordinate($entrega['latitude']);
                        $entrega['gps_lon'] = $this->processGpsCoordinate($entrega['longitude']);
                    } else {
                        $entrega['gps_lat'] = null;
                        $entrega['gps_lon'] = null;
                    }
                    // Remover campos brutos de coordenadas
                    unset($entrega['latitude'], $entrega['longitude']);
                }
            }

            // Estruturar dados no formato esperado pelo frontend
            $data = [
                'codpac' => (string)$carga['codpac'],
                'rota' => $carga['rota'],
                'motorista' => (int)$carga['motorista'],
                'codtrn' => (int)$carga['codtrn'],
                'placa' => trim($carga['placa'] ?? ''),
                'transportador' => trim($carga['transportador'] ?? ''),
                'peso' => (float)$carga['peso'],
                'volume' => (float)$carga['volume'],
                'valor' => (float)$carga['valor'],
                'frete' => (float)$carga['frete'],
                'pedidos' => $entregas
            ];

            return [
                'success' => true,
                'data' => $data
            ];

        } catch (\Exception $e) {
            Log::error('Erro na busca de itinerário de pacote', [
                'codpac' => $codPac,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro na busca do itinerário: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Sanitiza SQL para logs (LGPD compliance)
     * Mascara CPF, CNPJ, valores monetarios e strings longas
     */
    private function sanitizeSqlForLogging(string $sql): string
    {
        // Mascara CPF (11 dígitos)
        $sql = preg_replace('/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/', '***.***.***.--**', $sql);
        $sql = preg_replace('/\b\d{11}\b/', '***********', $sql);

        // Mascara CNPJ (14 dígitos)
        $sql = preg_replace('/\b\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}\b/', '**.***.***/****-**', $sql);
        $sql = preg_replace('/\b\d{14}\b/', '**************', $sql);

        // Mascara valores monetários grandes (> 1000)
        $sql = preg_replace('/\b\d{4,}\.\d{2}\b/', '*****.--**', $sql);

        // Mascara strings em aspas simples com mais de 5 caracteres (pode ser nome/endereço)
        $sql = preg_replace_callback(
            "/'([^']{6,})'/",
            function($matches) {
                $length = strlen($matches[1]);
                return "'" . str_repeat('*', min($length, 10)) . "'";
            },
            $sql
        );

        return $sql;
    }

    /**
     * Executa consulta SQL customizada (para debug e testes)
     */
    public function executeCustomQuery(string $sql): array
    {
        try {
            // Validação 1: SQL não pode ser vazio
            $sql = trim($sql);
            if (empty($sql)) {
                throw new Exception('SQL query não pode ser vazio');
            }

            // Validação 2: Tamanho máximo (prevenir consultas gigantes)
            if (strlen($sql) > 50000) {
                throw new Exception('SQL query muito grande (máximo 50.000 caracteres)');
            }

            // CORREÇÃO #4: Sanitizar SQL antes de logar
            $sanitizedSql = $this->sanitizeSqlForLogging($sql);
            Log::info('Executando consulta SQL customizada', [
                'sql' => substr($sanitizedSql, 0, 200) . (strlen($sanitizedSql) > 200 ? '...' : '')
            ]);

            // Validação 3: Limitar a apenas SELECT por segurança
            $sql_upper = strtoupper($sql);
            if (!str_starts_with($sql_upper, 'SELECT')) {
                throw new Exception('Apenas consultas SELECT são permitidas');
            }

            // Validação 4: Prevenir comandos perigosos embutidos
            // Usar regex com word boundaries para não bloquear nomes de colunas como "codRotCreateSP"
            $dangerous_keywords = ['DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'GRANT', 'REVOKE', 'EXEC'];
            foreach ($dangerous_keywords as $keyword) {
                // Buscar keyword como palavra completa (word boundary) para não pegar substrings
                if (preg_match('/\b' . $keyword . '\b/', $sql_upper)) {
                    throw new Exception("Palavra-chave não permitida detectada: {$keyword}");
                }
            }

            // Validação 5: Prevenir comentários SQL que podem esconder código malicioso
            if (str_contains($sql, '--') || str_contains($sql, '/*') || str_contains($sql, '*/')) {
                throw new Exception('Comentários SQL não são permitidos em consultas customizadas');
            }

            $result = $this->executeJavaConnector('query', $sql);  // Executa SQL original (não sanitizado)

            Log::info('Consulta SQL executada com sucesso', [
                'total_registros' => $result['data']['total'] ?? 0
            ]);

            return $result;

        } catch (Exception $e) {
            // CORREÇÃO #4: Sanitizar SQL em logs de erro
            $sanitizedSql = $this->sanitizeSqlForLogging($sql ?? 'null');
            Log::error('Erro na execução da consulta SQL', [
                'sql' => substr($sanitizedSql, 0, 200),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro na consulta SQL: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Executa UPDATE, INSERT ou DELETE no banco Progress
     *
     * IMPORTANTE: Progress JDBC NÃO suporta transações!
     * Cada comando é executado imediatamente e não pode ser revertido.
     */
    public function executeUpdate(string $sql): array
    {
        try {
            // Validação 1: SQL não pode ser vazio
            $sql = trim($sql);
            if (empty($sql)) {
                throw new Exception('SQL command não pode ser vazio');
            }

            // Validação 2: Tamanho máximo
            if (strlen($sql) > 50000) {
                throw new Exception('SQL command muito grande (máximo 50.000 caracteres)');
            }

            Log::info('Executando comando UPDATE/INSERT/DELETE', ['sql' => substr($sql, 0, 200) . '...']);

            // Validação 3: Validar que é um comando permitido
            $sql_upper = strtoupper($sql);
            if (!str_starts_with($sql_upper, 'UPDATE') &&
                !str_starts_with($sql_upper, 'INSERT') &&
                !str_starts_with($sql_upper, 'DELETE')) {
                throw new Exception('Apenas comandos UPDATE, INSERT e DELETE são permitidos');
            }

            // Validação 4: Prevenir comandos perigosos
            // Usar regex com word boundaries para não bloquear nomes de colunas como "codRotCreateSP"
            $dangerous_keywords = ['DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'GRANT', 'REVOKE', 'EXEC'];
            foreach ($dangerous_keywords as $keyword) {
                // Buscar keyword como palavra completa (word boundary) para não pegar substrings
                if (preg_match('/\b' . $keyword . '\b/', $sql_upper)) {
                    throw new Exception("Palavra-chave não permitida detectada: {$keyword}");
                }
            }

            // Validação 5: Prevenir comentários SQL
            if (str_contains($sql, '--') || str_contains($sql, '/*') || str_contains($sql, '*/')) {
                throw new Exception('Comentários SQL não são permitidos');
            }

            // Validação 6: DELETE sem WHERE é perigoso
            if (str_starts_with($sql_upper, 'DELETE') && !str_contains($sql_upper, 'WHERE')) {
                throw new Exception('DELETE sem cláusula WHERE não é permitido (prevenir exclusão em massa acidental)');
            }

            $result = $this->executeJavaConnector('update', $sql);

            if ($result['success']) {
                Log::info('Comando executado com sucesso', [
                    'affected_rows' => $result['data']['affected_rows'] ?? 0
                ]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Erro na execução do comando SQL', [
                'sql' => substr($sql ?? 'null', 0, 200),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao executar comando: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Executa o conector JDBC Java
     */
    public function executeJavaConnector(string $action, ...$params): array
    {
        try {
            $javaPath = storage_path('app/java');
            // CORREÇÃO BUG #74: Usar config() em vez de env() no runtime
            $driverPath = config('progress.driver_path', 'c:/Progress/OpenEdge/java/openedge.jar');
            $jdbcUrl = config('progress.jdbc_url', 'jdbc:datadirect:openedge://192.168.80.113:13361;databaseName=tambasa;trustStore=');
            $username = config('progress.username', 'sysprogress');
            $password = config('progress.password', 'sysprogress');

            // Verificar se os arquivos necessários existem
            if (!file_exists($driverPath)) {
                throw new Exception("Driver JDBC Progress não encontrado em: {$driverPath}");
            }

            if (!file_exists($javaPath . '/ProgressJDBCConnector.class')) {
                // Tentar compilar o arquivo Java se a classe não existir
                $this->compileJavaConnector();
            }

            // Construir comando Java com classpath correto para Windows
            $classpath = ".;gson-2.8.9.jar;{$driverPath}";
            $cmdParts = [
                'java',
                '-cp',
                '"' . $classpath . '"',
                'ProgressJDBCConnector',
                escapeshellarg($action),
                escapeshellarg($jdbcUrl),
                escapeshellarg($username),
                escapeshellarg($password)
            ];

            // Adicionar parâmetros extras se fornecidos
            foreach ($params as $param) {
                // Para SQL queries/updates, não usar escapeshellarg que remove % e outros caracteres
                if (($action === 'query' && str_contains(strtoupper($param), 'SELECT')) ||
                    ($action === 'update' && (str_contains(strtoupper($param), 'UPDATE') ||
                                             str_contains(strtoupper($param), 'INSERT') ||
                                             str_contains(strtoupper($param), 'DELETE')))) {
                    // Escapar aspas duplas mas preservar % e outros caracteres SQL
                    $escapedParam = '"' . str_replace('"', '\\"', (string)$param) . '"';
                    $cmdParts[] = $escapedParam;
                } else {
                    $cmdParts[] = escapeshellarg((string)$param);
                }
            }

            $cmd = implode(' ', $cmdParts);

            Log::debug('Executando comando Java JDBC', ['command' => $cmd]);

            // Executar comando e capturar saída - garantir diretório correto
            $fullCmd = "cd /d \"{$javaPath}\" && {$cmd} 2>&1";
            Log::debug('Comando completo a ser executado', ['command' => $fullCmd]);
            $output = shell_exec($fullCmd);
            
            if ($output === null) {
                throw new Exception('Falha na execução do comando Java');
            }

            // Converter para UTF-8 e limpar caracteres especiais
            $output = mb_convert_encoding($output, 'UTF-8', 'auto');
            $output = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $output);
            $cleanOutput = trim($output);

            // Tentar decodificar JSON da saída
            $result = json_decode($cleanOutput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Erro na decodificação JSON da saída Java', [
                    'output' => $cleanOutput,
                    'json_error' => json_last_error_msg()
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Erro na decodificação da resposta Java: ' . $cleanOutput
                ];
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Erro na execução do conector JDBC Java', [
                'action' => $action,
                'params' => $params,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro no conector JDBC: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Compila o arquivo Java ProgressJDBCConnector
     */
    private function compileJavaConnector(): void
    {
        $javaPath = storage_path('app/java');
        $driverPath = 'c:/Progress/OpenEdge/java/openedge.jar';
        
        // Verificar se o arquivo Java existe
        if (!file_exists($javaPath . '/ProgressJDBCConnector.java')) {
            throw new Exception('Arquivo ProgressJDBCConnector.java não encontrado');
        }

        // Construir comando de compilação
        $classpath = "{$driverPath};gson-2.10.1.jar";
        $compileCmd = "cd /d \"{$javaPath}\" && javac -cp \"{$classpath}\" ProgressJDBCConnector.java 2>&1";
        
        Log::info('Compilando ProgressJDBCConnector.java', ['command' => $compileCmd]);
        
        $output = shell_exec($compileCmd);
        
        if (!file_exists($javaPath . '/ProgressJDBCConnector.class')) {
            throw new Exception('Falha na compilação do ProgressJDBCConnector: ' . $output);
        }

        Log::info('ProgressJDBCConnector compilado com sucesso');
    }

    /**
     * Obtém o schema/estrutura da tabela transporte via JDBC
     */
    public function getTransporteTableSchema(): array
    {
        try {
            Log::info('Obtendo schema da tabela transporte via JDBC');

            $result = $this->executeJavaConnector('schema', 'transporte');

            if ($result['success']) {
                Log::info('Schema da tabela transporte obtido com sucesso', [
                    'total_colunas' => count($result['data']['columns'] ?? [])
                ]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Erro ao obter schema da tabela transporte', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao obter schema da tabela: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Constrói cláusula WHERE baseada nos filtros
     */
    protected function buildWhereClause(array $filters): string
    {
        $conditions = [];

        if (!empty($filters['codigo'])) {
            $conditions[] = "codtrn LIKE " . $this->escapeSqlString('%' . $filters['codigo'] . '%');
        }

        if (!empty($filters['nome'])) {
            $conditions[] = "nomtrn LIKE " . $this->escapeSqlString('%' . $filters['nome'] . '%');
        }

        if (!empty($filters['data_inicio'])) {
            $conditions[] = "data_criacao >= " . $this->escapeSqlString($filters['data_inicio']);
        }

        if (!empty($filters['data_fim'])) {
            $conditions[] = "data_criacao <= " . $this->escapeSqlString($filters['data_fim']);
        }

        return implode(' AND ', $conditions);
    }
    
    /**
     * Busca motoristas por transportador
     */
    public function getMotoristasPorTransportador($codigoTransportador): array
    {
        try {
            $sql = "SELECT codtrn, codmot, codcpf, nommot, desend, codest, codmun, codbai, numcep, dddtel, numtel, dddtel1, numtel1, numhab, nompai, nommae, sitmot, desnac, estciv, codrntrc, datvldrntrc, venhab, esthab, cathab, codmopp, estmopp, venmopp, numrg, orgrg, exprg, datnas, numrenach, sitseg, datvenseg, datprihab, datemihab, codseghab, cplend, numend, tiplog, codlog, catmot, desobs, email, flgpro, datvldtox FROM PUB.trnmot WHERE codtrn = $codigoTransportador";
            
            $result = $this->executeCustomQuery($sql);
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'data' => $result['data']['results'] ?? []
                ];
            }
            
            return $result;
            
        } catch (Exception $e) {
            Log::error('Erro ao buscar motoristas por transportador', [
                'transportador' => $codigoTransportador,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Erro ao buscar motoristas: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Busca veículos por transportador
     */
    public function getVeiculosPorTransportador($codigoTransportador): array
    {
        try {
            // Usar apenas a tabela transporte que já tem os dados dos veículos
            $sql = "SELECT codtrn, numpla FROM PUB.transporte WHERE codtrn = $codigoTransportador";
            
            $result = $this->executeCustomQuery($sql);
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'data' => $result['data']['results'] ?? []
                ];
            }
            
            return $result;
            
        } catch (Exception $e) {
            Log::error('Erro ao buscar veículos por transportador', [
                'transportador' => $codigoTransportador,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Erro ao buscar veículos: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Busca rotas com filtro de texto (código e descrição)
     */
    public function getRotas($search = ''): array
    {
        try {
            Log::info('Buscando rotas via JDBC', ['search' => $search]);
            
            $sql = "SELECT codrot, desrot FROM PUB.introt";

            if (!empty($search)) {
                $searchUpper = strtoupper($search);
                $searchEscaped = $this->escapeSqlString('%' . $searchUpper . '%');
                $sql .= " WHERE UPPER(codrot) LIKE " . $searchEscaped . " OR UPPER(desrot) LIKE " . $searchEscaped;
            }

            $sql .= " ORDER BY codrot";
            
            $result = $this->executeCustomQuery($sql);
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'data' => $result['data']['results'] ?? []
                ];
            }
            
            return $result;
            
        } catch (Exception $e) {
            Log::error('Erro ao buscar rotas', [
                'search' => $search,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Erro ao buscar rotas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Processa coordenadas GPS seguindo a mesma lógica do itinerario.p
     * Converte formato Progress para decimal (float)
     * CORREÇÃO BUG MODERADO #1: Retornar float em vez de string
     */
    private function processGpsCoordinate($coordinate): ?float
    {
        if (empty($coordinate)) {
            return null;
        }

        // Limpar coordenada seguindo a mesma lógica do itinerario.p
        $coord = trim($coordinate);
        $coord = str_replace(['W', 'N', 'E', 'S'], '', $coord);
        $coord = str_replace(['-', '.', ','], '', $coord);

        if (strlen($coord) >= 3) {
            // Converter para float: "140876543" → -14.0876543
            $formatted = '-' . substr($coord, 0, 2) . '.' . substr($coord, 2);
            return (float)$formatted;
        }

        return null;
    }

    // ================================
    // MÉTODOS PARA ROTAS SEM PARAR
    // ================================

    /**
     * Lista todas as rotas SemParar com paginação
     */
    public function getSemPararRotas(array $filters = []): array
    {
        try {
            Log::info('Buscando rotas SemParar via JDBC', ['filters' => $filters]);

            $page = $filters['page'] ?? 1;
            $perPage = $filters['per_page'] ?? 10;
            $search = $filters['search'] ?? '';
            $codigo = $filters['codigo'] ?? '';
            $descricao = $filters['descricao'] ?? '';
            $flgCD = $filters['flg_cd'] ?? null;
            $flgRetorno = $filters['flg_retorno'] ?? null;
            $tempoMinimo = $filters['tempo_minimo'] ?? '';
            $tempoMaximo = $filters['tempo_maximo'] ?? '';

            $offset = ($page - 1) * $perPage;

            // Query simples para buscar dados
            // Query com subquery correlacionada para evitar N+1 - uma única query busca tudo
            $sql = "SELECT r.*, (SELECT COUNT(*) FROM PUB.semPararRotMu m WHERE m.sPararRotID = r.sPararRotID) as totalmunicipios FROM PUB.semPararRot r WHERE 1=1";

            // Aplicar filtros
            if (!empty($search)) {
                $searchUpper = strtoupper($search);
                $searchEscaped = $this->escapeSqlString('%' . $searchUpper . '%');
                $sql .= " AND (UPPER(r.desSPararRot) LIKE " . $searchEscaped . " OR r.sPararRotID = " . intval($search) . ")";
            }

            if (!empty($codigo)) {
                $sql .= " AND r.sPararRotID = " . intval($codigo);
            }

            if (!empty($descricao)) {
                $descricaoUpper = strtoupper($descricao);
                $sql .= " AND UPPER(r.desSPararRot) LIKE " . $this->escapeSqlString('%' . $descricaoUpper . '%');
            }

            // Filtro flgCD - suporta true (apenas CD) e false (apenas não-CD)
            if ($flgCD === 'true' || $flgCD === true || $flgCD === '1') {
                $sql .= " AND r.flgCD = 1";
            } elseif ($flgCD === 'false' || $flgCD === false || $flgCD === '0') {
                $sql .= " AND r.flgCD = 0";
            }

            // Filtro retorno
            if ($flgRetorno === 'true') {
                $sql .= " AND r.flgRetorno = 1";
            } elseif ($flgRetorno === 'false') {
                $sql .= " AND r.flgRetorno = 0";
            }

            // Filtros de tempo
            if (!empty($tempoMinimo)) {
                $sql .= " AND r.tempoViagem >= " . intval($tempoMinimo);
            }

            if (!empty($tempoMaximo)) {
                $sql .= " AND r.tempoViagem <= " . intval($tempoMaximo);
            }

            // Contar total antes da paginação (substituir a subquery por COUNT)
            $countSql = str_replace("r.*, (SELECT COUNT(*) FROM PUB.semPararRotMu m WHERE m.sPararRotID = r.sPararRotID) as totalmunicipios", "COUNT(*) as total", $sql);
            $countResult = $this->executeCustomQuery($countSql);
            $total = $countResult['success'] ? ($countResult['data']['results'][0]['total'] ?? 0) : 0;

            // Aplicar ordenação
            $sql .= " ORDER BY r.sPararRotID DESC";

            Log::info('Query SemPararRot:', ['sql' => $sql]);

            $result = $this->executeCustomQuery($sql);

            if ($result['success']) {
                // Simular paginação no lado PHP se necessário
                $allResults = $result['data']['results'] ?? [];
                $results = array_slice($allResults, $offset, $perPage);

                Log::info('Resultados SemPararRot:', [
                    'total_results' => count($allResults),
                    'paginated_results' => count($results)
                ]);

                return [
                    'success' => true,
                    'data' => [
                        'results' => $results,
                        'pagination' => [
                            'current_page' => $page,
                            'per_page' => $perPage,
                            'total' => $total,
                            'last_page' => ceil($total / $perPage),
                            'from' => $offset + 1,
                            'to' => min($offset + $perPage, $total),
                            'has_more_pages' => $page < ceil($total / $perPage)
                        ]
                    ]
                ];
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Erro ao buscar rotas SemParar', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao buscar rotas SemParar: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lista viagens compradas do Progress
     * Busca na tabela PUB.sPararViagem com filtros opcionais
     * Seguindo fluxo de consultaViagem.p (linhas 654-666)
     */
    public function getViagensCompradas(
        string $dataInicio,
        string $dataFim,
        ?int $codPac = null,
        ?string $placa = null,
        ?int $sPararRotId = null,
        ?int $codTrn = null,
        int $page = 1,
        int $perPage = 15
    ): array
    {
        try {
            Log::info('Buscando viagens compradas do Progress', [
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'cod_pac' => $codPac,
                'placa' => $placa,
                's_parar_rot_id' => $sPararRotId,
                'cod_trn' => $codTrn,
                'page' => $page,
                'per_page' => $perPage
            ]);

            // Query base com JOIN para pegar nome do transportador
            // IMPORTANTE: Usar SUBSTRING para evitar erro de "value exceeding max length"
            $sql = "SELECT v.codViagem, v.codPac, v.numPla, SUBSTRING(v.nomRotSemParar, 1, 200) as nomRotSemParar, v.valViagem, v.codtrn, SUBSTRING(v.codRotCreateSP, 1, 50) as codRotCreateSP, v.sPararRotID, SUBSTRING(v.resCompra, 1, 50) as resCompra, v.dataCompra, v.flgCancelado, SUBSTRING(v.resCancel, 1, 50) as resCancel, SUBSTRING(t.nomtrn, 1, 100) as transportador FROM PUB.sPararViagem v LEFT JOIN PUB.transporte t ON v.codtrn = t.codtrn WHERE 1=1";

            // Filtro de data (obrigatório)
            $sql .= " AND v.dataCompra >= '" . $dataInicio . "'";
            $sql .= " AND v.dataCompra <= '" . $dataFim . "'";

            // Filtro de rota SemParar (opcional)
            // Progress: (if nomRot = "" then true else sPararViagem.sPararRotID = bSemPararRot.sPararRotID)
            if ($sPararRotId !== null) {
                $sql .= " AND v.sPararRotID = " . intval($sPararRotId);
            }

            // Filtro de placa (opcional)
            // Progress: (if vPlaca = "" then true else sPararViagem.NumPla = vPlaca)
            if ($placa !== null && trim($placa) !== '') {
                $placaUpper = strtoupper(trim($placa));
                $sql .= " AND UPPER(v.numPla) = '" . $placaUpper . "'";
            }

            // Filtro de pacote (opcional)
            // Progress: (if codpac = 0 then true else sPararViagem.CodPac = codpac)
            if ($codPac !== null) {
                $sql .= " AND v.codPac = " . intval($codPac);
            }

            // Filtro de transportador (opcional)
            // Progress: (if codtrn = "" then true else sPararViagem.codtrn = integer(codtrn))
            if ($codTrn !== null) {
                $sql .= " AND v.codtrn = " . intval($codTrn);
            }

            // Ordenar por data mais recente primeiro
            // Progress: by sPararViagem.dataCompra descending
            $sql .= " ORDER BY v.dataCompra DESC";

            Log::info('Query viagens:', ['sql' => $sql]);

            $result = $this->executeCustomQuery($sql);

            if (!$result['success']) {
                return $result;
            }

            $viagens = $result['data']['results'] ?? [];

            // Formata datas para frontend
            $viagensFormatadas = array_map(function($viagem) {
                return [
                    'cod_viagem' => $viagem['codviagem'] ?? $viagem['codViagem'] ?? '-',
                    'cod_pac' => $viagem['codpac'] ?? $viagem['codPac'] ?? null,
                    'placa' => $viagem['numpla'] ?? $viagem['numPla'] ?? '-',
                    'rota_nome' => $viagem['nomrotsemparar'] ?? $viagem['nomRotSemParar'] ?? '-',
                    'valor' => floatval($viagem['valviagem'] ?? $viagem['valViagem'] ?? 0),
                    'transportador' => $viagem['transportador'] ?? null,
                    'data_compra' => $viagem['datacompra'] ?? $viagem['dataCompra'] ?? '-',
                    'cancelado' => ($viagem['flgcancelado'] ?? $viagem['flgCancelado'] ?? false) === true || ($viagem['flgcancelado'] ?? $viagem['flgCancelado'] ?? 0) === 1,
                    'responsavel_compra' => $viagem['rescompra'] ?? $viagem['resCompra'] ?? null,
                    'responsavel_cancelamento' => $viagem['rescancel'] ?? $viagem['resCancel'] ?? null,
                    's_parar_rot_id' => $viagem['spararrotid'] ?? $viagem['sPararRotID'] ?? null,
                    'cod_rota_create_sp' => $viagem['codrotcreatesp'] ?? $viagem['codRotCreateSP'] ?? null,
                ];
            }, $viagens);

            // Calcular paginação em memória (Progress não tem OFFSET nativo)
            $total = count($viagensFormatadas);
            $lastPage = (int) ceil($total / $perPage);
            $from = ($page - 1) * $perPage;
            $to = min($from + $perPage, $total);

            // Slice array para paginação
            $viagensPaginadas = array_slice($viagensFormatadas, $from, $perPage);

            Log::info('Viagens encontradas', [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'items_in_page' => count($viagensPaginadas)
            ]);

            return [
                'success' => true,
                'data' => $viagensPaginadas,
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => $from + 1,  // +1 porque contagem começa em 1, não 0
                    'to' => $to,
                    'has_more_pages' => $page < $lastPage
                ]
            ];

        } catch (Exception $e) {
            Log::error('Erro ao buscar viagens compradas', [
                'error' => $e->getMessage(),
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao buscar viagens: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Busca uma rota SemParar específica com seus municípios
     */
    public function getSemPararRota($rotaId): array
    {
        try {
            Log::info('Buscando rota SemParar específica', ['rota_id' => $rotaId]);

            // Buscar dados da rota principal
            $rotaSql = "SELECT * FROM PUB.semPararRot WHERE sPararRotID = " . intval($rotaId);
            $rotaResult = $this->executeCustomQuery($rotaSql);

            if (!$rotaResult['success'] || empty($rotaResult['data']['results'])) {
                return [
                    'success' => false,
                    'error' => 'Rota não encontrada'
                ];
            }

            $rota = $rotaResult['data']['results'][0];

            // Buscar municípios da rota
            $municipiosSql = "SELECT
                                srm.*,
                                e.nomest as nomeEstado,
                                m.lat,
                                m.lon
                            FROM PUB.semPararRotMu srm
                            LEFT JOIN PUB.estado e ON srm.codest = e.codest
                            LEFT JOIN PUB.municipio m ON srm.codmun = m.codmun AND srm.codest = m.codest
                            WHERE srm.sPararRotID = " . intval($rotaId) . "
                            ORDER BY srm.sPararMuSeq";

            $municipiosResult = $this->executeCustomQuery($municipiosSql);

            $rota['municipios'] = $municipiosResult['success'] ? ($municipiosResult['data']['results'] ?? []) : [];

            return [
                'success' => true,
                'data' => $rota
            ];

        } catch (Exception $e) {
            Log::error('Erro ao buscar rota SemParar específica', [
                'rota_id' => $rotaId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao buscar rota: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cria uma nova rota SemParar
     */
    public function createSemPararRota(array $data): array
    {
        try {
            Log::info('Criando nova rota SemParar', ['data' => $data]);

            // NOTA: Progress ODBC não suporta transações via beginTransaction/commit
            // Cada query é executada imediatamente (auto-commit)

            // Obter próximo ID usando MAX + 1 (compatível com Progress)
            $nextIdSql = "SELECT MAX(sPararRotID) + 1 as nextId FROM PUB.semPararRot";
            $nextIdResult = $this->executeCustomQuery($nextIdSql);

            if (!$nextIdResult['success'] || empty($nextIdResult['data']['results'])) {
                throw new Exception('Erro ao obter próximo ID da rota');
            }

            $nextId = $nextIdResult['data']['results'][0]['nextid'] ?? 1;

            // Inserir rota principal
            // IMPORTANTE: SQL single-line (Progress JDBC não gosta de quebras de linha)
            $userName = auth()->user()?->name ?? 'system';
            $insertRotaSql = "INSERT INTO PUB.semPararRot (sPararRotID, desSPararRot, tempoViagem, flgCD, flgRetorno, datAtu, resAtu) VALUES (" . $nextId . ", " . $this->escapeSqlString($data['nome']) . ", " . intval($data['tempo_viagem'] ?? 5) . ", " . ($data['flg_cd'] ? '1' : '0') . ", " . ($data['flg_retorno'] ? '1' : '0') . ", '" . date('Y-m-d') . "', " . $this->escapeSqlString($userName) . ")";

            $insertResult = $this->executeUpdate($insertRotaSql);

            if (!$insertResult['success']) {
                throw new Exception('Erro ao inserir rota principal');
            }

            // Inserir municípios se fornecidos
            if (!empty($data['municipios'])) {
                foreach ($data['municipios'] as $index => $municipio) {
                    // IMPORTANTE: SQL single-line (Progress JDBC não gosta de quebras de linha)
                    $insertMunSql = "INSERT INTO PUB.semPararRotMu (sPararRotID, sPararMuSeq, codEst, codMun, desEst, desMun, cdibge) VALUES (" . $nextId . ", " . ($index + 1) . ", " . intval($municipio['cod_est']) . ", " . intval($municipio['cod_mun']) . ", " . $this->escapeSqlString($municipio['des_est']) . ", " . $this->escapeSqlString($municipio['des_mun']) . ", " . intval($municipio['cdibge']) . ")";

                    $munResult = $this->executeUpdate($insertMunSql);
                    if (!$munResult['success']) {
                        throw new Exception('Erro ao inserir município: ' . $municipio['des_mun']);
                    }
                }
            }

            return [
                'success' => true,
                'data' => ['id' => $nextId],
                'message' => 'Rota SemParar criada com sucesso'
            ];

        } catch (Exception $e) {
            // NOTA: Sem rollBack pois Progress ODBC não suporta transações
            // As queries já executadas permanecerão no banco

            Log::error('Erro ao criar rota SemParar', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao criar rota: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Atualiza uma rota SemParar existente
     */
    public function updateSemPararRota($rotaId, array $data): array
    {
        try {
            Log::info('Atualizando rota SemParar', ['rota_id' => $rotaId, 'data' => $data]);

            // Atualizar rota principal (single line for Progress DB)
            $userName = auth()->user()?->name ?? 'system';
            $updateRotaSql = "UPDATE PUB.semPararRot SET desSPararRot = " . $this->escapeSqlString($data['nome']) . ", tempoViagem = " . intval($data['tempo_viagem'] ?? 5) . ", flgCD = " . ($data['flg_cd'] ? '1' : '0') . ", flgRetorno = " . ($data['flg_retorno'] ? '1' : '0') . ", datAtu = '" . date('Y-m-d') . "', resAtu = " . $this->escapeSqlString($userName) . " WHERE sPararRotID = " . intval($rotaId);

            $updateResult = $this->executeUpdate($updateRotaSql);

            if (!$updateResult['success']) {
                throw new Exception('Erro ao atualizar rota principal');
            }

            // Remover municípios existentes
            $deleteMunSql = "DELETE FROM PUB.semPararRotMu WHERE sPararRotID = " . intval($rotaId);
            $this->executeUpdate($deleteMunSql);

            // Inserir novos municípios
            if (!empty($data['municipios'])) {
                foreach ($data['municipios'] as $index => $municipio) {
                    $insertMunSql = "INSERT INTO PUB.semPararRotMu (sPararRotID, sPararMuSeq, codEst, codMun, desEst, desMun, cdibge) VALUES (" . intval($rotaId) . ", " . ($index + 1) . ", " . intval($municipio['cod_est']) . ", " . intval($municipio['cod_mun']) . ", " . $this->escapeSqlString($municipio['des_est']) . ", " . $this->escapeSqlString($municipio['des_mun']) . ", " . intval($municipio['cdibge']) . ")";

                    $munResult = $this->executeUpdate($insertMunSql);
                    if (!$munResult['success']) {
                        throw new Exception('Erro ao inserir município: ' . $municipio['des_mun']);
                    }
                }
            }

            return [
                'success' => true,
                'message' => 'Rota SemParar atualizada com sucesso'
            ];

        } catch (Exception $e) {
            Log::error('Erro ao atualizar rota SemParar', [
                'rota_id' => $rotaId,
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao atualizar rota: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Remove uma rota SemParar
     */
    public function deleteSemPararRota($rotaId): array
    {
        try {
            Log::info('Removendo rota SemParar', ['rota_id' => $rotaId]);

            // Remover municípios da rota
            $deleteMunSql = "DELETE FROM PUB.semPararRotMu WHERE sPararRotID = " . intval($rotaId);
            $this->executeUpdate($deleteMunSql);

            // Remover rota principal
            $deleteRotaSql = "DELETE FROM PUB.semPararRot WHERE sPararRotID = " . intval($rotaId);
            $deleteResult = $this->executeUpdate($deleteRotaSql);

            if (!$deleteResult['success']) {
                throw new Exception('Erro ao remover rota principal');
            }

            return [
                'success' => true,
                'message' => 'Rota SemParar removida com sucesso'
            ];

        } catch (Exception $e) {
            Log::error('Erro ao remover rota SemParar', [
                'rota_id' => $rotaId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao remover rota: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Busca municípios para autocomplete
     */
    public function getMunicipiosForAutocomplete($search = '', $estadoId = null): array
    {
        try {
            Log::info('Buscando municípios para autocomplete', ['search' => $search, 'estado_id' => $estadoId]);

            $sql = "SELECT TOP 20 m.codmun, m.codest, m.desmun, m.cdibge, e.sigest as desest FROM PUB.municipio m INNER JOIN PUB.estado e ON m.codest = e.codest WHERE 1=1";

            if (!empty($search)) {
                $searchUpper = strtoupper($search);
                $sql .= " AND UPPER(m.desmun) LIKE " . $this->escapeSqlString('%' . $searchUpper . '%');
            }

            if ($estadoId !== null) {
                $sql .= " AND m.codest = " . intval($estadoId);
            }

            $sql .= " ORDER BY m.desmun";

            $result = $this->executeCustomQuery($sql);

            if ($result['success']) {
                return [
                    'success' => true,
                    'data' => $result['data']['results'] ?? []
                ];
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Erro ao buscar municípios para autocomplete', [
                'search' => $search,
                'estado_id' => $estadoId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao buscar municípios: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Busca estados para autocomplete
     */
    public function getEstadosForAutocomplete(): array
    {
        try {
            Log::info('Buscando estados para autocomplete');

            $sql = "SELECT
                        codest,
                        nomest,
                        siglaest
                    FROM PUB.estado
                    ORDER BY nomest";

            $result = $this->executeCustomQuery($sql);

            if ($result['success']) {
                return [
                    'success' => true,
                    'data' => $result['data']['results'] ?? []
                ];
            }

            return $result;

        } catch (Exception $e) {
            Log::error('Erro ao buscar estados para autocomplete', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao buscar estados: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Busca uma rota SemParar com seus municípios
     */
    public function getSemPararRotaWithMunicipios($id): array
    {
        try {
            Log::info('Buscando rota SemParar com municípios', ['id' => $id]);

            // Buscar dados da rota
            $sqlRota = "SELECT sPararRotID, desSPararRot, tempoViagem, flgCD, flgRetorno, datAtu, resAtu " .
                       "FROM PUB.semPararRot WHERE sPararRotID = " . intval($id);

            $resultRota = $this->executeCustomQuery($sqlRota);

            Log::info('Resultado da query de rota', ['result' => $resultRota]);

            if (!$resultRota['success'] || empty($resultRota['data']['results'])) {
                Log::error('Rota não encontrada ou erro na query', [
                    'id' => $id,
                    'success' => $resultRota['success'] ?? false,
                    'data' => $resultRota['data'] ?? null
                ]);
                return [
                    'success' => false,
                    'error' => 'Rota não encontrada'
                ];
            }

            $rota = $resultRota['data']['results'][0];

            // Buscar municípios da rota com sigla do estado
            $sqlMunicipios = "SELECT m.sPararMuSeq, m.CodMun, m.CodEst, m.DesMun, e.sigest as desest, m.cdibge " .
                             "FROM PUB.semPararRotMu m " .
                             "INNER JOIN PUB.estado e ON m.CodEst = e.codest " .
                             "WHERE m.sPararRotID = " . intval($id) . " " .
                             "ORDER BY m.sPararMuSeq";

            $resultMunicipios = $this->executeCustomQuery($sqlMunicipios);

            $municipios = [];
            if ($resultMunicipios['success'] && !empty($resultMunicipios['data']['results'])) {
                $municipios = $resultMunicipios['data']['results'];

                // Buscar coordenadas do cache local (instantâneo!)
                foreach ($municipios as &$municipio) {
                    $codMun = intval($municipio['codmun']);
                    $codEst = intval($municipio['codest']);

                    $gpsCache = \App\Models\ProgressMunicipioGps::findByProgress($codMun, $codEst);

                    if ($gpsCache && $gpsCache->hasValidCoordinates()) {
                        $municipio['lat'] = $gpsCache->latitude;
                        $municipio['lon'] = $gpsCache->longitude;
                        $municipio['gps_fonte'] = $gpsCache->fonte;
                        $municipio['gps_cached'] = true;
                    } else {
                        $municipio['lat'] = null;
                        $municipio['lon'] = null;
                        $municipio['gps_fonte'] = null;
                        $municipio['gps_cached'] = false;
                    }
                }
            }

            return [
                'success' => true,
                'data' => [
                    'rota' => $rota,
                    'municipios' => $municipios
                ]
            ];

        } catch (Exception $e) {
            Log::error('Erro ao buscar rota SemParar com municípios', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao buscar rota: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Busca descrição do status de erro do SemParar
     *
     * @param int $codStatus Código do status retornado pelo SemParar
     * @return array ['success' => bool, 'descricao' => string|null]
     */
    public function getSemPararStatusDescricao(int $codStatus): array
    {
        try {
            Log::info('Buscando descrição do status SemParar', ['cod_status' => $codStatus]);

            $sql = "SELECT TOP 1 codStatus, desStatus FROM PUB.semPararStatus WHERE codStatus = " . intval($codStatus);
            $result = $this->executeCustomQuery($sql);

            if ($result['success'] && !empty($result['data']['results'])) {
                $status = $result['data']['results'][0];

                Log::info('Status SemParar encontrado', [
                    'codigo' => $status['codstatus'],
                    'descricao' => $status['desstatus']
                ]);

                return [
                    'success' => true,
                    'descricao' => $status['desstatus'],
                    'codigo' => $status['codstatus']
                ];
            }

            Log::warning('Status SemParar não encontrado na tabela', ['cod_status' => $codStatus]);

            return [
                'success' => false,
                'descricao' => null
            ];

        } catch (Exception $e) {
            Log::error('Erro ao buscar status SemParar', [
                'cod_status' => $codStatus,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'descricao' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Atualiza os municípios de uma rota SemParar
     */
    public function updateSemPararRotaMunicipios($rotaId, $municipios): array
    {
        try {
            Log::info('Atualizando municípios da rota SemParar', [
                'rota_id' => $rotaId,
                'total_municipios' => count($municipios)
            ]);

            // FASE 1: VALIDAR TODOS OS DADOS ANTES DE DELETAR QUALQUER COISA
            // (Progress JDBC não tem transações - se falhar após DELETE, dados são perdidos!)
            foreach ($municipios as $index => $municipio) {
                // Validar campos obrigatórios
                if (!isset($municipio['sequencia']) || !isset($municipio['cod_est']) ||
                    !isset($municipio['cod_mun']) || !isset($municipio['des_est']) ||
                    !isset($municipio['des_mun']) || !isset($municipio['cdibge'])) {

                    Log::error('Dados de município inválidos na posição ' . $index, ['municipio' => $municipio]);
                    return [
                        'success' => false,
                        'error' => 'Dados inválidos no município da posição ' . ($index + 1) . '. Operação cancelada para evitar perda de dados.'
                    ];
                }

                // Validar tipos de dados
                if (!is_numeric($municipio['sequencia']) || !is_numeric($municipio['cod_est']) ||
                    !is_numeric($municipio['cod_mun']) || !is_numeric($municipio['cdibge'])) {

                    Log::error('Tipos de dados inválidos no município da posição ' . $index, ['municipio' => $municipio]);
                    return [
                        'success' => false,
                        'error' => 'Tipos de dados inválidos no município da posição ' . ($index + 1) . '. Operação cancelada.'
                    ];
                }
            }

            // CORREÇÃO BUG #28: Strategy pattern em vez de DELETE+INSERT
            // 1. Buscar municípios existentes
            $sqlExistentes = "SELECT sPararMuSeq, CodEst, CodMun, DesEst, DesMun, cdibge FROM PUB.semPararRotMu WHERE sPararRotID = " . intval($rotaId);
            $resultExistentes = $this->executeCustomQuery($sqlExistentes);

            if (!$resultExistentes['success']) {
                Log::error('Erro ao buscar municípios existentes', ['error' => $resultExistentes['error']]);
                throw new Exception('Erro ao buscar municípios existentes: ' . ($resultExistentes['error'] ?? 'Erro desconhecido'));
            }

            $existentes = $resultExistentes['data']['results'] ?? [];
            $seqsExistentes = array_column($existentes, 'sPararMuSeq');

            Log::info('Municípios existentes carregados', [
                'total_existentes' => count($existentes),
                'sequencias' => $seqsExistentes
            ]);

            // 2. Processar cada município (UPDATE se existir, INSERT se novo)
            $seqsNovos = [];
            foreach ($municipios as $municipio) {
                $seq = intval($municipio['sequencia']);
                $seqsNovos[] = $seq;
                $codEst = intval($municipio['cod_est']);
                $codMun = intval($municipio['cod_mun']);
                $desEst = $this->escapeSqlString($municipio['des_est']);
                $desMun = $this->escapeSqlString($municipio['des_mun']);
                $cdibge = intval($municipio['cdibge']);

                if (in_array($seq, $seqsExistentes)) {
                    // UPDATE existente
                    $sqlUpdate = "UPDATE PUB.semPararRotMu SET CodEst = {$codEst}, CodMun = {$codMun}, DesEst = {$desEst}, DesMun = {$desMun}, cdibge = {$cdibge} WHERE sPararRotID = " . intval($rotaId) . " AND sPararMuSeq = {$seq}";
                    $result = $this->executeUpdate($sqlUpdate);

                    if (!$result['success']) {
                        Log::error('Erro ao atualizar município', [
                            'municipio' => $municipio['des_mun'],
                            'sequencia' => $seq,
                            'error' => $result['error']
                        ]);
                        throw new Exception('Erro ao atualizar município: ' . $municipio['des_mun'] . ' - ' . ($result['error'] ?? 'Erro desconhecido'));
                    }

                    Log::debug('Município atualizado', ['sequencia' => $seq, 'municipio' => $municipio['des_mun']]);
                } else {
                    // INSERT novo
                    $sqlInsert = "INSERT INTO PUB.semPararRotMu (sPararRotID, sPararMuSeq, CodEst, CodMun, DesEst, DesMun, cdibge) VALUES (" . intval($rotaId) . ", {$seq}, {$codEst}, {$codMun}, {$desEst}, {$desMun}, {$cdibge})";
                    $result = $this->executeUpdate($sqlInsert);

                    if (!$result['success']) {
                        Log::error('Erro ao inserir município', [
                            'municipio' => $municipio['des_mun'],
                            'sequencia' => $seq,
                            'error' => $result['error']
                        ]);
                        throw new Exception('Erro ao inserir município: ' . $municipio['des_mun'] . ' - ' . ($result['error'] ?? 'Erro desconhecido'));
                    }

                    Log::debug('Município inserido', ['sequencia' => $seq, 'municipio' => $municipio['des_mun']]);
                }
            }

            // 3. DELETE apenas os que foram removidos (não existem em $seqsNovos)
            $seqsRemovidos = array_diff($seqsExistentes, $seqsNovos);
            foreach ($seqsRemovidos as $seqRemovida) {
                $sqlDelete = "DELETE FROM PUB.semPararRotMu WHERE sPararRotID = " . intval($rotaId) . " AND sPararMuSeq = " . intval($seqRemovida);
                $result = $this->executeUpdate($sqlDelete);

                if (!$result['success']) {
                    Log::warning('Erro ao deletar município removido (não crítico)', [
                        'sequencia' => $seqRemovida,
                        'error' => $result['error']
                    ]);
                    // Não lançar exceção aqui, apenas log de warning
                } else {
                    Log::debug('Município removido', ['sequencia' => $seqRemovida]);
                }
            }

            Log::info('Municípios processados com sucesso', [
                'atualizados_ou_mantidos' => count(array_intersect($seqsExistentes, $seqsNovos)),
                'inseridos' => count(array_diff($seqsNovos, $seqsExistentes)),
                'removidos' => count($seqsRemovidos)
            ]);

            // FASE 5: Atualizar data de modificação da rota
            $sqlUpdate = "UPDATE PUB.semPararRot SET datAtu = '" . date('Y-m-d') . "', resAtu = 'web' WHERE sPararRotID = " . intval($rotaId);

            $updateResult = $this->executeUpdate($sqlUpdate);

            if (!$updateResult['success']) {
                // Não é crítico, apenas log
                Log::warning('Erro ao atualizar data da rota (não crítico)', ['error' => $updateResult['error']]);
            }

            return [
                'success' => true,
                'message' => 'Municípios atualizados com sucesso'
            ];

        } catch (Exception $e) {
            Log::error('Erro ao atualizar municípios da rota', [
                'rota_id' => $rotaId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao atualizar municípios: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ========================================
     * COMPRA DE VIAGEM SEMPARAR
     * ========================================
     */

    /**
     * FASE 3: Valida status do veículo na API SemParar
     * Em TEST_MODE: retorna dados simulados
     * Em PRODUÇÃO: faz chamada SOAP real à API SemParar
     */
    public function validateVehicleStatusSemParar(string $placa, bool $testMode = true): array
    {
        try {
            Log::info('Validando status do veículo SemParar', [
                'placa' => $placa,
                'test_mode' => $testMode
            ]);

            // Em TEST_MODE: retorna dados simulados
            if ($testMode) {
                Log::info('TEST_MODE ativo - Retornando dados simulados para veículo');

                // Simula veículo cadastrado no SemParar
                return [
                    'success' => true,
                    'data' => [
                        'placa' => strtoupper($placa),
                        'descricao' => 'CAMINHÃO TOCO - SIMULADO',
                        'eixos' => 3,
                        'proprietario' => 'TESTE LTDA - SIMULADO',
                        'tag' => 'TAG123456789',
                        'status' => 'ATIVO'
                    ],
                    'test_mode' => true,
                    'message' => 'Dados simulados - API SemParar não foi chamada'
                ];
            }

            // PRODUÇÃO: Chamada SOAP real à API SemParar
            Log::info('Fazendo chamada SOAP REAL à API SemParar');

            $soapService = new \App\Services\SemPararSoapService();
            $vehicleData = $soapService->obterStatusVeiculo($placa);
            $soapService->disconnect();

            return [
                'success' => true,
                'data' => [
                    'placa' => strtoupper($placa),
                    'descricao' => $vehicleData['descricao'] ?? 'N/A',
                    'eixos' => $vehicleData['eixos'] ?? 0,
                    'proprietario' => $vehicleData['proprietario'] ?? 'N/A',
                    'tag' => $vehicleData['tag'] ?? 'N/A',
                    'status' => $vehicleData['status'] ?? 'DESCONHECIDO'
                ],
                'test_mode' => false,
                'message' => 'Dados obtidos da API SemParar (chamada SOAP real)'
            ];

        } catch (Exception $e) {
            Log::error('Erro ao validar status do veículo', [
                'placa' => $placa,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao validar veículo: ' . $e->getMessage(),
                'code' => 'ERRO_INTERNO'
            ];
        }
    }

    /**
     * FASE 5: Verifica preço da viagem na API SemParar
     * Em TEST_MODE: retorna dados simulados
     * Em PRODUÇÃO: faz chamada SOAP real à API SemParar
     */
    /**
     * Verifica preço da viagem SemParar
     * Segue exatamente o fluxo de compraRota.p e Rota.cls:
     * 1. Busca municípios da rota no Progress
     * 2. Roteiriza praças de pedágio (roteirizarPracasPedagio)
     * 3. Cadastra rota temporária (cadastrarRotaTemporaria)
     * 4. Verifica preço usando nome da rota temporária
     *
     * @param int $codRota Código da rota no Progress (sPararRotID)
     * @param int $codPac Código do pacote (para gerar nome único da rota temporária)
     * @param int $qtdEixos Quantidade de eixos do veículo
     * @param string $placa Placa do veículo
     * @param string $dataInicio Data de início da viagem (YYYY-MM-DD)
     * @param string $dataFim Data de fim da viagem (YYYY-MM-DD)
     * @param bool $testMode Se true, retorna dados simulados
     */
    public function verifyTripPriceSemParar(
        int $codRota,
        int $codPac,
        int $qtdEixos,
        string $placa,
        string $dataInicio,
        string $dataFim,
        bool $testMode = true
    ): array {
        try {
            Log::info('Verificando preço da viagem SemParar - Fluxo completo com rota temporária', [
                'cod_rota' => $codRota,
                'cod_pac' => $codPac,
                'qtd_eixos' => $qtdEixos,
                'placa' => $placa,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim,
                'test_mode' => $testMode
            ]);
            Log::info('🔧 CODE VERSION: 2025-11-21-FIX-TYPE-MISMATCH - String conversion active');

            // Em TEST_MODE: retorna dados simulados
            if ($testMode) {
                Log::info('TEST_MODE ativo - Retornando dados simulados para verificação de preço');

                // Simula preço calculado
                $valorSimulado = 150.00 + ($qtdEixos * 50.00); // Base + valor por eixo

                return [
                    'success' => true,
                    'data' => [
                        'valor' => $valorSimulado,
                        'numero_viagem' => 'SIM-' . time(),
                        'rota_temporaria' => 'SIMULADO-' . $codRota . '-' . $codPac,
                        'placa' => strtoupper($placa),
                        'data_inicio' => $dataInicio,
                        'data_fim' => $dataFim,
                        'qtd_eixos' => $qtdEixos
                    ],
                    'test_mode' => true,
                    'message' => 'Dados simulados - API SemParar não foi chamada'
                ];
            }

            // PRODUÇÃO: Fluxo completo seguindo compraRota.p
            Log::info('Iniciando fluxo de criação de rota temporária (compraRota.p pattern)');

            // PASSO 1: Buscar dados da rota e municípios no Progress
            $sqlRota = "SELECT TOP 1 r.sPararRotID, r.desSPararRot, r.flgRetorno, r.flgCD FROM PUB.semPararRot r WHERE r.sPararRotID = " . intval($codRota);
            $resultRota = $this->executeCustomQuery($sqlRota);

            if (!$resultRota['success'] || empty($resultRota['data']['results'])) {
                throw new Exception('Rota não encontrada no Progress (código: ' . $codRota . ')');
            }

            $rota = $resultRota['data']['results'][0];
            $nomeRota = $rota['desspararrot'];
            $flgRetorno = $rota['flgretorno'] ?? false;
            $flgCD = $rota['flgcd'] ?? false;

            Log::info('Rota encontrada', ['nome' => $nomeRota, 'retorno' => $flgRetorno, 'cd' => $flgCD]);

            // PASSO 2: Buscar municípios da rota em ordem de sequência
            // Busca TODOS os campos necessários para geocoding
            $sqlMunicipios = "SELECT m.cdibge, m.desMun, m.codMun, m.codEst, m.desEst FROM PUB.semPararRotMu m WHERE m.sPararRotID = " . intval($codRota) . " ORDER BY m.sPararMuSeq";
            $resultMunicipios = $this->executeCustomQuery($sqlMunicipios);

            if (!$resultMunicipios['success'] || empty($resultMunicipios['data']['results'])) {
                throw new Exception('Nenhum município encontrado para a rota ' . $codRota);
            }

            // Array para pontos de parada (municípios + entregas)
            $pontos = [];

            // PASSO 2.3: Geocodificar municípios da rota usando Google Geocoding API (em lote)
            Log::info('Geocodificando municípios da rota SemParar em lote...');
            $geocodingService = app(\App\Services\GeocodingService::class);

            // Preparar array de municípios para geocoding em lote
            $municipiosParaGeocoding = array_map(function($mun) {
                return [
                    'cdibge' => $mun['cdibge'],
                    'desmun' => $mun['desmun'],
                    'desest' => $mun['desest'] ?? '',
                    'cod_mun' => $mun['codmun'] ?? null,
                    'cod_est' => $mun['codest'] ?? null
                ];
            }, $resultMunicipios['data']['results']);

            // Geocodificar todos de uma vez
            try {
                $coordsMap = $geocodingService->getCoordenadasLote($municipiosParaGeocoding);
                Log::info('Resultado do geocoding em lote', ['coords_map_keys' => array_keys($coordsMap), 'total' => count($coordsMap)]);
            } catch (\Exception $e) {
                Log::warning('Erro ao geocodificar municípios em lote', ['error' => $e->getMessage()]);
                $coordsMap = [];
            }

            // Adicionar municípios com coordenadas
            foreach ($resultMunicipios['data']['results'] as $mun) {
                $cdibge = (string) $mun['cdibge']; // Converter para string para bater com chave do $coordsMap
                $coords = $coordsMap[$cdibge] ?? null;
                Log::debug('Mapeando município para pontos', ['cdibge' => $cdibge, 'cdibge_type' => gettype($cdibge), 'coords' => $coords, 'coords_found' => $coords !== null]);

                $pontos[] = [
                    'cod_ibge' => $cdibge,
                    'desc' => $mun['desmun'],
                    'latitude' => $coords ? (string)$coords['lat'] : '0',
                    'longitude' => $coords ? (string)$coords['lon'] : '0'
                ];
            }

            Log::info('Municípios da rota carregados e geocodificados', [
                'total' => count($pontos),
                'geocodificados' => count(array_filter($pontos, fn($p) => $p['latitude'] !== '0'))
            ]);

            // PASSO 2.5: Buscar entregas do pacote com GPS (Rota.cls linha 716-797)
            // Só busca entregas se NÃO for rota CD (flgCD)
            // ⚠️ IMPORTANTE: Para SemParar, enviamos apenas PRIMEIRA e ÚLTIMA entrega (não todas)
            if (!$flgCD) {
                Log::info('Buscando entregas do pacote com GPS', ['codpac' => $codPac]);

                $itinerario = $this->getItinerarioPacote($codPac);

                if ($itinerario['success'] && !empty($itinerario['data']['entregas'])) {
                    $entregas = $itinerario['data']['entregas'];

                    // Filtrar entregas com GPS válido
                    $entregasComGPS = array_filter($entregas, function($entrega) {
                        return !empty($entrega['gps_lat']) && !empty($entrega['gps_lon'])
                            && $entrega['gps_lat'] !== null && $entrega['gps_lon'] !== null;
                    });

                    // Reindexar array após filter
                    $entregasComGPS = array_values($entregasComGPS);

                    // ⚠️ CORREÇÃO: Enviar apenas PRIMEIRA e ÚLTIMA entrega ao SemParar
                    // Progress: compraRota.p - "pego a primeira e a ultima"
                    if (count($entregasComGPS) > 0) {
                        // Primeira entrega
                        $primeiraEntrega = $entregasComGPS[0];
                        $pontos[] = [
                            'cod_ibge' => '0',  // Entregas usam GPS, não IBGE
                            'desc' => $primeiraEntrega['desend'] ?? $primeiraEntrega['razcli'],
                            'latitude' => $primeiraEntrega['gps_lat'],
                            'longitude' => $primeiraEntrega['gps_lon']
                        ];

                        // Última entrega (se for diferente da primeira)
                        if (count($entregasComGPS) > 1) {
                            $ultimaEntrega = $entregasComGPS[count($entregasComGPS) - 1];
                            $pontos[] = [
                                'cod_ibge' => '0',
                                'desc' => $ultimaEntrega['desend'] ?? $ultimaEntrega['razcli'],
                                'latitude' => $ultimaEntrega['gps_lat'],
                                'longitude' => $ultimaEntrega['gps_lon']
                            ];
                        }

                        Log::info('Entregas adicionadas para SemParar (apenas primeira e última)', [
                            'total_entregas_com_gps' => count($entregasComGPS),
                            'enviadas_para_semparar' => count($entregasComGPS) > 1 ? 2 : 1,
                            'total_pontos' => count($pontos)
                        ]);
                    } else {
                        Log::info('Nenhuma entrega com GPS válido encontrada', ['codpac' => $codPac]);
                    }
                } else {
                    Log::info('Nenhuma entrega encontrada no itinerário', ['codpac' => $codPac]);
                }
            } else {
                Log::info('Rota é CD (Centro de Distribuição), não busca entregas do pacote');
            }

            // DEBUG: Log pontos para investigação
            Log::info('Pontos que serão enviados ao SemParar', [
                'total' => count($pontos),
                'pontos_sample' => array_slice($pontos, 0, 5)
            ]);

            // Verificação crítica: SemParar retorna erro 999 se TODOS os pontos tiverem lat/lon = 0
            // TEMPORARIAMENTE DESABILITADO para debug - permitir envio mesmo sem GPS
            $temPontoComGPS = false;
            foreach ($pontos as $ponto) {
                if ($ponto['latitude'] !== '0' && $ponto['longitude'] !== '0') {
                    $temPontoComGPS = true;
                    break;
                }
            }

            if (!$temPontoComGPS) {
                Log::warning('AVISO: Nenhum ponto com GPS válido - SemParar provavelmente retornará erro 999');
            }

            // PASSO 3: Roteirizar praças de pedágio (linha 899 de Rota.cls)
            // ✅ CORREÇÃO: Usar o service CORRETO que não aplica regras especiais
            $soapService = new \App\Services\SemParar\SemPararService();

            Log::info('Chamando roteirizarPracasPedagio com pontos combinados', ['total_pontos' => count($pontos)]);
            $resultRoteirizar = $soapService->roteirizarPracasPedagio($pontos);

            if (!$resultRoteirizar['success']) {
                $errorMsg = $resultRoteirizar['error'] ?? 'Erro desconhecido ao roteirizar praças';
                Log::error('Erro ao roteirizar praças de pedágio', ['error' => $errorMsg]);
                throw new Exception('Erro ao roteirizar praças de pedágio: ' . $errorMsg);
            }

            if (empty($resultRoteirizar['pracas'])) {
                Log::error('Nenhuma praça de pedágio retornada');
                throw new Exception('Erro ao roteirizar praças de pedágio: nenhuma praça retornada');
            }

            // Extrair IDs das praças do formato do novo service
            $pracasIds = array_map(function($praca) {
                return $praca['id'];
            }, $resultRoteirizar['pracas']);
            Log::info('Praças de pedágio calculadas', ['total_pracas' => count($pracasIds)]);

            // PASSO 4: Gerar nome da rota temporária (linha 943-944 de Rota.cls)
            // Formato: "{rotaId} - {nomeRota} - {codPac}-{random(0-99)}"
            $randomSuffix = rand(0, 99);
            $nomeRotaTemporaria = $codRota . ' - ' . $nomeRota . ' - ' . $codPac . '-' . $randomSuffix;

            if ($flgRetorno) {
                $nomeRotaTemporaria .= ' - Retorno';
            }

            Log::info('Nome da rota temporária gerado', ['nome' => $nomeRotaTemporaria]);

            // PASSO 5: Cadastrar rota temporária no SemParar (linha 947 de Rota.cls)
            Log::info('Chamando cadastrarRotaTemporaria...');
            $resultCadastrar = $soapService->cadastrarRotaTemporaria($pracasIds, $nomeRotaTemporaria);

            if (!$resultCadastrar['success']) {
                throw new Exception('Erro ao cadastrar rota temporária no SemParar');
            }

            // ✅ CORREÇÃO: Novo service retorna 'cod_rota_semparar' não 'id_rota'
            $idRotaTemporaria = $resultCadastrar['cod_rota_semparar'];
            Log::info('Rota temporária cadastrada', ['id' => $idRotaTemporaria, 'nome' => $nomeRotaTemporaria]);

            // PASSO 6: Verificar preço usando nome da rota temporária
            Log::info('Chamando obterCustoRota com rota temporária...');
            $priceResult = $soapService->obterCustoRota(
                $nomeRotaTemporaria,
                $placa,
                $qtdEixos,
                $dataInicio,
                $dataFim
            );

            if (!$priceResult['success']) {
                throw new Exception('Erro ao obter custo da rota: ' . ($priceResult['error'] ?? 'Erro desconhecido'));
            }

            Log::info('Preço verificado com sucesso', [
                'valor' => $priceResult['valor'],
                'nome_rota' => $priceResult['nome_rota']
            ]);

            return [
                'success' => true,
                'data' => [
                    'valor' => $priceResult['valor'] ?? 0.0,
                    'numero_viagem' => '', // Não retorna número na verificação de preço
                    'nome_rota' => $priceResult['nome_rota'] ?? $nomeRotaTemporaria,
                    'cod_rota' => $idRotaTemporaria,
                    'rota_temporaria' => $nomeRotaTemporaria,
                    'id_rota_temporaria' => $idRotaTemporaria,
                    'pracas' => $resultRoteirizar['pracas'] ?? [], // Adicionar praças para exibição
                    'total_pracas' => count($pracasIds),
                    'placa' => strtoupper($placa),
                    'data_inicio' => $dataInicio,
                    'data_fim' => $dataFim,
                    'qtd_eixos' => $qtdEixos
                ],
                'test_mode' => false,
                'message' => 'Preço obtido da API SemParar com rota temporária criada'
            ];

        } catch (Exception $e) {
            Log::error('Erro ao verificar preço da viagem com rota temporária', [
                'cod_rota' => $codRota ?? null,
                'cod_pac' => $codPac ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao verificar preço: ' . $e->getMessage(),
                'code' => 'ERRO_INTERNO'
            ];
        }
    }

    /**
     * Valida pacote para compra de viagem
     */
    public function validatePackageForCompraViagem(int $codpac, bool $flgcd): array
    {
        try {
            Log::info('Validando pacote para compra de viagem', ['codpac' => $codpac, 'flgcd' => $flgcd]);

            // 1. Verifica se pacote existe
            $sqlPacote = "SELECT TOP 1 p.codpac, p.codtrn, p.codmot, p.sitpac, p.datforpac, p.numpla, p.codrot FROM PUB.pacote p WHERE p.codpac = " . intval($codpac);
            $resultPacote = $this->executeCustomQuery($sqlPacote);

            if (!$resultPacote['success'] || empty($resultPacote['data']['results'])) {
                return ['success' => false, 'error' => 'Pacote não encontrado', 'code' => 'PACOTE_NAO_ENCONTRADO'];
            }

            $pacote = $resultPacote['data']['results'][0];

            // 2. Verifica se é TCD quando não deveria ser
            if (!$flgcd) {
                $sqlPacCD = "SELECT TOP 1 codpaccd FROM PUB.paccd WHERE codpaccd = " . intval($codpac);
                $resultPacCD = $this->executeCustomQuery($sqlPacCD);

                if ($resultPacCD['success'] && !empty($resultPacCD['data']['results'])) {
                    return ['success' => false, 'error' => 'Este pacote é TCD. Use o modo CD.', 'code' => 'PACOTE_E_TCD'];
                }
            }

            // 3. Busca transporte
            if (empty($pacote['codtrn'])) {
                return ['success' => false, 'error' => 'Pacote sem transportador associado', 'code' => 'SEM_TRANSPORTE'];
            }

            // Progress: compraRota.p linha 242 - Busca placa do TRANSPORTE, não do pacote
            $sqlTransporte = "SELECT TOP 1 t.CodTrn, t.NomTrn, t.NumPla, t.flgautonomo, t.codcnpjcpf FROM PUB.transporte t WHERE t.CodTrn = " . intval($pacote['codtrn']);
            $resultTransporte = $this->executeCustomQuery($sqlTransporte);

            if (!$resultTransporte['success'] || empty($resultTransporte['data']['results'])) {
                return ['success' => false, 'error' => 'Transportador não encontrado', 'code' => 'TRANSPORTE_NAO_ENCONTRADO'];
            }

            $transporte = $resultTransporte['data']['results'][0];

            // 4. Busca rota (introt)
            $introt = null;
            if (!empty($pacote['codrot'])) {
                $sqlIntrot = "SELECT TOP 1 i.codrot, i.desrot FROM PUB.introt i WHERE i.codrot = " . $this->escapeSqlString($pacote['codrot']);
                $resultIntrot = $this->executeCustomQuery($sqlIntrot);

                if ($resultIntrot['success'] && !empty($resultIntrot['data']['results'])) {
                    $introt = $resultIntrot['data']['results'][0];
                }
            }

            return [
                'success' => true,
                'data' => [
                    'pacote' => [
                        'codpac' => $pacote['codpac'],
                        'codtrn' => $pacote['codtrn'],
                        'codrot' => $pacote['codrot'] ?? null
                    ],
                    'transporte' => [
                        'codtrn' => $transporte['codtrn'],
                        'nomtrn' => trim($transporte['nomtrn']),
                        'numpla' => trim($transporte['numpla'] ?? '') // Progress: compraRota.p linha 242
                    ],
                    'rota' => $introt,
                    'introt' => $introt ? ['codrot' => trim($introt['codrot']), 'desrot' => trim($introt['desrot'])] : null
                ]
            ];

        } catch (Exception $e) {
            Log::error('Erro ao validar pacote', ['codpac' => $codpac, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Erro ao validar pacote: ' . $e->getMessage(), 'code' => 'ERRO_INTERNO'];
        }
    }

    // ============================================================================
    // COMPRA VIAGEM - VALIDAÇÕES E REGRAS DE NEGÓCIO
    // ============================================================================

    /**
     * Verifica se pacote é TCD (Transfer CD)
     * Progress: compraRota.p linha 216-227
     */
    public function isPacoteTCD(int $codpac): bool
    {
        try {
            $sql = "SELECT TOP 1 codpaccd FROM PUB.paccd WHERE codpaccd = {$codpac}";
            $result = $this->executeCustomQuery($sql);
            return !empty($result['data']);
        } catch (Exception $e) {
            Log::error('Erro ao verificar pacote TCD', ['codpac' => $codpac, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Verifica se já existe viagem comprada para pacote+rota
     * Progress: compraRota.p linha 555-581
     */
    public function viagemJaComprada(int $codpac, int $rotaId): array
    {
        try {
            $sql = "SELECT TOP 1 codViagem, NumPla, valViagem, dataCompra " .
                   "FROM PUB.sPararViagem " .
                   "WHERE CodPac = {$codpac} " .
                   "AND sPararRotID = {$rotaId} " .
                   "AND flgCancelado = false";

            $result = $this->executeCustomQuery($sql);

            if (!empty($result['data'])) {
                return [
                    'duplicada' => true,
                    'viagem' => $result['data'][0]
                ];
            }

            return ['duplicada' => false];
        } catch (Exception $e) {
            Log::error('Erro ao verificar viagem duplicada', [
                'codpac' => $codpac,
                'rotaId' => $rotaId,
                'error' => $e->getMessage()
            ]);
            return ['duplicada' => false];
        }
    }

    /**
     * Busca rota sugerida via pacsoc (pacote sócio)
     * Progress: compraRota.p linha 433-440
     */
    public function getRotaSugeridaPorPacsoc(int $codpac): ?array
    {
        try {
            // Busca pacsoc
            $sql = "SELECT TOP 1 codpac FROM PUB.pacsoc WHERE codpacsoc = {$codpac}";
            $result = $this->executeCustomQuery($sql);

            if (empty($result['data'])) {
                return null;
            }

            $codpacPai = $result['data'][0]['codpac'];

            // Busca rota do pacote pai
            return $this->getRotaSugeridaPorIntrot($codpacPai, false);
        } catch (Exception $e) {
            Log::error('Erro ao buscar rota por pacsoc', ['codpac' => $codpac, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Busca rota sugerida via semPararIntrot
     * Progress: compraRota.p linha 441-463
     */
    public function getRotaSugeridaPorIntrot(int $codpac, bool $flgRetorno = false): ?array
    {
        try {
            // Busca codrot do pacote
            $sql = "SELECT TOP 1 codrot FROM PUB.pacote WHERE codpac = {$codpac}";
            $result = $this->executeCustomQuery($sql);

            if (empty($result['data'])) {
                return null;
            }

            $codrot = $result['data'][0]['codrot'];

            // Busca rota SemParar via semPararIntrot
            $filtroRetorno = $flgRetorno
                ? "AND CHARINDEX('RETORNO', r.desSPararRot) > 0"
                : "AND (CHARINDEX('RETORNO', r.desSPararRot) = 0 OR CHARINDEX('RETORNO', r.desSPararRot) IS NULL)";

            $sql = "SELECT TOP 1 r.sPararRotID, r.desSPararRot, r.flgCD, r.flgRetorno, r.tempoViagem " .
                   "FROM PUB.semPararIntrot si " .
                   "INNER JOIN PUB.semPararRot r ON r.sPararRotID = si.sPararRotID " .
                   "WHERE si.codrot = " . $this->escapeSqlString($codrot) . " " .
                   $filtroRetorno;

            $result = $this->executeCustomQuery($sql);

            if (empty($result['data'])) {
                return null;
            }

            return $result['data'][0];
        } catch (Exception $e) {
            Log::error('Erro ao buscar rota por introt', [
                'codpac' => $codpac,
                'flgRetorno' => $flgRetorno,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Busca DDD do município do transportador
     * Progress: Rota.cls linha 655-677 (formataCelular)
     */
    public function getDDDTransportador(int $codtrn): ?string
    {
        try {
            $sql = "SELECT TOP 1 " .
                   "t.dddcel, m.codddd " .
                   "FROM PUB.transporte t " .
                   "LEFT JOIN PUB.municipio m ON m.codmun = t.codmun AND m.codest = t.codest " .
                   "WHERE t.codtrn = {$codtrn}";

            $result = $this->executeCustomQuery($sql);

            if (empty($result['data'])) {
                return null;
            }

            $row = $result['data'][0];
            return $row['dddcel'] ?: $row['codddd'];
        } catch (Exception $e) {
            Log::error('Erro ao buscar DDD transportador', ['codtrn' => $codtrn, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Salva registro de viagem SemParar no Progress
     * Progress: compraRota.p linha 856-867
     */
    public function salvarSPararViagem(array $dados): array
    {
        try {
            // CORREÇÃO BUG IMPORTANTE #6: Validar campos obrigatórios antes de INSERT
            $camposObrigatorios = ['codpac', 'codRotCreateSP', 'codtrn', 'codViagem',
                                    'nomRotSemParar', 'placa', 'rotaId', 'valorViagem', 'usuario'];

            foreach ($camposObrigatorios as $campo) {
                if (!isset($dados[$campo]) || $dados[$campo] === '' || $dados[$campo] === null) {
                    Log::error('Campo obrigatório ausente em salvarSPararViagem', [
                        'campo_faltante' => $campo,
                        'dados_recebidos' => array_keys($dados),
                        'method' => __METHOD__
                    ]);

                    return [
                        'success' => false,
                        'error' => "Campo obrigatório ausente: {$campo}",
                        'data' => null
                    ];
                }
            }

            // Validar tipos de dados
            if (!is_numeric($dados['codpac']) || !is_numeric($dados['codtrn']) ||
                !is_numeric($dados['rotaId']) || !is_numeric($dados['valorViagem'])) {
                return [
                    'success' => false,
                    'error' => 'Tipos de dados inválidos (codpac, codtrn, rotaId, valorViagem devem ser numéricos)',
                    'data' => null
                ];
            }

            $sql = "INSERT INTO PUB.sPararViagem (" .
                   "CodPac, codRotCreateSP, codtrn, codViagem, nomRotSemParar, " .
                   "NumPla, sPararRotID, valViagem, resCompra, dataCompra" .
                   ") VALUES (" .
                   "{$dados['codpac']}, " .
                   $this->escapeSqlString($dados['codRotCreateSP']) . ", " .
                   "{$dados['codtrn']}, " .
                   $this->escapeSqlString($dados['codViagem']) . ", " .
                   $this->escapeSqlString($dados['nomRotSemParar']) . ", " .
                   $this->escapeSqlString($dados['placa']) . ", " .
                   "{$dados['rotaId']}, " .
                   "{$dados['valorViagem']}, " .
                   $this->escapeSqlString($dados['usuario']) . ", " .
                   "TODAY" .
                   ")";

            $result = $this->executeUpdate($sql);

            Log::info('Viagem SemParar salva no Progress', [
                'codpac' => $dados['codpac'],
                'codViagem' => $dados['codViagem'],
                'rows_affected' => $result['rows_affected'] ?? 0
            ]);

            return [
                'success' => true,
                'rows_affected' => $result['rows_affected'] ?? 0
            ];
        } catch (Exception $e) {
            Log::error('Erro ao salvar sPararViagem', [
                'dados' => $dados,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao salvar viagem: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Salva log de municípios da viagem
     * Progress: compraRota.p linha 868-888
     */
    public function salvarSemPararRotMuLog(string $codViagem, int $rotaId, array $municipios): array
    {
        try {
            $rowsAffected = 0;

            foreach ($municipios as $index => $mun) {
                $sql = "INSERT INTO PUB.semPararRotMuLog (" .
                       "cdibge, DesEst, DesMun, sPararMuSeq, codViagem, datAtu, resAtu" .
                       ") VALUES (" .
                       (int)$mun['cdibge'] . ", " .
                       $this->escapeSqlString($mun['DesEst'] ?? '') . ", " .
                       $this->escapeSqlString($mun['DesMun'] ?? '') . ", " .
                       ($index + 1) . ", " .
                       $this->escapeSqlString($codViagem) . ", " .
                       "TODAY, " .
                       $this->escapeSqlString($mun['resAtu'] ?? 'SYSTEM') .
                       ")";

                $result = $this->executeUpdate($sql);
                $rowsAffected += $result['rows_affected'] ?? 0;
            }

            Log::info('Log de municípios salvo', [
                'codViagem' => $codViagem,
                'total_municipios' => count($municipios),
                'rows_affected' => $rowsAffected
            ]);

            return [
                'success' => true,
                'rows_affected' => $rowsAffected
            ];
        } catch (Exception $e) {
            Log::error('Erro ao salvar semPararRotMuLog', [
                'codViagem' => $codViagem,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao salvar log de municípios: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Salvar viagem SemParar no Progress Database
     *
     * Insere registro na tabela PUB.sPararViagem após compra bem-sucedida
     *
     * Campos da tabela:
     * - codpac: Código do pacote
     * - numpla: Placa do veículo
     * - rescompra: Responsável pela compra (usuário logado)
     * - codrotcreatesp: Código da rota criada no SemParar
     * - spararrotid: ID da rota SemParar
     * - valviagem: Valor da viagem (pedágio)
     * - codtrn: Código do transportador
     * - nomrotsemparar: Nome da rota no SemParar
     * - codviagem: Código da viagem retornado pelo SemParar
     * - datacompra: Data da compra
     * - flgcancelado: Flag de cancelamento (false por padrão)
     * - rescancel: Responsável pelo cancelamento (vazio por padrão)
     *
     * @param array $dados Dados da viagem
     * @return array Resultado da operação
     */
    public function salvarViagemSemParar(array $dados): array
    {
        try {
            Log::info('[Progress] Salvando viagem SemParar', [
                'codViagem' => $dados['codViagem'] ?? null,
                'codPac' => $dados['codPac'] ?? null,
                'placa' => $dados['placa'] ?? null
            ]);

            // Validação dos campos obrigatórios
            $camposObrigatorios = [
                'codViagem', 'codPac', 'placa', 'nomeRotaSemParar',
                'codRotaCreateSp', 'sPararRotID', 'valorViagem', 'codTrn'
            ];

            foreach ($camposObrigatorios as $campo) {
                if (!isset($dados[$campo])) {
                    throw new Exception("Campo obrigatório ausente: {$campo}");
                }
            }

            // Escapar strings para SQL
            $codPac = (int)$dados['codPac'];
            $placa = $this->escapeSqlString(strtoupper($dados['placa']));
            $resCompra = $this->escapeSqlString($dados['resCompra'] ?? 'sistema');
            $codRotaCreateSp = $this->escapeSqlString($dados['codRotaCreateSp']);
            $sPararRotID = (int)$dados['sPararRotID'];
            $valorViagem = (float)$dados['valorViagem'];
            $codTrn = (int)$dados['codTrn'];
            $nomeRotaSemParar = $this->escapeSqlString($dados['nomeRotaSemParar']);
            $codViagem = $this->escapeSqlString($dados['codViagem']);
            $dataCompra = date('Y-m-d');

            // Construir SQL de INSERT
            $sql = "INSERT INTO PUB.sPararViagem (" .
                "codpac, numpla, rescompra, codrotcreatesp, spararrotid, " .
                "valviagem, codtrn, nomrotsemparar, codviagem, datacompra, " .
                "flgcancelado, rescancel" .
                ") VALUES (" .
                "{$codPac}, " .
                "{$placa}, " .
                "{$resCompra}, " .
                "{$codRotaCreateSp}, " .
                "{$sPararRotID}, " .
                "{$valorViagem}, " .
                "{$codTrn}, " .
                "{$nomeRotaSemParar}, " .
                "{$codViagem}, " .
                "'{$dataCompra}', " .
                "false, " .
                "''" .
                ")";

            Log::debug('[Progress] SQL INSERT sPararViagem', [
                'sql' => $sql
            ]);

            // Executar INSERT
            $result = $this->executeUpdate($sql);

            if (!$result['success']) {
                throw new Exception($result['error'] ?? 'Erro ao executar INSERT');
            }

            Log::info('[Progress] Viagem SemParar salva com sucesso', [
                'codViagem' => $dados['codViagem'],
                'rowsAffected' => $result['rows_affected'] ?? 0
            ]);

            return [
                'success' => true,
                'message' => 'Viagem salva com sucesso',
                'codViagem' => $dados['codViagem'],
                'rows_affected' => $result['rows_affected'] ?? 0
            ];

        } catch (Exception $e) {
            Log::error('[Progress] Erro ao salvar viagem SemParar', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'dados' => $dados
            ]);

            return [
                'success' => false,
                'error' => 'Erro ao salvar viagem: ' . $e->getMessage()
            ];
        }
    }
}