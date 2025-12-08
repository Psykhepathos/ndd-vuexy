<?php

namespace App\Services\NddCargo\DTOs;

use Illuminate\Support\Facades\Log;

/**
 * DTO para resposta completa da consulta de roteirizador NDD Cargo
 *
 * Representa o resultado da consulta incluindo:
 * - Status da operação
 * - Distância total
 * - Tempo estimado
 * - Lista de praças de pedágio
 * - Valor total de pedágios
 * - Trechos da rota (se solicitado)
 *
 * @see docs/integracoes/ndd-cargo/README.md
 */
class RoteirizadorResponse
{
    /**
     * @param bool $sucesso Se a operação foi bem-sucedida
     * @param int $status Código de status (0 = sucesso, 202 = assíncrono, outros = erro)
     * @param string|null $mensagem Mensagem de erro ou informação
     * @param float|null $distanciaKm Distância total em quilômetros
     * @param int|null $tempoMinutos Tempo estimado em minutos
     * @param float|null $valorTotalPedagios Valor total dos pedágios em reais
     * @param array<PracaPedagioDTO> $pracasPedagio Lista de praças de pedágio
     * @param array|null $trechos Trechos detalhados da rota (opcional)
     * @param string|null $guid GUID para consulta assíncrona (quando status = 202)
     * @param array|null $rawData Dados brutos da resposta XML (para debug)
     */
    public function __construct(
        public readonly bool $sucesso,
        public readonly int $status = 0,
        public readonly ?string $mensagem = null,
        public readonly ?float $distanciaKm = null,
        public readonly ?int $tempoMinutos = null,
        public readonly ?float $valorTotalPedagios = null,
        public readonly array $pracasPedagio = [],
        public readonly ?array $trechos = null,
        public readonly ?string $guid = null,
        public readonly ?array $rawData = null
    ) {
    }

    /**
     * Cria resposta de sucesso
     *
     * @param float $distanciaKm
     * @param int $tempoMinutos
     * @param array<PracaPedagioDTO> $pracasPedagio
     * @param array|null $trechos
     * @return self
     */
    public static function success(
        float $distanciaKm,
        int $tempoMinutos,
        array $pracasPedagio,
        ?array $trechos = null
    ): self {
        $valorTotal = array_reduce(
            $pracasPedagio,
            fn($carry, $praca) => $carry + $praca->valor,
            0.0
        );

        return new self(
            sucesso: true,
            status: 0,
            mensagem: 'Rota calculada com sucesso',
            distanciaKm: $distanciaKm,
            tempoMinutos: $tempoMinutos,
            valorTotalPedagios: $valorTotal,
            pracasPedagio: $pracasPedagio,
            trechos: $trechos
        );
    }

    /**
     * Cria resposta de erro
     *
     * @param int $status
     * @param string $mensagem
     * @param array|null $rawData
     * @return self
     */
    public static function error(int $status, string $mensagem, ?array $rawData = null): self
    {
        return new self(
            sucesso: false,
            status: $status,
            mensagem: $mensagem,
            rawData: $rawData
        );
    }

    /**
     * Converte o DTO para array
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = [
            'sucesso' => $this->sucesso,
            'status' => $this->status,
            'mensagem' => $this->mensagem,
            'distancia_km' => $this->distanciaKm,
            'tempo_minutos' => $this->tempoMinutos,
            'valor_total_pedagogios' => $this->valorTotalPedagios,
            'pracas_pedagio' => array_map(fn($praca) => $praca->toArray(), $this->pracasPedagio),
            'quantidade_pracas' => count($this->pracasPedagio),
            'trechos' => $this->trechos,
        ];

        // Incluir GUID apenas quando status = 202 (assíncrono)
        if ($this->guid !== null) {
            $data['guid'] = $this->guid;
        }

        return $data;
    }

    /**
     * Cria instância a partir de XML de resposta da NDD Cargo
     *
     * @param string $xmlString
     * @return self
     * @throws \Exception Se o XML for inválido
     */
    public static function fromXml(string $xmlString): self
    {
        try {
            // Remove BOM e caracteres inválidos
            $xmlString = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $xmlString);

            // Log do XML recebido (DEBUG)
            Log::debug('Parseando XML de resposta NDD Cargo', [
                'xml_preview' => substr($xmlString, 0, 800)
            ]);

            // Log apenas preview e metadados (sem dados sensíveis)
            if (strpos($xmlString, '<ResponseCode>400</ResponseCode>') !== false) {
                Log::error('Resposta NDD Cargo com erro 400', [
                    'xml_preview' => substr($xmlString, 0, 300),
                    'xml_size_bytes' => strlen($xmlString),
                    'response_code' => 400
                ]);
            }

            $xml = new \SimpleXMLElement($xmlString);

            // Verificar se é resposta CrossTalk (com ResponseCode)
            if (isset($xml->CrossTalk_Header) || isset($xml->{'CrossTalk_Header'})) {
                $header = $xml->{'CrossTalk_Header'};
                $responseCode = (int) ((string) ($header->ResponseCode ?? 0));
                $responseMessage = (string) ($header->ResponseCodeMessage ?? '');
                $guid = (string) ($header->GUID ?? '');

                // ResponseCode 202 = Aceito para processamento assíncrono
                if ($responseCode === 202) {
                    return new self(
                        sucesso: false,
                        status: 202,
                        mensagem: $responseMessage,
                        guid: $guid,
                        rawData: ['response_code' => $responseCode]
                    );
                }

                // ResponseCode diferente de 200 = Erro
                if ($responseCode !== 200 && $responseCode !== 0) {
                    return self::error($responseCode, $responseMessage);
                }
            }

            // Navegar até o nó de dados (CrossTalk_Body > retornoConsultarRoteirizador > retConsultarRoteirizador)
            $dataNode = null;

            // Tentar encontrar o nó de resposta (pode estar em CrossTalk_Body ou direto)
            if (isset($xml->CrossTalk_Body)) {
                $body = $xml->CrossTalk_Body;

                // Registrar namespace para acessar elementos com xmlns
                $xml->registerXPathNamespace('ndd', 'http://www.nddigital.com.br/nddcargo');

                // Buscar retornoConsultarRoteirizador
                if (isset($body->retornoConsultarRoteirizador)) {
                    $retorno = $body->retornoConsultarRoteirizador;

                    // Tentar acessar com namespace
                    $retConsultar = $retorno->children('http://www.nddigital.com.br/nddcargo');

                    if (isset($retConsultar->retConsultarRoteirizador)) {
                        $dataNode = $retConsultar->retConsultarRoteirizador;

                        Log::debug('Nó de dados encontrado com namespace', [
                            'node_name' => $dataNode->getName()
                        ]);
                    } elseif (isset($retorno->retConsultarRoteirizador)) {
                        // Fallback sem namespace
                        $dataNode = $retorno->retConsultarRoteirizador;

                        Log::debug('Nó de dados encontrado sem namespace', [
                            'node_name' => $dataNode->getName()
                        ]);
                    }
                }
            }

            // Se não encontrou o nó de dados, tentar no nível raiz (resposta síncrona antiga)
            if ($dataNode === null) {
                Log::debug('Usando nó raiz para parsing (resposta síncrona)');
                $dataNode = $xml;

                // Verificar status da operação (resposta síncrona)
                $status = (int) ((string) ($dataNode->status ?? $dataNode->Status ?? 0));

                if ($status !== 0) {
                    $mensagem = (string) ($dataNode->mensagem ?? $dataNode->Mensagem ?? 'Erro desconhecido');
                    return self::error($status, $mensagem);
                }
            }

            // Parse dos dados da rota do nó correto
            $distanciaKm = (float) ((string) ($dataNode->totalKm ?? $dataNode->TotalKm ?? $dataNode->distancia ?? $dataNode->Distancia ?? 0));
            $tempoMinutos = (int) ((string) ($dataNode->tempo ?? $dataNode->Tempo ?? 0));

            // Parse das praças de pedágio
            $pracasPedagio = [];
            if (isset($dataNode->pracas) || isset($dataNode->Pracas)) {
                $pracasNode = $dataNode->pracas ?? $dataNode->Pracas;

                foreach ($pracasNode->children() as $pracaNode) {
                    $pracasPedagio[] = PracaPedagioDTO::fromXml($pracaNode);
                }
            }

            // Parse dos trechos (opcional)
            $trechos = null;
            if (isset($dataNode->trechos) || isset($dataNode->Trechos)) {
                $trechosNode = $dataNode->trechos ?? $dataNode->Trechos;
                $trechos = [];

                foreach ($trechosNode->children() as $trechoNode) {
                    $origem = (string) ($trechoNode->origem ?? $trechoNode->Origem ?? '');
                    $destino = (string) ($trechoNode->destino ?? $trechoNode->Destino ?? '');
                    $distancia = (float) ((string) ($trechoNode->distancia ?? $trechoNode->Distancia ?? 0));
                    $tempo = (int) ((string) ($trechoNode->tempo ?? $trechoNode->Tempo ?? 0));

                    // Apenas adicionar trechos com dados válidos
                    if ($origem !== '' || $destino !== '' || $distancia > 0 || $tempo > 0) {
                        $trechos[] = [
                            'origem' => $origem,
                            'destino' => $destino,
                            'distancia' => $distancia,
                            'tempo' => $tempo,
                        ];
                    }
                }

                // Se não houver trechos válidos, retornar null ao invés de array vazio
                if (empty($trechos)) {
                    $trechos = null;
                }
            }

            Log::debug('Dados parseados da resposta NDD Cargo', [
                'distancia_km' => $distanciaKm,
                'tempo_minutos' => $tempoMinutos,
                'quantidade_pracas' => count($pracasPedagio)
            ]);

            return self::success($distanciaKm, $tempoMinutos, $pracasPedagio, $trechos);

        } catch (\Exception $e) {
            return self::error(-1, 'Erro ao processar XML de resposta: ' . $e->getMessage());
        }
    }
}
