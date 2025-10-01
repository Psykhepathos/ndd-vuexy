# Eloquent ORM Usage in NDD Project

## 🎯 Quick Reference

```
Progress Tables (PUB.*)     →  ❌ NO Eloquent  →  ✅ Raw JDBC
Laravel Tables (SQLite/MySQL) →  ✅ Eloquent OK  →  ❌ No raw queries needed
```

---

## ✅ When to Use Eloquent

### 1. **Cache Tables (SQLite)**
```php
// ✅ CORRECT - Eloquent for geocoding cache
use App\Models\MunicipioCoordenada;

$coords = MunicipioCoordenada::where('cdibge', $codigoIBGE)->first();

if (!$coords) {
    // Geocode via Google API
    $coords = MunicipioCoordenada::create([
        'cdibge' => $codigoIBGE,
        'desmun' => $nomeMunicipio,
        'desest' => $nomeEstado,
        'latitude' => $lat,
        'longitude' => $lng
    ]);
}

// ✅ CORRECT - Eloquent for routing cache
use App\Models\RouteSegment;

$cached = RouteSegment::where('origin_lat', $originLat)
                      ->where('origin_lng', $originLng)
                      ->where('destination_lat', $destLat)
                      ->where('destination_lng', $destLng)
                      ->where('expires_at', '>', now())
                      ->first();
```

### 2. **Laravel Internal Tables**
```php
// ✅ CORRECT - User authentication
use App\Models\User;

$user = User::find($id);
$admin = User::where('email', $email)->first();
User::create(['name' => 'John', 'email' => 'john@example.com']);

// ✅ CORRECT - Jobs queue
use Illuminate\Database\Eloquent\Model;

class Job extends Model {
    protected $connection = 'mysql';  // or 'sqlite'
}

// ✅ CORRECT - Sessions, migrations, etc
Schema::create('municipio_coordenadas', function (Blueprint $table) {
    $table->id();
    $table->integer('cdibge')->unique();
    $table->decimal('latitude', 10, 8);
    $table->decimal('longitude', 11, 8);
    $table->timestamps();
});
```

### 3. **Any Non-Progress Database**
```php
// If you add MySQL/PostgreSQL for internal use
class InternalLog extends Model {
    protected $connection = 'mysql';
    protected $table = 'internal_logs';
}

$log = InternalLog::create([
    'action' => 'route_calculated',
    'user_id' => auth()->id()
]);
```

---

## ❌ When NOT to Use Eloquent

### 1. **Progress Tables - NEVER**
```php
// ❌ WRONG - This won't work
namespace App\Models;

class Pacote extends Model {
    protected $connection = 'progress';
    protected $table = 'PUB.pacote';
}

// ❌ WRONG - Will fail at runtime
$pacote = Pacote::find(123);
$transportes = Transporte::where('flgautonomo', true)->get();
```

**Why?** Progress uses JDBC (Java connector), Eloquent expects PDO/standard SQL drivers.

### 2. **Any PUB.* Table**
```php
// ❌ WRONG - Don't create models for Progress tables
class SemPararRota extends Model { }
class Municipio extends Model { }
class Pedido extends Model { }
```

---

## 🔧 Correct Patterns

### Pattern 1: Progress Data → Use ProgressService
```php
// In ProgressService.php
public function getPacotes(array $filters): array
{
    $sql = "SELECT * FROM PUB.pacote WHERE codtrn = " . intval($filters['codtrn']);
    return $this->executeCustomQuery($sql);
}

// In Controller
$pacotes = $this->progressService->getPacotes($request->all());
```

### Pattern 2: Cache Data → Use Eloquent
```php
// In GeocodingService.php
use App\Models\MunicipioCoordenada;

public function geocode(int $cdibge, string $cidade): array
{
    // Check Eloquent cache first
    $cached = MunicipioCoordenada::where('cdibge', $cdibge)->first();

    if ($cached) {
        return [
            'lat' => $cached->latitude,
            'lng' => $cached->longitude,
            'source' => 'cache'
        ];
    }

    // Call Google API
    $coords = $this->callGoogleGeocodingAPI($cidade);

    // Save to cache via Eloquent
    MunicipioCoordenada::create([
        'cdibge' => $cdibge,
        'desmun' => $cidade,
        'latitude' => $coords['lat'],
        'longitude' => $coords['lng']
    ]);

    return $coords;
}
```

### Pattern 3: Mixed - Progress + Cache
```php
public function getRotaWithCache(int $rotaId): array
{
    // 1. Get rota from Progress (JDBC)
    $sql = "SELECT * FROM PUB.semPararRot WHERE sPararRotID = " . intval($rotaId);
    $rota = $this->progressService->executeCustomQuery($sql);

    if (!$rota['success']) {
        return ['error' => 'Route not found'];
    }

    $rotaData = $rota['data']['results'][0];

    // 2. Get cached coordinates (Eloquent)
    $municipios = [];
    foreach ($rotaData['municipios'] as $mun) {
        $coords = MunicipioCoordenada::where('cdibge', $mun['cdibge'])->first();
        if ($coords) {
            $municipios[] = [
                'desmun' => $mun['desmun'],
                'lat' => $coords->latitude,
                'lng' => $coords->longitude
            ];
        }
    }

    return [
        'rota' => $rotaData,
        'municipios' => $municipios
    ];
}
```

---

## 📊 Database Connections Overview

### config/database.php
```php
'connections' => [
    // ✅ Eloquent works here
    'sqlite' => [
        'driver' => 'sqlite',
        'database' => database_path('database.sqlite'),
    ],

    // ✅ Eloquent works here
    'mysql' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        // ...
    ],

    // ❌ Eloquent DOES NOT work here
    'progress' => [
        'driver' => 'progress',  // Custom driver (JDBC-based)
        'host' => env('PROGRESS_HOST'),
        'database' => env('PROGRESS_DATABASE'),
        // Uses Java connector, not PDO
    ],
],
```

---

## 🎯 Decision Tree

```
Need to access database?
│
├─ Is it a Progress table (PUB.*)?
│  └─ YES → Use ProgressService + raw JDBC ✅
│
└─ Is it a Laravel table (cache, users, etc)?
   └─ YES → Use Eloquent ORM ✅
```

---

## 📚 Existing Eloquent Models in NDD

Located in `app/Models/`:

### 1. **MunicipioCoordenada.php**
```php
namespace App\Models;

class MunicipioCoordenada extends Model
{
    protected $fillable = [
        'cdibge',
        'desmun',
        'desest',
        'latitude',
        'longitude'
    ];

    // Stores: Geocoded municipality coordinates (cache)
    // Table: municipio_coordenadas (SQLite)
    // Connection: Default (SQLite)
}
```

### 2. **RouteSegment.php**
```php
namespace App\Models;

class RouteSegment extends Model
{
    protected $fillable = [
        'origin_lat',
        'origin_lng',
        'destination_lat',
        'destination_lng',
        'encoded_path',
        'distance_meters',
        'duration_seconds',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime'
    ];

    // Stores: Cached route segments from Google Directions API
    // Table: route_segments (SQLite)
    // Connection: Default (SQLite)

    public static function findCachedSegment($originLat, $originLng, $destLat, $destLng, $tolerance = 100)
    {
        // Custom query to find segment within tolerance
        // ...
    }
}
```

### 3. **User.php** (Laravel default)
```php
namespace App\Models;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    // Stores: Application users
    // Table: users (SQLite/MySQL)
    // Connection: Default
}
```

---

## ⚠️ Common Mistakes

### Mistake 1: Creating Eloquent Model for Progress Table
```php
// ❌ WRONG
namespace App\Models;

class Transporte extends Model {
    protected $connection = 'progress';
    protected $table = 'PUB.transporte';
}

// This won't work because:
// 1. Progress uses JDBC, not PDO
// 2. Eloquent expects standard SQL driver
// 3. Will throw connection/driver errors
```

### Mistake 2: Using Raw Queries for Cache Tables
```php
// ❌ WRONG - Unnecessarily complex
$result = DB::select('SELECT * FROM municipio_coordenadas WHERE cdibge = ?', [$cdibge]);
$coords = $result[0] ?? null;

// ✅ CORRECT - Use Eloquent
$coords = MunicipioCoordenada::where('cdibge', $cdibge)->first();
```

### Mistake 3: Mixing Connections
```php
// ❌ WRONG - Don't do this
$pacote = DB::connection('progress')->table('PUB.pacote')->where('codpac', 123)->first();

// ✅ CORRECT - Use ProgressService
$sql = "SELECT * FROM PUB.pacote WHERE codpac = " . intval(123);
$result = $this->progressService->executeCustomQuery($sql);
```

---

## ✅ Best Practices

1. **Clear Separation**
   - Progress data = ProgressService
   - Laravel data = Eloquent

2. **Cache Aggressively**
   - Store API results in SQLite via Eloquent
   - Reduces external API costs

3. **Use Appropriate Tool**
   - Don't force Eloquent for Progress
   - Don't avoid Eloquent for Laravel tables

4. **Document Connection**
   - Add comments about which database
   - Clear model docblocks

---

## 📖 Summary

| Data Type | Database | Tool | Example |
|-----------|----------|------|---------|
| Packages, Routes, Transporters | Progress | ProgressService + JDBC | `$this->executeCustomQuery()` |
| Geocoding Cache | SQLite | Eloquent | `MunicipioCoordenada::where()` |
| Route Cache | SQLite | Eloquent | `RouteSegment::find()` |
| Users, Auth | SQLite/MySQL | Eloquent | `User::find()` |

**Golden Rule**: If it's in Progress → JDBC. If it's in Laravel → Eloquent. Simple! ✅
