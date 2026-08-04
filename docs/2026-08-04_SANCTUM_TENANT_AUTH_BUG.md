# Bug: Sanctum token não reconhecido logo após login (multitenancy)
**Created:** 2026-08-04
**Status:** ✅ RESOLVED (2026-08-04) — ver "Causa raiz e correção" no final do documento.
**Reported from:** `starter_kit_frontend` (Nuxt admin panel), durante a adaptação do painel à multitenancy
**Purpose:** Reprodução isolada e autocontida de um bug de autenticação — leia sem precisar de contexto prévio da sessão que gerou este documento.

---

## Sintoma

Login (`POST /api/admin/login`) funciona e retorna um Sanctum token válido, mas **qualquer request autenticada seguinte com esse mesmo token retorna 401 "Unauthenticated."** — reproduzido no painel Nuxt (usuário é deslogado imediatamente após o login, redirecionado de volta para `/auth/login`) e confirmado direto via curl, isolando completamente o backend do frontend.

## Reprodução

Tenants de teste já existentes: `tenant-a` / `admina@tenant-a.test` / `password123`.

**1. Login (funciona, 200):**
```bash
curl -s "http://localhost:8006/api/admin/login?tenant=tenant-a" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"admina@tenant-a.test","password":"password123"}'
# -> 200, { admin: {...}, token: "11|xxxxx...", roles: [] }
```

**2. Usar esse token imediatamente depois (falha, 401):**
```bash
TOKEN="<token do passo 1>"

curl -s -w "\n%{http_code}\n" "http://localhost:8006/api/admin/dashboard?tenant=tenant-a" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
# -> {"message":"Unauthenticated."}  401

curl -s -w "\n%{http_code}\n" "http://localhost:8006/api/admin/me?tenant=tenant-a" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
# -> {"message":"Unauthenticated."}  401
```

## Já isolado — não é específico do modo de resolução de tenant

Testado nos dois mecanismos de resolução de tenant separadamente; resultado idêntico nos dois:

- **Via `?tenant=` query param** (acima, usado pelo frontend em dev quando `APP_ENV` é `local`/`testing`).
- **Via subdomínio real**, usando `Host` header em vez do query param:
```bash
curl -s "http://localhost:8006/api/admin/login" -H "Host: tenant-a.starterkit.test:8006" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"admina@tenant-a.test","password":"password123"}'
# -> 200, token válido

curl -s -w "\n%{http_code}\n" "http://localhost:8006/api/admin/me" \
  -H "Host: tenant-a.starterkit.test:8006" -H "Authorization: Bearer <token acima>" -H "Accept: application/json"
# -> {"message":"Unauthenticated."}  401
```

Mesmo resultado nos dois. Isso descarta o `?tenant=` query param (ou qualquer coisa do lado do Nuxt) como causa — o problema é 100% reproduzível com curl puro contra o backend, sem envolver o frontend.

## Hipótese

Suspeita: o middleware `auth:sanctum` está rodando **antes** de `tenant.identify` trocar a conexão de banco para a do tenant. Se isso for verdade, o guard do Sanctum procura o personal access token na conexão default/landlord em vez da conexão do tenant onde o token acabou de ser criado no login — e nunca encontra, resultando em 401 em toda request subsequente, para qualquer endpoint autenticado.

Vale checar:
1. Ordem dos middlewares em `routes/api.php` (ou no grupo de rotas `/admin/*`) — `tenant.identify` precisa rodar antes de `auth:sanctum`.
2. Se o model `PersonalAccessToken` (ou a tabela `personal_access_tokens`) está configurado para usar a conexão dinâmica do tenant (`Sanctum::usePersonalAccessTokenModel(...)` com um model que respeita a conexão trocada em runtime), e não uma conexão fixa resolvida antes da troca.
3. Se o token é de fato persistido na tenant DB certa no momento do login (dá pra confirmar olhando a tabela `personal_access_tokens` do banco de `tenant-a` logo após o passo 1).

## Impacto

Login funciona, mas a aplicação fica inutilizável — toda tela pós-login falha com 401 imediatamente (confirmado no dashboard do admin panel Nuxt). Bloqueia toda validação end-to-end da feature de multitenancy no frontend.

## Causa raiz e correção

A hipótese do item 1 estava correta: `auth:sanctum` rodava antes de `tenant.identify`, apesar da ordem de registro nas rotas. Confirmado empiricamente com logging temporário nos dois middlewares — na request `/api/admin/dashboard`, `IdentifyTenant::handle()` nunca era chamado; `Authenticate::handle()` (do `auth:sanctum`) rejeitava a request com 401 antes disso.

Causa: `bootstrap/app.php` já tinha uma chamada a `$middleware->prependToPriorityList(before: ..., prepend: \App\Http\Middleware\IdentifyTenant::class)` desde o Sprint 0.1, para forçar `IdentifyTenant` a rodar antes do `Authenticate` do Sanctum na lista de prioridade do Laravel (que reordena middleware de framework à frente de qualquer middleware não listado, independente da ordem de registro nas rotas). Só que o argumento `before:` apontava para `\Illuminate\Auth\Middleware\Authenticate::class` — a classe concreta. A lista de prioridade padrão do Laravel (`Illuminate\Foundation\Http\Kernel::$middlewarePriority`) contém a **interface** `\Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class`, não a classe concreta. `addToMiddlewarePriorityRelative()` faz um `array_search()` exato contra essa lista; como a string da classe concreta nunca aparece lá, a busca falhava silenciosamente e `IdentifyTenant` era simplesmente **anexado ao final** da lista de prioridade em vez de inserido antes do `Authenticate` — o oposto do que a chamada pretendia fazer. Sem exception, sem warning: um no-op silencioso.

**Fix** (`bootstrap/app.php`):
```php
$middleware->prependToPriorityList(
    before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class, // era \Illuminate\Auth\Middleware\Authenticate::class
    prepend: \App\Http\Middleware\IdentifyTenant::class,
);
```

**Verificação:**
- `route:list -v` em `/api/admin/dashboard` agora mostra `IdentifyTenant` antes de `Illuminate\Auth\Middleware\Authenticate:sanctum`.
- Reprodução exata dos passos acima (login → dashboard/me com o token) retorna 200 nos dois modos de resolução de tenant (`?tenant=` e `Host` header/subdomínio real).
- Suite de testes completa (`php artisan test`) rodada após o fix: 28 falhas, todas pré-existentes e sem relação com auth/tenant (role management, settings, notifications, file upload, unread count) — mesma baseline de antes do fix, nenhuma regressão.
