# 🏗️ Plan de Implementación Multi-Tenancy con stancl/tenancy

## 📋 Objetivo
Convertir la app de un solo cliente a **multi-tenant** (múltiples clientes en la misma DB y container).

---

## ⚙️ Estrategia Elegida
**Single Database Multi-Tenancy con Tenant ID**
- 1 Base de datos
- 1 Container Laravel
- Cada registro tiene `tenant_id`
- Aislamiento automático con Global Scopes

---

## 📦 PASO 1: Instalar stancl/tenancy

```bash
cd /Users/fpl3001/Documents/josecarlos/cobrador-web

# Instalar el package
composer require stancl/tenancy

# Publicar archivos de configuración
php artisan tenancy:install

# Esto crea:
# - config/tenancy.php
# - app/Models/Tenant.php
# - database/migrations/2019_09_15_000010_create_tenants_table.php
# - app/Providers/TenancyServiceProvider.php
```

---

## 📦 PASO 2: Configurar Tenancy Mode

**Editar: `config/tenancy.php`**

```php
<?php

return [
    // Modo: single database (todos los tenants en 1 DB)
    'database' => [
        'based_on' => null, // Single DB mode
    ],

    // Identificación de tenant
    'identification' => [
        // Opción A: Por subdomain (cliente1.tuapp.com)
        // 'drivers' => ['subdomain'],

        // Opción B: Por API token (Bearer token con tenant_id)
        'drivers' => ['request_data'], // Más flexible para APIs
    ],

    // Central domains (donde NO se aplica tenancy)
    'central_domains' => [
        'localhost',
        '127.0.0.1',
        'cobrador-web.test', // Tu dominio de desarrollo
    ],

    // Features automáticos
    'features' => [
        // Stancl\Tenancy\Features\UserImpersonation::class,
        // Stancl\Tenancy\Features\TelescopeTags::class,
        // Stancl\Tenancy\Features\TenantConfig::class,
    ],
];
```

---

## 📦 PASO 3: Crear Migración para tenant_id en TODAS las tablas

**Crear migración:**
```bash
php artisan make:migration add_tenant_id_to_all_tables
```

**Editar: `database/migrations/XXXX_add_tenant_id_to_all_tables.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lista de TODAS las tablas que necesitan tenant_id
        $tables = [
            'users',
            'credits',
            'payments',
            'notifications',
            'cash_balances',
            'routes',
            'interest_rates',
            'client_categories',
            // Agregar TODAS tus tablas aquí
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                // Agregar tenant_id (nullable por ahora para migración)
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');

                // Índice para performance
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'users', 'credits', 'payments', 'notifications',
            'cash_balances', 'routes', 'interest_rates', 'client_categories',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
```

---

## 📦 PASO 4: Crear Modelo Tenant

**Editar: `app/Models/Tenant.php`** (ya fue creado por tenancy:install)

```php
<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;

    /**
     * Los campos que se pueden asignar masivamente
     */
    protected $fillable = [
        'id',
        'name',          // Nombre del cliente: "Empresa XYZ"
        'subdomain',     // Subdominio único: "empresa-xyz"
        'api_key',       // API key único para identificar tenant
        'status',        // active, suspended, cancelled
        'plan',          // plan básico/premium/enterprise
    ];

    /**
     * Casts
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Generar API key único al crear tenant
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            if (!$tenant->api_key) {
                $tenant->api_key = 'tenant_' . bin2hex(random_bytes(16));
            }
        });
    }
}
```

**Migración de Tenant:**
```bash
# Editar: database/migrations/2019_09_15_000010_create_tenants_table.php
```

```php
Schema::create('tenants', function (Blueprint $table) {
    $table->id();
    $table->string('name');              // "Empresa XYZ"
    $table->string('subdomain')->unique()->nullable(); // "empresa-xyz"
    $table->string('api_key')->unique(); // "tenant_abc123..."
    $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active');
    $table->string('plan')->default('basic'); // basic/premium/enterprise
    $table->timestamps();
});
```

---

## 📦 PASO 5: Crear Global Scope para auto-filtrar por tenant_id

**Crear: `app/Scopes/TenantScope.php`**

```php
<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Aplicar scope a query
     */
    public function apply(Builder $builder, Model $model)
    {
        // Obtener tenant actual (si existe)
        if (tenancy()->initialized) {
            $builder->where($model->getTable() . '.tenant_id', tenant('id'));
        }
    }

    /**
     * Extender builder con métodos útiles
     */
    public function extend(Builder $builder)
    {
        // Agregar método para queries sin filtro de tenant (admin)
        $builder->macro('withoutTenancy', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
```

---

## 📦 PASO 6: Crear Trait para Modelos Multi-Tenant

**Crear: `app/Traits/BelongsToTenant.php`**

```php
<?php

namespace App\Traits;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    /**
     * Boot del trait
     */
    protected static function bootBelongsToTenant()
    {
        // Agregar Global Scope automáticamente
        static::addGlobalScope(new TenantScope());

        // Al crear, auto-asignar tenant_id
        static::creating(function (Model $model) {
            if (tenancy()->initialized && !$model->tenant_id) {
                $model->tenant_id = tenant('id');
            }
        });
    }

    /**
     * Relación con Tenant
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
```

---

## 📦 PASO 7: Agregar Trait a TODOS los modelos

**Editar cada modelo:**

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Credit extends Model
{
    use BelongsToTenant; // ← Agregar esta línea

    protected $fillable = [
        'tenant_id', // ← Agregar a fillable
        'client_id',
        'amount',
        // ... resto de campos
    ];
}
```

**Modelos que necesitan el trait:**
- ✅ `User.php`
- ✅ `Credit.php`
- ✅ `Payment.php`
- ✅ `Notification.php`
- ✅ `CashBalance.php`
- ✅ `Route.php`
- ✅ `InterestRate.php`
- ✅ `ClientCategory.php`

---

## 📦 PASO 8: Configurar Identificación de Tenant por API Key

**Crear: `app/Http/Middleware/IdentifyTenantByApiKey.php`**

```php
<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class IdentifyTenantByApiKey
{
    public function handle(Request $request, Closure $next)
    {
        // Obtener API key del header
        $apiKey = $request->header('X-Tenant-Api-Key')
               ?? $request->bearerToken(); // o desde Bearer token

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant API key required'
            ], 401);
        }

        // Buscar tenant por API key
        $tenant = Tenant::where('api_key', $apiKey)
                       ->where('status', 'active')
                       ->first();

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive tenant'
            ], 401);
        }

        // Inicializar tenancy
        tenancy()->initialize($tenant);

        return $next($request);
    }
}
```

---

## 📦 PASO 9: Registrar Middleware

**Editar: `app/Http/Kernel.php`**

```php
protected $middlewareAliases = [
    // ... otros middleware
    'tenant' => \App\Http\Middleware\IdentifyTenantByApiKey::class,
];
```

**Aplicar a rutas API:**

**Editar: `routes/api.php`**

```php
<?php

use Illuminate\Support\Facades\Route;

// ============================================
// RUTAS CENTRALES (sin tenancy)
// ============================================
Route::prefix('admin')->group(function () {
    // Rutas para gestionar tenants (solo admin)
    Route::post('/tenants', [TenantController::class, 'create']);
    Route::get('/tenants', [TenantController::class, 'index']);
});

// ============================================
// RUTAS CON TENANCY (requieren X-Tenant-Api-Key)
// ============================================
Route::middleware(['tenant'])->group(function () {

    // Todas las rutas existentes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/credits', [CreditController::class, 'index']);
        Route::post('/credits', [CreditController::class, 'store']);
        // ... todas tus rutas actuales
    });
});
```

---

## 📦 PASO 10: Migrar Datos Existentes

**Crear: `database/migrations/XXXX_assign_existing_data_to_default_tenant.php`**

```php
<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Crear tenant por defecto (Railway - Cliente original)
        $defaultTenant = Tenant::create([
            'name' => 'Cliente Railway (Original)',
            'subdomain' => 'railway',
            'api_key' => 'tenant_railway_default_' . bin2hex(random_bytes(8)),
            'status' => 'active',
            'plan' => 'premium',
        ]);

        // Asignar tenant_id a TODOS los registros existentes
        $tables = [
            'users', 'credits', 'payments', 'notifications',
            'cash_balances', 'routes', 'interest_rates', 'client_categories',
        ];

        foreach ($tables as $table) {
            DB::table($table)->update(['tenant_id' => $defaultTenant->id]);
        }

        echo "✅ Datos existentes asignados a tenant: {$defaultTenant->name}\n";
        echo "🔑 API Key: {$defaultTenant->api_key}\n";
    }

    public function down(): void
    {
        // Revertir: poner tenant_id en null
        $tables = [
            'users', 'credits', 'payments', 'notifications',
            'cash_balances', 'routes', 'interest_rates', 'client_categories',
        ];

        foreach ($tables as $table) {
            DB::table($table)->update(['tenant_id' => null]);
        }

        // Eliminar tenant por defecto
        Tenant::where('subdomain', 'railway')->delete();
    }
};
```

---

## 📦 PASO 11: Hacer tenant_id NOT NULL (después de migrar datos)

**Crear: `database/migrations/XXXX_make_tenant_id_not_nullable.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'users', 'credits', 'payments', 'notifications',
            'cash_balances', 'routes', 'interest_rates', 'client_categories',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'users', 'credits', 'payments', 'notifications',
            'cash_balances', 'routes', 'interest_rates', 'client_categories',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        }
    }
};
```

---

## 📦 PASO 12: Crear Controller para gestionar Tenants

**Crear: `app/Http/Controllers/Admin/TenantController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    /**
     * Listar todos los tenants (admin)
     */
    public function index()
    {
        $tenants = Tenant::all();
        return response()->json(['tenants' => $tenants]);
    }

    /**
     * Crear nuevo tenant (cliente nuevo)
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subdomain' => 'nullable|string|unique:tenants,subdomain',
            'plan' => 'in:basic,premium,enterprise',
        ]);

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'subdomain' => $validated['subdomain'] ?? null,
            'plan' => $validated['plan'] ?? 'basic',
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'tenant' => $tenant,
            'api_key' => $tenant->api_key, // ← Entregar al cliente
            'message' => 'Tenant creado exitosamente'
        ], 201);
    }

    /**
     * Suspender/Cancelar tenant
     */
    public function updateStatus(Request $request, $id)
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:active,suspended,cancelled'
        ]);

        $tenant->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'tenant' => $tenant
        ]);
    }
}
```

---

## 🧪 PASO 13: Testing Multi-Tenancy

**Crear: `tests/Feature/MultiTenancyTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Credit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_only_see_their_own_data()
    {
        // Crear 2 tenants
        $tenant1 = Tenant::create(['name' => 'Tenant 1']);
        $tenant2 = Tenant::create(['name' => 'Tenant 2']);

        // Crear créditos para cada tenant
        tenancy()->initialize($tenant1);
        $credit1 = Credit::create(['amount' => 1000]);

        tenancy()->initialize($tenant2);
        $credit2 = Credit::create(['amount' => 2000]);

        // Verificar aislamiento
        tenancy()->initialize($tenant1);
        $this->assertEquals(1, Credit::count()); // Solo ve su crédito
        $this->assertEquals(1000, Credit::first()->amount);

        tenancy()->initialize($tenant2);
        $this->assertEquals(1, Credit::count()); // Solo ve su crédito
        $this->assertEquals(2000, Credit::first()->amount);
    }

    public function test_api_requires_tenant_api_key()
    {
        $response = $this->getJson('/api/credits');
        $response->assertStatus(401);
        $response->assertJson(['message' => 'Tenant API key required']);
    }

    public function test_api_works_with_valid_tenant_api_key()
    {
        $tenant = Tenant::create(['name' => 'Test Tenant']);

        $response = $this->getJson('/api/credits', [
            'X-Tenant-Api-Key' => $tenant->api_key
        ]);

        $response->assertStatus(200);
    }
}
```

**Ejecutar tests:**
```bash
php artisan test --filter MultiTenancyTest
```

---

## 📱 PASO 14: Actualizar Flutter App

**Agregar API Key en requests:**

```dart
// lib/datos/servicios/api_service.dart

class ApiService {
  static const String _tenantApiKey = 'tenant_railway_default_abc123'; // ← API key del cliente

  Future<Response> get(String endpoint) async {
    return dio.get(
      endpoint,
      options: Options(headers: {
        'X-Tenant-Api-Key': _tenantApiKey, // ← Header con tenant
        'Accept': 'application/json',
      }),
    );
  }
}
```

---

## ✅ RESUMEN DE EJECUCIÓN

```bash
# 1. Instalar package
composer require stancl/tenancy
php artisan tenancy:install

# 2. Crear migraciones
php artisan make:migration add_tenant_id_to_all_tables
php artisan make:migration assign_existing_data_to_default_tenant
php artisan make:migration make_tenant_id_not_nullable

# 3. Ejecutar migraciones
php artisan migrate

# 4. Copiar archivos del plan (Scopes, Traits, Middleware, Controllers)

# 5. Actualizar modelos (agregar BelongsToTenant trait)

# 6. Configurar rutas con middleware 'tenant'

# 7. Testing
php artisan test --filter MultiTenancyTest

# 8. Crear nuevo cliente
POST /api/admin/tenants
{
  "name": "Nuevo Cliente XYZ",
  "subdomain": "cliente-xyz",
  "plan": "premium"
}

# Respuesta:
{
  "success": true,
  "api_key": "tenant_abc123...",  // ← Dar al cliente
  "message": "Tenant creado exitosamente"
}
```

---

## 🎯 VENTAJAS DE ESTA IMPLEMENTACIÓN

✅ **1 container Laravel** = $10/mes (sin importar # de clientes)
✅ **1 DB** = $15/mes (escala hasta 1000s de clientes)
✅ **Deploy 1 vez** = Todos los clientes actualizados
✅ **Cliente nuevo** = 30 segundos (POST a /api/admin/tenants)
✅ **Aislamiento garantizado** = Cliente1 NUNCA ve datos de Cliente2
✅ **Testing robusto** = Garantiza seguridad

---

## 🚀 PRÓXIMOS PASOS

1. ¿Quieres que empecemos con la implementación?
2. ¿Tienes dudas sobre algún paso específico?
3. ¿Prefieres que lo hagamos paso a paso juntos?

**Tiempo estimado:** 3-4 horas de implementación + 1 hora de testing.
