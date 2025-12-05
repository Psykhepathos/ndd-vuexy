<?php

namespace App\Services\NddCargo\DTOs;

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
     * @param int $status Código de status (0 = sucesso, outros = erro)
     * @param string|null $mensagem Mensagem de erro ou informação
     * @param float|null $distanciaKm Distância total em quilômetros
     * @param int|null $tempoMinutos Tempo estimado em minutos
     * @param float|null $valorTotalPedagios Valor total dos pedágios em reais
     * @param array<PracaPedagioDTO> $pracasPedagio Lista de praças de pedágio
     * @param array|null $trechos Trechos detalhados da rota (opcional)
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
        return [
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

            $xml = new \SimpleXMLElement($xmlString);

            // Verificar status da operação
            $status = (int) ((string) ($xml->status ?? $xml->Status ?? 0));

            if ($status !== 0) {
                $mensagem = (string) ($xml->mensagem ?? $xml->Mensagem ?? 'Erro desconhecido');
                return self::error($status, $mensagem);
            }

            // Parse dos dados da rota
            $distanciaKm = (float) ((string) ($xml->distancia ?? $xml->Distancia ?? 0));
            $tempoMinutos = (int) ((string) ($xml->tempo ?? $xml->Tempo ?? 0));

            // Parse das praças de pedágio
            $pracasPedagio = [];
            if (isset($xml->pracas) || isset($xml->Pracas)) {
                $pracasNode = $xml->pracas ?? $xml->Pracas;

                foreach ($pracasNode->children() as $pracaNode) {
                    $pracasPedagio[] = PracaPedagioDTO::fromXml($pracaNode);
                }
            }

            // Parse dos trechos (opcional)
            $trechos = null;
            if (isset($xml->trechos) || isset($xml->Trechos)) {
                $trechosNode = $xml->trechos ?? $xml->Trechos;
                $trechos = [];

                foreach ($trechosNode->children() as $trechoNode) {
                    $trechos[] = [
                        'origem' => (string) ($trechoNode->origem ?? $trechoNode->Origem ?? ''),
                        'destino' => (string) ($trechoNode->destino ?? $trechoNode->Destino ?? ''),
                        'distancia' => (float) ((string) ($trechoNode->distancia ?? $trechoNode->Distancia ?? 0)),
                        'tempo' => (int) ((string) ($trechoNode->tempo ?? $trechoNode->Tempo ?? 0)),
                    ];
                }
            }

            return self::success($distanciaKm, $tempoMinutos, $pracasPedagio, $trechos);

        } catch (\Exception $e) {
            return self::error(-1, 'Erro ao processar XML de resposta: ' . $e->getMessage());
        }
    }
}
