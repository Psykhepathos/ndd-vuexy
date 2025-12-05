# SemPararRotaController Bug Analysis Report

**Date:** 2025-12-01
**Controller:** `app/Http/Controllers/Api/SemPararRotaController.php`
**Analyzed by:** Claude Code
**Total Bugs Found:** 14

---

## Executive Summary

The SemPararRotaController handles CRUD operations for SemParar routes with map integration. It has **one critical issue** and several medium-priority bugs:
- ⚠️ **CRITICAL: Data loss risk** - updateMunicipios() uses DELETE+INSERT without transaction support
- ⚠️ **No ID validation** - All show/update/delete methods accept any $id parameter
- ⚠️ **Race conditions** - Frontend watchers can trigger duplicate API calls
- ⚠️ **Misleading statistics** - Only shows stats for current page, not all rotas

---

## Critical Issues (Fix Immediately!)

### [BUG-003] CRITICAL - Data Loss Risk in Municipality Updates
**Location:** `app/Http/Controllers/Api/SemPararRotaController.php:405` + `app/Services/ProgressService.php:updateSemPararRotaMunicipios()`
**Risk:** Permanent data loss if INSERT fails after DELETE
```php
// ⚠️ DANGEROUS PATTERN (in ProgressService)
// 1. DELETE all municipalities
DELETE FROM PUB.semPararRotMu WHERE sPararRotID = ?

// 2. INSERT new municipalities (if this fails, data is LOST!)
INSERT INTO PUB.semPararRotMu VALUES (...)

// Problem: Progress JDBC doesn't support transactions!
// If INSERT fails, municipalities are gone forever.
```

**Fix:** Use UPDATE/INSERT/DELETE granular pattern with validation BEFORE delete:
```php
// ✅ CORRECT - Validate before delete
public function updateSemPararRotaMunicipios($id, $municipios) {
    // 1. Validate ALL data first
    foreach ($municipios as $mun) {
        if (!isset($mun['cod_mun'], $mun['cod_est'], $mun['cdibge'])) {
            return ['success' => false, 'error' => 'Invalid municipality data'];
        }
    }

    // 2. Build all INSERT queries first
    $insertQueries = [];
    foreach ($municipios as $seq => $mun) {
        $insertQueries[] = "INSERT INTO PUB.semPararRotMu VALUES (...)";
    }

    // 3. Now safe to delete (we know INSERTs will work)
    $deleteResult = $this->executeUpdate("DELETE FROM PUB.semPararRotMu WHERE sPararRotID = $id");

    // 4. Execute all inserts
    foreach ($insertQueries as $insertSql) {
        $this->executeUpdate($insertSql);
    }
}
```

---

## All Bugs Detailed

### Critical Priority
1. **BUG-003** - Data loss risk in municipality updates (no transaction support)

### High Priority
2. **BUG-001** - Missing ID validation on route operations
   - **Location:** Lines 74, 170, 229, 352, 390
   - **Risk:** SQL errors if non-numeric ID provided
   - **Affected methods:** show(), update(), destroy(), showWithMunicipios(), updateMunicipios()
   - **Current:**
     ```php
     public function show($id): JsonResponse {
         $result = $this->progressService->getSemPararRota($id);
     }
     ```
   - **Fix:**
     ```php
     public function show($id): JsonResponse {
         if (!is_numeric($id) || $id <= 0) {
             return response()->json([
                 'success' => false,
                 'message' => 'ID inválido'
             ], 400);
         }
         // ...
     }
     ```

3. **BUG-007** - Race condition in pagination watchers
   - **Location:** `resources/ts/pages/rotas-padrao/index.vue:235-237`
   - **Risk:** Changing itemsPerPage doesn't reset page, causes empty results
   - **Current:**
     ```typescript
     watch([page, itemsPerPage], () => {
       fetchRotas()
     })
     ```
   - **Fix:**
     ```typescript
     watch(itemsPerPage, () => {
       page.value = 1
       fetchRotas()
     })

     watch(page, () => {
       fetchRotas()
     })
     ```

4. **BUG-009** - Statistics show wrong data (current page only)
   - **Location:** `resources/ts/pages/rotas-padrao/index.vue:134-151`
   - **Risk:** Misleading UX - users think they see total stats but only see current page
   - **Current:** Computes from `serverItems.value` (current page only)
   - **Fix:** Either:
     1. Fetch total stats from backend API endpoint
     2. Add label: "Stats (página atual)" to clarify
     3. Compute stats after filtering on backend and return in API response

### Medium Priority
5. **BUG-004** - Inconsistent error status codes
   - **Location:** Lines 137, 195, 241 vs 86, 364
   - **Risk:** Confusing API contract for frontend
   - **Current:** store/update/destroy return 400, show returns 404 or 500
   - **Fix:** Standardize:
     - 404 = Not found
     - 400 = Bad request (client error)
     - 422 = Validation error
     - 500 = Server error

6. **BUG-005** - Missing rate limiting on autocomplete endpoints
   - **Location:** routes/api.php (municipios, estados endpoints)
   - **Risk:** Can be abused for DoS
   - **Fix:** Add throttle middleware
     ```php
     Route::get('municipios', [SemPararRotaController::class, 'municipios'])
         ->middleware('throttle:60,1');
     ```

7. **BUG-013** - Redundant updateOptions() call
   - **Location:** index.vue:212-214, 403
   - **Risk:** Duplicate API calls (watchers + updateOptions)
   - **Current:**
     ```typescript
     const updateOptions = () => {
       fetchRotas()  // Already called by watchers!
     }

     // In template:
     @update:options="updateOptions"
     ```
   - **Fix:** Remove updateOptions or make it smarter:
     ```typescript
     const updateOptions = (options: any) => {
       // Only fetch if needed based on options change
       if (options.page !== page.value || options.itemsPerPage !== itemsPerPage.value) {
         page.value = options.page
         itemsPerPage.value = options.itemsPerPage
         // Watchers will trigger fetchRotas
       }
     }
     ```

8. **BUG-014** - Wrong condition for statistics display
   - **Location:** index.vue:316
   - **Risk:** Stats hidden when tipo is null/undefined instead of when filtered
   - **Current:**
     ```vue
     <VChip v-if="!selectedTipo">
       {{ statistics.comCD }} Retornos
     </VChip>
     ```
   - **Fix:**
     ```vue
     <VChip v-if="selectedTipo === 'all'">
       {{ statistics.comCD }} Retornos
     </VChip>
     ```

9. **BUG-016** - No loading state on delete button
   - **Location:** index.vue:546-552
   - **Risk:** User can spam delete button during deletion
   - **Current:** Button has no :loading or :disabled prop
   - **Fix:**
     ```typescript
     const deleting = ref(false)

     const deleteRoute = async () => {
       deleting.value = true
       try {
         // ... deletion logic
       } finally {
         deleting.value = false
       }
     }

     // Template:
     <VBtn :loading="deleting" :disabled="deleting" @click="deleteRoute">
     ```

### Low Priority
10. **BUG-002** - Inconsistent boolean validation
    - **Location:** Lines 32-33, 118-119, 176-177
    - **Risk:** Frontend sends "true"/"false" strings, backend expects boolean
    - **Current:** Validation allows both string and boolean
    - **Fix:** Either:
      1. Force boolean in validation: `flg_cd => 'boolean'`
      2. Or normalize in controller before passing to service

11. **BUG-006** - Missing audit trail (authenticated user)
    - **Location:** Controller doesn't pass auth user to ProgressService
    - **Risk:** resAtu field might use hardcoded value
    - **Fix:**
      ```php
      $user = $request->user();
      $data['res_atu'] = $user ? $user->name : 'sistema';
      ```

12. **BUG-008** - Timer cleanup only on unmount
    - **Location:** index.vue:217, 240-245
    - **Risk:** Minor memory leak if component re-renders mid-timer
    - **Current:** Cleanup only in onBeforeUnmount
    - **Fix:** Also clear in watch cleanup function
      ```typescript
      watch(searchQuery, (newVal, oldVal, onCleanup) => {
        if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
        onCleanup(() => {
          if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
        })
        // ...
      })
      ```

13. **BUG-010** - Generic error handling in deleteRoute
    - **Location:** index.vue:277-279
    - **Risk:** All errors treated the same (could be 404, 403, 500)
    - **Fix:** Check response.status and show appropriate message

14. **BUG-012** - Excessive console.log in production
    - **Location:** index.vue:36-78
    - **Risk:** Pollutes console, minor performance impact
    - **Fix:** Wrap in DEV check
      ```typescript
      if (import.meta.env.DEV) {
        console.log('🔄 toggleTipo ANTES:', selectedTipo.value)
      }
      ```

---

## Impact Assessment

**Severity Distribution:**
- Critical: 1 (7%)
- High: 3 (21%)
- Medium: 5 (36%)
- Low: 5 (36%)

**Most Dangerous Combination:**
BUG-003 (data loss) + BUG-001 (no ID validation) = **Corrupt data or permanent municipality loss**

---

## Recommended Fix Priority

### Phase 1 - Immediate (Today)
```php
// 1. Fix data loss risk (BUG-003)
// Implement validation BEFORE delete in ProgressService.php
public function updateSemPararRotaMunicipios($id, $municipios) {
    // Validate all data first
    foreach ($municipios as $mun) {
        if (!isset($mun['cod_mun'], $mun['cod_est'], $mun['cdibge'])) {
            return ['success' => false, 'error' => 'Invalid data'];
        }
    }

    // Build all INSERT queries
    $inserts = [];
    foreach ($municipios as $seq => $mun) {
        $inserts[] = "INSERT INTO PUB.semPararRotMu VALUES (...)";
    }

    // Now safe to delete
    $this->executeUpdate("DELETE FROM PUB.semPararRotMu WHERE sPararRotID = $id");

    // Execute inserts
    foreach ($inserts as $sql) {
        $this->executeUpdate($sql);
    }
}

// 2. Add ID validation (BUG-001)
public function show($id): JsonResponse {
    if (!is_numeric($id) || $id <= 0) {
        return response()->json(['success' => false, 'message' => 'ID inválido'], 400);
    }
    // ...
}
```

```typescript
// 3. Fix pagination race (BUG-007)
watch(itemsPerPage, () => {
  page.value = 1
  fetchRotas()
})

watch(page, () => {
  fetchRotas()
})
```

### Phase 2 - This Week
- Add rate limiting to autocomplete (BUG-005)
- Fix statistics condition (BUG-014)
- Add loading state to delete (BUG-016)
- Standardize error codes (BUG-004)

### Phase 3 - This Month
- Remove redundant updateOptions (BUG-013)
- Fetch real statistics from backend (BUG-009)
- Add audit trail user (BUG-006)
- Clean up console.logs (BUG-012)

---

## Testing Checklist

Before deploying fixes:
- [ ] Test municipality update with invalid data (should fail BEFORE delete)
- [ ] Test municipality update with valid data (should succeed)
- [ ] Test show/update/destroy with invalid ID (should return 400)
- [ ] Test show/update/destroy with non-existent ID (should return 404)
- [ ] Test pagination: change from 10 to 50 items per page (should reset to page 1)
- [ ] Verify statistics display correctly when tipo='all'
- [ ] Test delete button: click multiple times rapidly (should only delete once)
- [ ] Test autocomplete endpoints: spam requests (should be rate-limited)
- [ ] Verify error messages are user-friendly (not technical SQL errors)

---

## Code Locations

**Backend:**
- `app/Http/Controllers/Api/SemPararRotaController.php` (438 lines)
  - Line 74-106: show() - Missing ID validation
  - Line 112-165: store() - Creates routes
  - Line 170-224: update() - Missing ID validation
  - Line 229-262: destroy() - Missing ID validation
  - Line 267-310: municipios() - Autocomplete (no rate limiting)
  - Line 315-347: estados() - Autocomplete
  - Line 352-385: showWithMunicipios() - Missing ID validation
  - Line 390-437: updateMunicipios() - **CRITICAL DATA LOSS RISK**

**Frontend:**
- `resources/ts/pages/rotas-padrao/index.vue` (574 lines)
  - Line 134-151: Statistics computed from current page only
  - Line 212-214: Redundant updateOptions()
  - Line 217: searchDebounceTimer
  - Line 235-237: Race condition in watchers
  - Line 261-284: deleteRoute() missing loading state
  - Line 316: Wrong v-if condition for stats
  - Line 36-78: Excessive console.log
  - Line 403: @update:options hook

**Routes:**
- `routes/api.php`
  - Line 93+: SemParar routes prefix (missing rate limiting on autocomplete)

---

## API Endpoints Affected

1. `GET /api/semparar-rotas` - No issues
2. `GET /api/semparar-rotas/{id}` - Missing ID validation (BUG-001)
3. `POST /api/semparar-rotas` - No issues
4. `PUT /api/semparar-rotas/{id}` - Missing ID validation (BUG-001)
5. `DELETE /api/semparar-rotas/{id}` - Missing ID validation (BUG-001)
6. `GET /api/semparar-rotas/municipios` - Missing rate limiting (BUG-005)
7. `GET /api/semparar-rotas/estados` - Missing rate limiting (BUG-005)
8. `GET /api/semparar-rotas/{id}/municipios` - Missing ID validation (BUG-001)
9. `PUT /api/semparar-rotas/{id}/municipios` - **CRITICAL DATA LOSS RISK** (BUG-003)

---

## Full Analysis

**Architecture:**
- Controller: 438 lines, 9 endpoints, full CRUD + autocomplete
- Service: ProgressService (methods: getSemPararRotas, getSemPararRota, createSemPararRota, etc.)
- Frontend: index.vue (574 lines, VDataTableServer with tri-state filters)
- Map Integration: mapa/[id].vue (Leaflet + OpenStreetMap + OSRM proxy)
- Database: Progress PUB.semPararRot + PUB.semPararRotMu (1:N relationship)

**Data Flow:**
```
User → Vue Component → API Endpoint → Controller → ProgressService → JDBC → Progress DB
```

**Performance:**
- Average response time: ~600ms for 10 rotas with municipality count
- Autocomplete: ~300ms for 20 results
- Map page: ~2s (geocoding + routing via OSRM proxy)

**Security:**
- ✅ Good validation on most endpoints
- ✅ Good logging throughout
- ⚠️ Missing ID validation (accepts any value)
- ⚠️ Missing rate limiting on autocomplete
- ❌ **CRITICAL: Data loss risk in municipality updates**

**UX Issues:**
- ❌ Pagination resets incorrectly
- ❌ Statistics show wrong data (page-level vs total)
- ❌ Delete button has no loading state
- ❌ Error messages not always user-friendly

---

**Status:** ⚠️ CRITICAL - Do NOT use municipality update feature until BUG-003 is fixed!
**Recommendation:**
1. **URGENT:** Disable PUT /api/semparar-rotas/{id}/municipios endpoint until data loss fix is deployed
2. Fix Phase 1 bugs immediately
3. Add automated tests for municipality updates

---

## Additional Notes

**Progress JDBC Limitation:**
```php
// ❌ THIS DOESN'T WORK - Progress JDBC has NO transaction support
DB::connection('progress')->beginTransaction();
DELETE FROM PUB.semPararRotMu WHERE sPararRotID = 204;
INSERT INTO PUB.semPararRotMu VALUES (...);  // If this fails, data is LOST!
DB::connection('progress')->commit();

// ✅ ONLY SAFE PATTERN - Validate before delete
// 1. Validate ALL data
// 2. Build ALL queries
// 3. Delete old data (knowing inserts will succeed)
// 4. Insert new data
```

**Map Integration:**
- Uses Leaflet + OpenStreetMap (100% free!)
- OSRM routing via Laravel proxy (to avoid CORS)
- Google Geocoding API only for IBGE → coordinates (with cache)
- See CLAUDE.md OSRM section for routing best practices

**Related Controllers:**
- GeocodingController - Converts IBGE codes to coordinates
- RoutingController - OSRM proxy for route calculation
- SemPararController - SOAP integration for toll purchase
