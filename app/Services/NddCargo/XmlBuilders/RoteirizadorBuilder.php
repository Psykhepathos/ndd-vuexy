<?php

namespace App\Services\NddCargo\XmlBuilders;

use App\Services\NddCargo\DTOs\ConsultarRoteirizadorRequest;
use Illuminate\Support\Str;

/**
 * Builder para construir XML consultarRoteirizador_envio da NDD Cargo
 *
 * Constrói o XML de negócio que será assinado digitalmente e enviado
 * encapsulado dentro de uma mensagem SOAP CrossTalk
 *
 * IMPORTANTE: Este XML será assinado digitalmente, portanto:
 * - O elemento raiz deve ter atributo ID (UUID)
 * - A estrutura deve seguir exatamente o padrão NDD Cargo
 * - Namespace correto: http://www.nddcargo.com.br/
 *
 * @see docs/integracoes/ndd-cargo/ANALISE_NTESTE_PY.md (linhas 50-86)
 */
class RoteirizadorBuilder
{
    /**
     * Namespace do XML NDD Cargo
     */
    private const NDD_NAMESPACE = 'http://www.nddigital.com.br/nddcargo';

    /**
     * Versão do layout XML
     */
    private const VERSAO_LAYOUT = '4.2.12.0';

    /**
     * Constrói XML consultarRoteirizador_envio
     *
     * @param ConsultarRoteirizadorRequest $request Dados da requisição
     * @param string|null $uuid UUID para o atributo ID (gera automaticamente se não informado)
     * @return array ['xml' => string, 'uuid' => string]
     */
    public function build(ConsultarRoteirizadorRequest $request, ?string $uuid = null): array
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

        // infConsultarRoteirizador (elemento principal que será assinado)
        $inf = $xml->createElement('infConsultarRoteirizador');
        $inf->setAttribute('ID', $uuid);
        $root->appendChild($inf);

        // cnpj
        $cnpj = $xml->createElement('cnpj', $request->cnpjEmpresa);
        $inf->appendChild($cnpj);

        // consulta
        $consulta = $xml->createElement('consulta');
        $inf->appendChild($consulta);

        // cnpjContratante
        $cnpjContratante = $xml->createElement('cnpjContratante', $request->cnpjContratante);
        $consulta->appendChild($cnpjContratante);

        // categoriaPedagio
        $categoriaPedagio = $xml->createElement('categoriaPedagio', (string) $request->categoriaPedagio);
        $consulta->appendChild($categoriaPedagio);

        // informacoes
        $informacoes = $xml->createElement('informacoes');
        $consulta->appendChild($informacoes);

        // tipoRotaPadrao
        $tipoRotaPadrao = $xml->createElement('tipoRotaPadrao', (string) $request->tipoRotaPadrao);
        $informacoes->appendChild($tipoRotaPadrao);

        // pontosParada - usa método getPontosParadaSequencial para obter lista ordenada
        $pontosParada = $xml->createElement('pontosParada');
        $informacoes->appendChild($pontosParada);

        // Obter pontos em ordem sequencial (origem -> intermediários -> destino)
        $pontosSequenciais = $request->getPontosParadaSequencial();

        foreach ($pontosSequenciais as $cep) {
            $pontoParada = $xml->createElement('pontoParada');
            $pontosParada->appendChild($pontoParada);

            // Apenas CEP (tipo não é aceito pelo schema)
            $cepElemento = $xml->createElement('cep', $cep);
            $pontoParada->appendChild($cepElemento);
        }

        // configuracaoRoteirizador
        $configuracao = $xml->createElement('configuracaoRoteirizador');
        $informacoes->appendChild($configuracao);

        // evitarPedagios
        $evitarPedagios = $xml->createElement('evitarPedagios', $request->evitarPedagios ? '1' : '0');
        $configuracao->appendChild($evitarPedagios);

        // priorizarRodovias
        $priorizarRodovias = $xml->createElement('priorizarRodovias', $request->priorizarRodovias ? '1' : '0');
        $configuracao->appendChild($priorizarRodovias);

        // tipoRota
        $tipoRota = $xml->createElement('tipoRota', (string) $request->tipoRota);
        $configuracao->appendChild($tipoRota);

        // tipoVeiculo
        $tipoVeiculo = $xml->createElement('tipoVeiculo', (string) $request->tipoVeiculo);
        $configuracao->appendChild($tipoVeiculo);

        // retornarTrecho
        $retornarTrecho = $xml->createElement('retornarTrecho', $request->retornarTrecho ? '1' : '0');
        $configuracao->appendChild($retornarTrecho);

        return [
            'xml' => $xml->saveXML(),
            'uuid' => $uuid
        ];
    }

    /**
     * Constrói XML de forma simplificada (apenas CEPs origem/destino)
     *
     * @param string $cnpjEmpresa
     * @param string $cnpjContratante
     * @param string $cepOrigem
     * @param string $cepDestino
     * @param int $categoriaPedagio
     * @return array ['xml' => string, 'uuid' => string]
     */
    public function buildSimple(
        string $cnpjEmpresa,
        string $cnpjContratante,
        string $cepOrigem,
        string $cepDestino,
        int $categoriaPedagio = 7
    ): array {
        $request = new ConsultarRoteirizadorRequest(
            cnpjEmpresa: $cnpjEmpresa,
            cnpjContratante: $cnpjContratante,
            categoriaPedagio: $categoriaPedagio,
            pontosParada: [
                'origem' => $cepOrigem,
                'destino' => $cepDestino
            ]
        );

        return $this->build($request);
    }
}
