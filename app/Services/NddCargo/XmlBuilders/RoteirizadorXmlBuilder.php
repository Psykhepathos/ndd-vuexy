<?php

namespace App\Services\NddCargo\XmlBuilders;

use Illuminate\Support\Str;

/**
 * Builder para XML de Consulta ao Roteirizador da NDD Cargo
 *
 * ProcessCode: 2027, ExchangePattern: 7 (sincrono)
 * Retorna as praças de pedágio para uma rota definida por pontosParada
 */
class RoteirizadorXmlBuilder
{
    private const NDD_NAMESPACE = 'http://www.nddigital.com.br/nddcargo';
    private const VERSAO_LAYOUT = '4.2.12.0';

    /**
     * Constroi XML de consulta ao roteirizador
     *
     * @param array $waypoints Array de waypoints com CEP ou codigoIBGE
     * @param int $categoriaPedagio Categoria do veículo (1-7)
     * @param string|null $uuid UUID para assinatura
     * @return array ['xml' => string, 'uuid' => string]
     */
    public function build(array $waypoints, int $categoriaPedagio = 3, ?string $uuid = null): array
    {
        $uuid = $uuid ?? Str::uuid()->toString();

        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $xml->preserveWhiteSpace = false;

        // Elemento raiz: consultarRoteirizador_envio
        $root = $xml->createElementNS(self::NDD_NAMESPACE, 'consultarRoteirizador_envio');
        $root->setAttribute('versao', self::VERSAO_LAYOUT);
        $root->setAttribute('token', config('nddcargo.token'));
        $xml->appendChild($root);

        // infConsultarRoteirizador (elemento que sera assinado)
        $inf = $xml->createElement('infConsultarRoteirizador');
        $inf->setAttribute('ID', $uuid);
        $root->appendChild($inf);

        // cnpj da empresa
        $cnpjEmpresa = config('nddcargo.cnpj_empresa');
        $this->addElement($xml, $inf, 'cnpj', $cnpjEmpresa);

        // consulta
        $consulta = $xml->createElement('consulta');
        $inf->appendChild($consulta);

        $this->addElement($xml, $consulta, 'cnpjContratante', $cnpjEmpresa);
        $this->addElement($xml, $consulta, 'categoriaPedagio', (string) $categoriaPedagio);

        // informacoes
        $informacoes = $xml->createElement('informacoes');
        $consulta->appendChild($informacoes);

        $this->addElement($xml, $informacoes, 'tipoRotaPadrao', '1');

        // pontosParada
        $pontosParada = $xml->createElement('pontosParada');
        $informacoes->appendChild($pontosParada);

        foreach ($waypoints as $waypoint) {
            $pontoParada = $xml->createElement('pontoParada');
            $pontosParada->appendChild($pontoParada);

            // Preferir codigoIBGE, se não tiver usar CEP
            if (!empty($waypoint['cdibge'])) {
                $cdibge = preg_replace('/[^0-9]/', '', $waypoint['cdibge']);
                $this->addElement($xml, $pontoParada, 'codigoIBGE', $cdibge);
            } elseif (!empty($waypoint['cep'])) {
                $cep = preg_replace('/[^0-9]/', '', $waypoint['cep']);
                $this->addElement($xml, $pontoParada, 'cep', $cep);
            }
        }

        // configuracaoRoteirizador
        $configRoteirizador = $xml->createElement('configuracaoRoteirizador');
        $informacoes->appendChild($configRoteirizador);

        $this->addElement($xml, $configRoteirizador, 'evitarPedagios', '0');
        $this->addElement($xml, $configRoteirizador, 'priorizarRodovias', '1');
        $this->addElement($xml, $configRoteirizador, 'tipoRota', '1');
        $this->addElement($xml, $configRoteirizador, 'tipoVeiculo', '2');
        $this->addElement($xml, $configRoteirizador, 'retornarTrecho', '1');

        return [
            'xml' => $xml->saveXML(),
            'uuid' => $uuid
        ];
    }

    /**
     * Helper para adicionar elemento ao DOM
     */
    private function addElement(\DOMDocument $xml, \DOMElement $parent, string $name, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $trimmedValue = trim($value);
            if ($trimmedValue !== '') {
                $element = $xml->createElement($name, htmlspecialchars($trimmedValue, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
                $parent->appendChild($element);
            }
        }
    }
}
