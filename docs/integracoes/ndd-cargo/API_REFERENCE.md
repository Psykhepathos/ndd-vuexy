# API Reference - VPO System

> Referência rápida de todos os endpoints da API VPO

**Base URL:** `http://localhost:8002/api`

---

## Índice de Endpoints

| Grupo | Endpoint | Método | Descrição |
|-------|----------|--------|-----------|
| **Auth** | `/auth/login` | POST | Login |
| **Auth** | `/auth/register` | POST | Registro |
| **Auth** | `/auth/logout` | POST | Logout |
| **VPO Sync** | `/vpo/transportadores` | GET | Listar transportadores |
| **VPO Sync** | `/vpo/transportadores/{codtrn}` | GET | Buscar transportador |
| **VPO Sync** | `/vpo/transportadores/{codtrn}` | PUT | Atualizar transportador |
| **VPO Sync** | `/vpo/sync/transportador` | POST | Sincronizar transportador |
| **VPO Motoristas** | `/vpo/motoristas/{codtrn}/verificar` | GET | Verificar se precisa seleção |
| **VPO Motoristas** | `/vpo/motoristas/{codtrn}` | GET | Listar motoristas |
| **VPO Motoristas** | `/vpo/motoristas/{codtrn}/{codmot}` | GET | Buscar motorista |
| **VPO Motoristas** | `/vpo/motoristas/{codtrn}/{codmot}` | POST | Salvar motorista |
| **VPO Motoristas** | `/vpo/motoristas/{codtrn}/completos` | GET | Listar completos |
| **VPO Emissão** | `/vpo/emissao/iniciar` | POST | Iniciar emissão |
| **VPO Emissão** | `/vpo/emissao/{uuid}/status` | GET | Status da emissão |
| **VPO Emissão** | `/vpo/emissao/{uuid}/calcular` | POST | Calcular custo |
| **VPO Emissão** | `/vpo/emissao/{uuid}/confirmar` | POST | Confirmar emissão |
| **VPO Emissão** | `/vpo/emissao/{uuid}/recibo` | POST | Gerar recibo |
| **VPO Emissão** | `/vpo/emissao/{uuid}/cancelar` | POST | Cancelar |
| **Pacotes** | `/pacotes` | GET | Listar pacotes |
| **Pacotes** | `/pacotes/{id}` | GET | Detalhes pacote |
| **Rotas** | `/semparar-rotas` | GET | Listar rotas |
| **Rotas** | `/semparar-rotas/{id}/municipios` | GET | Rota com municípios |

---

## 1. Autenticação

### POST /auth/login

```bash
curl -X POST http://localhost:8002/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@ndd.com","password":"Admin@123"}'
```

**Response:**
```json
{
  "accessToken": "1|abc123...",
  "userData": {
    "id": 1,
    "name": "Admin",
    "email": "admin@ndd.com",
    "role": "admin"
  },
  "userAbilityRules": [...]
}
```

**Usar token:**
```
Authorization: Bearer 1|abc123...
```

---

## 2. VPO - Sincronização

### GET /vpo/transportadores

Lista transportadores com dados VPO em cache.

**Query Params:**
| Param | Tipo | Default | Descrição |
|-------|------|---------|-----------|
| `page` | int | 1 | Página |
| `per_page` | int | 15 | Itens por página (max: 100) |
| `search` | string | - | Busca por nome/documento |
| `tipo` | string | - | `autonomo` ou `empresa` |
| `dados_completos` | bool | - | Filtrar por dados completos |
| `score_minimo` | int | - | Score mínimo (0-100) |

```bash
curl "http://localhost:8002/api/vpo/transportadores?page=1&tipo=empresa&score_minimo=50"
```

### POST /vpo/sync/transportador

Sincroniza transportador do Progress para cache.

**Body:**
```json
{
  "codtrn": 3247,
  "codmot": 1,          // Opcional (empresas)
  "placa": "ABC1234",   // Opcional
  "force_antt": false   // Opcional
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 4,
    "codtrn": 3247,
    "codmot": 1,
    "cpf_cnpj": "12345678901",
    "antt_rntrc": "00000012345",
    "condutor_nome": "BRUNO FERNANDES",
    "condutor_nome_mae": "MARIA SILVA",
    "score_qualidade": 85,
    "fontes_dados": {
      "progress_transporte": true,
      "progress_trnmot": true,
      "cache_motorista": true,
      "cache_motorista_id": 1
    }
  },
  "message": "Sincronização concluída com sucesso (score: 85/100)"
}
```

### PUT /vpo/transportadores/{codtrn}

Atualiza dados do transportador manualmente.

**Body:**
```json
{
  "antt_rntrc": "00000012345",
  "condutor_nome_mae": "MARIA SILVA",
  "condutor_data_nascimento": "1990-05-15",
  "contato_celular": "31999999999",
  "contato_email": "email@example.com"
}
```

---

## 3. VPO - Motoristas de Empresas

### GET /vpo/motoristas/{codtrn}/verificar

Verifica se transportador é empresa e precisa selecionar motorista.

```bash
curl http://localhost:8002/api/vpo/motoristas/3247/verificar
```

**Response:**
```json
{
  "success": true,
  "codtrn": 3247,
  "is_empresa": true,
  "tem_motoristas": true,
  "requer_selecao_motorista": true,
  "mensagem": "Empresa com motoristas. Necessário selecionar motorista para VPO."
}
```

### GET /vpo/motoristas/{codtrn}

Lista todos motoristas de uma empresa.

```bash
curl http://localhost:8002/api/vpo/motoristas/3247
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "codtrn": 3247,
      "codmot": 1,
      "nommot": "BRUNO FERNANDES",
      "numrg": "MG16405467",
      "nompai": "JOSE FERNANDES",
      "nommae": "MARIA SILVA",
      "codrntrc_progress": "",
      "cpf": "12345678901",
      "rntrc": "00000012345",
      "data_nascimento": "1990-05-15",
      "tem_cache": true,
      "dados_completos": true,
      "campos_faltantes": []
    },
    {
      "codtrn": 3247,
      "codmot": 2,
      "nommot": "CARLOS DIAS",
      "numrg": "MG2054673",
      "nompai": "JOSE DIAS",
      "nommae": "LUZIA DIAS",
      "codrntrc_progress": "",
      "cpf": "",
      "rntrc": "",
      "data_nascimento": "",
      "tem_cache": false,
      "dados_completos": false,
      "campos_faltantes": ["cpf", "rntrc", "nommot", "nommae", "data_nascimento"]
    }
  ],
  "total": 2,
  "completos": 1
}
```

### POST /vpo/motoristas/{codtrn}/{codmot}

Salva dados complementares do motorista.

```bash
curl -X POST http://localhost:8002/api/vpo/motoristas/3247/2 \
  -H "Content-Type: application/json" \
  -d '{
    "cpf": "98765432100",
    "rntrc": "00000067890",
    "nommae": "LUZIA AUTINA DIAS",
    "data_nascimento": "1985-03-20"
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Motorista salvo com sucesso",
  "data": {
    "codtrn": 3247,
    "codmot": 2,
    "nommot": "CARLOS DIAS",
    "cpf": "98765432100",
    "rntrc": "00000067890",
    "nommae": "LUZIA AUTINA DIAS",
    "data_nascimento": "1985-03-20",
    "dados_completos": true,
    "campos_faltantes": []
  },
  "dados_completos": true,
  "campos_faltantes": []
}
```

### GET /vpo/motoristas/{codtrn}/completos

Lista apenas motoristas prontos para VPO.

```bash
curl http://localhost:8002/api/vpo/motoristas/3247/completos
```

---

## 4. VPO - Emissão

### POST /vpo/emissao/iniciar

Inicia processo de emissão.

**Body:**
```json
{
  "codpac": 850000,
  "codtrn": 3247,
  "codmot": 1,
  "placa": "ABC1234",
  "rota_id": 204
}
```

**Response:**
```json
{
  "success": true,
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "status": "iniciado",
  "data": {
    "pacote": {...},
    "transportador": {...},
    "motorista": {...},
    "veiculo": {...}
  }
}
```

### POST /vpo/emissao/{uuid}/calcular

Calcula custo via NDD Cargo.

**Body:**
```json
{
  "data_inicio": "2025-12-15",
  "data_fim": "2025-12-20",
  "quantidade_eixos": 6
}
```

**Response:**
```json
{
  "success": true,
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "custo": {
    "valor_total": 450.00,
    "pedagios": [
      {
        "codigo": "P001",
        "nome": "Praça X",
        "rodovia": "BR-116",
        "km": 350,
        "valor": 150.00,
        "cidade": "São Paulo",
        "uf": "SP"
      }
    ],
    "rota_nome": "SP-MG Principal",
    "km_total": 650,
    "tempo_estimado": "7h 30min"
  }
}
```

### POST /vpo/emissao/{uuid}/confirmar

Confirma e emite o VPO.

**Body:**
```json
{
  "confirmar": true,
  "observacoes": "Viagem urgente"
}
```

**Response:**
```json
{
  "success": true,
  "uuid": "550e8400-e29b-41d4-a716-446655440000",
  "status": "concluido",
  "vpo": {
    "codigo_viagem": "VPO2025121500001",
    "data_emissao": "2025-12-09T15:30:00",
    "valor_total": 450.00,
    "validade_inicio": "2025-12-15",
    "validade_fim": "2025-12-20"
  }
}
```

### POST /vpo/emissao/{uuid}/recibo

Gera recibo do VPO.

**Body:**
```json
{
  "enviar_whatsapp": true,
  "enviar_email": true,
  "telefone": "31999999999",
  "email": "motorista@email.com"
}
```

---

## 5. Pacotes

### GET /pacotes

Lista pacotes.

**Query Params:**
| Param | Tipo | Descrição |
|-------|------|-----------|
| `page` | int | Página |
| `per_page` | int | Itens por página |
| `search` | string | Busca |
| `codigo` | string | Código do pacote |
| `transportador` | string | Código/nome do transportador |
| `situacao` | string | Situação |
| `apenas_recentes` | bool | codpac > 800000 |
| `data_inicio` | date | Data início |
| `data_fim` | date | Data fim |

```bash
curl "http://localhost:8002/api/pacotes?apenas_recentes=1&per_page=10"
```

---

## 6. Rotas SemParar

### GET /semparar-rotas

Lista rotas cadastradas.

**Query Params:**
| Param | Tipo | Descrição |
|-------|------|-----------|
| `page` | int | Página |
| `per_page` | int | Itens por página |
| `search` | string | Busca por nome |
| `flg_cd` | bool | Filtro CD |
| `flg_retorno` | bool | Filtro retorno |

### GET /semparar-rotas/{id}/municipios

Busca rota com municípios ordenados.

**Response:**
```json
{
  "success": true,
  "data": {
    "sPararRotID": 204,
    "desSPararRot": "ROTA SP-MG PRINCIPAL",
    "flgCD": false,
    "flgRetorno": false,
    "tempoViagem": 8,
    "municipios": [
      {
        "sPararMuSeq": 1,
        "codMun": 9668,
        "codEst": 26,
        "desMun": "SAO PAULO",
        "desEst": "SP",
        "cdibge": 3550308
      },
      {
        "sPararMuSeq": 2,
        "codMun": 4123,
        "codEst": 13,
        "desMun": "BELO HORIZONTE",
        "desEst": "MG",
        "cdibge": 3106200
      }
    ]
  }
}
```

---

## Códigos de Erro Comuns

| HTTP | Código | Descrição |
|------|--------|-----------|
| 400 | VALIDATION_ERROR | Dados inválidos |
| 401 | UNAUTHORIZED | Não autenticado |
| 403 | FORBIDDEN | Sem permissão |
| 404 | NOT_FOUND | Recurso não encontrado |
| 422 | UNPROCESSABLE | Regra de negócio violada |
| 429 | TOO_MANY_REQUESTS | Rate limit excedido |
| 500 | SERVER_ERROR | Erro interno |

**Exemplo de erro:**
```json
{
  "success": false,
  "error": "Motorista não encontrado no Progress",
  "code": "MOTORISTA_NOT_FOUND"
}
```

---

## Rate Limits

| Tipo | Limite |
|------|--------|
| Leitura (GET) | 60 req/min |
| Escrita (POST/PUT) | 30 req/min |
| Sync | 30 req/min |
| Debug | 10 req/min |

---

## Dicas para Frontend

### 1. Determinar Tipo de Transportador
```typescript
const isEmpresa = (doc: string) => doc.replace(/\D/g, '').length === 14
const isAutonomo = (doc: string) => doc.replace(/\D/g, '').length === 11
```

### 2. Fluxo de Seleção de Motorista
```typescript
// 1. Verificar se precisa
const { requer_selecao_motorista } = await api.get(`/vpo/motoristas/${codtrn}/verificar`)

if (requer_selecao_motorista) {
  // 2. Listar motoristas
  const { data: motoristas } = await api.get(`/vpo/motoristas/${codtrn}`)

  // 3. Filtrar completos
  const completos = motoristas.filter(m => m.dados_completos)

  // 4. Se não tem completos, pedir para completar
  if (completos.length === 0) {
    // Mostrar formulário
  }
}
```

### 3. Campos Obrigatórios VPO
```typescript
const CAMPOS_OBRIGATORIOS = ['cpf', 'rntrc', 'nommot', 'nommae', 'data_nascimento']
```

### 4. Score de Qualidade
```typescript
const getScoreLevel = (score: number) => {
  if (score >= 90) return { color: 'success', label: 'Excelente' }
  if (score >= 70) return { color: 'info', label: 'Bom' }
  if (score >= 50) return { color: 'warning', label: 'Regular' }
  return { color: 'error', label: 'Incompleto' }
}
```

---

**Última atualização:** 2025-12-09
