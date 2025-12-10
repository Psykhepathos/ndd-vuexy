<?php

namespace App\Services\Vpo;

use App\Models\VpoEmissaoLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Service para gerenciar logs de auditoria de emissões VPO
 *
 * Centraliza toda a lógica de criação e atualização de logs,
 * garantindo rastreabilidade completa de cada emissão.
 */
class VpoEmissaoLogService
{
    /**
     * Inicia um novo log de emissão VPO
     *
     * @param array $formData Dados do formulário de emissão
     * @param Request|null $request Request HTTP para auditoria
     * @return VpoEmissaoLog
     */
    public function iniciarLog(array $formData, ?Request $request = null): VpoEmissaoLog
    {
        $logData = [
            // Pacote
            'codpac' => $formData['codpac'] ?? null,
            'pacote_situacao' => $formData['pacote_situacao'] ?? null,

            // Transportador
            'codtrn' => $formData['codtrn'] ?? null,
            'transportador_nome' => $formData['transportador_nome'] ?? null,
            'transportador_cpf_cnpj' => $formData['transportador_cpf_cnpj'] ?? null,
            'transportador_autonomo' => $formData['transportador_autonomo'] ?? false,
            'transportador_rntrc' => $formData['transportador_rntrc'] ?? null,

            // Motorista (se empresa)
            'codmot' => $formData['codmot'] ?? null,
            'motorista_nome' => $formData['motorista_nome'] ?? null,
            'motorista_cpf' => $formData['motorista_cpf'] ?? null,

            // Veículo
            'veiculo_placa' => $formData['veiculo_placa'] ?? null,
            'veiculo_modelo' => $formData['veiculo_modelo'] ?? null,
            'veiculo_eixos' => $formData['veiculo_eixos'] ?? null,
            'categoria_pedagio' => $formData['categoria_pedagio'] ?? null,

            // Rota
            'rota_id' => $formData['rota_id'] ?? null,
            'rota_nome' => $formData['rota_nome'] ?? null,
            'rota_municipios_count' => $formData['rota_municipios_count'] ?? null,
            'rota_municipios' => $formData['rota_municipios'] ?? null,

            // Período
            'data_inicio' => $formData['data_inicio'] ?? null,
            'data_fim' => $formData['data_fim'] ?? null,
        ];

        $log = VpoEmissaoLog::iniciar($logData, $request);

        Log::info('VpoEmissaoLog: Log iniciado', [
            'log_id' => $log->id,
            'uuid' => $log->uuid,
            'codpac' => $log->codpac,
            'codtrn' => $log->codtrn,
        ]);

        return $log;
    }

    /**
     * Registra o cálculo de praças via NDD Cargo
     *
     * @param VpoEmissaoLog $log
     * @param string $guid GUID do roteirizador
     * @param array $request Request enviado
     * @return VpoEmissaoLog
     */
    public function registrarRoteirizadorEnviado(VpoEmissaoLog $log, string $guid, array $request): VpoEmissaoLog
    {
        $log->registrarRoteirizadorEnviado($guid, $request);

        Log::info('VpoEmissaoLog: Roteirizador enviado', [
            'log_id' => $log->id,
            'guid' => $guid,
        ]);

        return $log;
    }

    /**
     * Registra resposta do roteirizador
     *
     * @param VpoEmissaoLog $log
     * @param array $response Resposta completa
     * @param array $pracas Array de praças de pedágio
     * @param float $valorTotal Valor total dos pedágios
     * @param float|null $distanciaKm Distância em km
     * @param int|null $tempoMin Tempo estimado em minutos
     * @return VpoEmissaoLog
     */
    public function registrarRoteirizadorResposta(
        VpoEmissaoLog $log,
        array $response,
        array $pracas,
        float $valorTotal,
        ?float $distanciaKm = null,
        ?int $tempoMin = null
    ): VpoEmissaoLog {
        $log->registrarRoteirizadorResposta($response, $pracas, $valorTotal, $distanciaKm, $tempoMin);

        Log::info('VpoEmissaoLog: Roteirizador respondido', [
            'log_id' => $log->id,
            'pracas_count' => count($pracas),
            'valor_total' => $valorTotal,
        ]);

        return $log;
    }

    /**
     * Registra envio da emissão VPO
     *
     * @param VpoEmissaoLog $log
     * @param string $guid GUID da emissão
     * @param array $request Request enviado
     * @return VpoEmissaoLog
     */
    public function registrarEmissaoEnviada(VpoEmissaoLog $log, string $guid, array $request): VpoEmissaoLog
    {
        $log->registrarEmissaoEnviada($guid, $request);

        Log::info('VpoEmissaoLog: Emissão enviada', [
            'log_id' => $log->id,
            'guid' => $guid,
        ]);

        return $log;
    }

    /**
     * Registra resposta da emissão VPO
     *
     * @param VpoEmissaoLog $log
     * @param array $response Resposta completa
     * @param string|null $codigoRetorno Código de retorno NDD
     * @param string|null $mensagem Mensagem de retorno
     * @param string|null $protocolo Protocolo NDD
     * @return VpoEmissaoLog
     */
    public function registrarEmissaoResposta(
        VpoEmissaoLog $log,
        array $response,
        ?string $codigoRetorno = null,
        ?string $mensagem = null,
        ?string $protocolo = null
    ): VpoEmissaoLog {
        $log->registrarEmissaoResposta($response, $codigoRetorno, $mensagem, $protocolo);

        Log::info('VpoEmissaoLog: Emissão respondida', [
            'log_id' => $log->id,
            'codigo_retorno' => $codigoRetorno,
            'protocolo' => $protocolo,
        ]);

        return $log;
    }

    /**
     * Marca log como sucesso
     *
     * @param VpoEmissaoLog $log
     * @param string|null $protocolo Protocolo da emissão
     * @return VpoEmissaoLog
     */
    public function marcarSucesso(VpoEmissaoLog $log, ?string $protocolo = null): VpoEmissaoLog
    {
        $log->marcarSucesso($protocolo);

        Log::info('VpoEmissaoLog: Emissão concluída com sucesso', [
            'log_id' => $log->id,
            'uuid' => $log->uuid,
            'protocolo' => $protocolo,
            'duracao_segundos' => $log->duracao_segundos,
        ]);

        return $log;
    }

    /**
     * Marca log como erro
     *
     * @param VpoEmissaoLog $log
     * @param string $mensagem Mensagem de erro
     * @param string|null $detalhes Detalhes adicionais
     * @return VpoEmissaoLog
     */
    public function marcarErro(VpoEmissaoLog $log, string $mensagem, ?string $detalhes = null): VpoEmissaoLog
    {
        $log->marcarErro($mensagem, $detalhes);

        Log::error('VpoEmissaoLog: Emissão com erro', [
            'log_id' => $log->id,
            'uuid' => $log->uuid,
            'mensagem' => $mensagem,
            'detalhes' => $detalhes,
        ]);

        return $log;
    }

    /**
     * Busca log por UUID
     *
     * @param string $uuid
     * @return VpoEmissaoLog|null
     */
    public function buscarPorUuid(string $uuid): ?VpoEmissaoLog
    {
        return VpoEmissaoLog::where('uuid', $uuid)->first();
    }

    /**
     * Busca log por ID
     *
     * @param int $id
     * @return VpoEmissaoLog|null
     */
    public function buscarPorId(int $id): ?VpoEmissaoLog
    {
        return VpoEmissaoLog::find($id);
    }

    /**
     * Lista logs com paginação e filtros
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listar(array $filters = [], int $perPage = 15)
    {
        $query = VpoEmissaoLog::query()->orderBy('created_at', 'desc');

        // Filtro por status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filtro por transportador
        if (!empty($filters['codtrn'])) {
            $query->where('codtrn', $filters['codtrn']);
        }

        // Filtro por pacote
        if (!empty($filters['codpac'])) {
            $query->where('codpac', $filters['codpac']);
        }

        // Filtro por placa
        if (!empty($filters['placa'])) {
            $query->where('veiculo_placa', 'like', '%' . $filters['placa'] . '%');
        }

        // Filtro por período
        if (!empty($filters['data_inicio']) && !empty($filters['data_fim'])) {
            $query->whereBetween('created_at', [
                $filters['data_inicio'] . ' 00:00:00',
                $filters['data_fim'] . ' 23:59:59',
            ]);
        }

        // Filtro por termo de busca (transportador, placa, rota)
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('transportador_nome', 'like', '%' . $search . '%')
                  ->orWhere('veiculo_placa', 'like', '%' . $search . '%')
                  ->orWhere('rota_nome', 'like', '%' . $search . '%')
                  ->orWhere('uuid', 'like', '%' . $search . '%')
                  ->orWhere('codpac', 'like', '%' . $search . '%');
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Retorna estatísticas dos logs
     *
     * @param string|null $dataInicio
     * @param string|null $dataFim
     * @return array
     */
    public function estatisticas(?string $dataInicio = null, ?string $dataFim = null): array
    {
        $query = VpoEmissaoLog::query();

        if ($dataInicio && $dataFim) {
            $query->whereBetween('created_at', [
                $dataInicio . ' 00:00:00',
                $dataFim . ' 23:59:59',
            ]);
        }

        $total = $query->count();
        $sucesso = (clone $query)->where('status', 'sucesso')->count();
        $erro = (clone $query)->where('status', 'erro')->count();
        $pendente = (clone $query)->whereIn('status', ['iniciado', 'calculando', 'aguardando'])->count();

        $valorTotal = (clone $query)->where('status', 'sucesso')->sum('valor_total_pedagios');
        $pracasTotal = (clone $query)->where('status', 'sucesso')->sum('pracas_count');

        return [
            'total' => $total,
            'sucesso' => $sucesso,
            'erro' => $erro,
            'pendente' => $pendente,
            'taxa_sucesso' => $total > 0 ? round(($sucesso / $total) * 100, 1) : 0,
            'valor_total_pedagios' => $valorTotal,
            'valor_total_formatado' => 'R$ ' . number_format($valorTotal, 2, ',', '.'),
            'pracas_total' => $pracasTotal,
        ];
    }
}
