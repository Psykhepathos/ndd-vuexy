<?php

namespace App\Services\NddCargo\DTOs;

/**
 * DTO para representar uma praça de pedágio retornada pela API NDD Cargo
 *
 * Contém informações sobre localização, concessionária e valores
 * de pedágio retornados na resposta do roteirizador
 */
class PracaPedagioDTO
{
    /**
     * @param int $id ID da praça de pedágio
     * @param string $nome Nome da praça
     * @param string $localizacao Descrição da localização
     * @param string $rodovia Código da rodovia (ex: BR-116, SP-160)
     * @param string $concessionaria Nome da concessionária responsável
     * @param float $valor Valor do pedágio em reais
     * @param string|null $latitude Latitude da praça (opcional)
     * @param string|null $longitude Longitude da praça (opcional)
     * @param int|null $km Quilômetro da rodovia (opcional)
     * @param string|null $sentido Sentido da rodovia (opcional)
     */
    public function __construct(
        public readonly int $id,
        public readonly string $nome,
        public readonly string $localizacao,
        public readonly string $rodovia,
        public readonly string $concessionaria,
        public readonly float $valor,
        public readonly ?string $latitude = null,
        public readonly ?string $longitude = null,
        public readonly ?int $km = null,
        public readonly ?string $sentido = null
    ) {
    }

    /**
     * Converte o DTO para array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'localizacao' => $this->localizacao,
            'rodovia' => $this->rodovia,
            'concessionaria' => $this->concessionaria,
            'valor' => $this->valor,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'km' => $this->km,
            'sentido' => $this->sentido,
        ];
    }

    /**
     * Cria instância a partir de array (resposta XML parseada)
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? $data['ID'] ?? 0),
            nome: $data['nome'] ?? $data['Nome'] ?? '',
            localizacao: $data['localizacao'] ?? $data['Localizacao'] ?? '',
            rodovia: $data['rodovia'] ?? $data['Rodovia'] ?? '',
            concessionaria: $data['concessionaria'] ?? $data['Concessionaria'] ?? '',
            valor: (float) ($data['valor'] ?? $data['Valor'] ?? 0.0),
            latitude: $data['latitude'] ?? $data['Latitude'] ?? null,
            longitude: $data['longitude'] ?? $data['Longitude'] ?? null,
            km: isset($data['km']) || isset($data['KM']) ? (int) ($data['km'] ?? $data['KM']) : null,
            sentido: $data['sentido'] ?? $data['Sentido'] ?? null
        );
    }

    /**
     * Cria instância a partir de nó XML SimpleXMLElement
     *
     * @param \SimpleXMLElement $xmlNode
     * @return self
     */
    public static function fromXml(\SimpleXMLElement $xmlNode): self
    {
        return new self(
            id: (int) ((string) ($xmlNode->id ?? $xmlNode->ID ?? 0)),
            nome: (string) ($xmlNode->nome ?? $xmlNode->Nome ?? ''),
            localizacao: (string) ($xmlNode->localizacao ?? $xmlNode->Localizacao ?? ''),
            rodovia: (string) ($xmlNode->rodovia ?? $xmlNode->Rodovia ?? ''),
            concessionaria: (string) ($xmlNode->concessionaria ?? $xmlNode->Concessionaria ?? ''),
            valor: (float) ((string) ($xmlNode->valor ?? $xmlNode->Valor ?? 0.0)),
            latitude: isset($xmlNode->latitude) || isset($xmlNode->Latitude) ? (string) ($xmlNode->latitude ?? $xmlNode->Latitude) : null,
            longitude: isset($xmlNode->longitude) || isset($xmlNode->Longitude) ? (string) ($xmlNode->longitude ?? $xmlNode->Longitude) : null,
            km: isset($xmlNode->km) || isset($xmlNode->KM) ? (int) ((string) ($xmlNode->km ?? $xmlNode->KM)) : null,
            sentido: isset($xmlNode->sentido) || isset($xmlNode->Sentido) ? (string) ($xmlNode->sentido ?? $xmlNode->Sentido) : null
        );
    }
}
