# 🚛 Sistema NDD - Gestão de Transporte

Sistema unificado para gestão de transportes com integração Progress OpenEdge, desenvolvido com Laravel + Vue.js utilizando o template Vuexy.

## 📋 Sobre o Projeto

O Sistema NDD é uma modernização completa da arquitetura de transporte da empresa, migrando de uma estrutura Flutter + Laravel separados para um sistema unificado Laravel + Vue.js com template profissional Vuexy.

### 🎯 Objetivos

- **Unificação**: Sistema único Laravel + Vue.js substituindo arquiteturas separadas
- **Modernização**: Interface moderna e responsiva com Vuexy TypeScript
- **Performance**: Conexão direta ODBC Progress eliminando overhead do Kafka
- **Usabilidade**: Interface intuitiva seguindo padrões Material Design

## 🚀 Funcionalidades

### ✅ Implementadas

#### 🔐 Autenticação
- Login/logout com Laravel Sanctum
- Gerenciamento de usuários
- Controle de sessões

#### 🚚 Gestão de Transportadores
- **Listagem paginada** com 6.913+ registros Progress
- **Busca avançada** por código numérico e nome
- **Interface responsiva** seguindo padrão Vuexy
- **Paginação otimizada** com controle de itens por página

#### 🔧 APIs REST
- **TransporteController** com endpoints CRUD
- **Paginação server-side** com Progress SQL
- **CORS configurado** para integração frontend
- **Filtros dinâmicos** para busca eficiente

### 🔄 Em Desenvolvimento

#### 👥 Gestão de Motoristas
- CRUD completo de motoristas
- Integração com CNH e documentos
- Controle de status (ativo/inativo/suspenso)

#### 📊 Dashboard Executivo
- Métricas de transporte em tempo real
- Gráficos de performance
- Indicadores de produtividade

#### 🧾 Sistema CIOT
- Gestão de Conhecimentos de Transporte
- Integração com órgãos reguladores
- Controle fiscal automatizado

#### 💳 Vale Pedágio
- Gestão de vales pedagio
- Controle de rotas e tarifas
- Relatórios financeiros

## 🛠️ Stack Tecnológica

### Backend
- **Laravel 12.15.0** - Framework PHP moderno
- **Progress OpenEdge** - Banco de dados corporativo
- **JDBC Connection** - Conectividade direta com Progress
- **Laravel Sanctum** - Autenticação API

### Frontend
- **Vue 3.5.14** - Framework JavaScript reativo
- **TypeScript 5.8.3** - Tipagem estática
- **Vuexy Template** - Template profissional
- **Vuetify 3.8.5** - Material Design Components
- **Pinia 3.0.2** - Gerenciamento de estado

### DevOps & Ferramentas
- **Vite 6.3.5** - Build tool moderna
- **PNPM** - Gerenciador de pacotes eficiente
- **ESLint** - Linting código
- **Git** - Controle de versão

## 🏗️ Arquitetura

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Vue/Vuexy     │    │   Laravel API   │    │ Progress OpenEdge│
│   Frontend      │◄──►│   Backend       │◄──►│   Database      │
│   Port: 5174    │    │   Port: 8002    │    │   JDBC Direct   │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Migração Arquitetural

**ANTES (Deprecado)**:
```
Flutter App ← REST API → Laravel ← Kafka → Progress (via Java JDBC)
```

**AGORA (Ativo)**:
```
Vue/Vuexy ← REST API → Laravel ← ODBC DIRETO → Progress Database
```

## 📦 Instalação

### Pré-requisitos
- PHP 8.2+
- Composer
- Node.js 18+
- PNPM
- Progress OpenEdge Client

### Setup do Projeto

```bash
# Clone o repositório
git clone https://github.com/Psykhepathos/ndd-vuexy.git
cd ndd-vuexy

# Instalar dependências PHP
composer install

# Instalar dependências Node.js
pnpm install

# Configurar ambiente
cp .env.example .env
# Editar .env com credenciais Progress

# Gerar chave da aplicação
php artisan key:generate

# Executar migrações
php artisan migrate

# Criar usuários padrão
php artisan db:seed
```

### Executar em Desenvolvimento

```bash
# Terminal 1: Laravel API
php artisan serve --port=8002

# Terminal 2: Frontend Vue
pnpm run dev

# Acessar aplicação
# Frontend: http://localhost:5174
# API: http://localhost:8002
```

### Login Padrão

```
Email: admin@ndd.com
Senha: 123456
```

## 🗄️ Configuração do Banco

### Progress OpenEdge

```env
# Progress Database
PROGRESS_HOST=192.168.80.113
PROGRESS_DATABASE=tambasa
PROGRESS_USERNAME=
PROGRESS_PASSWORD=
```

### Estrutura Atual

```sql
-- Tabela principal de transportadores
PUB.transporte (
  codtrn INTEGER,    -- Código do transportador
  nomtrn VARCHAR     -- Nome do transportador
)

-- 6.913+ registros ativos
```

## 📊 Performance

### Métricas Atuais

- **6.913 transportadores** indexados
- **Busca otimizada** usando `LEFT()` function
- **Paginação server-side** com TOP queries
- **Tempo de resposta** < 500ms para consultas

### Otimizações Implementadas

- Conexão JDBC direta eliminando overhead
- Queries SQL otimizadas para Progress
- Cache de resultados em memória
- Paginação eficiente com TOP/SKIP

## 🚦 Roadmap

### 📅 Próximas Versões

#### v2.0 - Gestão Completa
- [ ] CRUD Motoristas
- [ ] Sistema CIOT
- [ ] Vale Pedágio
- [ ] Relatórios avançados

#### v2.1 - Dashboard Executivo
- [ ] Métricas em tempo real
- [ ] Gráficos interativos
- [ ] Exportação de relatórios
- [ ] Notificações push

#### v2.2 - Mobile & PWA
- [ ] Aplicativo móvel
- [ ] Progressive Web App
- [ ] Sincronização offline
- [ ] Geolocalização

## 🤝 Desenvolvimento

### Padrões de Código

- **Frontend**: Seguir templates Vuexy rigorosamente
- **Backend**: PSR-12 PHP Standards
- **Git**: Conventional Commits
- **Testes**: Cobertura mínima 80%

### Estrutura de Diretórios

```
ndd-vuexy/
├── app/
│   ├── Http/Controllers/Api/    # Controllers da API
│   ├── Services/                # Lógica de negócio
│   └── Models/                  # Models Eloquent
├── resources/ts/
│   ├── pages/                   # Páginas Vue
│   ├── components/              # Componentes reutilizáveis
│   └── plugins/                 # Configurações Vue
├── routes/api.php              # Rotas da API
└── CLAUDE.md                   # Diretrizes de desenvolvimento
```

### Comandos Úteis

```bash
# Desenvolvimento
pnpm run dev              # Frontend dev server
php artisan serve         # Backend dev server

# Build & Deploy
pnpm run build           # Build produção
php artisan optimize     # Otimizar Laravel

# Testes & Qualidade
pnpm run lint            # ESLint frontend
php artisan test         # PHPUnit backend
pnpm run typecheck       # Verificação TypeScript
```

## 📞 Suporte

### Documentação
- [Laravel Documentation](https://laravel.com/docs)
- [Vue.js Guide](https://vuejs.org/guide/)
- [Vuexy Documentation](https://pixinvent.com/vuexy-vuejs-admin-template/)

### Contato
- **Desenvolvedor**: Psykhepathos
- **Repositório**: [GitHub](https://github.com/Psykhepathos/ndd-vuexy)
- **Issues**: [GitHub Issues](https://github.com/Psykhepathos/ndd-vuexy/issues)

---

<div align="center">

**🚛 Sistema NDD - Transportando o Futuro**

Desenvolvido com ❤️ utilizando tecnologias modernas

</div>
