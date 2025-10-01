# Agent Usage Examples

## 📖 Real-World Scenarios

This document contains practical examples of how to use the NDD specialized agents for common development tasks.

---

## Example 1: Create New List Page

**Scenario**: You need to create a new "Motoristas" (Drivers) list page with search and pagination.

**Agents Used**: `ndd-frontend-vuexy` (primary), `ndd-backend-progress` (secondary)

### Step 1: Design Backend Query
```
@ndd-backend-progress

I need to create a paginated list of drivers (motoristas) with:
- Search by name or code
- Filter by transporter (codtrn)
- 10 items per page
- Include transporter name (nomtrn) via JOIN

Table: PUB.motorista
Columns: codmot, nommot, codtrn
```

**Agent Response**: Creates `getMotoristasPaginated()` method in ProgressService.php

### Step 2: Create Frontend Page
```
@ndd-frontend-vuexy

I need to create a new page at resources/ts/pages/motoristas/index.vue

Requirements:
- List of drivers with search
- Columns: Code, Name, Transporter, Actions
- Server-side pagination
- Edit and delete buttons
- Based on existing Vuexy patterns

Reference similar page: resources/ts/pages/transportes/index.vue
```

**Agent Response**: Creates complete Vue component with proper Vuexy structure

---

## Example 2: Fix SQL Injection Vulnerability

**Scenario**: Security audit found SQL injection in search functionality.

**Agents Used**: `ndd-security-audit` (primary), `ndd-backend-progress` (secondary)

### Step 1: Identify Vulnerability
```
@ndd-security-audit

Please scan this method for SQL injection vulnerabilities:

public function searchRotas(string $search): array
{
    $sql = "SELECT * FROM PUB.introt WHERE desrot LIKE '%" . $search . "%'";
    return $this->executeCustomQuery($sql);
}
```

**Agent Response**: Identifies unsafe concatenation, suggests fix with escapeSqlString()

### Step 2: Implement Fix
```
@ndd-backend-progress

Please fix this SQL injection vulnerability using the correct Progress pattern:
[paste vulnerable code]
```

**Agent Response**: Provides corrected code with `escapeSqlString()`

---

## Example 3: Optimize Slow Query

**Scenario**: Route list page takes 3+ seconds to load.

**Agents Used**: `ndd-performance-optimizer` (primary), `ndd-backend-progress` (secondary)

### Step 1: Diagnose Issue
```
@ndd-performance-optimizer

The rotas-semparar list page is very slow (3+ seconds).

File: resources/ts/pages/rotas-semparar/index.vue
Backend: app/Services/ProgressService.php::getSemPararRotas()

API logs show:
- 101 SELECT queries per request
- Main query: 50ms
- 100x COUNT queries: 2500ms total

What's the issue and how do I fix it?
```

**Agent Response**: Identifies N+1 query problem, explains solution with subquery

### Step 2: Implement Optimization
```
@ndd-backend-progress

I need to fix this N+1 query issue:

Current code:
foreach ($rotas as $rota) {
    $countSql = "SELECT COUNT(*) FROM PUB.semPararRotMu WHERE sPararRotID = " . $rota['id'];
    $count = $this->executeCustomQuery($countSql);
}

Can you rewrite this to use a single query with subquery?
```

**Agent Response**: Provides optimized single-query solution

---

## Example 4: Add Map Feature

**Scenario**: Add route visualization on map with custom markers.

**Agents Used**: `ndd-maps-integration` (primary), `ndd-frontend-vuexy` (secondary)

### Step 1: Design Map Integration
```
@ndd-maps-integration

I need to add a map view to show a route with municipalities.

Requirements:
- Display all municipalities as numbered markers
- Connect them with a polyline following real roads
- Different colors for start (green) and end (red)
- Auto-zoom to fit all markers
- Click marker to show city name

Data structure:
interface Municipality {
  codmun: number
  desmun: string
  lat: number
  lng: number
}

Should I use Google Directions API or just draw straight lines?
```

**Agent Response**: Recommends Directions API, provides complete implementation with caching

### Step 2: Implement UI Component
```
@ndd-frontend-vuexy

I need to add this map component to resources/ts/pages/rotas-semparar/mapa/[id].vue

Follow the pattern from existing code and integrate the map properly in a VCard.
```

**Agent Response**: Provides Vue component structure with proper Vuexy styling

---

## Example 5: Implement Business Logic

**Scenario**: Calculate ETA for package delivery.

**Agents Used**: `ndd-business-logic` (primary), `ndd-backend-progress` (secondary)

### Step 1: Understand Requirements
```
@ndd-business-logic

I need to implement ETA (Estimated Time of Arrival) calculation for packages.

Given:
- Package has a route (SemParar route with predefined travel time)
- Package has N deliveries
- Current date

What business rules should I consider?
```

**Agent Response**: Explains:
- Use route's `tempoviagem` as base
- Add penalty for number of deliveries
- Consider current package status
- Return Carbon date

### Step 2: Implement Method
```
@ndd-backend-progress

Based on these business rules:
[paste business logic from previous response]

Please implement a calculatePackageETA($codpac) method in ProgressService.php
that queries the necessary data and returns the ETA date.
```

**Agent Response**: Provides complete implementation with SQL queries

---

## Example 6: Pre-Deployment Security Check

**Scenario**: Before deploying to production, run full security audit.

**Agents Used**: `ndd-security-audit` (primary), multiple agents (secondary)

### Step 1: Run Security Scan
```
@ndd-security-audit

I'm about to deploy to production. Please run a full security audit checklist:

Focus on:
1. SQL injection vulnerabilities
2. Hardcoded secrets or API keys
3. Missing authentication on API endpoints
4. XSS vulnerabilities
5. Rate limiting on public APIs

Files to check:
- app/Services/ProgressService.php
- app/Http/Controllers/Api/*.php
- resources/ts/pages/**/*.vue
- resources/ts/config/api.ts
```

**Agent Response**: Provides comprehensive security report with issues found

### Step 2: Fix Critical Issues
For each critical issue found:
```
@ndd-backend-progress (for SQL issues)
@ndd-frontend-vuexy (for frontend issues)

Please fix this security issue:
[paste specific issue from audit]
```

---

## Example 7: Reduce API Costs

**Scenario**: Google Maps API bill is $200/month, need to reduce it.

**Agents Used**: `ndd-maps-integration` (primary), `ndd-performance-optimizer` (secondary)

### Step 1: Analyze Current Usage
```
@ndd-maps-integration

My Google Maps API costs are too high ($200/month).

Current usage:
- Geocoding: ~5000 requests/month
- Directions: ~3000 requests/month
- Maps JavaScript: unlimited (free)

I have caching implemented but cache hit rate is only 40%.

How can I reduce costs?
```

**Agent Response**: Suggests:
- Increase cache TTL
- Reduce cache tolerance (currently 100m)
- Batch geocoding requests
- Implement more aggressive rate limiting

### Step 2: Optimize Caching
```
@ndd-performance-optimizer

I need to improve cache hit rate for geocoding/routing.

Current implementation: [paste GeocodingService.php]

Cache hit rate: 40% (target 80%+)

What's wrong and how do I fix it?
```

**Agent Response**: Identifies issues and provides optimizations

---

## Example 8: Debug Complex Issue

**Scenario**: Map markers are overlapping incorrectly.

**Agents Used**: Multiple agents in sequence

### Step 1: Identify Layer
```
@ndd-maps-integration

Map markers are overlapping incorrectly - marker #5 appears below marker #2,
but it should be on top since it has a higher index.

Code: resources/ts/pages/rotas-semparar/mapa/[id].vue

All markers use the same z-index (200). How should I fix this?
```

**Agent Response**: Explains z-index layering strategy

### Step 2: Implement Fix
```
@ndd-frontend-vuexy

I need to update the marker creation to use incremental z-index:
- Route markers: 1000, 1001, 1002, ...
- Delivery markers: 2000, 2001, 2002, ...

Current code: [paste marker creation]

Please update it following Vue reactive patterns.
```

**Agent Response**: Provides corrected Vue code

### Step 3: Performance Check
```
@ndd-performance-optimizer

I just changed how markers are rendered (incremental z-index).

Does this change affect performance? Are there any concerns with
creating 100+ markers each with a unique z-index?
```

**Agent Response**: Validates performance impact

---

## Example 9: Implement New Feature (Full Workflow)

**Scenario**: Implement "Package Simulation" - overlay package deliveries on SemParar route.

### Workflow:

```
1. @ndd-business-logic
   Define requirements for package simulation feature.
   What data do we need? What's the user workflow?

2. @ndd-backend-progress
   Design API endpoints:
   - GET /api/pacotes/autocomplete (search packages)
   - GET /api/pacotes/{id}/entregas (get deliveries with GPS)

3. @ndd-frontend-vuexy
   Design UI components:
   - Autocomplete field for package search
   - "Simulate" button
   - Map overlay for deliveries

4. @ndd-maps-integration
   Implement map integration:
   - Combined route (SemParar + deliveries)
   - Different colored markers
   - Separate polylines

5. @ndd-security-audit
   Review implementation for vulnerabilities:
   - SQL injection in autocomplete
   - Input validation

6. @ndd-performance-optimizer
   Optimize:
   - Debounce autocomplete search
   - Cache route calculations
   - Minimize map re-renders
```

---

## Example 10: Code Review

**Scenario**: Team member submitted code, you need to review it.

### Multi-Agent Review:

```
@ndd-backend-progress
Review this new ProgressService method for SQL correctness and Progress compatibility:
[paste code]

@ndd-security-audit
Review the same code for security vulnerabilities:
[paste code]

@ndd-performance-optimizer
Review the same code for performance issues:
[paste code]

@ndd-frontend-vuexy
Review this new Vue component for Vuexy compliance:
[paste component]
```

Each agent provides focused feedback from their specialty.

---

## 🎓 Tips for Effective Agent Usage

### 1. **Be Specific**
```
❌ BAD: "This doesn't work, help me"
✅ GOOD: "SQL query returns 0 results, expected 10. Query: [paste]. Error: [paste]"
```

### 2. **Provide Context**
```
❌ BAD: "How do I add a button?"
✅ GOOD: "I need to add a delete button to the actions column in VDataTableServer on
         resources/ts/pages/transportes/index.vue, following Vuexy patterns"
```

### 3. **Reference Files**
```
❌ BAD: "How does authentication work?"
✅ GOOD: "In app/Http/Controllers/Api/AuthController.php:45, how does the login
         method validate credentials against Progress database?"
```

### 4. **Ask Follow-Up Questions**
```
Agent provides solution → Test it → Ask clarifying questions if needed

"This works but why did you use intval() instead of casting to (int)?"
"Can you explain why we need the subquery here?"
```

### 5. **Combine Agents for Complex Tasks**
Don't try to make one agent do everything. Use workflows.

---

## 📝 Template Prompts

### For Backend Work
```
@ndd-backend-progress

Task: [Create/Update/Debug] [feature name]
File: [file path]
Context: [what exists now]
Requirements: [what you need]
Data: [relevant table/column names]
```

### For Frontend Work
```
@ndd-frontend-vuexy

Task: [Create/Update/Style] [component name]
File: [file path]
Reference: [similar existing component]
Requirements: [list of features]
Data: [API endpoint and data structure]
```

### For Security Review
```
@ndd-security-audit

Review type: [Full audit / Specific vulnerability / Pre-deployment]
Files: [list of files or "entire codebase"]
Focus: [SQL injection / XSS / Auth / etc]
```

### For Performance Issue
```
@ndd-performance-optimizer

Issue: [slow page / high memory / large bundle]
Location: [file and line number if known]
Symptoms: [measurements, timings, observations]
Logs: [relevant log output]
```

---

**Remember**: Agents are tools, not magic. They work best with clear, specific questions and adequate context.
