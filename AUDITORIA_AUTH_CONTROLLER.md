# 🔒 Auditoria de Segurança: AuthController

**Data:** 2025-12-03
**Auditor:** Sistema de Segurança Automatizado
**Arquivo:** `app/Http/Controllers/Api/AuthController.php`
**Linhas de código:** 149
**Endpoints auditados:** 4 (login, register, logout, user)

---

## 📋 Resumo Executivo

### Estatísticas da Auditoria
- ✅ **Vulnerabilidades encontradas:** 9 total
  - 🔴 **CRITICAL:** 0
  - 🟠 **HIGH:** 2
  - 🟡 **MEDIUM:** 4
  - 🔵 **LOW:** 3
- ⚠️ **Risco geral:** MÉDIO-ALTO
- 📊 **Score de segurança:** 72/100

### Pontos Positivos ✅
- ✅ Senha forte obrigatória (lowercase, uppercase, number, special char)
- ✅ Rate limiting configurado (10/min login, 5/min register)
- ✅ Laravel Sanctum para tokens stateless
- ✅ Hash::make() para senhas (bcrypt seguro)
- ✅ Role validation no model com mutator
- ✅ Validação de integridade de role no login
- ✅ Double-check de password_confirmation
- ✅ Logout deleta apenas currentAccessToken (não revoga todos)

### Principais Riscos 🚨
1. 🟠 **Brute Force Detection:** Sem logging de tentativas falhadas
2. 🟠 **Email Verification:** Qualquer pessoa pode criar conta sem verificação
3. 🟡 **RBAC Hardcoded:** userAbilityRules não usa role real do usuário
4. 🟡 **Automated Bots:** Sem CAPTCHA/proteção anti-bot

---

## 🔍 Vulnerabilidades Detalhadas

### 🟠 VULNERABILIDADE #1 (HIGH): Brute Force Detection Impossível
**Severidade:** HIGH
**CWE:** CWE-307 (Improper Restriction of Excessive Authentication Attempts)
**OWASP:** A07:2021 - Identification and Authentication Failures

**Localização:** `AuthController.php` linhas 14-72 (método `login()`)

**Problema:**
Não há logging de tentativas falhadas de login. Isso impossibilita:
- Detectar ataques de brute force em andamento
- Analisar padrões de ataque após incidentes
- Implementar rate limiting por usuário (apenas por IP existe)
- Compliance com LGPD Art. 46 (registro de eventos de segurança)

**Código atual:**
```php
public function login(Request $request)
{
    // ...validação...

    if (Auth::attempt($credentials)) {
        // ✅ Sucesso - logado
        $user = Auth::user();
        // ... cria token e retorna ...
    }

    // ❌ Falha - SEM LOGGING!
    return response()->json([
        'success' => false,
        'message' => 'Credenciais inválidas'
    ], 401);
}
```

**Cenário de Exploração:**
```bash
# Atacante pode tentar milhares de senhas sem deixar rastro
for password in $(cat rockyou.txt); do
    curl -X POST http://localhost:8002/api/auth/login \
        -d "email=admin@ndd.com&password=$password"
done

# Sistema NÃO registra essas tentativas - ataque invisível!
```

**Impacto:**
- Ataques de brute force não são detectados
- Análise forense impossível após breach
- Não conformidade com LGPD Art. 46
- Impossível implementar "account lockout" baseado em tentativas

**CORREÇÃO #1:**
```php
public function login(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required|string|min:8',
    ]);

    if ($validator->fails()) {
        return response()->json([...], 422);
    }

    $credentials = $request->only('email', 'password');

    if (Auth::attempt($credentials)) {
        $user = Auth::user();

        // Validar integridade de role
        if (!$user->role || !in_array($user->role, ['admin', 'user'], true)) {
            \Log::error('Usuário com role inválido detectado', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role
            ]);

            return response()->json([...], 500);
        }

        // CORREÇÃO #1: Logging de login bem-sucedido
        \Log::info('Login bem-sucedido', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String()
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([...]);
    }

    // CORREÇÃO #1: Logging de tentativa falhada (CRÍTICO para segurança)
    \Log::warning('Tentativa de login falhada', [
        'email' => $request->input('email'),
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'timestamp' => now()->toIso8601String()
    ]);

    return response()->json([
        'success' => false,
        'message' => 'Credenciais inválidas'
    ], 401);
}
```

---

### 🟠 VULNERABILIDADE #2 (HIGH): Sem Verificação de Email
**Severidade:** HIGH
**CWE:** CWE-640 (Weak Password Recovery Mechanism for Forgotten Password)
**OWASP:** A07:2021 - Identification and Authentication Failures

**Localização:** `AuthController.php` linhas 92-149 (método `register()`)

**Problema:**
Qualquer pessoa pode criar conta sem verificação de email. Isso permite:
- Automated account creation por bots
- Spam de registros com emails falsos
- Consumo de recursos do banco
- Possível DDoS via criação massiva de contas

**Código atual:**
```php
public function register(Request $request)
{
    // ...validação...

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,  // ❌ Email não verificado!
        'password' => Hash::make($request->password),
        'role' => 'user',
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;  // ✅ Já logado!

    return response()->json([...], 201);
}
```

**Cenário de Exploração:**
```bash
# Atacante cria 1000 contas em 3 minutos (rate limit 5/min = 15 contas/min)
# Usando proxies diferentes para bypass do rate limit por IP

for i in {1..1000}; do
    curl -X POST http://localhost:8002/api/auth/register \
        -d "name=bot$i&email=fake$i@test.com&password=Bot@12345&password_confirmation=Bot@12345" \
        --proxy "socks5://proxy-$((i % 100)).com:1080"
done

# Resultado: 1000 contas falsas no banco, tokens válidos criados!
```

**Impacto:**
- Database pollution com contas falsas
- Consumo de recursos (tokens, sessões)
- Possível DDoS econômico (storage costs)
- Impossível distinguir usuários reais de bots

**CORREÇÃO #2 (Duas opções):**

**Opção A: Email Verification (Recomendado para produção)**
```php
use Illuminate\Auth\Events\Registered;

public function register(Request $request)
{
    // ...validação...

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'role' => 'user',
        'email_verified_at' => null,  // ❌ Não verificado ainda
    ]);

    // Enviar email de verificação
    event(new Registered($user));

    // CORREÇÃO #2: NÃO criar token ainda (forçar verificação)
    return response()->json([
        'success' => true,
        'message' => 'Conta criada! Verifique seu email para ativar.',
        'requires_verification' => true
    ], 201);
}

// Novo endpoint para verificar email
public function verifyEmail(Request $request)
{
    $user = User::findOrFail($request->id);

    if (!hash_equals((string) $request->hash, sha1($user->getEmailForVerification()))) {
        return response()->json(['error' => 'Link inválido'], 403);
    }

    $user->markEmailAsVerified();

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'success' => true,
        'token' => $token
    ]);
}
```

**Opção B: CAPTCHA (Proteção básica, mais rápida de implementar)**
```php
use ReCaptcha\ReCaptcha;

public function register(Request $request)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'email' => 'required|string|email|max:255|unique:users',
        'password' => [...],
        'password_confirmation' => 'required|string|min:8',
        'recaptcha_token' => 'required|string',  // NOVO
    ]);

    if ($validator->fails()) {
        return response()->json([...], 422);
    }

    // CORREÇÃO #2: Validar reCAPTCHA
    $recaptcha = new ReCaptcha(config('services.recaptcha.secret'));
    $response = $recaptcha->verify($request->input('recaptcha_token'), $request->ip());

    if (!$response->isSuccess()) {
        \Log::warning('reCAPTCHA falhou no registro', [
            'email' => $request->input('email'),
            'ip' => $request->ip(),
            'errors' => $response->getErrorCodes()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Verificação anti-bot falhou. Tente novamente.'
        ], 422);
    }

    // ... restante do código de registro ...
}
```

---

### 🟡 VULNERABILIDADE #3 (MEDIUM): RBAC Hardcoded (userAbilityRules)
**Severidade:** MEDIUM
**CWE:** CWE-269 (Improper Privilege Management)
**OWASP:** A01:2021 - Broken Access Control

**Localização:** `AuthController.php` linhas 59-64 (método `login()`)

**Problema:**
`userAbilityRules` retorna hardcoded `{action: 'manage', subject: 'all'}` para TODOS os usuários, ignorando completamente o campo `role` do usuário.

**Código atual:**
```php
return response()->json([
    'accessToken' => $token,
    'userData' => [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'role' => $user->role,  // ✅ Role enviado...
        'avatar' => null,
    ],
    'userAbilityRules' => [  // ❌ MAS ABILITIES IGNORAM ROLE!
        [
            'action' => 'manage',
            'subject' => 'all'  // ❌ TODOS podem tudo!
        ]
    ]
]);
```

**Cenário de Exploração:**
```javascript
// Frontend - usuário com role='user' deveria ter acesso limitado
const userData = useCookie('userData').value
console.log(userData.role)  // "user" (correto)

const abilities = useCookie('userAbilityRules').value
console.log(abilities)  // [{action: 'manage', subject: 'all'}]
// ❌ "user" comum tem permissões de admin no frontend!

// Usuário pode acessar rotas/componentes protegidos
if (can('manage', 'all')) {
    // ❌ Usuário comum entra aqui!
    router.push('/admin/users')
}
```

**Impacto:**
- Frontend não consegue implementar RBAC real
- Usuários comuns podem acessar UI de admin (se backend não validar)
- Violação do princípio do privilégio mínimo
- Confusão entre role e abilities

**CORREÇÃO #3:**
```php
public function login(Request $request)
{
    // ...código de autenticação...

    if (Auth::attempt($credentials)) {
        $user = Auth::user();

        // ...validação de role...

        $token = $user->createToken('auth_token')->plainTextToken;

        // CORREÇÃO #3: Abilities baseadas em role real
        $userAbilityRules = $this->getAbilitiesForRole($user->role);

        return response()->json([
            'accessToken' => $token,
            'userData' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'avatar' => null,
            ],
            'userAbilityRules' => $userAbilityRules  // ✅ Agora usa role!
        ]);
    }

    // ...
}

/**
 * CORREÇÃO #3: Retorna abilities baseadas em role
 *
 * @param string $role
 * @return array
 */
private function getAbilitiesForRole(string $role): array
{
    // Admin: acesso total
    if ($role === 'admin') {
        return [
            ['action' => 'manage', 'subject' => 'all']
        ];
    }

    // User: acesso limitado
    if ($role === 'user') {
        return [
            // Leitura permitida
            ['action' => 'read', 'subject' => 'transportes'],
            ['action' => 'read', 'subject' => 'pacotes'],
            ['action' => 'read', 'subject' => 'rotas'],
            ['action' => 'read', 'subject' => 'vale-pedagio'],

            // Operações permitidas
            ['action' => 'create', 'subject' => 'compra-viagem'],

            // Gerenciar próprio perfil
            ['action' => 'manage', 'subject' => 'own-profile']
        ];
    }

    // Fallback: sem permissões (seguro)
    \Log::error('Role desconhecido detectado', [
        'role' => $role
    ]);

    return [];
}
```

---

### 🟡 VULNERABILIDADE #4 (MEDIUM): Sem Logging de Novos Registros
**Severidade:** MEDIUM
**CWE:** CWE-778 (Insufficient Logging)
**LGPD:** Art. 46 (Registro de eventos de segurança)

**Localização:** `AuthController.php` linhas 92-149 (método `register()`)

**Problema:**
Não há logging quando novos usuários se registram. Isso impossibilita:
- Auditoria de compliance (LGPD Art. 46)
- Detecção de padrões suspeitos de registro
- Análise de crescimento de usuários
- Investigação de contas fraudulentas

**Código atual:**
```php
public function register(Request $request)
{
    // ...validação...

    $user = User::create([...]);  // ❌ Sem logging!

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([...], 201);
}
```

**Impacto:**
- Não conformidade com LGPD
- Análise forense incompleta
- Impossível detectar automated registration

**CORREÇÃO #4:**
```php
public function register(Request $request)
{
    $validator = Validator::make($request->all(), [...]);

    if ($validator->fails()) {
        return response()->json([...], 422);
    }

    if ($request->password !== $request->password_confirmation) {
        return response()->json([...], 422);
    }

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'role' => 'user',
    ]);

    // CORREÇÃO #4: Logging de novo registro (LGPD compliance)
    \Log::info('Novo usuário registrado', [
        'user_id' => $user->id,
        'email' => $user->email,
        'name' => $user->name,
        'role' => $user->role,
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'timestamp' => now()->toIso8601String()
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'success' => true,
        'message' => 'Usuário criado com sucesso',
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]
    ], 201);
}
```

---

### 🟡 VULNERABILIDADE #5 (MEDIUM): Validação de Password Confirmation Manual
**Severidade:** MEDIUM
**CWE:** CWE-20 (Improper Input Validation)

**Localização:** `AuthController.php` linhas 118-127 (método `register()`)

**Problema:**
A validação de `password_confirmation` é feita manualmente APÓS a validação do Laravel, o que é redundante e pode causar inconsistências.

**Código atual:**
```php
$validator = Validator::make($request->all(), [
    // ...
    'password' => [
        'required',
        'string',
        'min:8',
        'confirmed',  // ✅ Laravel valida confirmed
        // ...
    ],
    'password_confirmation' => 'required|string|min:8',
]);

if ($validator->fails()) {
    return response()->json([...], 422);
}

// ❌ Double-check REDUNDANTE - Laravel já validou com 'confirmed'
if ($request->password !== $request->password_confirmation) {
    return response()->json([...], 422);
}
```

**Problema técnico:**
- Laravel `'confirmed'` rule já valida que `password` === `password_confirmation`
- Double-check manual é redundante e pode causar bugs se lógica divergir
- Se `validator->fails()` passou, senhas JÁ SÃO iguais!

**CORREÇÃO #5 (Remover redundância):**
```php
$validator = Validator::make($request->all(), [
    'name' => 'required|string|max:255',
    'email' => 'required|string|email|max:255|unique:users',
    'password' => [
        'required',
        'string',
        'min:8',
        'confirmed',  // ✅ Isso JÁ valida que password === password_confirmation
        'regex:/[a-z]/',
        'regex:/[A-Z]/',
        'regex:/[0-9]/',
        'regex:/[@$!%*#?&]/',
    ],
    'password_confirmation' => 'required|string|min:8',
]);

if ($validator->fails()) {
    return response()->json([
        'success' => false,
        'message' => 'Dados inválidos',
        'errors' => $validator->errors()
    ], 422);
}

// CORREÇÃO #5: Remover double-check redundante
// (Se chegou aqui, senhas já foram validadas como iguais)

$user = User::create([...]);
```

---

### 🔵 VULNERABILIDADE #6 (LOW): GET /auth/user Sem Rate Limiting
**Severidade:** LOW
**CWE:** CWE-307 (Improper Restriction of Excessive Authentication Attempts)

**Localização:** `routes/api.php` linha 29 + `AuthController.php` linha 84-90

**Problema:**
Endpoint `GET /auth/user` não tem rate limiting. Pode ser abusado para enumerate tokens válidos via brute force.

**Código atual:**
```php
// routes/api.php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/user', [AuthController::class, 'user']);  // ❌ Sem throttle!
});
```

**Cenário de Exploração:**
```bash
# Atacante testa tokens roubados/vazados
for token in $(cat leaked-tokens.txt); do
    curl http://localhost:8002/api/auth/user \
        -H "Authorization: Bearer $token"
done

# Sem rate limit, pode testar milhares de tokens/segundo!
```

**CORREÇÃO #6:**
```php
// routes/api.php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // CORREÇÃO #6: Adicionar rate limiting
    Route::get('auth/user', [AuthController::class, 'user'])
        ->middleware('throttle:60,1');  // 60 req/min
});
```

---

### 🔵 VULNERABILIDADE #7 (LOW): Auto-login Após Registro (Session Fixation Risk)
**Severidade:** LOW
**CWE:** CWE-384 (Session Fixation)

**Localização:** `AuthController.php` linhas 136-147 (método `register()`)

**Problema:**
Após criar conta, o sistema IMEDIATAMENTE retorna um token de autenticação, fazendo auto-login do usuário. Isso pode facilitar session fixation attacks se o token for predictable ou se houver XSS.

**Código atual:**
```php
public function register(Request $request)
{
    // ...

    $user = User::create([...]);

    $token = $user->createToken('auth_token')->plainTextToken;  // ❌ Auto-login

    return response()->json([
        'success' => true,
        'message' => 'Usuário criado com sucesso',
        'token' => $token,  // ❌ Token imediato
        'user' => [...]
    ], 201);
}
```

**Risco:**
- Se token vazar (XSS, MITM), atacante tem acesso imediato
- Não dá tempo para verificação de email
- Viola princípio de "verify before trust"

**CORREÇÃO #7 (Opcional, depende de UX):**
```php
public function register(Request $request)
{
    // ...validação...

    $user = User::create([...]);

    // CORREÇÃO #7: NÃO criar token automaticamente
    // Forçar usuário a fazer login explicitamente

    \Log::info('Novo usuário registrado', [...]);

    return response()->json([
        'success' => true,
        'message' => 'Usuário criado com sucesso! Faça login para continuar.',
        'redirect_to_login' => true  // ✅ Frontend redireciona para /login
    ], 201);
}
```

---

### 🔵 VULNERABILIDADE #8 (LOW): rememberMe Não Implementado
**Severidade:** LOW
**Impacto:** UX ruim, não é vulnerabilidade de segurança

**Localização:** `login.vue` linha 45 + `AuthController.php` (não existe)

**Problema:**
Frontend envia `rememberMe` mas backend não implementa. Token tem expiração padrão independente do checkbox.

**Código frontend:**
```typescript
// login.vue
const rememberMe = ref(false)

const login = async () => {
    const res = await $api('/auth/login', {
        method: 'POST',
        body: {
            email: credentials.value.email,
            password: credentials.value.password,
            // ❌ rememberMe não enviado!
        },
        // ...
    })
}
```

**CORREÇÃO #8 (Opcional, feature não crítica):**
```php
// AuthController.php
public function login(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required|string|min:8',
        'remember_me' => 'nullable|boolean',  // NOVO
    ]);

    // ...

    if (Auth::attempt($credentials)) {
        $user = Auth::user();

        // ...validação de role...

        // CORREÇÃO #8: TTL baseado em remember_me
        $rememberMe = $request->input('remember_me', false);
        $tokenName = 'auth_token';
        $expiresAt = $rememberMe ? now()->addDays(30) : now()->addHours(8);

        $token = $user->createToken($tokenName, ['*'], $expiresAt)->plainTextToken;

        return response()->json([...]);
    }

    // ...
}
```

```typescript
// login.vue - enviar rememberMe
const login = async () => {
    const res = await $api('/auth/login', {
        method: 'POST',
        body: {
            email: credentials.value.email,
            password: credentials.value.password,
            remember_me: rememberMe.value,  // ✅ Enviar para backend
        },
        // ...
    })
}
```

---

### 🔵 VULNERABILIDADE #9 (LOW): Password Minimum Length Não Documentado
**Severidade:** LOW
**Impacto:** UX - usuários podem não saber requisitos de senha

**Localização:** Frontend `register.vue` linha 168

**Problema:**
Frontend usa `passwordValidator` mas mensagem de erro do backend não é clara sobre requisitos exatos.

**Backend retorna:**
```json
{
  "success": false,
  "message": "Dados inválidos",
  "errors": {
    "password": [
      "The password field must be at least 8 characters.",
      "The password field format is invalid."  // ❌ Não diz O QUE é inválido!
    ]
  }
}
```

**CORREÇÃO #9:**
```php
$validator = Validator::make($request->all(), [
    'name' => 'required|string|max:255',
    'email' => 'required|string|email|max:255|unique:users',
    'password' => [
        'required',
        'string',
        'min:8',
        'confirmed',
        'regex:/[a-z]/',
        'regex:/[A-Z]/',
        'regex:/[0-9]/',
        'regex:/[@$!%*#?&]/',
    ],
    'password_confirmation' => 'required|string|min:8',
], [
    // CORREÇÃO #9: Mensagens customizadas mais claras
    'password.regex' => 'A senha deve conter: 1 letra minúscula, 1 maiúscula, 1 número e 1 caractere especial (@$!%*#?&)',
    'password.min' => 'A senha deve ter no mínimo 8 caracteres',
    'password.confirmed' => 'As senhas não correspondem',
]);
```

---

## 📊 Análise de Compatibilidade com Frontend

### Endpoints Afetados
1. ✅ `POST /auth/login` - Frontend usa em `login.vue` linha 49
2. ✅ `POST /auth/register` - Frontend usa em `register.vue` linha 54
3. ✅ `POST /auth/logout` - Assumido uso em componentes protegidos
4. ✅ `GET /auth/user` - Assumido uso para verificar sessão

### Impacto das Correções
- ✅ **CORREÇÃO #1 (Logging):** 100% backward compatible - apenas adiciona logs
- ✅ **CORREÇÃO #2 (Email Verification):** ⚠️ BREAKING CHANGE se implementar Opção A
  - Opção B (CAPTCHA) requer mudanças no frontend
- ✅ **CORREÇÃO #3 (RBAC):** 🔄 Requer atualização do frontend para usar abilities corretamente
- ✅ **CORREÇÃO #4 (Logging):** 100% backward compatible
- ✅ **CORREÇÃO #5 (Remover redundância):** 100% backward compatible
- ✅ **CORREÇÃO #6 (Rate Limiting):** 100% backward compatible
- ✅ **CORREÇÃO #7 (Sem auto-login):** ⚠️ BREAKING CHANGE - frontend espera token
- ✅ **CORREÇÃO #8 (rememberMe):** Requer mudanças no frontend
- ✅ **CORREÇÃO #9 (Mensagens):** 100% backward compatible

---

## 🛠️ Plano de Implementação

### FASE 1 - Correções Imediatas (100% Backward Compatible)
**Prioridade:** CRÍTICA
**Tempo:** 30 minutos
**Breaking Changes:** NENHUM

**Implementar:**
- ✅ CORREÇÃO #1: Logging de login (success + failed)
- ✅ CORREÇÃO #4: Logging de novos registros
- ✅ CORREÇÃO #5: Remover double-check redundante
- ✅ CORREÇÃO #6: Rate limiting em GET /auth/user
- ✅ CORREÇÃO #9: Mensagens de erro customizadas

**Resultado:** 4 vulnerabilidades corrigidas, sistema continua 100% funcional

---

### FASE 2 - RBAC Implementação (Requer Frontend Update)
**Prioridade:** ALTA
**Tempo:** 2 horas
**Breaking Changes:** Frontend precisa usar abilities corretamente

**Implementar:**
- ✅ CORREÇÃO #3: Abilities baseadas em role real
- 🔄 Atualizar frontend para verificar abilities específicas
- 🔄 Adicionar guards nos componentes admin

**Arquivos a modificar:**
- `AuthController.php` - adicionar método `getAbilitiesForRole()`
- `resources/ts/plugins/casl/ability.ts` - configurar abilities
- `resources/ts/@layouts/components/VerticalNav.vue` - guards nas rotas

---

### FASE 3 - Email Verification (Opcional, Longo Prazo)
**Prioridade:** MÉDIA
**Tempo:** 4-6 horas
**Breaking Changes:** Workflow de registro muda completamente

**Implementar:**
- ✅ CORREÇÃO #2 (Opção A): Email verification workflow
- 🔄 Criar migration para `email_verified_at`
- 🔄 Configurar SMTP para envio de emails
- 🔄 Criar rota de verificação de email
- 🔄 Atualizar frontend para mostrar mensagem de verificação
- 🔄 Criar página de "Email enviado"

**OU Implementar:**
- ✅ CORREÇÃO #2 (Opção B): reCAPTCHA (mais rápido)
- 🔄 Cadastrar site no Google reCAPTCHA v3
- 🔄 Adicionar reCAPTCHA no formulário de registro
- 🔄 Validar token no backend

---

### FASE 4 - Features Opcionais (UX Improvements)
**Prioridade:** BAIXA
**Tempo:** 2-3 horas

**Implementar:**
- ✅ CORREÇÃO #7: Remover auto-login após registro
- ✅ CORREÇÃO #8: Implementar rememberMe funcional

---

## 📝 Checklist de Implementação

### FASE 1 - Correções Imediatas ✅
```bash
[ ] Ler AuthController.php linha por linha
[ ] Implementar CORREÇÃO #1 (Logging de login)
[ ] Implementar CORREÇÃO #4 (Logging de registro)
[ ] Implementar CORREÇÃO #5 (Remover double-check)
[ ] Implementar CORREÇÃO #6 (Rate limiting /auth/user)
[ ] Implementar CORREÇÃO #9 (Mensagens customizadas)
[ ] Testar endpoints com curl
[ ] Verificar logs em storage/logs/laravel.log
[ ] Commitar mudanças
```

---

## 🔐 Mapeamento de Compliance

### LGPD (Lei Geral de Proteção de Dados)
- ✅ **Art. 46:** Registro de eventos de segurança
  - CORREÇÃO #1: Logs de tentativas de login
  - CORREÇÃO #4: Logs de novos usuários

### OWASP Top 10 2021
- ✅ **A01:2021 - Broken Access Control:** CORREÇÃO #3 (RBAC)
- ✅ **A07:2021 - Identification and Authentication Failures:** CORREÇÃO #1, #2, #6

### CWE (Common Weakness Enumeration)
- ✅ **CWE-307:** Brute Force - CORREÇÃO #1, #6
- ✅ **CWE-640:** Email Verification - CORREÇÃO #2
- ✅ **CWE-269:** Privilege Management - CORREÇÃO #3
- ✅ **CWE-778:** Insufficient Logging - CORREÇÃO #1, #4

---

## 📚 Referências

- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [Laravel Sanctum Documentation](https://laravel.com/docs/12.x/sanctum)
- [LGPD Art. 46](http://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm)
- [CWE-307: Brute Force Attacks](https://cwe.mitre.org/data/definitions/307.html)

---

**Próximos Passos:**
1. ✅ Revisar esta documentação
2. ✅ Implementar FASE 1 (correções imediatas)
3. ⏳ Planejar FASE 2 (RBAC) com equipe de frontend
4. ⏳ Decidir entre Email Verification vs CAPTCHA (FASE 3)
