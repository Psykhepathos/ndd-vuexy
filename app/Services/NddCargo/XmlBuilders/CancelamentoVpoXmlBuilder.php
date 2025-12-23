<?php

namespace App\Services\NddCargo\XmlBuilders;

use Illuminate\Support\Str;

/**
 * Builder para XML de Cancelamento de Operacao Vale Pedagio (CAVP) da NDD Cargo
 *
 * Nomenclatura do arquivo: EnvCavp_nomeQualquer.xml
 * ProcessCode: 2020 (Cancelamento VPO)
 *
 * Estrutura baseada na documentação oficial NDD Cargo 4.2.12.0:
 * - cancelarOperacaoValePedagio_envio (raiz)
 *   - versao (atributo)
 *   - token (atributo)
 *   - infCancelarOperacaoValePedagio
 *     - Id (atributo para assinatura)
 *     - cnpj (contratante)
 *     - autorizacao
 *       - cnpj
 *       - ndvp OU ide (identificação da operação)
 *     - motivoCancelamento
 *   - ds:Signature (assinatura digital)
 *
 * @see docs/integracoes/ndd-cargo/CANCELAMENTO_VPO.md
 */
class CancelamentoVpoXmlBuilder
{
    private const NDD_NAMESPACE = 'http://www.nddigital.com.br/nddcargo';
    private const VERSAO_LAYOUT = '4.2.12.0';

    /**
     * Constrói XML de Cancelamento de Operação de Vale-Pedágio
     *
     * @param string $cnpjContratante CNPJ da contratante (14 dígitos)
     * @param string $motivoCancelamento Motivo do cancelamento (1-500 chars)
     * @param array $identificacao Identificação da operação (ndvp ou ide)
     *        - Para NDVP: ['tipo' => 'ndvp', 'numero' => '123456789012', 'codVerificador' => '1234']
     *        - Para IDE: ['tipo' => 'ide', 'numero' => '123456', 'serie' => '1016']
     * @param string|null $uuid UUID para assinatura (opcional)
     * @return array ['xml' => string, 'uuid' => string]
     */
    public function build(
        string $cnpjContratante,
        string $motivoCancelamento,
        array $identificacao,
        ?string $uuid = null
    ): array {
        $uuid = $uuid ?? Str::uuid()->toString();

        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $xml->preserveWhiteSpace = false;

        // Elemento raiz: cancelarOperacaoValePedagio_envio
        $root = $xml->createElementNS(self::NDD_NAMESPACE, 'cancelarOperacaoValePedagio_envio');
        $root->setAttribute('versao', self::VERSAO_LAYOUT);
        $root->setAttribute('token', config('nddcargo.token'));
        $xml->appendChild($root);

        // infCancelarOperacaoValePedagio (elemento que será assinado)
        $inf = $xml->createElement('infCancelarOperacaoValePedagio');
        $inf->setAttribute('Id', $uuid);
        $root->appendChild($inf);

        // cnpj da contratante (raiz do infCancelarOperacaoValePedagio)
        $cnpjLimpo = preg_replace('/[^0-9]/', '', $cnpjContratante);
        $cnpjLimpo = str_pad($cnpjLimpo, 14, '0', STR_PAD_LEFT);
        $this->addElement($xml, $inf, 'cnpj', $cnpjLimpo);

        // autorizacao - Dados da autorização a ser cancelada
        $autorizacao = $xml->createElement('autorizacao');
        $inf->appendChild($autorizacao);

        // cnpj dentro de autorizacao (pode ser diferente da contratante em alguns casos)
        $this->addElement($xml, $autorizacao, 'cnpj', $cnpjLimpo);

        // Identificação: NDVP ou IDE
        if (($identificacao['tipo'] ?? 'ide') === 'ndvp') {
            // Identificação por NDVP (Número de Declaração de Vale-Pedágio)
            $ndvp = $xml->createElement('ndvp');
            $autorizacao->appendChild($ndvp);

            $this->addElement($xml, $ndvp, 'numero', $identificacao['numero'] ?? '');
            $this->addElement($xml, $ndvp, 'ndvpCodVerificador', $identificacao['codVerificador'] ?? '');
        } else {
            // Identificação por IDE (número/série internos)
            $ide = $xml->createElement('ide');
            $autorizacao->appendChild($ide);

            $this->addElement($xml, $ide, 'numero', $identificacao['numero'] ?? '');
            $this->addElement($xml, $ide, 'serie', $identificacao['serie'] ?? '1016');
        }

        // motivoCancelamento (obrigatório, 1-500 caracteres)
        $motivo = substr(trim($motivoCancelamento), 0, 500);
        if (empty($motivo)) {
            $motivo = 'Cancelamento solicitado pelo usuário';
        }
        $this->addElement($xml, $inf, 'motivoCancelamento', $motivo);

        return [
            'xml' => $xml->saveXML(),
            'uuid' => $uuid
        ];
    }

    /**
     * Constrói XML de cancelamento usando NDVP (forma preferida)
     *
     * @param string $numeroNdvp Número do NDVP (12 dígitos)
     * @param string $codVerificador Código verificador do NDVP (4 dígitos)
     * @param string $motivoCancelamento Motivo do cancelamento
     * @param string|null $cnpjContratante CNPJ (se null, usa config)
     * @return array ['xml' => string, 'uuid' => string]
     */
    public function buildByNdvp(
        string $numeroNdvp,
        string $codVerificador,
        string $motivoCancelamento,
        ?string $cnpjContratante = null
    ): array {
        $cnpj = $cnpjContratante ?? config('nddcargo.cnpj_empresa');

        return $this->build($cnpj, $motivoCancelamento, [
            'tipo' => 'ndvp',
            'numero' => preg_replace('/[^0-9]/', '', $numeroNdvp),
            'codVerificador' => preg_replace('/[^0-9]/', '', $codVerificador),
        ]);
    }

    /**
     * Constrói XML de cancelamento usando IDE (número/série internos)
     *
     * @param string $numero Número da operação
     * @param string $serie Série da operação
     * @param string $motivoCancelamento Motivo do cancelamento
     * @param string|null $cnpjContratante CNPJ (se null, usa config)
     * @return array ['xml' => string, 'uuid' => string]
     */
    public function buildByIde(
        string $numero,
        string $serie,
        string $motivoCancelamento,
        ?string $cnpjContratante = null
    ): array {
        $cnpj = $cnpjContratante ?? config('nddcargo.cnpj_empresa');

        return $this->build($cnpj, $motivoCancelamento, [
            'tipo' => 'ide',
            'numero' => $numero,
            'serie' => $serie,
        ]);
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
