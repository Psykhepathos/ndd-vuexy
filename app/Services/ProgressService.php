<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class ProgressService
{
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
     */
    public function getTransportesPaginated(array $filters): array
    {
        try {
            Log::info('Buscando transportes paginados via JDBC', ['filters' => $filters]);

            $page = $filters['page'] ?? 1;
            $perPage = $filters['per_page'] ?? 10;
            $search = $filters['search'] ?? '';
            $codigo = $filters['codigo'] ?? '';
            $nome = $filters['nome'] ?? '';

            // Calcular offset para paginação
            $offset = ($page - 1) * $perPage;

            // Construir condições WHERE
            $whereConditions = [];
            $params = [];

            if (!empty($search)) {
                $whereConditions[] = "(UPPER(nomtrn) LIKE UPPER(?) OR codtrn = ?)";
                $params[] = "%{$search}%";
                $params[] = is_numeric($search) ? (int)$search : 0;
            }

            if (!empty($codigo)) {
                $whereConditions[] = "codtrn = ?";
                $params[] = (int)$codigo;
            }

            if (!empty($nome)) {
                $whereConditions[] = "UPPER(nomtrn) LIKE UPPER(?)";
                $params[] = "%{$nome}%";
            }

            $whereClause = '';
            if (!empty($whereConditions)) {
                $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            }

            // Construir SQL paginado usando TOP do Progress
            $sql = "SELECT TOP {$perPage} codtrn, nomtrn FROM PUB.transporte";
            
            if (!empty($whereClause)) {
                $sql .= " " . $whereClause;
            }
            
            // Simular offset usando condição WHERE (já que Progress não tem OFFSET nativo)
            if ($offset > 0) {
                $offsetCondition = empty($whereClause) ? " WHERE " : " AND ";
                $sql .= $offsetCondition . "codtrn > (SELECT MAX(codtrn) FROM (SELECT TOP {$offset} codtrn FROM PUB.transporte";
                
                if (!empty($whereClause)) {
                    $sql .= " " . $whereClause;
                }
                
                $sql .= " ORDER BY codtrn) sub)";
            }
            
            $sql .= " ORDER BY codtrn";

            // Campos essenciais para diferenciar tipos de transportadores
            $campos = "codtrn, nomtrn, flgautonomo, natcam, tipcam, codcnpjcpf, numpla, numtel, dddtel, flgati, indcd";
            
            // Construir condições WHERE baseadas nos filtros
            $whereConditions = [];
            
            // Filtro de busca por código ou nome
            if (!empty($search)) {
                $searchTerm = trim($search);
                if (is_numeric($searchTerm)) {
                    $whereConditions[] = "codtrn = $searchTerm";
                } else {
                    $whereConditions[] = "UPPER(nomtrn) LIKE '%" . strtoupper($searchTerm) . "%'";
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
            
            // Solução alternativa para paginação no Progress usando array de IDs
            $whereClause = !empty($whereConditions) ? " WHERE " . implode(' AND ', $whereConditions) : "";
            
            if ($page === 1 || $offset === 0) {
                // Primeira página - SQL simples
                $simpleSql = "SELECT TOP $perPage $campos FROM PUB.transporte$whereClause ORDER BY codtrn";
            } else {
                // Para páginas subsequentes, primeiro buscar todos os IDs, depois filtrar
                // Busca só os IDs dos primeiros registros para pular
                $skipSql = "SELECT TOP $offset codtrn FROM PUB.transporte$whereClause ORDER BY codtrn";
                $skipResult = $this->executeCustomQuery($skipSql);
                
                if ($skipResult['success'] && !empty($skipResult['data']['results'])) {
                    $lastId = end($skipResult['data']['results'])['codtrn'];
                    $conditionPrefix = $whereClause ? " AND " : " WHERE ";
                    $simpleSql = "SELECT TOP $perPage $campos FROM PUB.transporte$whereClause{$conditionPrefix}codtrn > $lastId ORDER BY codtrn";
                } else {
                    // Se não conseguiu pular, retorna vazio
                    $simpleSql = "SELECT TOP $perPage $campos FROM PUB.transporte WHERE 1=0";
                }
            }
            
            Log::info('SQL simplificado para busca', ['sql' => $simpleSql]);
            
            $result = $this->executeCustomQuery($simpleSql);

            if ($result['success']) {
                // Contar total de registros para paginação (aplicando os mesmos filtros)
                $countSql = "SELECT COUNT(*) as total FROM PUB.transporte$whereClause";
                
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

                // Adicionar informações de filtros aplicados
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
            Log::info('Buscando transporte por ID', ['id' => $id]);

            $sql = "SELECT codtrn, nomtrn, flgautonomo, natcam, tipcam, codcnpjcpf, numpla, numtel, dddtel, flgati, indcd FROM PUB.transporte WHERE codtrn = $id";

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

            // Campos principais da consulta
            $campos = "p.codpac, p.datforpac, p.horforpac, p.codtrn, p.codmot, p.numpla, p.valpac, p.volpac, p.pespac, p.sitpac, p.codrot, p.nroped, t.nomtrn";

            // Construir condições WHERE
            $whereConditions = [];

            // Filtro por código do pacote
            if (!empty($codigo)) {
                $whereConditions[] = "p.codpac = $codigo";
            }

            // Filtro por busca geral (código do pacote ou nome do transportador)
            if (!empty($search)) {
                $whereConditions[] = "(p.codpac LIKE '%$search%' OR UPPER(t.nomtrn) LIKE '%". strtoupper($search) ."%')";
            }

            // Filtro por transportador (nome)
            if (!empty($transportador)) {
                $whereConditions[] = "UPPER(t.nomtrn) LIKE '%". strtoupper($transportador) ."%'";
            }

            // Filtro por código do transportador
            if (!empty($codigoTransportador)) {
                $whereConditions[] = "p.codtrn = $codigoTransportador";
            }

            // Filtro por rota
            if (!empty($rota)) {
                $whereConditions[] = "p.codrot LIKE '%$rota%'";
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
                $sql = "SELECT TOP $perPage $campos FROM PUB.pacote p LEFT JOIN PUB.transporte t ON p.codtrn = t.codtrn $whereClause ORDER BY p.codpac DESC";
            } else {
                // Páginas subsequentes usando offset simulado
                $offsetSql = "SELECT TOP $offset p.codpac FROM PUB.pacote p LEFT JOIN PUB.transporte t ON p.codtrn = t.codtrn $whereClause ORDER BY p.codpac DESC";
                $offsetResult = $this->executeCustomQuery($offsetSql);
                
                if ($offsetResult['success'] && !empty($offsetResult['data']['results'])) {
                    $lastId = end($offsetResult['data']['results'])['codpac'];
                    $sql = "SELECT TOP $perPage $campos FROM PUB.pacote p LEFT JOIN PUB.transporte t ON p.codtrn = t.codtrn $whereClause AND p.codpac < $lastId ORDER BY p.codpac DESC";
                } else {
                    $sql = "SELECT TOP $perPage $campos FROM PUB.pacote p LEFT JOIN PUB.transporte t ON p.codtrn = t.codtrn WHERE 1=0";
                }
            }

            Log::info('SQL Pacotes', ['sql' => $sql]);
            
            $result = $this->executeCustomQuery($sql);

            if ($result['success']) {
                // Contar total com os mesmos filtros
                $countSql = "SELECT COUNT(*) as total FROM PUB.pacote p LEFT JOIN PUB.transporte t ON p.codtrn = t.codtrn $whereClause";
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

            // Buscar dados principais da carga (similar ao procedure coleta_cargas)
            $sqlCarga = "SELECT p.codpac, p.codrot as rota, p.codmot as motorista, p.pespac as peso, p.volpac as volume, p.valpac as valor, COALESCE(cf.valfre, 0) as frete FROM PUB.pacote p LEFT JOIN PUB.cxapacote cp ON cp.codpac = p.codpac LEFT JOIN PUB.caixafech cf ON cf.codcxa = cp.codcxa WHERE p.codpac = $codPac";

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
            $sqlEntregas = "SELECT ped.numseqped as seqent, COALESCE(nf.numnotfis, 0) as nf, cli.codcli, raz.desraz as razcli, est.sigest as uf, mun.desmun, bai.desbai, cli.desend, COALESCE(CAST(nf.numnotfis AS CHAR), '-') as numnot, ped.valtotateped as valnot, ped.pesped as peso, ped.volped as volume, ard.lat, ard.long FROM PUB.carga car INNER JOIN PUB.pedido ped ON ped.codcar = car.codcar INNER JOIN PUB.cliente cli ON cli.codcli = ped.codcli INNER JOIN PUB.estado est ON est.codest = cli.codest INNER JOIN PUB.municipio mun ON mun.codest = cli.codest AND mun.codmun = cli.codmun INNER JOIN PUB.bairro bai ON bai.codest = cli.codest AND bai.codmun = cli.codmun AND bai.codbai = cli.codbai INNER JOIN PUB.basecliente bc ON bc.codcli = cli.codcli INNER JOIN PUB.razao raz ON raz.codraz = bc.codraz LEFT JOIN PUB.notafiscal nf ON nf.codped = ped.codped LEFT JOIN PUB.arqrdnt ard ON ard.asdped = ped.asdped WHERE car.codpac = $codPac AND ped.valtotateped > 0 AND ped.tipped != 'RAS' ORDER BY ped.numseqped";

            $resultEntregas = $this->executeJavaConnector('query', $sqlEntregas);
            
            if (!$resultEntregas['success']) {
                Log::warning('Erro ao buscar entregas, continuando sem elas', ['error' => $resultEntregas['error']]);
                $entregas = [];
            } else {
                $entregas = $resultEntregas['data']['results'] ?? [];
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
                // Para SQL queries, não usar escapeshellarg que remove % e outros caracteres
                if ($action === 'query' && str_contains(strtoupper($param), 'SELECT')) {
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
            $conditions[] = "codtrn LIKE '%" . addslashes($filters['codigo']) . "%'";
        }

        if (!empty($filters['nome'])) {
            $conditions[] = "nomtrn LIKE '%" . addslashes($filters['nome']) . "%'";
        }

        if (!empty($filters['data_inicio'])) {
            $conditions[] = "data_criacao >= '" . addslashes($filters['data_inicio']) . "'";
        }

        if (!empty($filters['data_fim'])) {
            $conditions[] = "data_criacao <= '" . addslashes($filters['data_fim']) . "'";
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
}