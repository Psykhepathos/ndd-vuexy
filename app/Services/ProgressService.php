<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
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
                    $searchLen = strlen($searchTerm);
                    $whereConditions[] = "LEFT(nomtrn, $searchLen) = '$searchTerm'";
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
                $whereConditions[] = $ativo === 'true' ? "flgati = 1" : "flgati = 0";
            }
            
            // Montar SQL final
            $simpleSql = "SELECT TOP $perPage $campos FROM PUB.transporte";
            if (!empty($whereConditions)) {
                $simpleSql .= " WHERE " . implode(' AND ', $whereConditions);
            }
            $simpleSql .= " ORDER BY codtrn";
            
            Log::info('SQL simplificado para busca', ['sql' => $simpleSql]);
            
            $result = $this->executeCustomQuery($simpleSql);

            if ($result['success']) {
                // Contar total de registros para paginação (simples sem filtros primeiro)
                $countSql = "SELECT COUNT(*) as total FROM PUB.transporte";
                
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
            Log::info('Buscando transporte por ID via ODBC', ['id' => $id]);

            $transporte = DB::connection($this->connection)
                ->select('SELECT nomtrn, codtrn FROM PUB.transporte WHERE codtrn = ?', [$id]);
            
            if (empty($transporte)) {
                return [
                    'success' => false,
                    'message' => 'Transporte não encontrado',
                    'data' => null
                ];
            }

            Log::info('Transporte encontrado via ODBC', ['transporte_id' => $id]);

            return [
                'success' => true,
                'message' => 'Transporte encontrado com sucesso',
                'data' => $transporte[0]
            ];

        } catch (Exception $e) {
            Log::error('Erro na busca de transporte por ID via ODBC', [
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
                $cmdParts[] = escapeshellarg((string)$param);
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
}