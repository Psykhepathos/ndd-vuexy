<?php

namespace App\Services\NddCargo;

use Illuminate\Support\Facades\Log;

/**
 * Serviço de assinatura digital XML para NDD Cargo
 *
 * Implementa assinatura digital RSA-SHA1 seguindo o padrão XML Digital Signature
 * conforme exigido pela API NDD Cargo
 *
 * IMPORTANTE: A NDD Cargo exige:
 * - Canonicalização C14N (Canonical XML 1.0)
 * - Algoritmo de assinatura: RSA-SHA1
 * - Algoritmo de digest: SHA1
 * - Certificado digital A1 (ICP-Brasil)
 *
 * ⚠️ SECURITY WARNING: SHA1 está deprecated para criptografia, mas ainda é
 * exigido pela API NDD Cargo (2025). Não usar para outras finalidades!
 *
 * @see docs/integracoes/ndd-cargo/ANALISE_NTESTE_PY.md (linhas 89-121)
 * @see https://www.w3.org/TR/xmldsig-core/ - XML Digital Signature spec
 */
class DigitalSignature
{
    /**
     * Algoritmos XML Digital Signature
     */
    private const CANONICALIZATION_METHOD = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';
    private const SIGNATURE_METHOD = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';
    private const DIGEST_METHOD = 'http://www.w3.org/2000/09/xmldsig#sha1';
    private const TRANSFORM_ENVELOPED = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';

    /**
     * @var resource|null Chave privada RSA
     */
    private $privateKey = null;

    /**
     * @var string|null Certificado X.509 em formato PEM
     */
    private ?string $certificate = null;

    /**
     * Carrega certificado e chave privada de arquivo .pfx
     *
     * @param string $pfxPath Caminho para arquivo .pfx
     * @param string $password Senha do certificado
     * @return self
     * @throws \Exception Se não conseguir carregar o certificado
     */
    public function loadFromPfx(string $pfxPath, string $password): self
    {
        if (!file_exists($pfxPath)) {
            throw new \Exception("Arquivo .pfx não encontrado: {$pfxPath}");
        }

        $pfxContent = file_get_contents($pfxPath);
        $certs = [];

        if (!openssl_pkcs12_read($pfxContent, $certs, $password)) {
            throw new \Exception('Erro ao ler certificado .pfx: ' . openssl_error_string());
        }

        // Carregar chave privada
        $this->privateKey = openssl_pkey_get_private($certs['pkey']);
        if (!$this->privateKey) {
            throw new \Exception('Erro ao extrair chave privada: ' . openssl_error_string());
        }

        // Carregar certificado
        $this->certificate = $certs['cert'];

        Log::info('Certificado digital carregado com sucesso', [
            'arquivo' => basename($pfxPath)
        ]);

        return $this;
    }

    /**
     * Carrega certificado e chave privada de arquivos PEM separados
     *
     * @param string $certPath Caminho para certificado (.pem)
     * @param string $keyPath Caminho para chave privada (.pem)
     * @param string|null $keyPassword Senha da chave privada (opcional)
     * @return self
     * @throws \Exception Se não conseguir carregar
     */
    public function loadFromPem(string $certPath, string $keyPath, ?string $keyPassword = null): self
    {
        if (!file_exists($certPath)) {
            throw new \Exception("Arquivo de certificado não encontrado: {$certPath}");
        }

        if (!file_exists($keyPath)) {
            throw new \Exception("Arquivo de chave privada não encontrado: {$keyPath}");
        }

        // Carregar certificado
        $this->certificate = file_get_contents($certPath);

        // Carregar chave privada
        $keyContent = file_get_contents($keyPath);
        $this->privateKey = openssl_pkey_get_private($keyContent, $keyPassword ?? '');

        if (!$this->privateKey) {
            throw new \Exception('Erro ao carregar chave privada: ' . openssl_error_string());
        }

        Log::info('Certificado e chave privada carregados com sucesso');

        return $this;
    }

    /**
     * Assina XML seguindo o padrão XML Digital Signature
     *
     * O XML deve conter um elemento raiz com atributo ID que será referenciado
     * na assinatura. Por exemplo: <infConsultarRoteirizador ID="uuid-123">
     *
     * @param string $xmlContent Conteúdo XML a ser assinado
     * @param string $referenceId ID do elemento a ser assinado (sem "#")
     * @return string XML assinado
     * @throws \Exception Se a assinatura falhar
     */
    public function signXml(string $xmlContent, string $referenceId): string
    {
        if (!$this->privateKey || !$this->certificate) {
            throw new \Exception('Certificado não carregado. Use loadFromPfx() ou loadFromPem() primeiro.');
        }

        try {
            // 1. Canonicalizar XML (C14N)
            $canonical = $this->canonicalizeXml($xmlContent);

            // 2. Calcular digest SHA1 do XML canonicalizado
            $digestValue = base64_encode(sha1($canonical, true));

            // 3. Criar SignedInfo
            $signedInfo = $this->createSignedInfo($referenceId, $digestValue);

            // 4. Canonicalizar SignedInfo
            $signedInfoCanonical = $this->canonicalizeXml($signedInfo);

            // 5. Assinar SignedInfo com RSA-SHA1
            $signatureValue = $this->signData($signedInfoCanonical);

            // 6. Extrair certificado em Base64 (sem headers PEM)
            $certBase64 = $this->getCertificateBase64();

            // 7. Criar estrutura Signature completa
            $signatureXml = $this->createSignatureElement($signedInfo, $signatureValue, $certBase64);

            // 8. Inserir Signature no XML original (antes do fechamento do elemento raiz)
            return $this->embedSignature($xmlContent, $signatureXml);

        } catch (\Exception $e) {
            Log::error('Erro ao assinar XML', [
                'erro' => $e->getMessage(),
                'reference_id' => $referenceId
            ]);
            throw new \Exception('Falha na assinatura digital: ' . $e->getMessage());
        }
    }

    /**
     * Canonicaliza XML usando C14N
     *
     * @param string $xmlContent
     * @return string
     * @throws \Exception
     */
    private function canonicalizeXml(string $xmlContent): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (!$dom->loadXML($xmlContent)) {
            throw new \Exception('XML inválido para canonicalização');
        }

        return $dom->C14N();
    }

    /**
     * Cria elemento SignedInfo
     *
     * @param string $referenceId
     * @param string $digestValue
     * @return string
     */
    private function createSignedInfo(string $referenceId, string $digestValue): string
    {
        return <<<XML
<SignedInfo xmlns="http://www.w3.org/2000/09/xmldsig#">
  <CanonicalizationMethod Algorithm="{$this::CANONICALIZATION_METHOD}"/>
  <SignatureMethod Algorithm="{$this::SIGNATURE_METHOD}"/>
  <Reference URI="#{$referenceId}">
    <Transforms>
      <Transform Algorithm="{$this::TRANSFORM_ENVELOPED}"/>
      <Transform Algorithm="{$this::CANONICALIZATION_METHOD}"/>
    </Transforms>
    <DigestMethod Algorithm="{$this::DIGEST_METHOD}"/>
    <DigestValue>{$digestValue}</DigestValue>
  </Reference>
</SignedInfo>
XML;
    }

    /**
     * Assina dados com RSA-SHA1
     *
     * @param string $data
     * @return string Base64 da assinatura
     * @throws \Exception
     */
    private function signData(string $data): string
    {
        $signature = '';

        if (!openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA1)) {
            throw new \Exception('Erro ao gerar assinatura RSA: ' . openssl_error_string());
        }

        return base64_encode($signature);
    }

    /**
     * Extrai certificado em Base64 (sem headers PEM)
     *
     * @return string
     */
    private function getCertificateBase64(): string
    {
        $cert = $this->certificate;

        // Remover headers PEM
        $cert = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'], '', $cert);
        $cert = str_replace(["\n", "\r", " "], '', $cert);

        return $cert;
    }

    /**
     * Cria elemento Signature completo
     *
     * @param string $signedInfo
     * @param string $signatureValue
     * @param string $certBase64
     * @return string
     */
    private function createSignatureElement(string $signedInfo, string $signatureValue, string $certBase64): string
    {
        return <<<XML
<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">
{$signedInfo}
  <SignatureValue>{$signatureValue}</SignatureValue>
  <KeyInfo>
    <X509Data>
      <X509Certificate>{$certBase64}</X509Certificate>
    </X509Data>
  </KeyInfo>
</Signature>
XML;
    }

    /**
     * Insere Signature no XML original (antes do fechamento do elemento raiz)
     *
     * @param string $xmlContent
     * @param string $signatureXml
     * @return string
     */
    private function embedSignature(string $xmlContent, string $signatureXml): string
    {
        // Encontrar fechamento do elemento raiz
        // Assumindo que o XML termina com </infConsultarRoteirizador> ou similar
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = true;

        if (!$dom->loadXML($xmlContent)) {
            throw new \Exception('XML inválido para inserção de assinatura');
        }

        // Carregar Signature como DOM
        $sigDom = new \DOMDocument('1.0', 'UTF-8');
        if (!$sigDom->loadXML($signatureXml)) {
            throw new \Exception('Signature XML inválido');
        }

        // Importar nó Signature para o documento principal
        $signatureNode = $dom->importNode($sigDom->documentElement, true);

        // Adicionar Signature como último filho do elemento raiz
        $dom->documentElement->appendChild($signatureNode);

        return $dom->saveXML();
    }

    /**
     * Libera recursos
     */
    public function __destruct()
    {
        if ($this->privateKey) {
            openssl_free_key($this->privateKey);
        }
    }
}
