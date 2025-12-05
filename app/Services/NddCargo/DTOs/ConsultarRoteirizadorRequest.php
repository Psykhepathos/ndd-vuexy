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
     * @param int $categoriaPedagio Categoria do pedágio (1-7, recomendado: 7 para caminhão pesado)
     * @param array $pontosParada Array de CEPs ['origem' => '01310100', 'destino' => '20040020']
     * @param int $tipoRotaPadrao Tipo de rota (1=menor custo, 2=menor tempo, 3=menor distância)
     * @param bool $evitarPedagios Se deve evitar pedágios (0=não, 1=sim)
     * @param bool $priorizarRodovias Se deve priorizar rodovias (0=não, 1=sim)
     * @param int $tipoRota Tipo de rota (1=cidade-cidade, 2=ponto-ponto, 3=trajeto)
     * @param int $tipoVeiculo Tipo de veículo (1-10, ex: 1=carro, 5=caminhão)
     * @param bool $retornarTrecho Se deve retornar trechos detalhados (0=não, 1=sim)
     */
    public function __construct(
        public readonly string $cnpjEmpresa,
        public readonly string $cnpjContratante,
        public readonly int $categoriaPedagio = 7,
        public readonly array $pontosParada = [],
        public readonly int $tipoRotaPadrao = 1,
        public readonly bool $evitarPedagios = false,
        public readonly bool $priorizarRodovias = false,
        public readonly int $tipoRota = 1,
        public readonly int $tipoVeiculo = 5,
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

        if (!isset($this->pontosParada['origem']) || !isset($this->pontosParada['destino'])) {
            throw new \InvalidArgumentException('Pontos de parada devem conter origem e destino');
        }

        // Validar CEPs (8 dígitos)
        foreach ($this->pontosParada as $cep) {
            if (!preg_match('/^\d{8}$/', $cep)) {
                throw new \InvalidArgumentException('CEP deve conter 8 dígitos');
            }
        }

        // Validar tipo de rota padrão (1-3)
        if ($this->tipoRotaPadrao < 1 || $this->tipoRotaPadrao > 3) {
            throw new \InvalidArgumentException('Tipo de rota padrão deve estar entre 1 e 3');
        }

        // Validar tipo de rota (1-3)
        if ($this->tipoRota < 1 || $this->tipoRota > 3) {
            throw new \InvalidArgumentException('Tipo de rota deve estar entre 1 e 3');
        }

        // Validar tipo de veículo (1-10)
        if ($this->tipoVeiculo < 1 || $this->tipoVeiculo > 10) {
            throw new \InvalidArgumentException('Tipo de veículo deve estar entre 1 e 10');
        }
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
