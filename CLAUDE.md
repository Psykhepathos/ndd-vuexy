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

### Notas Importantes
- **Não criar arquivos desnecessários de teste** - usar terminal para sequências
- **Economia de tokens**: resposta mínima, sem emojis
- **Não modificar CLAUDE.md** no comando /init
- **Focar nos testes ODBC** como próxima prioridade