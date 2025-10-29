<?php

namespace App\Services\SemParar;

use SoapClient;
use SoapFault;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * SemPararSoapClient - Low-level SOAP client for SemParar API
 *
 * Based on Progress Connect.cls (lines 52-108 in SEMPARAR_AI_REFERENCE.md)
 * Handles SOAP connection, authentication, and raw SOAP method calls
 */
class SemPararSoapClient
{
    /**
     * PHP SoapClient instance (main WSDL - wsvp)
     */
    protected ?SoapClient $soapClient = null;

    /**
     * PHP SoapClient instance for extratoCreditos (vpextrato WSDL)
     */
    protected ?SoapClient $soapExtratoClient = null;

    /**
     * Current session token (cached)
     */
    protected ?string $cToken = null;

    /**
     * WSDL URL (main - wsvp)
     */
    protected string $wsdlUrl;

    /**
     * WSDL URL for extratoCreditos (vpextrato)
     */
    protected string $wsdlExtratoUrl;

    /**
     * SOAP client options
     */
    protected array $soapOptions;

    /**
     * Authentication credentials
     */
    protected string $cnpj;
    protected string $user;
    protected string $password;

    /**
     * Token cache key
     */
    protected const CACHE_KEY = 'semparar_token';

    /**
     * Initialize SOAP client with TLS 1.2/1.3 support
     */
    public function __construct()
    {
        $this->wsdlUrl = config('semparar.wsdl_url');
        $this->wsdlExtratoUrl = config('semparar.wsdl_extrato_url');
        $this->cnpj = config('semparar.cnpj');
        $this->user = config('semparar.user');
        $this->password = config('semparar.password');
        $this->soapOptions = config('semparar.soap_options');

        $this->connectToServer();
    }

    /**
     * Establish SOAP connection to SemParar WSDL
     *
     * Based on Connect.cls::connectToServer() (line 67-82)
     *
     * @throws Exception if connection fails
     */
    protected function connectToServer(): void
    {
        try {
            $this->soapClient = new SoapClient($this->wsdlUrl, $this->soapOptions);

            Log::info('[SemParar SOAP] Connected to WSDL', [
                'url' => $this->wsdlUrl,
                'functions' => count($this->soapClient->__getFunctions())
            ]);
        } catch (SoapFault $e) {
            Log::error('[SemParar SOAP] Connection failed', [
                'error' => $e->getMessage(),
                'wsdl' => $this->wsdlUrl
            ]);
            throw new Exception("Failed to connect to SemParar WSDL: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Authenticate user and retrieve session token (cToken)
     *
     * Based on Connect.cls::GET() (line 84-97)
     * Progress code:
     *   RUN VALUE("autenticarUsuario") IN hPorta(
     *     INPUT "2024209702",
     *     INPUT "CORPORATIVO",
     *     INPUT "Tambasa20",
     *     OUTPUT xml
     *   )
     *
     * @return string Session token
     * @throws Exception if authentication fails
     */
    public function autenticarUsuario(): string
    {
        // Check cache first
        $cachedToken = $this->getCachedToken();
        if ($cachedToken) {
            $this->cToken = $cachedToken;
            Log::info('[SemParar SOAP] Using cached token');
            return $cachedToken;
        }

        try {
            // Call SOAP autenticarUsuario
            // IMPORTANT: Use direct method call with positional params, NOT __soapCall with named params
            // __soapCall causes "Array to string conversion" error
            $response = $this->soapClient->autenticarUsuario(
                $this->cnpj,
                $this->user,
                $this->password
            );

            // Parse response - WSDL shows return type is "Identificador" object
            // Progress: extractContentFromXml(xml)
            $token = null;

            // Log response structure for debugging
            Log::info('[SemParar SOAP] Authentication response received', [
                'response_type' => gettype($response),
                'response_class' => is_object($response) ? get_class($response) : null,
                'response_vars' => is_object($response) ? array_keys(get_object_vars($response)) : null
            ]);

            if (is_string($response)) {
                // XML string response
                $xml = simplexml_load_string($response);
                $token = (string)$xml->sessao;
            } elseif (is_object($response)) {
                // Object response (Identificador type)
                // Try common property names
                if (isset($response->sessao)) {
                    $token = (string)$response->sessao;
                } elseif (isset($response->identificador)) {
                    $token = (string)$response->identificador;
                } elseif (isset($response->token)) {
                    $token = (string)$response->token;
                } elseif (isset($response->return)) {
                    $token = (string)$response->return;
                }
            } elseif (is_array($response)) {
                // Array response
                if (isset($response['sessao'])) {
                    $token = (string)$response['sessao'];
                } elseif (isset($response['identificador'])) {
                    $token = (string)$response['identificador'];
                } elseif (isset($response['token'])) {
                    $token = (string)$response['token'];
                } elseif (isset($response['return'])) {
                    $token = (string)$response['return'];
                }
            }

            if (empty($token)) {
                Log::error('[SemParar SOAP] Authentication returned empty token', [
                    'response_type' => gettype($response),
                    'response_class' => is_object($response) ? get_class($response) : null,
                    'response_preview' => is_object($response) ? json_encode(get_object_vars($response)) : print_r($response, true)
                ]);
                throw new Exception('Authentication returned empty token - check logs for response structure');
            }

            // Cache token for 1 hour
            $ttl = config('semparar.token_cache_ttl', 3600);
            Cache::put(self::CACHE_KEY, $token, now()->addSeconds($ttl));

            $this->cToken = $token;

            Log::info('[SemParar SOAP] Authenticated successfully', [
                'token_length' => strlen($token),
                'cache_ttl' => $ttl
            ]);

            return $token;
        } catch (SoapFault $e) {
            Log::error('[SemParar SOAP] Authentication failed', [
                'error' => $e->getMessage(),
                'cnpj' => $this->cnpj,
                'user' => $this->user
            ]);
            throw new Exception("SemParar authentication failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get cached session token
     *
     * @return string|null Token if cached and valid, null otherwise
     */
    public function getCachedToken(): ?string
    {
        return Cache::get(self::CACHE_KEY);
    }

    /**
     * Clear cached token (force re-authentication)
     */
    public function clearCachedToken(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->cToken = null;
        Log::info('[SemParar SOAP] Token cache cleared');
    }

    /**
     * Call any SOAP method with automatic token injection
     *
     * Based on Rota.cls SOAP call pattern (lines 156-294 in SEMPARAR_AI_REFERENCE.md)
     * All Progress SOAP calls include cToken parameter
     *
     * @param string $method SOAP method name
     * @param array $params Method parameters (token will be auto-injected)
     * @return mixed SOAP response
     * @throws Exception if SOAP call fails
     */
    public function callSoapMethod(string $method, array $params = []): mixed
    {
        // Ensure we have a valid token
        if (!$this->cToken) {
            $this->autenticarUsuario();
        }

        try {
            // Inject token into parameters (Progress pattern: INPUT cToken)
            $paramsWithToken = array_merge(['sessao' => $this->cToken], $params);

            Log::debug('[SemParar SOAP] Calling method', [
                'method' => $method,
                'params' => array_keys($paramsWithToken)
            ]);

            $response = $this->soapClient->__soapCall($method, [$paramsWithToken]);

            Log::debug('[SemParar SOAP] Method call successful', [
                'method' => $method,
                'response_type' => gettype($response)
            ]);

            return $response;
        } catch (SoapFault $e) {
            // If token expired, try re-authenticating once
            if (str_contains($e->getMessage(), 'sessao') || str_contains($e->getMessage(), 'token')) {
                Log::warning('[SemParar SOAP] Token may be expired, re-authenticating');
                $this->clearCachedToken();
                $this->autenticarUsuario();

                // Retry with new token
                $paramsWithToken = array_merge(['sessao' => $this->cToken], $params);
                try {
                    return $this->soapClient->__soapCall($method, [$paramsWithToken]);
                } catch (SoapFault $retryError) {
                    Log::error('[SemParar SOAP] Method call failed after token refresh', [
                        'method' => $method,
                        'error' => $retryError->getMessage()
                    ]);
                    throw new Exception("SemParar SOAP call failed: {$retryError->getMessage()}", 0, $retryError);
                }
            }

            Log::error('[SemParar SOAP] Method call failed', [
                'method' => $method,
                'error' => $e->getMessage()
            ]);
            throw new Exception("SemParar SOAP call failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Parse XML response and extract specific tag content
     *
     * Based on Connect.cls::extractContentFromXml() (line 99-108)
     * Progress uses regex: SEARCH(xml) MATCHES ".*<" + tag + ">(.*)</" + tag + ">.*"
     *
     * @param string $xml XML string
     * @param string $tag Tag name to extract
     * @return mixed Extracted content or null
     */
    public function parseXmlResponse(string $xml, string $tag): mixed
    {
        try {
            $xmlObj = simplexml_load_string($xml);

            if ($xmlObj === false) {
                Log::error('[SemParar SOAP] XML parsing failed', ['xml_preview' => substr($xml, 0, 200)]);
                return null;
            }

            // Use XPath to extract tag content
            $result = $xmlObj->xpath("//{$tag}");

            if (empty($result)) {
                Log::warning('[SemParar SOAP] Tag not found in XML', ['tag' => $tag]);
                return null;
            }

            return (string)$result[0];
        } catch (Exception $e) {
            Log::error('[SemParar SOAP] XML parsing error', [
                'tag' => $tag,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get SOAP client instance (for advanced usage)
     *
     * @return SoapClient
     */
    public function getSoapClient(): SoapClient
    {
        return $this->soapClient;
    }

    /**
     * Get SOAP Extrato client instance (vpextrato WSDL)
     * Used for obterExtratoCreditos method
     *
     * @return SoapClient
     * @throws Exception if connection fails
     */
    public function getSoapExtratoClient(): SoapClient
    {
        // Lazy initialization - only connect when needed
        if ($this->soapExtratoClient === null) {
            try {
                $this->soapExtratoClient = new SoapClient($this->wsdlExtratoUrl, $this->soapOptions);

                Log::info('[SemParar SOAP] Connected to Extrato WSDL', [
                    'url' => $this->wsdlExtratoUrl,
                    'functions' => count($this->soapExtratoClient->__getFunctions())
                ]);
            } catch (SoapFault $e) {
                Log::error('[SemParar SOAP] Extrato WSDL connection failed', [
                    'error' => $e->getMessage(),
                    'wsdl' => $this->wsdlExtratoUrl
                ]);
                throw new Exception("Failed to connect to SemParar Extrato WSDL: {$e->getMessage()}", 0, $e);
            }
        }

        return $this->soapExtratoClient;
    }

    /**
     * Get current session token
     *
     * @return string|null
     */
    public function getToken(): ?string
    {
        return $this->cToken;
    }

    /**
     * Test connection and authentication
     *
     * @return array Test results
     */
    public function testConnection(): array
    {
        try {
            // Test WSDL connection first
            if (!$this->soapClient) {
                throw new Exception('SOAP client not initialized');
            }

            $functions = $this->soapClient->__getFunctions();

            // Try authentication
            try {
                $token = $this->autenticarUsuario();
                $authSuccess = true;
                $authError = null;
            } catch (Exception $authException) {
                $authSuccess = false;
                $authError = $authException->getMessage();
                $token = null;
            }

            return [
                'success' => $authSuccess,
                'message' => $authSuccess ? 'Connection and authentication successful' : 'Connection OK but authentication failed',
                'auth_success' => $authSuccess,
                'auth_error' => $authError,
                'token_length' => $token ? strlen($token) : 0,
                'wsdl_url' => $this->wsdlUrl,
                'available_functions' => count($functions),
                'functions_sample' => array_slice($functions, 0, 5)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'wsdl_url' => $this->wsdlUrl,
                'auth_success' => false
            ];
        }
    }
}
