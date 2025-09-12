# 🗄️ Progress Database Schema - Sistema NDD

Documentação completa das tabelas do banco Progress OpenEdge utilizado no sistema NDD de gestão de transporte.

## 📊 Informações Gerais do Banco

- **Servidor**: 192.168.80.113:13361
- **Database**: tambasa
- **Schema**: PUB
- **Conexão**: JDBC Progress OpenEdge Driver

---

## 🚚 Tabela: `PUB.transporte`

Tabela principal que armazena informações das transportadoras/transportadores.

### Estatísticas
- **Total de Registros**: 6,913 transportadores
- **Total de Colunas**: 115 campos
- **Chave Primária**: `codtrn` (INTEGER)
- **Total de Índices**: 10 índices

### Campos Principais com Descrições

#### 🏢 Identificação da Transportadora
| Campo | Tipo | Tamanho | Descrição |
|-------|------|---------|-----------|
| `codtrn` | INTEGER | 10 | **Código único do transportador** (PK) |
| `nomtrn` | VARCHAR | 60 | **Nome da Transportadora** |
| `codcnpjcpf` | VARCHAR | 28 | **Código CNPJ da empresa** |
| `flgautonomo` | BIT | 1 | Flag se é autônomo |
| `flgati` | BIT | 1 | Flag se está ativo |

#### 📍 Endereço Completo
| Campo | Tipo | Tamanho | Descrição |
|-------|------|---------|-----------|
| `desend` | VARCHAR | 60 | **Endereço da Transportadora** |
| `numend` | VARCHAR | 16 | **Número do endereço da transportadora** |
| `cplend` | VARCHAR | 60 | **Complemento do endereço** |
| `tiplog` | VARCHAR | 16 | **Tipo de logradouro** |
| `codlog` | INTEGER | 10 | **Código do logradouro** (referência corporativa) |
| `codest` | INTEGER | 10 | **Código do estado** (referência corporativa) |
| `codmun` | INTEGER | 10 | **Código do município** (referência corporativa) |
| `codbai` | INTEGER | 10 | **Código do bairro** (referência corporativa) |
| `numceptrn` | INTEGER | 10 | **CEP da transportadora** |

#### 📞 Contatos
| Campo | Tipo | Tamanho | Descrição |
|-------|------|---------|-----------|
| `numtel` | INTEGER | 10 | **Número de telefone da transportadora** |
| `dddtel` | INTEGER | 10 | DDD do telefone principal |
| `numtel1` | INTEGER | 10 | **Número de telefone extra** |
| `dddtel1` | INTEGER | 10 | **DDD do número de telefone extra** |
| `numtel2` | INTEGER | 10 | **Número de telefone extra** |
| `dddtel2` | INTEGER | 10 | **DDD do número de telefone extra** |
| `numcel` | INTEGER | 10 | **Número de celular da transportadora** |
| `dddcel` | INTEGER | 10 | DDD do celular |
| `numfax` | INTEGER | 10 | **Fax da transportadora** |
| `dddfax` | INTEGER | 10 | **DDD do fax** |
| `e-mail` | VARCHAR | 100 | **E-mail da transportadora** |
| `emailcd` | VARCHAR | 300 | Email para CD |
| `descnt` | VARCHAR | 40 | **Descrição de contato da transportadora** |

#### 🚛 Informações do Veículo
| Campo | Tipo | Tamanho | Descrição |
|-------|------|---------|-----------|
| `numpla` | VARCHAR | 14 | Placa do veículo principal |
| `desvei` | VARCHAR | 60 | Descrição do veículo |
| `fabmod` | VARCHAR | 18 | Fabricante/modelo |
| `marvei` | VARCHAR | 15 | Marca do veículo |
| `corvei` | VARCHAR | 15 | Cor do veículo |
| `ufvei` | VARCHAR | 2 | UF do veículo |
| `renavam` | VARCHAR | 16 | RENAVAM |
| `numcha` | VARCHAR | 50 | Número do chassi |
| `tipcam` | INTEGER | 10 | **Código do caminhão** (campo obrigatório) |
| `natcam` | VARCHAR | 2 | **Tipo de transporte** ("T" ou "A") |

#### 🚚 Carreta/Reboque
| Campo | Tipo | Tamanho | Descrição |
|-------|------|---------|-----------|
| `placar` | VARCHAR | 14 | Placa da carreta |
| `placar2` | VARCHAR | 7 | Placa carreta 2 |
| `rencar` | VARCHAR | 11 | RENAVAM carreta |
| `rencar2` | VARCHAR | 11 | RENAVAM carreta 2 |
| `chacar` | VARCHAR | 25 | Chassi carreta |
| `chacar2` | VARCHAR | 25 | Chassi carreta 2 |
| `ufcar` | VARCHAR | 2 | UF carreta |
| `ufcar2` | VARCHAR | 2 | UF carreta 2 |

#### 📦 Capacidades do Veículo
| Campo | Tipo | Tamanho | Descrição |
|-------|------|---------|-----------|
| `pesmax` | NUMERIC | 17,2 | Peso máximo |
| `volmax` | NUMERIC | 17,2 | Volume máximo |
| `altmax` | NUMERIC | 10,2 | Altura máxima |
| `larmax` | NUMERIC | 10,2 | Largura máxima |
| `commax` | NUMERIC | 10,2 | Comprimento máximo |

#### 🆔 Documentação Pessoal
| Campo | Tipo | Tamanho | Descrição |
|-------|------|---------|-----------|
| `numhab` | VARCHAR | 40 | Número da habilitação (CNH) |
| `venhab` | DATE | 10 | Vencimento da habilitação |
| `esthab` | VARCHAR | 2 | Estado da habilitação |
| `cathab` | VARCHAR | 1 | Categoria da habilitação |
| `numrg` | VARCHAR | 15 | RG |
| `orgrg` | VARCHAR | 10 | Órgão expedidor RG |
| `exprg` | DATE | 10 | Data expedição RG |
| `datnas` | DATE | 10 | Data de nascimento |

#### 🏪 Operacional
| Campo | Tipo | Tamanho | Descrição |
|-------|------|---------|-----------|
| `indcd` | VARCHAR | 2 | **Indica se faz transporte de CD** |
| `perfre` | NUMERIC | 17,2 | Percentual frete |
| `flgfrefix` | BIT | 1 | Flag frete fixo |
| `flgpgt` | BIT | 1 | Flag pagamento |
| `flgrastrear` | BIT | 1 | Flag rastreamento |

### 🗂️ Índices da Tabela
1. **CodTrn** (UNIQUE) - Chave primária
2. **NomTrn** - Nome do transportador
3. **NumPla** - Placa do veículo
4. **CodEstMunBai** - Localização (Estado/Município/Bairro)
5. **natnrocam** - Natureza e número do caminhão
6. **tipcam** - Tipo do caminhão
7. **codfor** - Código fornecedor
8. **codban** - Código banco
9. **codcli** - Código cliente
10. **codlog** - Código logradouro

---

## 🚶‍♂️ Tabela: `PUB.trnmot` (Motoristas)

Tabela que armazena motoristas vinculados às transportadoras. Alguns transportadores são autônomos (operam por si mesmos), outros são motoristas de transportadoras.

### Campos Principais
> **Nota**: A estrutura é muito similar à tabela `transporte`, mas focada em motoristas individuais.

#### 👤 Identificação do Motorista
- **Mesma estrutura base** da tabela transporte
- Diferencia **transportadores autônomos** de **motoristas de empresas**
- Relacionamento com transportadoras através de chaves estrangeiras

#### 📋 Campos Compartilhados
| Campo | Descrição |
|-------|-----------|
| `nomtrn` | Nome do Motorista |
| `desend` | Endereço do Motorista |
| `codest` | Código do estado |
| `codmun` | Código do município |
| `codbai` | Código do bairro |
| `numceptrn` | CEP |
| `numtel` | Telefone principal |
| `numfax` | Fax |
| `descnt` | Descrição de contato |
| `tipcam` | Código do caminhão (obrigatório) |
| `natcam` | Tipo de transporte ("T" ou "A") |
| `numcel` | Celular |
| `codcnpjcpf` | CPF/CNPJ |
| `numend` | Número do endereço |
| `cplend` | Complemento |
| `tiplog` | Tipo de logradouro |
| `codlog` | Código do logradouro |
| `e-mail` | Email |
| `indcd` | Transporte de CD |
| `dddtel1` | DDD telefone extra 1 |
| `numtel1` | Telefone extra 1 |
| `dddtel2` | DDD telefone extra 2 |
| `numtel2` | Telefone extra 2 |
| `dddfax` | DDD do fax |

---

## 🔧 APIs Implementadas

### Endpoints da Tabela Transporte
- **GET** `/api/transportes` - Lista transportadores paginados
- **GET** `/api/transportes/{id}` - Busca transportador específico
- **GET** `/api/transportes/schema` - Schema completo da tabela
- **POST** `/api/transportes/query` - Executa SQL customizado
- **GET** `/api/transportes/test-connection` - Testa conexão

### Exemplo de Uso da API Schema
```bash
curl -X GET "http://localhost:8002/api/transportes/schema"
```

---

## ⚠️ Observações Importantes

### Dados Corrompidos
- **Campo problemático**: `nattrn` no registro rowid 2605569
- **Solução**: Evitar consultas `SELECT *` que incluam este registro

### Campos Obrigatórios Especiais
- **tipcam**: Campo "estranhamente obrigatório" segundo documentação corporativa
- **natcam**: Tipo de transporte com valores específicos ("T" ou "A")

### Relacionamentos
- Estados, municípios e bairros são referenciados por códigos corporativos
- Logradouros têm códigos específicos no sistema
- Tipos de caminhão seguem codificação própria da empresa

---

## 🎯 Campos Mais Utilizados

Para interfaces e relatórios, focar nestes campos essenciais:

### Básicos
- `codtrn` - ID único
- `nomtrn` - Nome
- `codcnpjcpf` - CNPJ/CPF
- `flgati` - Status ativo

### Contato
- `e-mail` - Email principal
- `numtel` + `dddtel` - Telefone
- `numcel` + `dddcel` - Celular

### Veículo
- `numpla` - Placa
- `tipcam` - Tipo do caminhão
- `natcam` - Natureza do transporte

### Endereço
- `desend` - Endereço
- `numend` - Número
- `codest`, `codmun`, `codbai` - Localização

---

## 📈 Performance

- **Total de registros**: 6,913 transportadores
- **Tempo de resposta**: < 500ms para consultas básicas
- **Paginação**: Implementada no servidor usando TOP do Progress
- **Busca otimizada**: Função LEFT() para busca por nome
- **Índices**: 10 índices para otimização de consultas

---

**Documentação gerada**: 2025-09-12  
**Sistema**: NDD Vuexy - Gestão de Transporte  
**Versão do Progress**: OpenEdge 10.2B  