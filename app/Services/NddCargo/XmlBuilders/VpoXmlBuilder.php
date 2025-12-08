<?php

namespace App\Services\NddCargo\XmlBuilders;

use Illuminate\Support\Str;

/**
 * Builder para XML de emissão VPO (Vale Pedágio Obrigatório)
 *
 * Constrói XML com 19 campos VPO + waypoints para submissão à NDD Cargo
 */
class VpoXmlBuilder
{
    private const NDD_NAMESPACE = 'http://www.nddigital.com.br/nddcargo';
    private const VERSAO_LAYOUT = '4.2.12.0';

    /**
     * Constrói XML de emissão VPO
     *
     * @param array $vpoData 19 campos VPO
     * @param array $waypoints Array de coordenadas [['lat' => float, 'lon' => float, 'tipo' => string], ...]
     * @param string|null $uuid UUID para assinatura
     * @return array ['xml' => string, 'uuid' => string]
     */
    public function build(array $vpoData, array $waypoints, ?string $uuid = null): array
    {
        $uuid = $uuid ?? Str::uuid()->toString();

        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $xml->preserveWhiteSpace = false;

        // Elemento raiz
        $root = $xml->createElementNS(self::NDD_NAMESPACE, 'emitirVPO_envio');
        $root->setAttribute('versao', self::VERSAO_LAYOUT);
        $root->setAttribute('token', config('nddcargo.token'));
        $xml->appendChild($root);

        // infEmitirVPO (elemento que será assinado)
        $inf = $xml->createElement('infEmitirVPO');
        $inf->setAttribute('ID', $uuid);
        $root->appendChild($inf);

        // Transportador
        $transportador = $xml->createElement('transportador');
        $inf->appendChild($transportador);

        $this->addElement($xml, $transportador, 'cpfCnpj', $vpoData['cpf_cnpj']);
        $this->addElement($xml, $transportador, 'anttRntrc', $vpoData['antt_rntrc']);
        $this->addElement($xml, $transportador, 'anttNome', $vpoData['antt_nome']);
        $this->addElement($xml, $transportador, 'anttValidade', $vpoData['antt_validade']);
        $this->addElement($xml, $transportador, 'anttStatus', $vpoData['antt_status']);

        // Veículo
        $veiculo = $xml->createElement('veiculo');
        $inf->appendChild($veiculo);

        $this->addElement($xml, $veiculo, 'placa', $vpoData['placa']);
        $this->addElement($xml, $veiculo, 'tipo', $vpoData['veiculo_tipo']);
        $this->addElement($xml, $veiculo, 'modelo', $vpoData['veiculo_modelo']);

        // Condutor
        $condutor = $xml->createElement('condutor');
        $inf->appendChild($condutor);

        $this->addElement($xml, $condutor, 'rg', $vpoData['condutor_rg']);
        $this->addElement($xml, $condutor, 'nome', $vpoData['condutor_nome']);
        $this->addElement($xml, $condutor, 'sexo', $vpoData['condutor_sexo']);
        $this->addElement($xml, $condutor, 'nomeMae', $vpoData['condutor_nome_mae']);
        $this->addElement($xml, $condutor, 'dataNascimento', $vpoData['condutor_data_nascimento']);

        // Endereço
        $endereco = $xml->createElement('endereco');
        $inf->appendChild($endereco);

        $this->addElement($xml, $endereco, 'rua', $vpoData['endereco_rua']);
        $this->addElement($xml, $endereco, 'bairro', $vpoData['endereco_bairro']);
        $this->addElement($xml, $endereco, 'cidade', $vpoData['endereco_cidade']);
        $this->addElement($xml, $endereco, 'estado', $vpoData['endereco_estado']);

        // Contato
        $contato = $xml->createElement('contato');
        $inf->appendChild($contato);

        $this->addElement($xml, $contato, 'celular', $vpoData['contato_celular']);
        $this->addElement($xml, $contato, 'email', $vpoData['contato_email']);

        // Rota (waypoints)
        $rota = $xml->createElement('rota');
        $inf->appendChild($rota);

        $pontosRota = $xml->createElement('pontosRota');
        $rota->appendChild($pontosRota);

        foreach ($waypoints as $index => $waypoint) {
            $ponto = $xml->createElement('pontoRota');
            $pontosRota->appendChild($ponto);

            $this->addElement($xml, $ponto, 'sequencia', (string) ($index + 1));
            $this->addElement($xml, $ponto, 'latitude', (string) $waypoint['lat']);
            $this->addElement($xml, $ponto, 'longitude', (string) $waypoint['lon']);
            $this->addElement($xml, $ponto, 'tipo', $waypoint['tipo'] ?? 'rota');

            if (isset($waypoint['nome'])) {
                $this->addElement($xml, $ponto, 'descricao', $waypoint['nome']);
            }
        }

        return [
            'xml' => $xml->saveXML(),
            'uuid' => $uuid
        ];
    }

    /**
     * Helper para adicionar elemento ao DOM (só se não vazio)
     */
    private function addElement(\DOMDocument $xml, \DOMElement $parent, string $name, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $element = $xml->createElement($name, htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            $parent->appendChild($element);
        }
    }
}
