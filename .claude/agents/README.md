# NDD Specialized AI Agents

## 📚 Overview

This directory contains **6 specialized AI agents** designed to work with the NDD Transport Management System. Each agent is an expert in a specific domain, with deep knowledge of the codebase, best practices, and common patterns.

---

## 🤖 Available Agents

### 1. **ndd-backend-progress** ⭐⭐⭐ ESSENTIAL
**When to use**: Any backend work involving Progress database

**Expertise:**
- Progress SQL syntax (TOP, no OFFSET, single-line queries)
- JDBC connector (Java-based) integration
- ProgressService patterns and methods
- SQL injection prevention (`escapeSqlString()`)
- N+1 query optimization
- No transaction support (JDBC limitation)

**Use for:**
- Creating new database queries
- Debugging Progress SQL errors
- Optimizing slow queries
- Implementing CRUD operations
- Adding new ProgressService methods

**Key Rule**: NEVER use Eloquent for Progress tables - always raw JDBC queries
**Exception**: Eloquent CAN be used for Laravel internal tables (SQLite cache, users, etc)

📖 See [ELOQUENT-USAGE.md](ELOQUENT-USAGE.md) for detailed guidance

---

### 2. **ndd-frontend-vuexy** ⭐⭐⭐ ESSENTIAL
**When to use**: Any frontend UI development

**Expertise:**
- Vue 3 Composition API + TypeScript
- Vuexy template components (App*, not V*)
- Vuetify 3.8.5 Material Design patterns
- API integration with centralized config
- Responsive design (mobile-first)

**Use for:**
- Creating new pages (list, detail, form)
- Building data tables with pagination
- Implementing forms with validation
- Adding statistics dashboards
- Integrating with backend APIs

**Key Rule**: NEVER create UI from scratch - ALWAYS copy from existing Vuexy templates

**Reference templates:**
- List: `resources/ts/pages/apps/user/list/index.vue`
- Detail: `resources/ts/pages/apps/user/view/[id].vue`
- Form: `resources/ts/pages/apps/user/view/UserBioPanel.vue`

---

### 3. **ndd-security-audit** ⭐⭐ VERY IMPORTANT
**When to use**: Security reviews, pre-deployment checks

**Expertise:**
- SQL injection detection/prevention
- Input validation (backend + frontend)
- XSS protection
- Authentication & authorization
- Secrets management
- API security and rate limiting

**Use for:**
- Reviewing new code for vulnerabilities
- Running security scans before deployment
- Auditing API endpoints
- Checking for hardcoded secrets
- Validating input sanitization

**Run regularly:**
- Before each commit (quick scan)
- Weekly (deep scan)
- Before production deployment (full audit)

---

### 4. **ndd-performance-optimizer** ⭐⭐ IMPORTANT
**When to use**: Performance issues, optimization tasks

**Expertise:**
- N+1 query detection
- Database query optimization
- Frontend performance (bundle size, reactivity)
- Caching strategies (SQLite, browser, API)
- Debouncing and rate limiting

**Use for:**
- Investigating slow pages
- Optimizing database queries
- Reducing API calls
- Bundle size optimization
- Memory leak detection

**Target metrics:**
- API response: < 500ms
- FCP: < 1.5s
- Bundle: < 500KB
- Cache hit rate: > 80%

---

### 5. **ndd-business-logic** ⭐ USEFUL
**When to use**: Implementing complex business rules

**Expertise:**
- Transport management domain knowledge
- Package tracking and delivery workflow
- Route planning (SemParar routes)
- Vale Pedágio calculations
- GPS coordinate processing
- Status workflows and validations

**Use for:**
- Understanding domain requirements
- Implementing business rules
- Validating workflows
- Calculating metrics/KPIs
- Processing GPS coordinates

**Key terms:**
- Transporte = Transporter
- Pacote = Package
- Pedido = Delivery
- Rota = Route
- Vale Pedágio = Toll Pass

---

### 6. **ndd-maps-integration** ⭐ USEFUL
**When to use**: Working with maps and geospatial features

**Expertise:**
- Google Maps JavaScript API
- Google Geocoding API (address → coordinates)
- Google Directions API (route calculation)
- Marker and polyline customization
- API cost optimization with caching

**Use for:**
- Adding/modifying map features
- Geocoding municipalities
- Calculating routes
- Optimizing Google API costs
- Debugging map visualization issues

**Cost targets:**
- Monthly API costs: < $50
- Cache hit rate: > 80%
- Geocode cached: < 10ms
- Route calculation: < 3s

---

## 🎯 How to Use Agents

### Method 1: Direct Consultation
When you need specific expertise:

```
@ndd-backend-progress
I need to create a query to fetch all packages from the last 30 days
with their delivery counts, sorted by date descending.
```

### Method 2: Code Review
Ask an agent to review your code:

```
@ndd-security-audit
Please review this code for security vulnerabilities:
[paste code]
```

### Method 3: Debugging
Get help with specific issues:

```
@ndd-performance-optimizer
This list page is loading very slowly (3+ seconds).
File: resources/ts/pages/rotas-semparar/index.vue
Can you identify performance bottlenecks?
```

### Method 4: Planning
Get guidance before implementing:

```
@ndd-business-logic
I need to implement a feature to calculate ETA for deliveries.
What business rules should I consider?
```

---

## 🔄 Multi-Agent Workflows

### Workflow 1: New Feature (Full Stack)
```
1. @ndd-business-logic - Define requirements and business rules
2. @ndd-backend-progress - Design database queries
3. @ndd-frontend-vuexy - Create UI components
4. @ndd-security-audit - Review for vulnerabilities
5. @ndd-performance-optimizer - Optimize performance
```

### Workflow 2: Bug Fix
```
1. @ndd-backend-progress OR @ndd-frontend-vuexy - Identify root cause
2. Implement fix
3. @ndd-security-audit - Ensure fix doesn't introduce vulnerabilities
4. @ndd-performance-optimizer - Ensure fix doesn't hurt performance
```

### Workflow 3: Performance Issue
```
1. @ndd-performance-optimizer - Diagnose bottleneck
2. @ndd-backend-progress - Optimize queries if backend issue
3. @ndd-frontend-vuexy - Optimize UI if frontend issue
4. Test and measure improvements
```

### Workflow 4: Pre-Deployment
```
1. @ndd-security-audit - Run full security scan
2. @ndd-performance-optimizer - Run performance benchmarks
3. @ndd-backend-progress - Review all database queries
4. @ndd-frontend-vuexy - Check bundle size and assets
```

---

## 📊 Agent Specialization Matrix

| Task | Primary Agent | Secondary Agent | Tertiary Agent |
|------|--------------|-----------------|----------------|
| New backend API | ndd-backend-progress | ndd-security-audit | ndd-performance-optimizer |
| New frontend page | ndd-frontend-vuexy | ndd-business-logic | ndd-security-audit |
| Map feature | ndd-maps-integration | ndd-frontend-vuexy | ndd-performance-optimizer |
| Security fix | ndd-security-audit | ndd-backend-progress | - |
| Performance issue | ndd-performance-optimizer | ndd-backend-progress | ndd-frontend-vuexy |
| Business rule | ndd-business-logic | ndd-backend-progress | ndd-frontend-vuexy |
| SQL query | ndd-backend-progress | ndd-security-audit | ndd-performance-optimizer |
| UI component | ndd-frontend-vuexy | - | - |
| Route calculation | ndd-maps-integration | ndd-backend-progress | ndd-business-logic |

---

## ✅ Best Practices

### 1. **Choose the Right Agent**
Don't ask a frontend agent about SQL queries. Match the task to the agent's expertise.

### 2. **Provide Context**
Include relevant files, error messages, and what you've already tried.

### 3. **Be Specific**
Instead of "this doesn't work", say "SQL query returns 0 results, expected 10+ results".

### 4. **Trust but Verify**
Agents are experts but not infallible. Always test their suggestions.

### 5. **Combine Agents**
Complex tasks often need multiple agents. Use workflows for best results.

### 6. **Keep Agents Updated**
If you make major changes to the codebase, update the agent documentation.

---

## 🚀 Quick Reference

### Critical Rules (Never Violate)

**Backend (Progress JDBC):**
- ❌ NO Eloquent ORM - use raw JDBC queries
- ❌ NO transactions (beginTransaction/commit/rollBack)
- ❌ NO multi-line SQL - single-line only
- ✅ ALWAYS use escapeSqlString() for user input
- ✅ ALWAYS use intval() for integers

**Frontend (Vuexy):**
- ❌ NO custom UI from scratch
- ❌ NO Vuetify components directly (V*)
- ❌ NO hardcoded URLs
- ✅ ALWAYS copy from Vuexy templates
- ✅ ALWAYS use App* components
- ✅ ALWAYS use API_ENDPOINTS and apiFetch()

**Security:**
- ❌ NO SQL injection (escape all inputs)
- ❌ NO secrets in code (use .env)
- ❌ NO unauthenticated sensitive endpoints
- ✅ ALWAYS validate input (backend + frontend)
- ✅ ALWAYS rate limit public APIs

**Performance:**
- ❌ NO N+1 queries (use subqueries)
- ❌ NO API calls on every keystroke (debounce)
- ❌ NO unnecessary watchers on large objects
- ✅ ALWAYS cache expensive operations
- ✅ ALWAYS paginate large datasets

---

## 📚 Additional Resources

### Project Files
- **Main Documentation**: `CLAUDE.md` (project root)
- **API Config**: `resources/ts/config/api.ts`
- **Backend Service**: `app/Services/ProgressService.php`
- **Java Connector**: `storage/app/java/ProgressJDBCConnector.java`

### External Links
- **Vuexy Template**: Materio Vuetify Vue Admin Template
- **Progress JDBC**: `c:/Progress/OpenEdge/java/openedge.jar`
- **Google Maps API**: https://developers.google.com/maps/documentation

---

## 🆘 Getting Help

**If an agent can't help:**
1. Check if you're using the right agent for the task
2. Provide more context (files, errors, screenshots)
3. Try combining multiple agents
4. Consult the main CLAUDE.md documentation

**Common mistakes:**
- Asking frontend agent about SQL queries → Use ndd-backend-progress
- Asking backend agent about UI layout → Use ndd-frontend-vuexy
- Asking general agent about Progress syntax → Use ndd-backend-progress

---

## 🔄 Maintenance

**Update agents when:**
- Major codebase refactoring
- New patterns emerge
- New technologies added
- Business rules change
- Common issues identified

**Who maintains:**
- Developers who find gaps in agent knowledge
- Team leads reviewing code quality
- Project managers when business rules change

---

**Last Updated**: 2025-10-01
**Version**: 1.0.0
**Status**: Production Ready ✅
