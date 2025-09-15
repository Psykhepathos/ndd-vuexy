# CLAUDE.md

Este arquivo fornece orientações para o Claude Code (claude.ai/code) ao trabalhar neste repositório.

## Workflow de Desenvolvimento

### Versionamento e Git
- **IMPORTANTE**: Toda modificação testada e funcionando deve ser commitada no GitHub
- **NUNCA** mencionar Claude, AI ou ferramentas de IA nos commits
- **NUNCA** usar emojis nos commits
- Commits devem ser descritivos e técnicos
- Exemplo: `Add Vue dashboard with Laravel API integration`
- Exemplo: `Fix CORS configuration for Vuexy frontend`
- Exemplo: `Migrate Motorista CRUD from ndd-app to ndd-vuexy`

## Projeto NDD - Migração Flutter → Laravel + Vue (Vuexy)

Este é o **novo sistema unificado** Laravel + Vue usando o template **Vuexy TypeScript** para substituir a arquitetura anterior Flutter + Laravel separados.

**Repositórios do Projeto:**
- ❌ Backend Laravel (ANTIGO): https://github.com/Psykhepathos/ndd-laravel.git
- ❌ Frontend Flutter (ANTIGO): https://github.com/Psykhepathos/ndd-flutter.git  
- ✅ Sistema Unificado (NOVO): https://github.com/Psykhepathos/ndd-vuexy.git

## Estado da Migração - O QUE FOI FEITO

### ✅ Concluído
- ✅ Setup Vuexy TypeScript template completo
- ✅ Migração código Laravel de `ndd-app` → `ndd-vuexy`
- ✅ Remoção completa da arquitetura Kafka (substituída por ODBC direto)
- ✅ AuthController migrado com formato compatível Vuexy
- ✅ MotoristaController migrado com CRUD completo
- ✅ CORS configurado corretamente entre Laravel + Vue
- ✅ Vue dashboard criado em `/ndd-dashboard` 
- ✅ Login funcionando com Laravel Sanctum
- ✅ MSW (Mock Service Worker) desabilitado - usando APIs reais
- ✅ Estrutura de usuários criada para testes

### 🔄 PRÓXIMA ETAPA - TESTES ODBC PROGRESS
- **Testar conectividade ODBC com banco Progress**
- **Validar consultas SQL diretas ao banco corporativo**
- **Verificar se credenciais e configurações estão corretas**

## Instruções Importantes de Desenvolvimento

### Paths dos Projetos
- **Sistema Atual (Laravel + Vue)**: `C:\Users\15857\Desktop\NDD\ndd-vuexy`
- **Sistemas Antigos (DEPRECADOS)**:
  - Laravel Backend: `C:\Users\15857\Desktop\NDD\ndd-app`
  - Flutter Frontend: `C:\Users\15857\Desktop\NDD\ndd-flutter`

### Credenciais e Configurações
```env
# Progress Database (Sistema Corporativo)
PROGRESS_HOST=192.168.80.113
PROGRESS_DATABASE=tambasa
PROGRESS_USERNAME=sysprogress  
PROGRESS_PASSWORD=sysprogress

# Certificado Digital NDD
NDD_CERT_PASSWORD=AP300480

# URLs do Sistema
LARAVEL_API=http://localhost:8002
VUE_FRONTEND=http://localhost:5174
```

### Usuários de Teste Criados
```
Email: admin@ndd.com
Senha: 123456

Email: test@ndd.com  
Senha: 123456
```

## Comandos de Inicialização

### /init - Sequência de Inicialização (ATUALIZADA)
Execute SEMPRE nesta ordem:

```bash
# 1. Sistema Laravel + Vue (Terminal único)
cd "C:\Users\15857\Desktop\NDD\ndd-vuexy"

# 2. Backend Laravel (Terminal 1)
php artisan serve --port=8002        # API Laravel na porta 8002

# 3. Frontend Vue (Terminal 2)
pnpm run dev                         # Vue Vuexy na porta 5174

# 4. Verificação de Status
curl http://localhost:8002/api/motoristas    # Deve retornar JSON
# Abrir Vue: http://localhost:5174
# Login: admin@ndd.com / 123456
```

### Arquitetura do Sistema (NOVA)

#### Stack Tecnológica Atual
- **Laravel 12.15.0** - API backend unificado
- **Vue 3.5.14 + TypeScript 5.8.3** - Frontend com Vuexy template
- **Vuetify 3.8.5** - Material Design components
- **Pinia 3.0.2** - State management Vue
- **Laravel Sanctum** - Autenticação API
- **Progress ODBC** - Conexão direta banco corporativo (sem Kafka)
- **Vite 6.3.5** - Build tool frontend
- **PNPM** - Gerenciador de pacotes

#### Mudanças Arquiteturais
```
ANTES (Flutter + Laravel):
Flutter App ← REST API → Laravel ← Kafka → Progress (via Java JDBC)

AGORA (Vuexy + Laravel):  
Vue/Vuexy ← REST API → Laravel ← ODBC DIRETO → Progress Database
```

#### Estrutura de Diretórios
```
ndd-vuexy/
├── app/Models/                  # Models Laravel (User, Motorista, etc.)
├── app/Http/Controllers/Api/    # Controllers API (Auth, Motorista)
├── app/Services/                # Lógica negócio (sem Kafka)
├── database/migrations/         # Migrações banco
├── routes/api.php              # Rotas API Laravel
├── resources/ts/               # Frontend Vue/TypeScript
├── resources/ts/pages/         # Páginas Vue (login.vue, ndd-dashboard.vue)
├── resources/ts/plugins/       # Plugins Vue (MSW desabilitado)
└── CLAUDE.md                   # Este arquivo
```

### URLs Importantes  
- **Laravel API**: http://localhost:8002
- **Vue Frontend**: http://localhost:5174
- **Login Page**: http://localhost:5174/login
- **Dashboard NDD**: http://localhost:5174/ndd-dashboard
- **API Motoristas**: http://localhost:8002/api/motoristas

## PRÓXIMOS TESTES OBRIGATÓRIOS

### 1. Teste ODBC Progress Database
```bash
# Teste conexão ODBC Progress
php artisan tinker
# No tinker:
DB::connection('progress')->select('SELECT COUNT(*) FROM motoristas');
```

### 2. Teste APIs Migradas
```bash
# Testar CRUD Motorista
curl -X GET http://localhost:8002/api/motoristas

# Criar motorista teste
curl -X POST http://localhost:8002/api/motoristas \
  -H "Content-Type: application/json" \
  -d '{"codigo_progress":"TEST001","nome":"Teste ODBC","cpf":"11111111111","cnh":"CNH111"}'
```

### 3. Teste Frontend Vuexy
- Login: http://localhost:5174/login (admin@ndd.com / 123456)
- Dashboard: http://localhost:5174/ndd-dashboard
- Verificar se dados carregam do Laravel
- Console deve mostrar: "MSW/Fake API disabled - using real Laravel backend"

### 4. Validação Progress Integration
- Verificar se consultas SQL funcionam no banco corporativo
- Testar CRUD real com dados Progress
- Validar se certificado digital está sendo usado corretamente

## Regras de Desenvolvimento (ATUALIZADAS)

- **SEMPRE** testar Vue + Laravel integration após mudanças
- **NUNCA** usar Kafka (foi removido da arquitetura)  
- **SEMPRE** usar ODBC direto para Progress
- **USAR** Vuexy components nativos quando possível
- **TESTAR** responsividade do Vuexy template
- **EVITAR** muitos testes para economizar tokens
- **SEMPRE** testar funcionalidade antes de commit

### 🎨 REGRA FUNDAMENTAL - TEMPLATES VUEXY OBRIGATÓRIOS

**NUNCA criar interface do zero - SEMPRE copiar templates existentes:**

#### Templates de Referência Obrigatórios:
- **Lista com paginação**: `resources/ts/pages/apps/user/list/index.vue`
- **Cards de estatística**: `resources/ts/views/apps/logistics/LogisticsCardStatistics.vue` 
- **Formulários**: `resources/ts/pages/apps/user/view/UserBioPanel.vue`
- **Dashboards**: `resources/ts/pages/apps/logistics/dashboard.vue`

#### Padrões Vuexy OBRIGATÓRIOS:
- **Headers de página**: `text-h4 font-weight-medium mb-1` + `text-body-1 mb-0`
- **Cards**: Sempre usar `VCard` com `VCardText` 
- **Botões**: `VBtn` com `prepend-icon` quando apropriado
- **Textfields**: `AppTextField` ao invés de `VTextField`  
- **Selects**: `AppSelect` ao invés de `VSelect`
- **Paginação**: `TablePagination` component
- **DataTables**: `VDataTableServer` com `v-model:items-per-page` e `v-model:page`
- **Cores**: Classes `text-high-emphasis`, `text-medium-emphasis` para temas
- **Espaçamentos**: `gap-4`, `mb-6`, `me-3` seguindo padrão Vuetify

#### ❌ PROIBIDO:
- Criar layouts customizados sem verificar templates existentes
- Usar `VTextField` ou `VSelect` diretamente
- Ignorar classes de tema (`text-high-emphasis`, etc.)
- Criar paginação customizada
- Usar cores hardcoded ao invés de theme colors

### 🔄 COMMITS E GITHUB

**SEMPRE fazer commits como o próprio usuário (não Claude):**

#### Configuração Git Obrigatória:
```bash
# Commits devem ser sempre como Psykhepathos
git config --global user.name "Psykhepathos"
git config --global user.email "[email protegido]"

# Verificar configuração
git config --global user.name
git config --global user.email
```

#### Fluxo de Commits:
1. **SEMPRE** commitar mudanças funcionais testadas
2. **NUNCA** mencionar Claude/AI nos commits
3. **USAR** mensagens técnicas descritivas
4. **PUSH** para: https://github.com/Psykhepathos/ndd-vuexy.git

#### Exemplos de Commits Corretos:
- ✅ `Add transporter search with Progress JDBC integration`
- ✅ `Fix pagination issues in data table components`
- ✅ `Update Vuexy template styling for consistency`
- ❌ `Claude helped implement search functionality`
- ❌ `AI-generated transporter page improvements`

## Estado Atual - Sistema Vuexy Funcionando ✅

- ✅ Laravel API rodando na porta 8002
- ✅ Vue Vuexy rodando na porta 5174  
- ✅ Login/Auth funcionando com Sanctum
- ✅ CORS configurado corretamente
- ✅ Dashboard NDD carregando dados via API
- ✅ MSW desabilitado (console limpo)
- ✅ MotoristaController migrado e funcional
- 🔄 **PRÓXIMO**: Testar conectividade ODBC Progress

## Comandos de Desenvolvimento

### Frontend (Vue/Vuexy)
```bash
# Desenvolvimento
pnpm run dev                    # Servidor desenvolvimento (porta 5174)
pnpm run build                  # Build produção
pnpm run typecheck              # Verificação TypeScript
pnpm run lint                   # Linter/formatter ESLint

# Dependências
pnpm install                    # Instalar dependências
pnpm run build:icons            # Build ícones Iconify
```

### Backend (Laravel)
```bash
# Servidor
php artisan serve --port=8002   # API Laravel (porta 8002)

# Cache e configuração
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Database
php artisan migrate
php artisan db:seed
php artisan tinker              # Console interativo

# Testes
php artisan test                # Executar testes PHPUnit
```

### Desenvolvimento Integrado
```bash
# Composer script personalizado (inicia tudo)
composer dev                    # Laravel + Queue + Logs + Vite concorrente

# Verificação manual
curl http://localhost:8002/api/motoristas    # Teste API
# Abrir: http://localhost:5174               # Frontend Vue
```

## 🗄️ ACESSO AO SCHEMA DO BANCO PROGRESS

### Conexão JDBC Progress OpenEdge
O sistema utiliza **JDBC direto** com Progress OpenEdge via Laravel:

```php
// Configuração no config/database.php
'progress' => [
    'driver' => 'odbc',
    'dsn' => 'odbc:Driver={DataDirect 32-BIT OpenEdge Wire Protocol};Host=192.168.80.113;Port=13361;Database=tambasa',
    'username' => 'sysprogress',
    'password' => 'sysprogress',
]
```

### 🔍 COMANDOS PARA EXPLORAR O SCHEMA

#### 1. Teste de Conexão
```bash
# Via API (método mais confiável)
curl "http://localhost:8002/api/progress/test-connection"

# Via PHP Artisan Tinker
php artisan tinker
DB::connection('progress')->select('SELECT COUNT(*) as total FROM PUB.pacote')
```

#### 2. Listar Todas as Tabelas
```bash
# Query para listar tabelas do schema PUB
curl -X POST "http://localhost:8002/api/progress/query" \
  -H "Content-Type: application/json" \
  -d '{"sql":"SELECT TBL FROM SYSPROGRESS.SYSTABLES WHERE OWNER = '\''PUB'\'' ORDER BY TBL"}'
```

#### 3. Ver Estrutura de uma Tabela
```bash
# Schema da tabela pacote
curl -X POST "http://localhost:8002/api/progress/query" \
  -H "Content-Type: application/json" \
  -d '{"sql":"SELECT COL, COLTYPE, WIDTH, SCALE FROM SYSPROGRESS.SYSCOLUMNS WHERE TBL = '\''pacote'\'' AND OWNER = '\''PUB'\'' ORDER BY COL"}'

# Schema da tabela transporte
curl -X POST "http://localhost:8002/api/progress/query" \
  -H "Content-Type: application/json" \
  -d '{"sql":"SELECT COL, COLTYPE, WIDTH, SCALE FROM SYSPROGRESS.SYSCOLUMNS WHERE TBL = '\''transporte'\'' AND OWNER = '\''PUB'\'' ORDER BY COL"}'
```

#### 4. Explorar Relacionamentos (Chaves Estrangeiras)
```bash
# Ver índices e relacionamentos
curl -X POST "http://localhost:8002/api/progress/query" \
  -H "Content-Type: application/json" \
  -d '{"sql":"SELECT IDXNAME, COL, ASCENDING FROM SYSPROGRESS.SYSINDEXES WHERE TBL = '\''pacote'\'' AND OWNER = '\''PUB'\'' ORDER BY IDXNAME, IDXSEQ"}'
```

#### 5. Amostras de Dados
```bash
# Ver primeiros registros de uma tabela
curl -X POST "http://localhost:8002/api/progress/query" \
  -H "Content-Type: application/json" \
  -d '{"sql":"SELECT TOP 5 * FROM PUB.pacote ORDER BY codpac DESC"}'

# Contar registros
curl -X POST "http://localhost:8002/api/progress/query" \
  -H "Content-Type: application/json" \
  -d '{"sql":"SELECT COUNT(*) as total FROM PUB.pacote"}'
```

### 📋 TABELAS PRINCIPAIS IDENTIFICADAS

#### Sistema de Transporte:
- **`PUB.transporte`** - Transportadores (empresas e autônomos)
- **`PUB.motorista`** - Motoristas associados aos transportadores  
- **`PUB.veiculos`** - Veículos dos transportadores
- **`PUB.trnmot`** - Relacionamento transporte-motorista

#### Sistema de Pacotes:
- **`PUB.pacote`** - Pacotes de entrega
- **`PUB.carga`** - Cargas dentro dos pacotes
- **`PUB.pedido`** - Pedidos individuais nas cargas
- **`PUB.cliente`** - Dados dos clientes destinatários
- **`PUB.notafiscal`** - Notas fiscais dos pedidos

#### Dados Geográficos:
- **`PUB.arqrdnt`** - Coordenadas GPS (lat/long) dos pedidos
- **`PUB.estado`** - Estados (UF)
- **`PUB.municipio`** - Municípios
- **`PUB.bairro`** - Bairros
- **`PUB.basecliente`** - Base de dados dos clientes
- **`PUB.razao`** - Razão social das empresas

### 🔗 RELACIONAMENTOS PRINCIPAIS

#### Estrutura de Pacotes:
```
PACOTE (codpac) 
  ↓ 1:N
CARGA (codpac, codcar)
  ↓ 1:N  
PEDIDO (codcar, numseqped)
  ↓ 1:1
CLIENTE (codcli)
  ↓ 1:1
ARQRDNT (asdped) -- GPS coordinates
```

#### Query de Exemplo - Itinerário Completo:
```sql
SELECT 
  p.codpac,
  ped.numseqped as seqent,
  cli.codcli,
  cli.desend,
  ard.lat as gps_lat,
  ard.long as gps_lon
FROM PUB.pacote p
  INNER JOIN PUB.carga car ON car.codpac = p.codpac
  INNER JOIN PUB.pedido ped ON ped.codcar = car.codcar  
  INNER JOIN PUB.cliente cli ON cli.codcli = ped.codcli
  LEFT JOIN PUB.arqrdnt ard ON ard.asdped = ped.asdped
WHERE p.codpac = 3000001
  AND ped.valtotateped > 0
ORDER BY ped.numseqped
```

### 🛠️ FERRAMENTAS DE DESENVOLVIMENTO

#### ProgressService.php - Métodos Úteis:
```php
// Classe: App\Services\ProgressService
$service = new ProgressService();

// Testar conexão
$service->testConnection()

// Query customizada  
$service->executeCustomQuery($sql)

// Métodos específicos
$service->getPacotesPaginated($filters)
$service->getItinerarioPacote($codPac)
$service->getTransportesPaginated($filters)
```

#### Controllers API Disponíveis:
- **`/api/progress/test-connection`** - Teste de conexão
- **`/api/progress/query`** - Query SQL customizada
- **`/api/pacotes`** - CRUD pacotes
- **`/api/pacotes/itinerario`** - Itinerário de entregas
- **`/api/transportes`** - CRUD transportadores

### 🚨 IMPORTANTE - JDBC vs ELOQUENT

**O sistema usa JDBC DIRETO, NÃO Eloquent ORM:**

❌ **NUNCA fazer:**
```php
// ERRADO - Eloquent models não funcionam
$pacote = Pacote::find(123);
$transporte = Transporte::where('nome', 'like', '%test%')->get();
```

✅ **SEMPRE fazer:**
```php
// CORRETO - JDBC direto via DB::connection
$pacotes = DB::connection('progress')->select(
    'SELECT * FROM PUB.pacote WHERE codpac = ?', [$id]
);

// Ou via ProgressService
$result = $this->progressService->executeCustomQuery($sql);
```

### 📝 NOTAS DE DESENVOLVIMENTO

#### Convenções Progress:
- **Schema**: Sempre usar `PUB.tabela`
- **Campos**: Progress é case-sensitive
- **TOP N**: Usar `SELECT TOP 10` (não LIMIT)
- **Strings**: Usar aspas simples `'valor'`
- **Booleans**: 0/1 (não true/false)

#### Performance:
- **Sempre** usar índices nas consultas
- **Evitar** SELECT * em tabelas grandes
- **Usar** paginação com TOP/SKIP
- **Testar** queries grandes via `/api/progress/query` primeiro

### Notas Importantes
- **Não criar arquivos desnecessários de teste** - usar terminal para sequências
- **Economia de tokens**: resposta mínima, sem emojis
- **Não modificar CLAUDE.md** no comando /init
- **Usar APIs para explorar schema** - mais confiável que tinker direto