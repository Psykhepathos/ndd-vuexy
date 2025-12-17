<?php

namespace App\Services\NddCargo\XmlBuilders;

use Illuminate\Support\Str;

/**
 * Builder para XML de Operacao Vale Pedagio (OVP) da NDD Cargo
 *
 * Estrutura baseada no SOAP UI Project REAL (Cargo Projeto Doug-soapui-project.xml)
 * ProcessCode: 2019, ExchangePattern: 7 (sincrono)
 *
 * IMPORTANTE: A estrutura segue EXATAMENTE o padrao do SOAP UI:
 * - Id (nao ID maiusculo!)
 * - tipoPagamento obrigatorio
 * - infTransportador com estrutura completa (tac, endereco, telefone)
 * - rota/rotaERP (nao rotaERP direto)
 * - informacoes dentro de veiculo
 * - SEM pontosParada (isso e do roteirizador, nao do OVP!)
 */
class VpoXmlBuilder
{
    private const NDD_NAMESPACE = 'http://www.nddigital.com.br/nddcargo';
    private const VERSAO_LAYOUT = '4.2.12.0';

    /**
     * Constroi XML de Operacao Vale Pedagio (OVP)
     *
     * @param array $vpoData Dados do VPO (transportador, veiculo, etc)
     * @param array $waypoints Array de waypoints (usado apenas para rotaERP)
     * @param string|null $uuid UUID para assinatura
     * @param array $pracas Array de praças de pedágio (do roteirizador)
     * @param string|null $codigoTag Código da TAG SemParar (de sParargetExtra.tag)
     * @return array ['xml' => string, 'uuid' => string]
     */
    public function build(array $vpoData, array $waypoints, ?string $uuid = null, array $pracas = [], ?string $codigoTag = null): array
    {
        $uuid = $uuid ?? Str::uuid()->toString();

        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $xml->preserveWhiteSpace = false;

        // Elemento raiz: operacaoValePedagio_envio
        $root = $xml->createElementNS(self::NDD_NAMESPACE, 'operacaoValePedagio_envio');
        $root->setAttribute('versao', self::VERSAO_LAYOUT);
        $root->setAttribute('token', config('nddcargo.token'));
        $xml->appendChild($root);

        // infOperacaoValePedagio (elemento que sera assinado)
        // IMPORTANTE: Id com i minusculo + tipoPagamento obrigatorio!
        $inf = $xml->createElement('infOperacaoValePedagio');
        $inf->setAttribute('Id', $uuid);  // Id nao ID!
        $inf->setAttribute('tipoPagamento', '1');  // 1 = Pagamento padrao
        $root->appendChild($inf);

        // cnpj da empresa (raiz)
        $cnpjEmpresa = config('nddcargo.cnpj_empresa');
        $this->addElement($xml, $inf, 'cnpj', $cnpjEmpresa);

        // ide - Identificacao da operacao
        $ide = $xml->createElement('ide');
        $inf->appendChild($ide);

        $this->addElement($xml, $ide, 'cnpj', $cnpjEmpresa);  // cnpj repetido dentro de ide
        // numero deve ser sequencial curto (max 6 digitos baseado no SOAP UI: "9991", "128178")
        $numero = (string) rand(100000, 999999);
        $this->addElement($xml, $ide, 'numero', $numero);
        // serie tambem deve ser numero curto (ex: "1016", "1")
        $this->addElement($xml, $ide, 'serie', '1016');
        // ptEmissor: NomeERP do Ponto Emissor cadastrado no NDD Cargo
        // PODE ser diferente do CNPJ da empresa!
        $ptEmissor = config('nddcargo.pt_emissor', $cnpjEmpresa);
        $this->addElement($xml, $ide, 'ptEmissor', $ptEmissor);
        // dataFinal é OPCIONAL - se não informada, NDD define automaticamente como +30 dias
        // NÃO enviar para evitar erro 751 "Data de término inválida"

        // transportador
        $transportador = $xml->createElement('transportador');
        $inf->appendChild($transportador);

        // rntrc DEVE ter 9 digitos (com zeros a esquerda se necessario)
        $rntrc = preg_replace('/[^0-9]/', '', $vpoData['antt_rntrc'] ?? '');
        $rntrc = str_pad($rntrc, 9, '0', STR_PAD_LEFT);
        $this->addElement($xml, $transportador, 'rntrc', $rntrc);

        // cpfTransportador OU cnpjTransportador (depende do TAMANHO do documento!)
        // CPF (11 dígitos) → cpfTransportador
        // CNPJ (14 dígitos) → cnpjTransportador
        // IMPORTANTE: O tamanho do documento tem prioridade sobre flgautonomo
        // porque alguns registros têm dados inconsistentes (CNPJ marcado como autônomo)
        $cpfCnpj = preg_replace('/[^0-9]/', '', $vpoData['cpf_cnpj'] ?? '');

        if (strlen($cpfCnpj) <= 11) {
            // CPF (11 dígitos ou menos) - usar cpfTransportador
            $cpf = str_pad($cpfCnpj, 11, '0', STR_PAD_LEFT);
            $this->addElement($xml, $transportador, 'cpfTransportador', $cpf);
        } else {
            // CNPJ (12+ dígitos) - usar cnpjTransportador
            $cnpj = str_pad($cpfCnpj, 14, '0', STR_PAD_LEFT);
            $this->addElement($xml, $transportador, 'cnpjTransportador', $cnpj);
        }

        // infTransportador - estrutura completa obrigatoria!
        $infTransportador = $xml->createElement('infTransportador');
        $transportador->appendChild($infTransportador);

        // infTransportador/ide/tac
        $ideTransp = $xml->createElement('ide');
        $infTransportador->appendChild($ideTransp);

        $tac = $xml->createElement('tac');
        $ideTransp->appendChild($tac);

        // IMPORTANTE: Todos os elementos do TAC são obrigatórios pelo XSD!
        // Usar valores padrão quando vazios
        $nomeCompleto = trim($vpoData['condutor_nome'] ?? $vpoData['antt_nome'] ?? '');
        $nomeMae = trim($vpoData['condutor_nome_mae'] ?? '');
        $dataNascimento = $this->formatDate($vpoData['condutor_data_nascimento'] ?? '');
        $identidade = trim($vpoData['condutor_rg'] ?? '');

        $this->addElementRequired($xml, $tac, 'nomeCompleto', $nomeCompleto ?: 'NAO INFORMADO');
        $this->addElementRequired($xml, $tac, 'nomeMae', $nomeMae ?: 'NAO INFORMADA');
        $this->addElementRequired($xml, $tac, 'dataNascimento', $dataNascimento ?: '1980-01-01');
        $this->addElementRequired($xml, $tac, 'identidade', $identidade ?: '000000000');

        // infTransportador/endereco
        // IMPORTANTE: A ORDEM dos elementos é CRÍTICA para o XSD!
        // Ordem correta: UF, cidade, bairro, logradouro, numero, CEP
        // Todos os elementos devem existir mesmo se vazios (usar valor padrão)
        $endereco = $xml->createElement('endereco');
        $infTransportador->appendChild($endereco);

        $uf = trim($vpoData['endereco_estado'] ?? '');
        $cidade = trim($vpoData['endereco_cidade'] ?? '');
        $bairro = trim($vpoData['endereco_bairro'] ?? '');
        $logradouro = trim($vpoData['endereco_rua'] ?? '');
        $cep = preg_replace('/[^0-9]/', '', $vpoData['endereco_cep'] ?? '');

        // Valores padrão para campos obrigatórios
        $this->addElementRequired($xml, $endereco, 'UF', $uf ?: 'SP');
        $this->addElementRequired($xml, $endereco, 'cidade', $cidade ?: 'NAO INFORMADO');
        $this->addElementRequired($xml, $endereco, 'bairro', $bairro ?: 'CENTRO');
        $this->addElementRequired($xml, $endereco, 'logradouro', $logradouro ?: 'NAO INFORMADO');
        // numero do endereco DEVE ser numerico (nao aceita "S/N" ou texto)
        $enderecoNumero = preg_replace('/[^0-9]/', '', $vpoData['endereco_numero'] ?? '');
        $enderecoNumero = $enderecoNumero ?: '0';  // Se vazio, usar 0
        $this->addElementRequired($xml, $endereco, 'numero', $enderecoNumero);

        // infTransportador/telefone
        $telefone = preg_replace('/[^0-9]/', '', $vpoData['contato_celular'] ?? '');
        $this->addElement($xml, $infTransportador, 'telefone', $telefone);

        // infRota
        $infRota = $xml->createElement('infRota');
        $inf->appendChild($infRota);

        // categoriaPedagio (1-7, baseado no número de eixos)
        // Usar eixos diretamente se fornecido, senão tentar inferir do tipo de veículo
        $eixos = $vpoData['eixos'] ?? $vpoData['veiculo_eixos'] ?? null;
        if ($eixos !== null) {
            $categoria = $this->getCategoriaPedagioByEixos((int) $eixos);
        } else {
            $categoria = $this->getCategoriaPedagio($vpoData['veiculo_tipo'] ?? '');
        }
        $this->addElement($xml, $infRota, 'categoriaPedagio', (string) $categoria);

        // rota com pontosParada (igual fazemos com SemParar)
        $rota = $xml->createElement('rota');
        $infRota->appendChild($rota);

        // rotaERP - nome curto da rota (max 30 chars)
        $rotaErp = $vpoData['rota_nome'] ?? '';
        if (empty($rotaErp) || strlen($rotaErp) > 30) {
            // Gerar codigo baseado no primeiro e ultimo ponto
            $firstIbge = $waypoints[0]['cdibge'] ?? '0000000';
            $lastIbge = $waypoints[count($waypoints) - 1]['cdibge'] ?? '0000000';
            $rotaErp = $firstIbge . ' x ' . $lastIbge;
        }
        $rotaErp = substr($rotaErp, 0, 30);
        $this->addElement($xml, $rota, 'rotaERP', $rotaErp);

        // informacoes da rota com pontosParada
        if (!empty($waypoints)) {
            $rotaInfo = $xml->createElement('informacoes');
            $rota->appendChild($rotaInfo);

            // nome da rota (igual ao rotaERP)
            $this->addElement($xml, $rotaInfo, 'nome', $rotaErp);

            // pontosParada - lista de municipios/pontos
            $pontosParada = $xml->createElement('pontosParada');
            $rotaInfo->appendChild($pontosParada);

            foreach ($waypoints as $waypoint) {
                $pontoParada = $xml->createElement('pontoParada');
                $pontosParada->appendChild($pontoParada);

                // codigoIBGE do municipio (7 digitos)
                $cdibge = preg_replace('/[^0-9]/', '', $waypoint['cdibge'] ?? '');
                $this->addElement($xml, $pontoParada, 'codigoIBGE', $cdibge);

                // tipoRotaEspecifico: 1 = normal
                $this->addElement($xml, $pontoParada, 'tipoRotaEspecifico', '1');
            }

            // utilizarRoteirizador: 2 = NÃO usar roteirizador interno (praças manuais)
            $this->addElement($xml, $rotaInfo, 'utilizarRoteirizador', '2');

            // pedagios - praças de pedágio (OBRIGATÓRIO quando usa TAG!)
            // Estrutura: infRota > rota > informacoes > pedagios > pedagio
            if (!empty($pracas)) {
                $pedagiosNode = $xml->createElement('pedagios');
                $rotaInfo->appendChild($pedagiosNode);

                foreach ($pracas as $praca) {
                    $pedagioNode = $xml->createElement('pedagio');
                    $pedagiosNode->appendChild($pedagioNode);

                    // cnp = Código Nacional da Praça (obrigatório)
                    $cnp = $praca['codigo'] ?? $praca['cnp'] ?? $praca['codigoPraca'] ?? '';
                    $this->addElement($xml, $pedagioNode, 'cnp', $cnp);

                    // nomePraca (obrigatório)
                    $nomePraca = $praca['nome'] ?? $praca['nomePraca'] ?? 'Praca ' . $cnp;
                    $this->addElement($xml, $pedagioNode, 'nomePraca', substr($nomePraca, 0, 255));

                    // valorPraca (obrigatório)
                    $valorPraca = $praca['valor'] ?? $praca['valorPraca'] ?? 0;
                    $this->addElement($xml, $pedagioNode, 'valorPraca', number_format((float) $valorPraca, 2, '.', ''));
                }
            }
        }

        // veiculo
        $veiculo = $xml->createElement('veiculo');
        $inf->appendChild($veiculo);

        $placa = preg_replace('/[^A-Z0-9]/', '', strtoupper($vpoData['placa'] ?? ''));
        $this->addElement($xml, $veiculo, 'placa', $placa);

        // veiculo/informacoes (nao direto!)
        $informacoes = $xml->createElement('informacoes');
        $veiculo->appendChild($informacoes);

        $this->addElement($xml, $informacoes, 'modelo', $vpoData['veiculo_modelo'] ?? 'CAMINHAO');
        $this->addElement($xml, $informacoes, 'tipo', $this->getTipoVeiculo($vpoData['veiculo_tipo'] ?? ''));
        // RNTRCTransportador DEVE ter 9 digitos (igual ao rntrc)
        $rntrcTransp = preg_replace('/[^0-9]/', '', $vpoData['antt_rntrc'] ?? '');
        $rntrcTransp = str_pad($rntrcTransp, 9, '0', STR_PAD_LEFT);
        $this->addElement($xml, $informacoes, 'RNTRCTransportador', $rntrcTransp);

        // informacoesTag - OBRIGATÓRIO quando usando TAG
        // codigoFornecedor (da documentação NDD Cargo 4.2.12.0):
        //   1 = ConectCar
        //   2 = SemParar
        //   3 = Veloe
        //   4 = Move Mais
        //   5 = Ticketlog
        // codigoTag: número da TAG do motorista (de sParargetExtra.tag)
        //
        // IMPORTANTE: Quando usa TAG, DEVE informar as praças em pedagios!
        if (!empty($codigoTag)) {
            $informacoesTag = $xml->createElement('informacoesTag');
            $inf->appendChild($informacoesTag);
            $this->addElement($xml, $informacoesTag, 'codigoFornecedor', '2');  // 2 = SemParar
            $this->addElement($xml, $informacoesTag, 'codigoTag', trim($codigoTag));
        }

        return [
            'xml' => $xml->saveXML(),
            'uuid' => $uuid
        ];
    }

    /**
     * Formata data para o padrao XML (YYYY-MM-DD)
     */
    private function formatDate(?string $date): string
    {
        if (empty($date)) {
            return '';
        }

        // Tentar parsear varios formatos
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed) {
                return $parsed->format('Y-m-d');
            }
        }

        return $date;
    }

    /**
     * Retorna categoria de pedagio baseado no tipo de veiculo
     * 1=Moto, 2=Auto, 3=2eixos, 4=3eixos, 5=4eixos, 6=5eixos, 7=6+eixos
     */
    private function getCategoriaPedagio(?string $tipoVeiculo): int
    {
        $tipoLower = strtolower($tipoVeiculo ?? '');

        if (str_contains($tipoLower, 'moto')) {
            return 1;
        }
        if (str_contains($tipoLower, 'auto') || str_contains($tipoLower, 'carro')) {
            return 2;
        }
        if (str_contains($tipoLower, 'truck') || str_contains($tipoLower, '3/4')) {
            return 3;
        }
        if (str_contains($tipoLower, 'toco')) {
            return 4;
        }
        if (str_contains($tipoLower, 'carreta')) {
            return 6;
        }

        // Default: 5 (caminhão leve 2 eixos)
        return 5;
    }

    /**
     * Retorna categoria de pedágio baseado no número de eixos
     * Mapeamento oficial NDD Cargo:
     * - 5 = Caminhão leve (2 eixos)
     * - 6 = Caminhão médio (3-5 eixos)
     * - 7 = Caminhão pesado (6+ eixos)
     *
     * @param int $eixos Número de eixos do veículo
     * @return int Categoria de pedágio (5-7 para caminhões)
     */
    private function getCategoriaPedagioByEixos(int $eixos): int
    {
        if ($eixos <= 2) {
            return 5; // Caminhão leve (2 eixos)
        }
        if ($eixos <= 5) {
            return 6; // Caminhão médio (3-5 eixos)
        }
        return 7; // Caminhão pesado (6+ eixos)
    }

    /**
     * Retorna tipo do veículo para NDD Cargo
     * IMPORTANTE: API só aceita 1 (Tração) ou 2 (Reboque)!
     *
     * @param string|null $tipoVeiculo Tipo do veículo (TOCO, TRUCK, CARRETA, etc)
     * @return string '1' para tração (veículo principal), '2' para reboque
     */
    private function getTipoVeiculo(?string $tipoVeiculo): string
    {
        $tipoLower = strtolower($tipoVeiculo ?? '');

        // Reboque = 2 (carretas, reboques, semirreboques)
        if (str_contains($tipoLower, 'reboque') ||
            str_contains($tipoLower, 'semi') ||
            str_contains($tipoLower, 'carreta')) {
            return '2';
        }

        // Default: 1 = Tração (caminhões, cavalos mecânicos, etc)
        // TOCO, TRUCK, BITREM, etc = todos são veículos de tração
        return '1';
    }

    /**
     * Helper para adicionar elemento ao DOM (so se nao vazio)
     * IMPORTANTE: Aplica trim() automaticamente para evitar espacos trailing
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

    /**
     * Adiciona elemento SEMPRE, mesmo se vazio (para campos obrigatórios no XSD)
     */
    private function addElementRequired(\DOMDocument $xml, \DOMElement $parent, string $name, string $value): void
    {
        $element = $xml->createElement($name, htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
        $parent->appendChild($element);
    }
}
