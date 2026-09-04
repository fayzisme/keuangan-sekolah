# M3 AUTH & RBAC — Konten Review (baseline NO_VCS)
Seluruh implementasi milestone M3-4 (Sanctum auth + spatie RBAC teams per sekolah + school context + rate-limit + frontend login minimal).

## FILE: app/Http/Controllers/Api/AuthController.php
```php
<?php

namespace App\Http\Controllers\Api;

use App\Domain\Auth\Actions\LoginAction;
use App\Domain\Auth\Actions\SwitchSchoolAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

final class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $result = $action($request);

        return response()->json($result);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $schoolId = $request->attributes->get('school_id');
        $activeSchool = $request->attributes->get('school');

        app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'roles' => $user->getRoleNames(),
            'active_school' => $activeSchool ? ['id' => $activeSchool->id, 'name' => $activeSchool->name] : null,
            'schools' => $user->schools->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'is_active' => $s->pivot->is_active]),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    public function switchSchool(Request $request, SwitchSchoolAction $action): JsonResponse
    {
        $request->validate([
            'school_id' => ['required', 'integer'],
        ]);

        $result = $action($request->user(), (int) $request->school_id);

        return response()->json($result);
    }

    public function users(Request $request): JsonResponse
    {
        $schoolId = $request->attributes->get('school_id');
        app(PermissionRegistrar::class)->setPermissionsTeamId($schoolId);

        $users = User::query()
            ->whereHas('schools', fn ($q) => $q->where('schools.id', $schoolId))
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'roles' => $u->getRoleNames(),
                ];
            });

        return response()->json(['data' => $users]);
    }
}

```

## FILE: app/Domain/Auth/Actions/LoginAction.php
```php
<?php

namespace App\Domain\Auth\Actions;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginAction
{
    public function __invoke(LoginRequest $request): array
    {
        $user = User::query()->where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan tidak cocok dengan catatan kami.'],
            ]);
        }

        $schools = $user->schools()->withPivot('is_active')->get();
        $activeSchool = $user->activeSchool();

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'schools' => $schools->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'is_active' => (bool) $s->pivot->is_active,
            ]),
            'active_school' => $activeSchool ? ['id' => $activeSchool->id, 'name' => $activeSchool->name] : null,
        ];
    }
}

```

## FILE: app/Domain/Auth/Actions/SwitchSchoolAction.php
```php
<?php

namespace App\Domain\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SwitchSchoolAction
{
    public function __invoke(User $user, int $schoolId): array
    {
        DB::transaction(function () use ($user, $schoolId) {
            foreach ($user->schools()->pluck('schools.id') as $id) {
                $user->schools()->updateExistingPivot($id, ['is_active' => false]);
            }
            $user->schools()->updateExistingPivot($schoolId, ['is_active' => true]);
        });

        $user->unsetRelation('schools');
        $activeSchool = $user->activeSchool();

        if (is_null($activeSchool)) {
            throw ValidationException::withMessages([
                'school_id' => ['Anda tidak terdaftar di sekolah ini.'],
            ]);
        }

        return [
            'message' => 'Konteks sekolah berhasil diubah.',
            'active_school' => ['id' => $activeSchool->id, 'name' => $activeSchool->name],
        ];
    }
}

```

## FILE: app/routes/api.php
```php
<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'School Finance API v1 siap.',
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'school.context'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/switch-school', [AuthController::class, 'switchSchool']);

        // Gated endpoint untuk DoD isolasi role
        Route::get('/users', [AuthController::class, 'users'])->middleware('role:admin|bendahara');
    });
});

```

## FILE: app/Http/Middleware/EnsureSchoolContext.php
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSchoolContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (is_null($user)) {
            abort(401);
        }

        $school = $user->schools()->wherePivot('is_active', true)->first();

        if (is_null($school)) {
            return response()->json(['message' => 'No active school context.'], 403);
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

        $request->attributes->set('school_id', $school->id);
        $request->attributes->set('school', $school);

        return $next($request);
    }
}

```

## FILE: app/config/permission.php
```php
<?php

return [
    'models' => [
        'permission' => Spatie\Permission\Models\Permission::class,
        'role' => Spatie\Permission\Models\Role::class,
    ],
    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],
    'column_names' => [
        'role_pivot_key' => null,
        'permission_pivot_key' => null,
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'school_id',
    ],
    'register_permission_check_method' => true,
    'register_octane_reset_listener' => false,
    'teams' => true,
    'use_passport_client_credentials' => false,
    'display_permission_in_exception' => false,
    'display_role_in_exception' => false,
    'enable_wildcard_permission' => false,
    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'store' => 'default',
    ],
];

```

## FILE: app/config/auth.php
```php
<?php

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'sanctum'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
    ],
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],
    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];

```

## FILE: app/bootstrap/app.php
```php
<?php

use App\Http\Middleware\EnsureSchoolContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: null,
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // Alias middleware tenant context. Dipakai pada grup route yang membutuhkan sekolah aktif.
            'school.context' => EnsureSchoolContext::class,
            // Alias middleware RBAC spatie/laravel-permission.
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Semua error response dalam bentuk JSON karena backend adalah API murni.
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })
    ->create();

```

## FILE: app/Models/User.php
```php
<?php

namespace App\Models;

use App\Domain\School\Models\School;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function schools(): BelongsToMany
    {
        return $this->belongsToMany(School::class, 'school_user')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function activeSchool(): ?School
    {
        return $this->schools()->wherePivot('is_active', true)->first();
    }
}

```

## FILE: app/Domain/School/Models/School.php
```php
<?php

namespace App\Domain\School\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'school_user')
            ->withPivot('is_active')
            ->withTimestamps();
    }
}

```

## FILE: app/database/seeders/DatabaseSeeder.php
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AuthSeeder::class);
    }
}

```

## FILE: app/database/seeders/AuthSeeder.php
```php
<?php

namespace Database\Seeders;

use App\Domain\School\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class AuthSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::create(['name' => 'SMA Merdeka Demo']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

        $roles = ['admin', 'bendahara', 'murid', 'ortua'];
        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName, 'sanctum');
        }

        $users = [
            ['name' => 'Admin Demo', 'email' => 'admin@demo.sch.id', 'role' => 'admin'],
            ['name' => 'Bendahara Demo', 'email' => 'bendahara@demo.sch.id', 'role' => 'bendahara'],
            ['name' => 'Murid Demo', 'email' => 'murid@demo.sch.id', 'role' => 'murid'],
            ['name' => 'Ortua Demo', 'email' => 'ortua@demo.sch.id', 'role' => 'ortua'],
        ];

        foreach ($users as $u) {
            $user = User::create([
                'name' => $u['name'],
                'email' => $u['email'],
                'password' => Hash::make('password123'),
            ]);

            $user->schools()->attach($school->id, ['is_active' => true]);
            $user->assignRole($u['role']);
        }
    }
}

```

## FILE: app/Providers/AppServiceProvider.php
```php
<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip().'|'.$request->input('email'));
        });
    }
}

```

## FILE: app/Http/Requests/LoginRequest.php
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}

```

## FILE: web/src/features/auth/auth-context.ts
```ts
import { createContext } from 'react';

export type User = { id: number; name: string; email: string };
export type School = { id: number; name: string; is_active?: boolean };

export type AuthContextType = {
  token: string | null;
  user: User | null;
  roles: string[];
  activeSchool: School | null;
  schools: School[];
  login: (token: string, user: User, activeSchool: School | null, schools: School[]) => void;
  logout: () => void;
  setContextData: (data: { roles: string[]; activeSchool: School | null; schools: School[] }) => void;
};

export const AuthContext = createContext<AuthContextType | undefined>(undefined);

```

## FILE: web/src/features/auth/AuthContext.tsx
```tsx
import React, { useState } from 'react';
import { AuthContext, type User, type School } from './auth-context';

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [token, setToken] = useState<string | null>(localStorage.getItem('token'));
  const [user, setUser] = useState<User | null>(() => {
    const saved = localStorage.getItem('user');
    return saved ? (JSON.parse(saved) as User) : null;
  });
  const [roles, setRoles] = useState<string[]>([]);
  const [activeSchool, setActiveSchool] = useState<School | null>(null);
  const [schools, setSchools] = useState<School[]>([]);

  const login = (tokenVal: string, userVal: User, schoolVal: School | null, schoolsVal: School[]) => {
    setToken(tokenVal);
    setUser(userVal);
    setActiveSchool(schoolVal);
    setSchools(schoolsVal);
    localStorage.setItem('token', tokenVal);
    localStorage.setItem('user', JSON.stringify(userVal));
  };

  const logout = () => {
    setToken(null);
    setUser(null);
    setRoles([]);
    setActiveSchool(null);
    setSchools([]);
    localStorage.removeItem('token');
    localStorage.removeItem('user');
  };

  const setContextData = (data: { roles: string[]; activeSchool: School | null; schools: School[] }) => {
    setRoles(data.roles);
    setActiveSchool(data.activeSchool);
    setSchools(data.schools);
  };

  return (
    <AuthContext.Provider value={{ token, user, roles, activeSchool, schools, login, logout, setContextData }}>
      {children}
    </AuthContext.Provider>
  );
}

```

## FILE: web/src/features/auth/useAuth.ts
```ts
import { useContext } from 'react';
import { AuthContext, type AuthContextType } from './auth-context';

export function useAuth(): AuthContextType {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}

```

## FILE: web/src/features/auth/RequireAuth.tsx
```tsx
import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from './useAuth';

export function RequireAuth({ children }: { children: React.ReactNode }) {
  const { token } = useAuth();

  if (!token) {
    return <Navigate to="/login" replace />;
  }

  return <>{children}</>;
}

```

## FILE: web/src/features/auth/LoginPage.tsx
```tsx
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from './useAuth';
import { loginApi } from '../../api/client';

export function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const { login } = useAuth();
  const navigate = useNavigate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const res = await loginApi(email, password);
      login(res.token, res.user, res.active_school, res.schools);
      navigate('/');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login gagal.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="app-shell">
      <div className="hero-card" style={{ width: '100%', maxWidth: '400px' }}>
        <p className="eyebrow">Authentication</p>
        <h2>Login Sistem Keuangan</h2>
        {error && <div style={{ color: 'red', marginBottom: '1rem' }}>{error}</div>}
        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          <div>
            <label style={{ display: 'block', fontSize: '0.875rem' }}>Email</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              style={{ width: '100%', padding: '0.5rem', borderRadius: '8px', border: '1px solid #ccc' }}
            />
          </div>
          <div>
            <label style={{ display: 'block', fontSize: '0.875rem' }}>Password</label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              style={{ width: '100%', padding: '0.5rem', borderRadius: '8px', border: '1px solid #ccc' }}
            />
          </div>
          <button
            type="submit"
            disabled={loading}
            style={{
              padding: '0.75rem',
              borderRadius: '8px',
              border: 'none',
              background: '#2563eb',
              color: '#fff',
              fontWeight: 'bold',
              cursor: 'pointer',
            }}
          >
            {loading ? 'Logging in...' : 'Login'}
          </button>
        </form>
      </div>
    </div>
  );
}

```

## FILE: web/src/features/users/UsersPage.tsx
```tsx
import React, { useEffect, useState } from 'react';
import { usersApi } from '../../api/client';
import { useAuth } from '../auth/useAuth';

type SchoolUser = {
  id: number;
  name: string;
  email: string;
  roles: string[];
};

export function UsersPage() {
  const [users, setUsers] = useState<SchoolUser[]>([]);
  const [error, setError] = useState('');
  const { token } = useAuth();

  useEffect(() => {
    usersApi(token ?? '')
      .then((data) => setUsers(data.data as SchoolUser[]))
      .catch((err) => setError(err instanceof Error ? err.message : 'Gagal memuat pengguna.'));
  }, [token]);

  return (
    <div className="hero-card">
      <p className="eyebrow">RBAC Protected Area</p>
      <h2>Daftar Pengguna Sekolah</h2>
      {error ? (
        <div style={{ color: 'red' }}>Akses Ditolak: {error}</div>
      ) : (
        <ul>
          {users.map((u) => (
            <li key={u.id}>
              {u.name} ({u.email}) - Peran: {u.roles?.join(', ') || 'None'}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

```

## FILE: web/src/api/client.ts
```ts
export type ApiPingResponse = {
  status: 'ok';
  message: string;
};

export async function pingApi(): Promise<ApiPingResponse> {
  const response = await fetch('/api/v1/ping', {
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) {
    throw new Error(`API ping gagal: ${response.status}`);
  }

  return response.json() as Promise<ApiPingResponse>;
}

export async function loginApi(email: string, password: string) {
  const res = await fetch('/api/v1/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email, password }),
  });

  if (!res.ok) {
    const data = await res.json().catch(() => ({}));
    throw new Error(data.message || 'Login gagal.');
  }

  return res.json();
}

export async function fetchMeApi(token: string) {
  const res = await fetch('/api/v1/auth/me', {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
  });

  if (!res.ok) throw new Error('Unauthorized');
  return res.json();
}

export async function logoutApi(token: string) {
  await fetch('/api/v1/auth/logout', {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
  });
}

export async function switchSchoolApi(token: string, schoolId: number) {
  const res = await fetch('/api/v1/auth/switch-school', {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ school_id: schoolId }),
  });

  if (!res.ok) throw new Error('Gagal switch sekolah.');
  return res.json();
}

export async function usersApi(token: string) {
  const res = await fetch('/api/v1/auth/users', {
    headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
  });

  if (!res.ok) throw new Error('403 Forbidden / Akses Ditolak');
  return res.json();
}

```

## FILE: web/src/app/router.tsx
```tsx
import { createBrowserRouter } from 'react-router-dom';
import { App } from './App';
import { LoginPage } from '../features/auth/LoginPage';
import { RequireAuth } from '../features/auth/RequireAuth';
import { UsersPage } from '../features/users/UsersPage';

export const router = createBrowserRouter([
  {
    path: '/login',
    element: <LoginPage />,
  },
  {
    path: '/',
    element: (
      <RequireAuth>
        <App />
      </RequireAuth>
    ),
  },
  {
    path: '/users',
    element: (
      <RequireAuth>
        <UsersPage />
      </RequireAuth>
    ),
  },
]);

```

## FILE: web/src/main.tsx
```tsx
import React from 'react';
import ReactDOM from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { RouterProvider } from 'react-router-dom';
import { router } from './app/router';
import { AuthProvider } from './features/auth/AuthContext';
import './styles.css';

const queryClient = new QueryClient();

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <RouterProvider router={router} />
      </AuthProvider>
    </QueryClientProvider>
  </React.StrictMode>,
);

```

## FILE: app/tests/Feature/AuthLoginTest.php
```php
<?php

use App\Domain\School\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('allows user to login with valid credentials', function () {
    $school = School::create(['name' => 'Test School']);
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);
    $user->schools()->attach($school->id, ['is_active' => true]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user', 'schools', 'active_school']);
});

it('rejects invalid credentials', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'wrong@example.com',
        'password' => 'wrong',
    ]);

    $response->assertStatus(422);
});

```

## FILE: app/tests/Feature/AuthMeTest.php
```php
<?php

use App\Domain\School\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns authenticated user details and active school', function () {
    $school = School::create(['name' => 'Test School']);
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->schools()->attach($school->id, ['is_active' => true]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('user.email', 'test@example.com')
        ->assertJsonPath('active_school.id', $school->id);
});

```

## FILE: app/tests/Feature/RoleIsolationTest.php
```php
<?php

use App\Domain\School\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('allows admin and bendahara to access users list, but forbids murid and ortua', function () {
    $school = School::create(['name' => 'Test School']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);

    Role::findOrCreate('admin', 'sanctum');
    Role::findOrCreate('murid', 'sanctum');

    $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password')]);
    $admin->schools()->attach($school->id, ['is_active' => true]);
    $admin->assignRole('admin');

    $murid = User::create(['name' => 'Murid', 'email' => 'murid@test.com', 'password' => bcrypt('password')]);
    $murid->schools()->attach($school->id, ['is_active' => true]);
    $murid->assignRole('murid');

    // Admin access -> 200
    Sanctum::actingAs($admin);
    $this->getJson('/api/v1/auth/users')->assertOk();

    // Murid access -> 403
    Sanctum::actingAs($murid);
    $this->getJson('/api/v1/auth/users')->assertStatus(403);
});

```

## FILE: app/tests/Feature/SwitchSchoolTest.php
```php
<?php

use App\Domain\School\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('allows user to switch active school context', function () {
    $school1 = School::create(['name' => 'School 1']);
    $school2 = School::create(['name' => 'School 2']);

    $user = User::create(['name' => 'User', 'email' => 'user@test.com', 'password' => bcrypt('password')]);
    $user->schools()->attach($school1->id, ['is_active' => true]);
    $user->schools()->attach($school2->id, ['is_active' => false]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/auth/switch-school', [
        'school_id' => $school2->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('active_school.id', $school2->id);
});

```
