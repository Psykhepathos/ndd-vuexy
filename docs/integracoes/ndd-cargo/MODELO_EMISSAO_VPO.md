# Modelo de Dados - Emissão VPO (Vale Pedágio Obrigatório)
**Data:** 2025-12-05
**API:** NDD Cargo - Emissão de Vale Pedágio

---

## 📋 CAMPOS OBRIGATÓRIOS

### 🟢 DADOS VALIDADOS (Críticos - API valida)

**Transportador:**
- `cpf_cnpj` - CPF/CNPJ do transportador (validado pela ANTT)

**Veículo:**
- `placa` - Placa do veículo (validada pela ANTT)
- `antt_rntrc` - Código RNTRC (Registro Nacional de Transportadores Rodoviários de Cargas)
- `antt_nome` - Nome/Razão Social cadastrado na ANTT
- `antt_validade` - Data de validade do RNTRC (formato: YYYY-MM-DD)
- `antt_status` - Status do RNTRC (ex: "Ativo", "Suspenso")

**Condutor:**
- `condutor_rg` - RG do condutor (Identidade)

**Veículo Detalhes:**
- `veiculo_tipo` - Tipo do veículo (ex: "Caminhão", "Carreta")
- `veiculo_modelo` - Modelo do veículo

### 🔴 DADOS OBRIGATÓRIOS (Não validados - apenas registro)

**Condutor:**
- `condutor_nome` - Nome completo
- `condutor_sexo` - Sexo (M/F)
- `condutor_nome_mae` - Nome da mãe
- `condutor_data_nascimento` - Data de nascimento (formato: YYYY-MM-DD)

**Endereço:**
- `endereco_rua` - Logradouro/Rua
- `endereco_bairro` - Bairro
- `endereco_cidade` - Cidade
- `endereco_estado` - UF (2 letras)

**Contato:**
- `contato_celular` - Telefone celular
- `contato_email` - E-mail

---

## 📄 MODELO JSON - Emissão VPO

```json
{
  "transportador": {
    "cpf_cnpj": "17359233000188",
    "antt_rntrc": "123456",
    "antt_nome": "TRANSPORTADORA EXEMPLO LTDA",
    "antt_validade": "2025-12-31",
    "antt_status": "Ativo"
  },
  "veiculo": {
    "placa": "ABC1D23",
    "tipo": "Caminhão Trator",
    "modelo": "Scania R450"
  },
  "condutor": {
    "nome": "João da Silva",
    "rg": "12.345.678-9",
    "sexo": "M",
    "nome_mae": "Maria da Silva",
    "data_nascimento": "1985-06-15"
  },
  "endereco": {
    "rua": "Rua das Flores",
    "bairro": "Centro",
    "cidade": "São Paulo",
    "estado": "SP"
  },
  "contato": {
    "celular": "11987654321",
    "email": "joao.silva@exemplo.com"
  }
}
```

---

## 🎯 EXEMPLO PRÁTICO - Dados Reais

```json
{
  "transportador": {
    "cpf_cnpj": "17359233000188",
    "antt_rntrc": "ANTT987654",
    "antt_nome": "TAMBASA TRANSPORTES LTDA",
    "antt_validade": "2026-03-15",
    "antt_status": "Ativo"
  },
  "veiculo": {
    "placa": "FXY2024",
    "tipo": "Caminhão Truck",
    "modelo": "Mercedes-Benz Atego 2426"
  },
  "condutor": {
    "nome": "Carlos Eduardo Santos",
    "rg": "45.678.901-2",
    "sexo": "M",
    "nome_mae": "Ana Maria Santos",
    "data_nascimento": "1978-11-20"
  },
  "endereco": {
    "rua": "Av. Paulista, 1234",
    "bairro": "Bela Vista",
    "cidade": "São Paulo",
    "estado": "SP"
  },
  "contato": {
    "celular": "11999887766",
    "email": "carlos.santos@tambasa.com.br"
  }
}
```

---

## 🔍 VALIDAÇÕES APLICADAS

### 🟢 Validações Críticas (API NDD Cargo)

| Campo | Validação | Exemplo Válido | Exemplo Inválido |
|-------|-----------|----------------|------------------|
| `cpf_cnpj` | CNPJ válido + cadastro ANTT | `17359233000188` | `11111111111111` ❌ |
| `placa` | Padrão Mercosul ou antigo | `ABC1D23` ou `ABC1234` | `ABCD123` ❌ |
| `antt_rntrc` | Código RNTRC válido | `ANTT123456` | `INVALIDO` ❌ |
| `antt_validade` | Data futura | `2026-12-31` | `2020-01-01` ❌ |
| `antt_status` | "Ativo" | `Ativo` | `Suspenso` ❌ |
| `condutor_rg` | RG válido | `12.345.678-9` | `000000000` ❌ |
| `veiculo_tipo` | Tipo conhecido | `Caminhão Trator` | `Bicicleta` ❌ |

### 🔴 Campos Obrigatórios (Sem validação)

| Campo | Formato | Exemplo |
|-------|---------|---------|
| `condutor_nome` | String (min 3 chars) | `João da Silva` |
| `condutor_sexo` | M ou F | `M` |
| `condutor_nome_mae` | String (min 3 chars) | `Maria da Silva` |
| `condutor_data_nascimento` | YYYY-MM-DD | `1985-06-15` |
| `endereco_rua` | String | `Rua das Flores` |
| `endereco_bairro` | String | `Centro` |
| `endereco_cidade` | String | `São Paulo` |
| `endereco_estado` | UF (2 letras) | `SP` |
| `contato_celular` | String (números) | `11987654321` |
| `contato_email` | String (formato email) | `email@exemplo.com` |

---

## 🛡️ ESTRATÉGIA DE PREENCHIMENTO

### Para Campos 🟢 VALIDADOS
```php
// ✅ Buscar dados REAIS do Progress/ANTT
$transportador = DB::connection('progress')
    ->select("SELECT codcnpjcpf, numrntrc FROM PUB.transporte WHERE codtrn = ?", [$codtrn]);

// ✅ Validar placa com RENAVAM/ANTT
$veiculo = $this->validatePlacaANTT($placa);

// ✅ Validar RG do condutor
$condutor = $this->validateRG($rg);
```

### Para Campos 🔴 NÃO VALIDADOS (mas obrigatórios)
```php
// ⚠️ Pode usar dados genéricos/padrão se não houver no sistema
$dados_genericos = [
    'condutor_nome' => $motorista->nome ?? 'MOTORISTA NAO CADASTRADO',
    'condutor_sexo' => 'M',  // Padrão
    'condutor_nome_mae' => 'NAO INFORMADO',
    'condutor_data_nascimento' => '1980-01-01',  // Data genérica
    'endereco_rua' => $transportador->endereco ?? 'NAO INFORMADO',
    'endereco_bairro' => 'CENTRO',
    'endereco_cidade' => $transportador->cidade ?? 'SAO PAULO',
    'endereco_estado' => $transportador->uf ?? 'SP',
    'contato_celular' => $transportador->telefone ?? '00000000000',
    'contato_email' => $transportador->email ?? 'nao.informado@exemplo.com'
];
```

---

## 📦 DTO Laravel - EmitirVPORequest

```php
<?php

namespace App\Services\NddCargo\DTOs;

class EmitirVPORequest
{
    /**
     * @param string $cpfCnpj CPF/CNPJ do transportador (validado)
     * @param string $anttRntrc Código RNTRC (validado)
     * @param string $anttNome Nome/Razão Social na ANTT (validado)
     * @param string $anttValidade Data validade RNTRC YYYY-MM-DD (validado)
     * @param string $anttStatus Status RNTRC (validado)
     * @param string $placa Placa do veículo (validado)
     * @param string $veiculoTipo Tipo do veículo (validado)
     * @param string $veiculoModelo Modelo do veículo (validado)
     * @param string $condutorRg RG do condutor (validado)
     * @param string $condutorNome Nome completo (obrigatório)
     * @param string $condutorSexo M ou F (obrigatório)
     * @param string $condutorNomeMae Nome da mãe (obrigatório)
     * @param string $condutorDataNascimento Data YYYY-MM-DD (obrigatório)
     * @param string $enderecoRua Rua/logradouro (obrigatório)
     * @param string $enderecoBairro Bairro (obrigatório)
     * @param string $enderecoCidade Cidade (obrigatório)
     * @param string $enderecoEstado UF (obrigatório)
     * @param string $contatoCelular Telefone celular (obrigatório)
     * @param string $contatoEmail E-mail (obrigatório)
     */
    public function __construct(
        // 🟢 VALIDADOS
        public readonly string $cpfCnpj,
        public readonly string $anttRntrc,
        public readonly string $anttNome,
        public readonly string $anttValidade,
        public readonly string $anttStatus,
        public readonly string $placa,
        public readonly string $veiculoTipo,
        public readonly string $veiculoModelo,
        public readonly string $condutorRg,

        // 🔴 OBRIGATÓRIOS (não validados)
        public readonly string $condutorNome,
        public readonly string $condutorSexo,
        public readonly string $condutorNomeMae,
        public readonly string $condutorDataNascimento,
        public readonly string $enderecoRua,
        public readonly string $enderecoBairro,
        public readonly string $enderecoCidade,
        public readonly string $enderecoEstado,
        public readonly string $contatoCelular,
        public readonly string $contatoEmail
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        // 🟢 Validar campos críticos
        if (!preg_match('/^\d{14}$/', $this->cpfCnpj)) {
            throw new \InvalidArgumentException('CPF/CNPJ inválido (14 dígitos)');
        }

        if (!preg_match('/^[A-Z]{3}\d[A-Z0-9]\d{2}$/', $this->placa)) {
            throw new \InvalidArgumentException('Placa inválida (formato Mercosul)');
        }

        if ($this->anttStatus !== 'Ativo') {
            throw new \InvalidArgumentException('RNTRC deve estar Ativo');
        }

        if (strtotime($this->anttValidade) < time()) {
            throw new \InvalidArgumentException('RNTRC vencido');
        }

        // 🔴 Validar campos obrigatórios (formato básico)
        if (strlen($this->condutorNome) < 3) {
            throw new \InvalidArgumentException('Nome do condutor muito curto');
        }

        if (!in_array($this->condutorSexo, ['M', 'F'])) {
            throw new \InvalidArgumentException('Sexo deve ser M ou F');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->condutorDataNascimento)) {
            throw new \InvalidArgumentException('Data nascimento inválida (YYYY-MM-DD)');
        }

        if (strlen($this->enderecoEstado) !== 2) {
            throw new \InvalidArgumentException('UF deve ter 2 letras');
        }
    }

    public function toArray(): array
    {
        return [
            'transportador' => [
                'cpf_cnpj' => $this->cpfCnpj,
                'antt_rntrc' => $this->anttRntrc,
                'antt_nome' => $this->anttNome,
                'antt_validade' => $this->anttValidade,
                'antt_status' => $this->anttStatus,
            ],
            'veiculo' => [
                'placa' => $this->placa,
                'tipo' => $this->veiculoTipo,
                'modelo' => $this->veiculoModelo,
            ],
            'condutor' => [
                'nome' => $this->condutorNome,
                'rg' => $this->condutorRg,
                'sexo' => $this->condutorSexo,
                'nome_mae' => $this->condutorNomeMae,
                'data_nascimento' => $this->condutorDataNascimento,
            ],
            'endereco' => [
                'rua' => $this->enderecoRua,
                'bairro' => $this->enderecoBairro,
                'cidade' => $this->enderecoCidade,
                'estado' => $this->enderecoEstado,
            ],
            'contato' => [
                'celular' => $this->contatoCelular,
                'email' => $this->contatoEmail,
            ],
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            cpfCnpj: $data['transportador']['cpf_cnpj'],
            anttRntrc: $data['transportador']['antt_rntrc'],
            anttNome: $data['transportador']['antt_nome'],
            anttValidade: $data['transportador']['antt_validade'],
            anttStatus: $data['transportador']['antt_status'],
            placa: $data['veiculo']['placa'],
            veiculoTipo: $data['veiculo']['tipo'],
            veiculoModelo: $data['veiculo']['modelo'],
            condutorRg: $data['condutor']['rg'],
            condutorNome: $data['condutor']['nome'],
            condutorSexo: $data['condutor']['sexo'],
            condutorNomeMae: $data['condutor']['nome_mae'],
            condutorDataNascimento: $data['condutor']['data_nascimento'],
            enderecoRua: $data['endereco']['rua'],
            enderecoBairro: $data['endereco']['bairro'],
            enderecoCidade: $data['endereco']['cidade'],
            enderecoEstado: $data['endereco']['estado'],
            contatoCelular: $data['contato']['celular'],
            contatoEmail: $data['contato']['email']
        );
    }
}
```

---

## 🎯 CASOS DE USO

### Caso 1: Dados Completos no Progress
```php
// Buscar transportador
$transportador = ProgressService::getTransporteById($codtrn);

// Buscar motorista
$motorista = ProgressService::getMotoristaById($codmot);

// Criar request
$request = new EmitirVPORequest(
    cpfCnpj: $transportador->codcnpjcpf,
    anttRntrc: $transportador->numrntrc,
    anttNome: $transportador->nomtrn,
    anttValidade: $transportador->datvalrntrc,
    anttStatus: 'Ativo',  // Verificar com ANTT
    placa: $veiculo->numpla,
    veiculoTipo: $veiculo->tipo,
    veiculoModelo: $veiculo->modelo,
    condutorRg: $motorista->numrg,
    condutorNome: $motorista->nommot,
    condutorSexo: $motorista->sexo ?? 'M',
    condutorNomeMae: $motorista->nome_mae ?? 'NAO INFORMADO',
    condutorDataNascimento: $motorista->data_nascimento ?? '1980-01-01',
    enderecoRua: $transportador->endereco,
    enderecoBairro: $transportador->bairro ?? 'CENTRO',
    enderecoCidade: $transportador->cidade,
    enderecoEstado: $transportador->uf,
    contatoCelular: $transportador->telefone,
    contatoEmail: $transportador->email ?? 'contato@tambasa.com.br'
);
```

### Caso 2: Dados Parciais (usar defaults)
```php
$request = new EmitirVPORequest(
    // 🟢 Dados críticos REAIS
    cpfCnpj: '17359233000188',
    anttRntrc: 'ANTT123456',
    anttNome: 'TRANSPORTADORA XYZ LTDA',
    anttValidade: '2026-12-31',
    anttStatus: 'Ativo',
    placa: 'ABC1D23',
    veiculoTipo: 'Caminhão Trator',
    veiculoModelo: 'Scania R450',
    condutorRg: '12.345.678-9',

    // 🔴 Dados obrigatórios GENÉRICOS (não validados)
    condutorNome: 'CONDUTOR NAO CADASTRADO',
    condutorSexo: 'M',
    condutorNomeMae: 'NAO INFORMADO',
    condutorDataNascimento: '1980-01-01',
    enderecoRua: 'NAO INFORMADO',
    enderecoBairro: 'CENTRO',
    enderecoCidade: 'SAO PAULO',
    enderecoEstado: 'SP',
    contatoCelular: '00000000000',
    contatoEmail: 'nao.informado@exemplo.com'
);
```

---

## ⚠️ ATENÇÃO

1. **🟢 Campos VALIDADOS** - A API NDD Cargo **VAI REJEITAR** se estiverem incorretos
2. **🔴 Campos NÃO VALIDADOS** - A API **ACEITA** qualquer valor (apenas registro)
3. **Placa Mercosul** - Formato `ABC1D23` (7 caracteres)
4. **RNTRC** - Deve estar **Ativo** e **Válido**
5. **CPF/CNPJ** - Deve estar **cadastrado na ANTT**

---

## 📚 REFERÊNCIAS

- ANTT - Consulta RNTRC: https://consulta.antt.gov.br/
- Padrão Placa Mercosul: https://www.gov.br/infraestrutura/pt-br/
- NDD Cargo API Docs: http://manuais.nddigital.com.br/nddCargo/

---

**Criado em:** 2025-12-05
**Versão:** 1.0.0
