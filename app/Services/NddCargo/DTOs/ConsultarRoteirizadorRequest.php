<?php

namespace App\Services\NddCargo\DTOs;

/**
 * DTO para requisição de consulta de roteirizador NDD Cargo
 *
 * Representa os dados necessários para consultar rotas e praças de pedágio
 * através da API NDD Cargo (ProcessCode 2027)
 *
 * @see docs/integracoes/ndd-cargo/README.md
 * @see docs/integracoes/ndd-cargo/ANALISE_NTESTE_PY.md (linhas 50-86)
 */
class ConsultarRoteirizadorRequest
{
    /**
     * @param string $cnpjEmpresa CNPJ da empresa solicitante (14 dígitos)
     * @param string $cnpjContratante CNPJ do contratante do serviço (14 dígitos)
     * @param int $categoriaPedagio Categoria do pedágio (1-7, padrão: 7 = caminhão pesado 6+ eixos)
     * @param array $pontosParada Array de CEPs ['origem' => '01310100', 'destino' => '20040020']
     * @param int $tipoRotaPadrao Tipo de rota (1=menor custo, 2=menor tempo, 3=menor distância)
     * @param bool $evitarPedagios Se deve evitar pedágios (0=não, 1=sim)
     * @param bool $priorizarRodovias Se deve priorizar rodovias (0=não, 1=sim)
     * @param int $tipoRota Tipo de rota (1=asfalto, 2=terra, 3=mista)
     * @param int $tipoVeiculo Tipo de veículo (1=passeio, 2=caminhão, 3=ônibus, 4=caminhão trator)
     * @param bool $retornarTrecho Se deve retornar trechos detalhados (0=não, 1=sim)
     */
    public function __construct(
        public readonly string $cnpjEmpresa,
        public readonly string $cnpjContratante,
        public readonly int $categoriaPedagio = 7,
        public readonly array $pontosParada = [],
        public readonly int $tipoRotaPadrao = 1,
        public readonly bool $evitarPedagios = false,
        public readonly bool $priorizarRodovias = true,
        public readonly int $tipoRota = 1,
        public readonly int $tipoVeiculo = 2,
        public readonly bool $retornarTrecho = false
    ) {
        $this->validate();
    }

    /**
     * Valida os dados do request
     *
     * @throws \InvalidArgumentException Se algum dado for inválido
     */
    private function validate(): void
    {
        // Validar CNPJs
        if (!preg_match('/^\d{14}$/', $this->cnpjEmpresa)) {
            throw new \InvalidArgumentException('CNPJ da empresa deve conter 14 dígitos');
        }

        if (!preg_match('/^\d{14}$/', $this->cnpjContratante)) {
            throw new \InvalidArgumentException('CNPJ do contratante deve conter 14 dígitos');
        }

        // Validar categoria de pedágio (1-7)
        if ($this->categoriaPedagio < 1 || $this->categoriaPedagio > 7) {
            throw new \InvalidArgumentException('Categoria de pedágio deve estar entre 1 e 7');
        }

        // Validar pontos de parada
        if (empty($this->pontosParada)) {
            throw new \InvalidArgumentException('Pontos de parada não podem estar vazios');
        }

        // Aceita dois formatos:
        // 1. ['origem' => 'CEP1', 'destino' => 'CEP2'] (simples)
        // 2. ['origem' => 'CEP1', 'intermediarios' => ['CEP2', 'CEP3'], 'destino' => 'CEP4'] (completo)
        if (isset($this->pontosParada['origem']) && isset($this->pontosParada['destino'])) {
            // Formato estruturado - validar CEPs
            if (!preg_match('/^\d{8}$/', $this->pontosParada['origem'])) {
                throw new \InvalidArgumentException('CEP de origem deve conter 8 dígitos');
            }
            if (!preg_match('/^\d{8}$/', $this->pontosParada['destino'])) {
                throw new \InvalidArgumentException('CEP de destino deve conter 8 dígitos');
            }
            // Validar intermediários se existirem
            if (isset($this->pontosParada['intermediarios']) && is_array($this->pontosParada['intermediarios'])) {
                foreach ($this->pontosParada['intermediarios'] as $cep) {
                    if (!preg_match('/^\d{8}$/', $cep)) {
                        throw new \InvalidArgumentException('CEP intermediário deve conter 8 dígitos');
                    }
                }
            }
        } else {
            throw new \InvalidArgumentException('Pontos de parada devem conter origem e destino');
        }

        // Validar tipo de rota padrão (1-3)
        if ($this->tipoRotaPadrao < 1 || $this->tipoRotaPadrao > 3) {
            throw new \InvalidArgumentException('Tipo de rota padrão deve estar entre 1 e 3');
        }

        // Validar tipo de rota (1-3)
        if ($this->tipoRota < 1 || $this->tipoRota > 3) {
            throw new \InvalidArgumentException('Tipo de rota deve estar entre 1 e 3');
        }

        // Validar tipo de veículo (1-4: 1=passeio, 2=caminhão, 3=ônibus, 4=caminhão trator)
        if ($this->tipoVeiculo < 1 || $this->tipoVeiculo > 4) {
            throw new \InvalidArgumentException('Tipo de veículo deve estar entre 1 e 4 (1=passeio, 2=caminhão, 3=ônibus, 4=caminhão trator)');
        }
    }

    /**
     * Retorna os pontos de parada em formato de lista sequencial para o XML
     *
     * @return array Lista de CEPs na ordem: [origem, ...intermediarios, destino]
     */
    public function getPontosParadaSequencial(): array
    {
        $pontos = [$this->pontosParada['origem']];

        if (isset($this->pontosParada['intermediarios']) && is_array($this->pontosParada['intermediarios'])) {
            $pontos = array_merge($pontos, $this->pontosParada['intermediarios']);
        }

        $pontos[] = $this->pontosParada['destino'];

        return $pontos;
    }

    /**
     * Converte o DTO para array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'cnpj_empresa' => $this->cnpjEmpresa,
            'cnpj_contratante' => $this->cnpjContratante,
            'categoria_pedagio' => $this->categoriaPedagio,
            'pontos_parada' => $this->pontosParada,
            'tipo_rota_padrao' => $this->tipoRotaPadrao,
            'evitar_pedagogios' => $this->evitarPedagios,
            'priorizar_rodovias' => $this->priorizarRodovias,
            'tipo_rota' => $this->tipoRota,
            'tipo_veiculo' => $this->tipoVeiculo,
            'retornar_trecho' => $this->retornarTrecho,
        ];
    }

    /**
     * Cria instância a partir de array
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            cnpjEmpresa: $data['cnpj_empresa'] ?? '',
            cnpjContratante: $data['cnpj_contratante'] ?? '',
            categoriaPedagio: $data['categoria_pedagio'] ?? 7,
            pontosParada: $data['pontos_parada'] ?? [],
            tipoRotaPadrao: $data['tipo_rota_padrao'] ?? 1,
            evitarPedagios: $data['evitar_pedagogios'] ?? false,
            priorizarRodovias: $data['priorizar_rodovias'] ?? false,
            tipoRota: $data['tipo_rota'] ?? 1,
            tipoVeiculo: $data['tipo_veiculo'] ?? 5,
            retornarTrecho: $data['retornar_trecho'] ?? false
        );
    }
}
