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
            Log::info('Testando conexão Progress via JDBC', [
                'host' => env('PROGRESS_HOST'),
                'database' => env('PROGRESS_DATABASE')
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

            // Filtro por natureza do transporte
            $natureza = $filters['natureza'] ?? '';
            if (!empty($natureza)) {
                $whereConditions[] = "natcam = '$natureza'";
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

            // Filtro por situação
            if (!empty($situacao)) {
                $whereConditions[] = "p.sitpac = '$situacao'";
            }

            // Filtro "apenas recentes" (baseado no padrão Progress)
            if ($apenasRecentes) {
                $whereConditions[] = "p.codpac > 800000";
            }

            // Filtro por período
            if (!empty($dataInicio)) {
                $whereConditions[] = "p.datforpac >= '$dataInicio'";
            }
            if (!empty($dataFim)) {
                $whereConditions[] = "p.datforpac <= '$dataFim'";
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

            // Buscar dados principais da carga usando o pacote correto
            $sqlCarga = "SELECT p.codpac, p.codrot as rota, p.codmot as motorista, p.pespac as peso, p.volpac as volume, p.valpac as valor, COALESCE(cf.valfre, 0) as frete FROM PUB.pacote p LEFT JOIN PUB.cxapacote cp ON cp.codpac = p.codpac LEFT JOIN PUB.caixafech cf ON cf.codcxa = cp.codcxa WHERE p.codpac = $pacoteParaBuscar";

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
     * Executa consulta SQL customizada (para debug e testes)
     */
    public function executeCustomQuery(string $sql): array
    {
        try {
            Log::info('Executando consulta SQL customizada', ['sql' => $sql]);

            // Limitar a apenas SELECT por segurança
            $sql_upper = strtoupper(trim($sql));
            if (!str_starts_with($sql_upper, 'SELECT')) {
                throw new Exception('Apenas consultas SELECT são permitidas');
            }

            $result = $this->executeJavaConnector('query', $sql);

            Log::info('Consulta SQL executada com sucesso', [
                'total_registros' => $result['data']['total'] ?? 0
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Erro na execução da consulta SQL', [
                'sql' => $sql,
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
     */
    public function executeUpdate(string $sql): array
    {
        try {
            Log::info('Executando comando UPDATE/INSERT/DELETE', ['sql' => $sql]);

            // Validar que é um comando permitido
            $sql_upper = strtoupper(trim($sql));
            if (!str_starts_with($sql_upper, 'UPDATE') &&
                !str_starts_with($sql_upper, 'INSERT') &&
                !str_starts_with($sql_upper, 'DELETE')) {
                throw new Exception('Apenas comandos UPDATE, INSERT e DELETE são permitidos');
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
                'sql' => $sql,
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
            $driverPath = 'c:/Progress/OpenEdge/java/openedge.jar';
            $jdbcUrl = env('PROGRESS_JDBC_URL', 'jdbc:datadirect:openedge://192.168.80.113:13361;databaseName=tambasa;trustStore=');
            $username = env('PROGRESS_USERNAME', 'sysprogress');
            $password = env('PROGRESS_PASSWORD', 'sysprogress');

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
     * Converte formato Progress para decimal brasileiro
     */
    private function processGpsCoordinate($coordinate)
    {
        if (empty($coordinate)) {
            return null;
        }

        // Limpar coordenada seguindo a mesma lógica do itinerario.p
        $coord = trim($coordinate);
        $coord = str_replace(['W', 'N', 'E', 'S'], '', $coord);
        $coord = str_replace(['-', '.', ','], '', $coord);
        
        if (strlen($coord) >= 3) {
            // Formato brasileiro: "-14,0876543" (sinal negativo + 2 dígitos + vírgula + demais dígitos)
            $formatted = '-' . substr($coord, 0, 2) . ',' . substr($coord, 2);
            return trim($formatted);
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
            $insertRotaSql = "INSERT INTO PUB.semPararRot
                (sPararRotID, desSPararRot, tempoViagem, flgCD, flgRetorno, datAtu, resAtu)
                VALUES
                (" . $nextId . ",
                 " . $this->escapeSqlString($data['nome']) . ",
                 " . intval($data['tempo_viagem'] ?? 5) . ",
                 " . ($data['flg_cd'] ? '1' : '0') . ",
                 " . ($data['flg_retorno'] ? '1' : '0') . ",
                 '" . date('Y-m-d') . "',
                 " . $this->escapeSqlString(auth()->user()->name ?? 'system') . ")";

            $insertResult = $this->executeCustomQuery($insertRotaSql);

            if (!$insertResult['success']) {
                throw new Exception('Erro ao inserir rota principal');
            }

            // Inserir municípios se fornecidos
            if (!empty($data['municipios'])) {
                foreach ($data['municipios'] as $index => $municipio) {
                    $insertMunSql = "INSERT INTO PUB.semPararRotMu
                        (sPararRotID, sPararMuSeq, codEst, codMun, desEst, desMun, cdibge)
                        VALUES
                        (" . $nextId . ",
                         " . ($index + 1) . ",
                         " . intval($municipio['cod_est']) . ",
                         " . intval($municipio['cod_mun']) . ",
                         " . $this->escapeSqlString($municipio['des_est']) . ",
                         " . $this->escapeSqlString($municipio['des_mun']) . ",
                         " . intval($municipio['cdibge']) . ")";

                    $munResult = $this->executeCustomQuery($insertMunSql);
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
            $updateRotaSql = "UPDATE PUB.semPararRot SET desSPararRot = " . $this->escapeSqlString($data['nome']) . ", tempoViagem = " . intval($data['tempo_viagem'] ?? 5) . ", flgCD = " . ($data['flg_cd'] ? '1' : '0') . ", flgRetorno = " . ($data['flg_retorno'] ? '1' : '0') . ", datAtu = '" . date('Y-m-d') . "', resAtu = " . $this->escapeSqlString(auth()->user()->name ?? 'system') . " WHERE sPararRotID = " . intval($rotaId);

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
     * Atualiza os municípios de uma rota SemParar
     */
    public function updateSemPararRotaMunicipios($rotaId, $municipios): array
    {
        try {
            Log::info('Atualizando municípios da rota SemParar', [
                'rota_id' => $rotaId,
                'total_municipios' => count($municipios)
            ]);

            // Primeiro, deletar todos os municípios existentes
            $sqlDelete = "DELETE FROM PUB.semPararRotMu WHERE sPararRotID = " . intval($rotaId);
            $this->executeCustomQuery($sqlDelete);

            // Inserir novos municípios com a sequência correta
            foreach ($municipios as $municipio) {
                $sqlInsert = "INSERT INTO PUB.semPararRotMu (
                                sPararRotID,
                                sPararMuSeq,
                                CodEst,
                                CodMun,
                                DesEst,
                                DesMun,
                                cdibge
                            ) VALUES (
                                " . intval($rotaId) . ",
                                " . intval($municipio['sequencia']) . ",
                                " . intval($municipio['cod_est']) . ",
                                " . intval($municipio['cod_mun']) . ",
                                '" . $municipio['des_est'] . "',
                                '" . $municipio['des_mun'] . "',
                                " . intval($municipio['cdibge']) . "
                            )";

                $result = $this->executeCustomQuery($sqlInsert);

                if (!$result['success']) {
                    Log::error('Erro ao inserir município na rota', [
                        'municipio' => $municipio,
                        'error' => $result['error']
                    ]);
                }
            }

            // Atualizar data de modificação da rota
            $sqlUpdate = "UPDATE PUB.semPararRot SET
                            datAtu = CURDATE(),
                            resAtu = 'web'
                          WHERE sPararRotID = " . intval($rotaId);

            $this->executeCustomQuery($sqlUpdate);

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
}