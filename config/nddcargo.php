<?php

return [

    /*
    |--------------------------------------------------------------------------
    | NDD Cargo API Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações para integração com a API NDD Cargo (Roteirizador e
    | Gestão de Vale Pedágio via protocolo CrossTalk sobre SOAP)
    |
    | @see docs/integracoes/ndd-cargo/README.md
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Ambiente
    |--------------------------------------------------------------------------
    |
    | Define o ambiente de operação:
    | - 'homologacao': Ambiente de testes NDD Cargo
    | - 'producao': Ambiente de produção NDD Cargo
    |
    */
    'environment' => env('NDD_CARGO_ENVIRONMENT', 'homologacao'),

    /*
    |--------------------------------------------------------------------------
    | Endpoints
    |--------------------------------------------------------------------------
    |
    | URLs dos endpoints SOAP por ambiente
    |
    */
    'endpoints' => [
        'homologacao' => [
            'wsdl' => 'https://homologa.nddcargo.com.br/wsagente/ExchangeMessage.asmx?wsdl',
            'url' => 'https://homologa.nddcargo.com.br/wsagente/ExchangeMessage.asmx',
        ],
        'producao' => [
            'wsdl' => 'http://wsagent.nddcargo.com.br/wsagente/exchangemessage.asmx?wsdl',
            'url' => 'http://wsagent.nddcargo.com.br/wsagente/exchangemessage.asmx',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Endpoint URL (Computed)
    |--------------------------------------------------------------------------
    */
    'endpoint_url' => env(
        'NDD_CARGO_ENDPOINT_URL',
        env('NDD_CARGO_ENVIRONMENT', 'homologacao') === 'producao'
            ? 'http://wsagent.nddcargo.com.br/wsagente/exchangemessage.asmx'
            : 'https://homologa.nddcargo.com.br/wsagente/ExchangeMessage.asmx'
    ),

    /*
    |--------------------------------------------------------------------------
    | Credenciais
    |--------------------------------------------------------------------------
    |
    | CNPJ da empresa e token de autenticação fornecidos pela NDD Cargo
    |
    | ⚠️ IMPORTANTE: Nunca commitar credenciais reais no repositório!
    | Use variáveis de ambiente (.env)
    |
    */
    'cnpj_empresa' => env('NDD_CARGO_CNPJ', '17359233000188'), // Homologação
    'token' => env('NDD_CARGO_TOKEN', '2342bbkjkh23423bn2j3n42a'), // Homologação

    /*
    |--------------------------------------------------------------------------
    | Certificado Digital
    |--------------------------------------------------------------------------
    |
    | Configurações do certificado digital A1 (ICP-Brasil) usado para
    | assinatura digital XML
    |
    | Tipos suportados:
    | - 'pfx': Arquivo .pfx (PKCS#12) com certificado e chave privada
    | - 'pem': Arquivos separados .pem (certificado e chave privada)
    |
    */
    'certificate_type' => env('NDD_CARGO_CERT_TYPE', 'pfx'),

    // Caminho para arquivo .pfx (se type = 'pfx')
    'certificate_pfx_path' => env(
        'NDD_CARGO_CERT_PFX_PATH',
        storage_path('certificates/nddcargo/cert.pfx')
    ),

    // Caminhos para arquivos .pem separados (se type = 'pem')
    'certificate_cert_path' => env(
        'NDD_CARGO_CERT_CERT_PATH',
        storage_path('certificates/nddcargo/cert_cert.pem')
    ),
    'certificate_key_path' => env(
        'NDD_CARGO_CERT_KEY_PATH',
        storage_path('certificates/nddcargo/cert_key.pem')
    ),

    // Senha do certificado
    'certificate_password' => env('NDD_CARGO_CERT_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | Versão da API
    |--------------------------------------------------------------------------
    |
    | Versão do layout XML da API NDD Cargo
    |
    */
    'versao_layout' => env('NDD_CARGO_VERSAO_LAYOUT', '4.2.12.0'),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout em segundos para requisições HTTP à API NDD Cargo
    |
    */
    'timeout' => (int) env('NDD_CARGO_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Configurações de cache para respostas da API
    |
    */
    'cache' => [
        // TTL em segundos para cache de rotas (24 horas)
        'rotas_ttl' => (int) env('NDD_CARGO_CACHE_ROTAS_TTL', 86400),

        // Habilitar cache de rotas
        'enabled' => env('NDD_CARGO_CACHE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configurações de logging específicas para NDD Cargo
    |
    */
    'logging' => [
        // Logar XMLs completos (útil para debug, mas verboso)
        'log_xml' => env('NDD_CARGO_LOG_XML', false),

        // Logar respostas completas
        'log_responses' => env('NDD_CARGO_LOG_RESPONSES', true),

        // Nível de log
        'level' => env('NDD_CARGO_LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Limites de requisições por minuto (evitar sobrecarga da API)
    |
    */
    'rate_limiting' => [
        // Requisições por minuto (consultas)
        'requests_per_minute' => (int) env('NDD_CARGO_RATE_LIMIT', 60),

        // Requisições por minuto (testes)
        'test_requests_per_minute' => (int) env('NDD_CARGO_TEST_RATE_LIMIT', 10),
    ],

];
