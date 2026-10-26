# HANDOFF — Catálogo SaaS Multi-tenant

> Archivo de seguimiento. Actualizar al cerrar cada sesión de trabajo (sección [Registro de sesiones](#registro-de-sesiones)).

## Estado general

| | |
|---|---|
| Última actualización | 2026-10-25 |
| Fase | **MVP completo** (API admin + storefront + panel + ISR). Siguiente fase: **SaaS** (super panel, planes, registro público, Mercado Pago; ver [Visión de producto](#visión-de-producto-saas)). Deploy al final |
| Backend | Laravel 13 · PHP 8.4 · PostgreSQL 16 · Sanctum · Spatie Permission (teams) · Pest · Larastan nivel 8 |
| Frontend | Next.js 16 · React 19 · Tailwind 4 · Vitest · Playwright (e2e) |
| Tenancy | Global Scope custom, una sola base, columna `store_id` |
| Entorno local | Docker Compose ([backend/docker-compose.yml](backend/docker-compose.yml)): `app`, `queue`, `frontend`, `pgsql` |
| Control de versiones | GitHub privado [`rodrigoluque256/app-catalogo`](https://github.com/rodrigoluque256/app-catalogo) (rama `main`, SSH). Identidad local del repo: Rodrigo Luque &lt;rodrigoluque256@gmail.com&gt; |
| CI | GitHub Actions en verde (backend + frontend) desde el commit `6b7aae2` |

**Última verificación (2026-10-26):**

| Chequeo | Resultado |
|---|---|
| `migrate:fresh --seed` | OK |
| Pest (backend) | 108/108 (478 assertions) |
| PHPStan nivel 8 | sin errores |
| Vitest (frontend) | 51/51 |
| e2e de navegador del panel (Playwright, 20 pasos) | OK |
| Auditoría WCAG 2.2 AA con axe (tienda escritorio/móvil + panel, 27 pasos) | OK, 0 violaciones |
| e2e del super panel: login + 2FA + recuperación, con axe (9 pasos) | OK |
| Build de producción de Next + ISR (MISS → HIT) + revalidación real | OK |

Leyenda: `[x]` hecho · `[ ]` pendiente · `[~]` parcial

---

## Qué está hecho

### Backend — API

| Módulo | Endpoints | Permiso | Tests |
|---|---|---|---|
| Auth | `POST /api/v1/auth/login`, `POST /auth/logout`, `GET /auth/me`, `POST /auth/password` | autenticado | 7 + 3 |
| Productos | `apiResource /api/v1/admin/products` | `products.manage` | 12 feature + 7 unit |
| Categorías | `apiResource /api/v1/admin/categories` | `products.manage` | 11 |
| Uploads (R2) | `POST /api/v1/admin/uploads/presign` | según propósito | 5 |
| Usuarios | `GET/POST /admin/users`, `PUT/DELETE /admin/users/{id}`, `POST /admin/users/{id}/reset-password` | `users.manage` | 14 |
| Mi tienda | `GET/PATCH /api/v1/admin/store` | `store.manage` | 11 |
| Storefront público | `GET /api/v1/storefront/{store, categories, products, products/{slug}}` (header `X-Store-Slug`) | público | 9 |
| Revalidación ISR | listener en cola → `POST` al frontend | — | 7 |
| Super panel: acceso | `POST /api/v1/platform/auth/login`, `POST /platform/auth/two-factor/{setup,confirm,verify}` (token de desafío), `GET /platform/auth/me`, `POST /platform/auth/logout` | `platform.admin` | 15 + 5 (comando) |

Rutas: [backend/routes/api.php](backend/routes/api.php). Middleware del grupo admin: `auth:sanctum`, `tenant.user`, `password.changed`. Super panel: `auth:sanctum`, `platform.admin`, `abilities:platform` (o `abilities:platform:challenge` en el 2FA).

Roles (globales, asignados por tienda): **owner** = todos los permisos · **editor** = `products.manage`. Seeder: [RolesAndPermissionsSeeder](backend/database/seeders/RolesAndPermissionsSeeder.php). En bases existentes, después de agregar permisos: `php artisan db:seed --class=RolesAndPermissionsSeeder`.

### Frontend — `frontend/`

| Área | Rutas | Notas |
|---|---|---|
| Storefront | `/s/[store]` (inicio con destacados), `/c/[slug]`, `/p/[slug]`, `/buscar` | Se reescribe desde `{slug}.dominio` vía [proxy.ts](frontend/proxy.ts). ISR con tags; `/buscar` sin caché. Tema por tienda con contraste automático. Botón de WhatsApp (`wa.me`) con mensaje precargado desde el producto/variante. |
| Panel admin | `/admin/login`, `/admin`, `/admin/products[/new\|/{id}]`, `/admin/categories[/new\|/{id}]`, `/admin/settings`, `/admin/users`, `/admin/account` | Sesión con token Sanctum guardado del lado servidor ([lib/admin/session.ts](frontend/lib/admin/session.ts)). Primer ingreso forzado a cambiar contraseña. |
| Revalidación | `POST /api/revalidate` | Valida `x-revalidate-secret` y llama `revalidateTag` por cada tag. |
| Super panel | `/platform/login`, `/platform/two-factor`, `/platform/recovery-codes`, `/platform` | Cookies propias limitadas a `/platform` ([lib/platform/session.ts](frontend/lib/platform/session.ts)). Por ahora solo acceso; la gestión de tiendas es P2.1b. Cliente HTTP común de ambos paneles: [lib/server-api.ts](frontend/lib/server-api.ts). |

Detalle: [frontend/README.md](frontend/README.md).

---

## Arquitectura del backend

```
backend/app/
├── Domain/                     # reglas de negocio, sin HTTP
│   ├── Shared/Tenancy/         TenantContext, StoreScope, BelongsToStore, TenantNotResolvedException
│   ├── Shared/Events/          AffectsStorefront (interfaz), StorefrontTags (contrato de tags ISR)
│   ├── Shared/Exceptions/      DomainValidationException (→ 422)
│   ├── Auth/Exceptions/        InvalidCredentials, UserManagement, UserNotFound
│   ├── Store/                  Models/Store, Enums/StoreStatus, Events/StoreSettingsUpdated
│   │                           Repositories/{Store,StoreUser}RepositoryInterface
│   │                           Contracts/{StorefrontRevalidator, UploadUrlSigner, UploadedObjectInspector, …}
│   └── Product/                Models/{Product,ProductVariant,ProductImage,Category}
│                               Enums/ProductStatus, Repositories/{Product,Category}RepositoryInterface
│                               Events/{ProductCreated,ProductUpdated,ProductDeleted,CategoriesChanged}
│                               Exceptions/{DuplicateSku,CategoryNotFound,CategoryRecordNotFound,
│                                           InvalidCategoryParent,ProductNotFound}
├── Application/                # casos de uso: una clase = una acción
│   ├── Auth/Actions/           Login, ChangePassword
│   ├── Product/                Actions (Create/Update/Delete/Get/List + Storefront Get/List), DTOs, Services
│   ├── Category/               Actions (CRUD), DTOs/CategoryData, Services/{CategoryParentGuard, CategorySlugGenerator}
│   ├── Store/                  Actions/UpdateStoreSettings, DTOs
│   ├── User/Actions/           Create/Update/Delete/List/ResetPassword, EnsureOwnerRemains
│   ├── Upload/                 Actions/CreateUploadUrl, Enums/UploadPurpose
│   └── Storefront/Listeners/   RevalidateStorefront (en cola)
├── Infrastructure/
│   ├── Persistence/Eloquent/   Eloquent{Product,Category,Store,StoreUser}Repository
│   ├── Storefront/             NextJsStorefrontRevalidator, R2UploadUrlSigner, R2UploadedObjectInspector
│   └── Providers/              DomainServiceProvider (bindings + listeners)
└── UI/Http/
    ├── Controllers/Api/V1/Admin/       Auth, Product, Category, Upload, User, StoreSettings
    ├── Controllers/Api/V1/Storefront/  StorefrontController
    ├── Requests/{Auth,Product,Category,Store,Upload,User}/
    ├── Resources/              (incluye StorefrontProductResource: sin SKU, stock ni estado)
    ├── Rules/                  UploadedImage (HEAD a R2)
    └── Middleware/             ResolveTenantFromUser, ResolveTenantFromHeader, password.changed
```

Nota: existe una carpeta vacía `app/Domain/Category/Events` (sobrante; `CategoriesChanged` vive en `Domain/Product/Events`).

### Migraciones

| Archivo | Tabla | Puntos clave |
|---|---|---|
| [000001](backend/database/migrations/2026_10_23_000001_create_stores_table.php) | `stores` | slug = subdominio (único, formato DNS), colores hex con CHECK, WhatsApp E.164 con CHECK, `settings` jsonb |
| [000002](backend/database/migrations/2026_10_23_000002_add_store_id_to_users_table.php) | `users` | `store_id` nullable (pensado como null = super admin; **se reemplaza** por la tabla `platform_admins`, ver Visión de producto) |
| [000003](backend/database/migrations/2026_10_23_000003_create_categories_table.php) | `categories` | árbol `parent_id` en la misma tienda (FK compuesta), slug único por tienda |
| [000004](backend/database/migrations/2026_10_23_000004_create_products_table.php) | `products` | precio en centavos, FK compuesta a categoría, slug/SKU únicos por tienda, `search_vector` full-text español + GIN |
| [000005](backend/database/migrations/2026_10_23_000005_create_product_variants_table.php) | `product_variants` | `options` jsonb + GIN, precio y stock opcionales (null = hereda / sin control) |
| [000006](backend/database/migrations/2026_10_23_000006_create_product_images_table.php) | `product_images` | key de R2, una sola principal por producto, `variant_id` opcional |
| [000007](backend/database/migrations/2026_10_23_000007_create_permission_tables.php) | Spatie | `team_id` UUID (team = store) |
| [000008](backend/database/migrations/2026_10_23_000008_add_must_change_password_to_users_table.php) | `users` | `must_change_password` |
| [190444](backend/database/migrations/2026_10_23_190444_create_personal_access_tokens_table.php) | Sanctum | tokens de API (polimórficos: `User` y `PlatformAdmin`) |
| [2026_10_26_000001](backend/database/migrations/2026_10_26_000001_create_platform_admins_table.php) | `platform_admins` | super admins, tabla aparte de `users`; secreto TOTP y códigos de recuperación cifrados |
| [2026_10_26_000002](backend/database/migrations/2026_10_26_000002_create_platform_audit_logs_table.php) | `platform_audit_logs` | auditoría del super panel; `store_id` sin FK para sobrevivir al borrado |

Garantías: IDs UUID v7 · unicidades con índices parciales `WHERE deleted_at IS NULL` · **FKs compuestas `(x_id, store_id)`** (la base impide referenciar filas de otra tienda) · requiere PostgreSQL ≥ 15 por `ON DELETE SET NULL (columna)`.

---

## Contratos clave

### Multi-tenancy (defensa en profundidad)
1. Middleware fija el [TenantContext](backend/app/Domain/Shared/Tenancy/TenantContext.php): `tenant.user` (admin: tienda del usuario autenticado, nunca de la URL) o `tenant.header` (storefront: `X-Store-Slug`, que envía el proxy de Next).
2. [StoreScope](backend/app/Domain/Shared/Tenancy/StoreScope.php) global vía [BelongsToStore](backend/app/Domain/Shared/Tenancy/BelongsToStore.php); sin tenant lanza excepción (falla cerrado).
3. Los repositorios filtran y asignan `store_id` explícitamente.
4. FKs compuestas en la base.

Fuera de un request (seeders, jobs, comandos): `app(TenantContext::class)->runAs($store, fn () => ...)`.

### Revalidación ISR
- Eventos que implementan `AffectsStorefront`: `ProductCreated/Updated/Deleted`, `CategoriesChanged`, `StoreSettingsUpdated` → listener en cola `RevalidateStorefront` → [NextJsStorefrontRevalidator](backend/app/Infrastructure/Storefront/NextJsStorefrontRevalidator.php) hace `POST STOREFRONT_REVALIDATE_URL` con header `x-revalidate-secret` y `{"tags": [...]}`. 5 reintentos con backoff; sin URL configurada no hace nada.
- **Tags** ([StorefrontTags](backend/app/Domain/Shared/Events/StorefrontTags.php) ↔ [frontend/lib/tags.ts](frontend/lib/tags.ts)): `store:{slug}`, `store:{slug}:products`, `store:{slug}:product:{productSlug}`, `store:{slug}:categories`. Si se cambia uno, cambiar ambos lados.

### Subida de imágenes a R2
- `POST /admin/uploads/presign` `{purpose: product_image|store_logo, content_type: jpeg|png|webp, size ≤ 5 MB}` → `{path, upload_url, method: PUT, headers, public_url, expires_at}` (TTL 10 min). Key generada por el servidor: `stores/{storeId}/{products|logo}/{uuid7}.{ext}`.
- Flujo: presign → `PUT` directo a R2 → enviar `path` en `images[].path` o `logo_path`.
- **Límite del SDK:** la URL prefirmada no firma tipo ni tamaño. La barrera real es la regla [UploadedImage](backend/app/UI/Http/Rules/): HEAD a R2 exigiendo que exista, sea JPEG/PNG/WebP y ≤ 5 MB, y que la key tenga el prefijo de la tienda.
- **CORS del bucket** (R2 > bucket > Settings > CORS Policy). Sin esto el navegador bloquea el `PUT`. En producción, agregar el origen real del panel:
  ```json
  [{ "AllowedOrigins": ["http://localhost:3000"], "AllowedMethods": ["PUT"], "AllowedHeaders": ["content-type"], "MaxAgeSeconds": 3600 }]
  ```
- **URL pública:** en desarrollo, la Public Development URL (`https://pub-xxxx.r2.dev`), con límite de tráfico y sin caché. En producción, un dominio propio conectado al bucket: cambiar `R2_PUBLIC_URL` (backend) e `IMAGE_HOSTS` (frontend). Se guarda la key, no la URL, así que no hay datos que migrar.
- **e2e:** `app-e2e` pisa las `R2_*` con valores vacíos, para no escribir nunca en el bucket real aunque `backend/.env` tenga credenciales. El paso de subida del e2e registra el error como INFO.

### Errores
Errores de dominio → 422 con el mismo formato que la validación de Laravel. Recurso inexistente o de otra tienda → 404. 5xx sin mensaje interno. Ver [bootstrap/app.php](backend/bootstrap/app.php).

### Usuarios y contraseñas
Alta con contraseña temporal aleatoria (14 caracteres, se muestra una sola vez) → `must_change_password`; el middleware `password.changed` bloquea la API admin con 403 `password_change_required` hasta cambiarla. No se puede eliminar ni cambiar el rol de uno mismo; la tienda conserva siempre al menos un dueño. Email único en toda la plataforma. Borrar o resetear revoca tokens. Contraseñas: ≥ 10 caracteres con letras y números. Login: token de 12 h, mensaje genérico, throttle 5/min por email+IP.

### Super panel: acceso
- **Identidad:** `PlatformAdmin` ([modelo](backend/app/Domain/Platform/Models/PlatformAdmin.php)), tabla `platform_admins`, sin tienda ni roles. Un token de plataforma en `/api/v1/admin/*` da 403 (`tenant.user` exige `User`); un token de tienda en `/api/v1/platform/*` da 403 ([EnsurePlatformAdmin](backend/app/UI/Http/Middleware/EnsurePlatformAdmin.php)), aunque tenga habilidades `*`.
- **Login en dos pasos:** contraseña → token de **desafío** (5 min, habilidad `platform:challenge`, uno por admin) → `setup` (secreto nuevo + QR SVG) y `confirm` la primera vez, `verify` las siguientes (código TOTP o código de recuperación) → token de **sesión** (8 h, habilidad `platform`). El desafío se borra al usarse.
- **2FA:** TOTP RFC 6238 (`pragmarx/google2fa`, ±1 paso), secreto cifrado con `APP_KEY`, sin reutilizar códigos (`two_factor_last_step`, fila bloqueada al verificar). 8 códigos de recuperación de un solo uso, guardados como SHA-256 cifrado.
- **Throttle:** login 5/min por email+IP y 20/min por IP; `confirm`/`verify` 5/min por admin+IP. Mensaje genérico y mismo tiempo con email inexistente.
- **Auditoría** ([PlatformAuditLogger](backend/app/Application/Platform/Services/PlatformAuditLogger.php)): `login`, `login_failed`, `two_factor_enabled`, `two_factor_failed`, `recovery_code_used`, `logout`, y desde consola `admin_created`, `password_reset`, `two_factor_reset`.
- **Alta solo por consola:** `php artisan platform:admin <email> [--name=] [--reset-2fa]` (pide la contraseña oculta dos veces; resetear revoca sesiones). Seed de desarrollo: `admin@plataforma.test` / `password`, sin 2FA (el primer ingreso lo configura).
- **Frontend:** cookies httpOnly `platform_token` y `platform_challenge` (path `/platform`, `SameSite=Strict`); los códigos de recuperación se muestran en `/platform/recovery-codes` desde una cookie de 10 min limitada a esa ruta, que se borra al confirmar "Ya los guardé" (una Server Action que escribe cookies hace que Next vuelva a renderizar la página actual, y la del 2FA ya no tendría desafío).

---

## Tests

| Suite | Archivo | Casos |
|---|---|---|
| Unit | [CreateProductActionTest](backend/tests/Unit/Application/Product/CreateProductActionTest.php) | 7 |
| Feature | [ProductControllerTest](backend/tests/Feature/Api/Admin/ProductControllerTest.php) | 12 |
| Feature | [CategoryControllerTest](backend/tests/Feature/Api/Admin/CategoryControllerTest.php) | 11 |
| Feature | [StoreSettingsControllerTest](backend/tests/Feature/Api/Admin/StoreSettingsControllerTest.php) | 11 |
| Feature | [UploadControllerTest](backend/tests/Feature/Api/Admin/UploadControllerTest.php) | 5 |
| Feature | [UserControllerTest](backend/tests/Feature/Api/Admin/UserControllerTest.php) | 14 |
| Feature | [AuthControllerTest](backend/tests/Feature/Api/AuthControllerTest.php) | 7 |
| Feature | [PasswordChangeTest](backend/tests/Feature/Api/PasswordChangeTest.php) | 3 |
| Feature | [StorefrontControllerTest](backend/tests/Feature/Api/Storefront/StorefrontControllerTest.php) | 9 |
| Feature | [RevalidationTest](backend/tests/Feature/Storefront/RevalidationTest.php) | 7 |
| Feature | [PlatformAuthTest](backend/tests/Feature/Api/Platform/PlatformAuthTest.php) | 15 |
| Feature | [PlatformAdminCommandTest](backend/tests/Feature/Console/PlatformAdminCommandTest.php) | 5 |
| — | `ExampleTest` (Unit + Feature, del skeleton) | 2 |
| **Backend total** | | **108** |
| Frontend | [lib.test.ts](frontend/tests/lib.test.ts), [admin-forms.test.ts](frontend/tests/admin-forms.test.ts), [skeletons.test.ts](frontend/tests/skeletons.test.ts), [seo.test.ts](frontend/tests/seo.test.ts), [server-api.test.ts](frontend/tests/server-api.test.ts) | 51 |
| e2e | [admin.e2e.mjs](frontend/e2e/admin.e2e.mjs) (Playwright, navegador real) | 20 pasos |
| a11y | [a11y.e2e.mjs](frontend/e2e/a11y.e2e.mjs) (axe-core, WCAG 2.2 AA, corre en `bin/e2e.sh` después del e2e) | 27 pasos |
| Super panel | [platform.e2e.mjs](frontend/e2e/platform.e2e.mjs) (login, 2FA con TOTP calculado en Node, recuperación, separación de sesiones, axe) | 9 pasos |

Salvaguarda: [tests/TestCase.php](backend/tests/TestCase.php) aborta si la base no termina en `_test`, y [phpunit.xml](backend/phpunit.xml) fuerza `DB_*` en `<env>` y `<server>`.

---

## Cómo correrlo

```bash
cd backend
./bin/bootstrap.sh                              # solo la primera vez: skeleton + paquetes + migrate:fresh --seed
docker compose up -d                            # app :8000 · frontend :3000 · queue · pgsql :5432

# Backend
docker compose exec app ./vendor/bin/pest
docker compose exec app ./vendor/bin/phpstan analyse
docker compose exec app php artisan migrate:fresh --seed   # volver a los datos del seed

# Frontend
docker compose exec frontend npx vitest run
docker compose exec frontend npm run typecheck
```

- Tienda demo: http://demo.localhost:3000
- Panel: http://localhost:3000/admin — `owner@demo.test` / `password`
- e2e: `./bin/e2e.sh` (o `--keep` para dejarlo levantado). Stack aislado del perfil `e2e` en docker-compose (`app-e2e`, `frontend-e2e`, runner `e2e`) con base propia `catalogo_e2e`: parte del seed en cada corrida y **no toca la base de desarrollo**. Corre `admin.e2e.mjs` y después `a11y.e2e.mjs` (axe). Capturas y resultados en `frontend/e2e/output/` (`results.txt`, `a11y-results.txt`); sale con código 1 si falla un paso de cualquiera de los dos.

### Variables de entorno

| Variable | Dónde | Para qué | Estado en `.env.example` |
|---|---|---|---|
| `DB_*` | backend | Postgres | ✅ |
| `STOREFRONT_REVALIDATE_URL` / `_SECRET` | backend | revalidación ISR | ✅ |
| `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT`, `R2_PUBLIC_URL` | backend | uploads y URLs de imágenes | ✅ (vacías) |
| `API_URL` | frontend | URL interna de la API | ✅ |
| `ROOT_DOMAIN` | frontend | resolución de subdominios | ✅ |
| `REVALIDATE_SECRET` | frontend | igual a `STOREFRONT_REVALIDATE_SECRET` | ✅ |
| `IMAGE_HOSTS` | frontend | hosts de R2/CDN para `next/image` | ✅ (vacío) |
| `DEV_ORIGINS` | frontend | solo desarrollo | ✅ |

Importante: `php artisan serve` descarta las variables de entorno que también están en `.env`, por eso `backend/.env` apunta a Postgres explícitamente.

---

## Requisitos originales vs. estado real

| Requisito del pedido inicial | Estado |
|---|---|
| Clean Architecture, Repository, Actions, DTOs, Resources, Form Requests | [x] |
| Scope multi-tenant en toda consulta | [x] (4 capas) |
| Pest (Unit + Feature) | [x] |
| PHPStan nivel 8 | [x] |
| Productos, categorías, variantes (talle/color) | [x] |
| Configuración de tienda (logo, colores, WhatsApp) | [x] |
| Botón de WhatsApp con mensaje precargado desde el producto | [x] |
| ISR en catálogo y productos | [x] |
| Mobile-first, responsive | [x] |
| Paleta "Quiet Modern" + acento por tenant | [x] |
| Next.js 14/15 | [~] se usó **Next 16** (desvío no planificado, funciona) |
| shadcn/ui | [x] decidido: **no se usa por ahora** (componentes propios en Tailwind, sin widgets complejos; ver Decisiones) |
| TanStack Query | [x] decidido: **no se usa** (los datos se leen en el servidor y Next los cachea; el panel escribe con Server Actions) |
| Skeleton loaders | [x] `loading.tsx` en las 4 rutas del storefront y las 9 del panel; primitivas en [components/Skeleton.tsx](frontend/components/Skeleton.tsx) y [components/admin/skeletons.tsx](frontend/components/admin/skeletons.tsx) |
| Tipografía Inter o Geist | [x] Geist + Geist Mono con `next/font/google` en [app/layout.tsx](frontend/app/layout.tsx), expuestas a Tailwind como `font-sans`/`font-mono` |
| WCAG AA | [x] auditoría axe WCAG 2.2 AA automatizada en `bin/e2e.sh` (0 violaciones); skip link, `<main>`, foco visible, contraste y acento de marca legible |
| SEO | [x] `robots.txt` y `sitemap.xml` por tienda (por Host), canonical y Open Graph/Twitter con logo o foto del producto ([lib/seo.ts](frontend/lib/seo.ts)) |
| Hosting Hetzner + Vercel | [ ] sin configuración de deploy |

---

## Visión de producto (SaaS)

Definida el 2026-10-25. Las tiendas se crean solas, se cobran por plan con Mercado Pago y se administran desde un super panel central con acceso aparte. No cambia nada de lo hecho: es la próxima fase (ver [P2](#p2--saas-y-producto)).

### Registro público en 3 pasos
Referencia: vercatalogo.com/register. Ruta pública en el dominio raíz (ej. `/registro`).
1. **Acceso:** email, contraseña y confirmación (mismas reglas que el panel: ≥ 10 caracteres con letras y números).
2. **Tienda:** nombre, subdominio con chequeo de disponibilidad en vivo y slugs reservados (`www`, `api`, `admin`, `app`, `platform`…), WhatsApp opcional.
3. **Plan:** Gratis por defecto; un plan pago lleva al checkout de Mercado Pago.

Resultado: se crean tienda + dueño (rol `owner`) en una transacción y entra a su panel.

**Anti-abuso:** captcha **Cloudflare Turnstile** (decidido 2026-10-25 en vez de reCAPTCHA v3: gratis sin límite, sin cookies de seguimiento, mismo proveedor que R2) con el token **verificado en el backend** (`TURNSTILE_SITE_KEY` frontend, `TURNSTILE_SECRET_KEY` backend); además throttle por IP, campo trampa (honeypot) y verificación de email antes de publicar la tienda (requiere email transaccional).

### Planes y límites

Precios en ARS. Anual con **40 % de descuento** (decidido 2026-10-25): anual = mensual × 12 × 0,6.

| Plan | Productos | Mensual | 12 meses sin descuento | **Anual (−40 %)** | Equivale por mes | Ahorro anual |
|---|---|---|---|---|---|---|
| Gratis | hasta 25 | $0 | $0 | $0 | $0 | — |
| Basic | hasta 250 | $10.000 | $120.000 | **$72.000** | $6.000 | $48.000 |
| Intermediate | hasta 500 | $19.000 | $228.000 | **$136.800** | $11.400 | $91.200 |
| Advanced | hasta 1.000 | $36.000 | $432.000 | **$259.200** | $21.600 | $172.800 |
| Unlimited | ilimitados | $72.000 | $864.000 | **$518.400** | $43.200 | $345.600 |

- El anual se cobra en un solo pago por adelantado (en Mercado Pago, suscripción con frecuencia de 12 meses).
- `plans` guarda ambos precios en centavos; si cambia el mensual, el anual se recalcula con el mismo 40 %.
- Tabla `plans` (nombre, precio mensual/anual en centavos ARS, límites, flags de funciones como dominio propio) y `subscriptions` por tienda (plan, estado `active`/`past_due`/`canceled`, período actual, id de Mercado Pago).
- Los límites se exigen en las Actions (ej. crear producto por encima del límite → 422 con mensaje para subir de plan). El panel de la tienda muestra plan, uso y botón para cambiar.
- Referencia de límites y funciones: vercatalogo.com/#planes (el gratis incluye 500 visitas).

### Cobro con Mercado Pago
- Único medio de pago por ahora. Suscripciones recurrentes (preapproval de la API de Mercado Pago).
- Webhook con verificación de firma e idempotente → actualiza `subscriptions`.
- Variables: `MERCADOPAGO_ACCESS_TOKEN`, `MERCADOPAGO_PUBLIC_KEY`, `MERCADOPAGO_WEBHOOK_SECRET`. Probar en sandbox con usuarios de prueba.
- Falta de pago: días de gracia → suspensión automática (o pase a Gratis, a decidir) y aviso por email.

### Super panel central (acceso aparte)
- **Identidad separada:** tabla `platform_admins` con su propio guard de Sanctum; un usuario de tienda nunca puede volverse super admin (ni por `store_id` nulo ni por un bug). Reemplaza la idea "`users.store_id` null = super admin".
- **Login y rutas propios:** `/platform/login` (cookie distinta de `admin_token`) y API `/api/v1/platform/*` con middleware propio.
- **Seguridad:** 2FA obligatoria (TOTP), throttle más estricto, auditoría de cada acción (quién, qué tienda, cuándo). Sin alta pública: los super admins se crean solo por consola (`php artisan platform:admin`).
- **Funciones:** listar tiendas (estado, plan, vencimiento, uso, dueño); crear tienda a mano (dueño con contraseña temporal); suspender/reactivar (revalidando el ISR para que deje de verse al instante); borrar (soft delete y purga diferida de datos y objetos de R2); ver estado de pago. Futuro: entrar como la tienda para soporte.

### Decisiones pendientes
- Límites además de productos (fotos por producto, usuarios, visitas, funciones de cada plan), prueba gratuita sí/no.
- Falta de pago: suspender o pasar a Gratis; cuántos días de gracia.
- ¿Se publica la tienda antes de verificar el email?
- Retención de datos de una tienda borrada.
- Dominio del super panel: ruta en el dominio raíz o subdominio propio (`admin.dominio.com`).
- Proveedor de email transaccional (prerrequisito de verificación, recuperación y avisos de cobro).
- Facturación electrónica (AFIP/ARCA) de las suscripciones: fuera de alcance por ahora.

---

## Decisiones y deuda técnica

- **DDD pragmático:** los modelos Eloquent viven en Domain como aggregates, y los contratos de repositorio reciben DTOs de Application (Domain depende de una capa externa). Corregible moviendo los DTOs a Domain.
- **Variantes en el `PUT`:** se borran y recrean (cambian los IDs). Hay que pasar a un sync que conserve IDs antes del carrito.
- **Slug explícito repetido:** se le agrega sufijo en vez de 422.
- **SKUs:** sin normalizar mayúsculas/espacios. Con soft delete del producto, sus variantes retienen los SKUs.
- **R2:** objetos huérfanos (subidos sin asociar, o reemplazados) no se limpian. El HEAD de validación se repite por imagen en cada guardado (≤ 20).
- **Storefront:** home y categorías muestran hasta 60 productos (sin paginación en páginas cacheadas); una categoría no incluye productos de sus subcategorías; `custom_domain` no soportado (solo `X-Store-Slug`).
- **UI y datos en el cliente (2026-10-25):** no se usan shadcn/ui ni TanStack Query. *TanStack Query* es caché de datos en el navegador; acá todo se lee en Server Components (`lib/api.ts`, `lib/admin/api.ts`) con la caché de Next y el panel escribe con Server Actions, así que duplicaría la caché u obligaría a exponer la API/token al navegador (hoy en cookie httpOnly del lado del servidor). *shadcn/ui* aporta sobre todo widgets complejos accesibles (Dialog, DropdownMenu, Toast, Combobox) que hoy no existen; los inputs/botones propios (`components/admin/ui.tsx`) ya pasan axe. **Revisar si:** aparece una pantalla con datos en vivo, scroll infinito o actualización optimista (→ TanStack Query); hace falta el primer diálogo, menú, toast o combobox (→ adoptar shadcn de a un componente, empezando por un `AlertDialog` que reemplace los `window.confirm` de `UsersManager` y `DeleteButton`, ajustando los `page.once('dialog')` del e2e).
- **Accesibilidad:** la auditoría usa axe-core (`@axe-core/playwright`). Lighthouse no se agregó: su categoría de accesibilidad corre el mismo motor; queda como chequeo manual opcional desde DevTools. Se excluye solo el indicador del dev server de Next (`nextjs-portal`). Hallazgos corregidos: contraste de `text-zinc-400` (precios tachados, "Sin imagen", checklist, "Sin logo"), input de archivo sin etiqueta (ahora `hidden`, se abre con el botón), sin skip link en tienda y panel. Además: `<main id="contenido">` en la tienda, foco visible global (`:focus-visible` en `@layer base`), `--accent-text`/`--accent-fg` para que un acento claro elegido por la tienda no rompa el contraste, textos para lector de pantalla en variantes sin stock, ítems hechos y link que abre otra pestaña. axe no cubre todo: falta una pasada manual con lector de pantalla (NVDA/VoiceOver).
- **SEO:** `app/robots.ts` y `app/sitemap.ts` están en la raíz (el proxy no reescribe rutas con punto) y eligen la tienda por el `Host`. URLs absolutas con `STORE_ORIGIN_TEMPLATE` (vacío = `https://{store}.ROOT_DOMAIN`); el canonical apunta siempre al subdominio, también si se entra por `/s/...`. Sitemap sin `lastModified` (la API no expone `updated_at`) y con tope de 3000 productos. Pendiente: JSON-LD `Product`, imagen OG generada (`opengraph-image`), canonical con dominio propio cuando se enruten.
- **Fuentes:** `next/font/google` descarga Geist **en el build** (CI, Docker y `next dev` necesitan salida a fonts.googleapis.com/fonts.gstatic.com). Para buildear sin red: paquete `geist` + `next/font/local`.
- **Panel:** vive en el dominio raíz `/admin`; para `admin.dominio.com` hay que ajustar `proxy.ts`.
- **Soft-404 por `loading.tsx`:** un producto o categoría inexistente responde 200 + `<meta name="robots" content="noindex">` (el `notFound()` de la página llega después de que el skeleton empezó el stream). Una tienda inexistente sigue siendo 404 real (el `notFound()` está en el layout). Mitigación futura: validar el slug en `proxy.ts`.

## Bugs encontrados y corregidos (historial)

- **Los tests de Pest borraban la base de desarrollo:** Laravel lee `env()` de `$_SERVER` antes que de `$_ENV`. Corregido con `<server force>` en phpunit.xml + chequeo en `TestCase`.
- `bootstrap.sh`: `cp -rn` de busybox → `tar -k`; faltaba `allow-plugins` de Pest.
- El servidor web usaba SQLite pese a `DB_*` en compose (ver nota de `php artisan serve`).
- Panel: `crypto.randomUUID` fallaba en http:// no seguro; los 5xx mostraban el mensaje interno; el login vaciaba el email tras un error.
- Panel: una respuesta 2xx sin JSON válido (ej. un error de PHP impreso como HTML) se trataba como éxito y el login explotaba con 500. Ahora [lib/admin/api.ts](frontend/lib/admin/api.ts) la convierte en error genérico.

- **Los tests llamaban de verdad al frontend** (detectado por el primer CI en GitHub): heredaban `STOREFRONT_REVALIDATE_URL` de `.env` y cada alta/edición hacía un POST real a `http://frontend:3000`. En local pasaba porque ese contenedor existe; en CI fallaban 11 tests con `Could not resolve host: frontend`. Ahora `phpunit.xml` fuerza la URL vacía y [tests/TestCase.php](backend/tests/TestCase.php) activa `Http::preventStrayRequests()` (cualquier HTTP real sin `Http::fake` hace fallar el test).

Nota operativa: si el frontend de desarrollo está apagado, el servicio `queue` reintenta las revalidaciones pendientes y registra `Job timed out`; se normaliza al levantar `frontend`.

---

## Backlog priorizado

### P0 — antes de seguir desarrollando
- [x] `.gitignore` revisados y endurecidos (raíz, backend, frontend) + `frontend/.dockerignore` sin secretos
- [x] `git init -b main` en la raíz (verificado: `.env`, logs, sqlite, `vendor`, `node_modules` y `.next` ignorados; 270 archivos a rastrear, sin claves)
- [x] Identidad de git (solo este repo) y primer commit
- [x] Repositorio **privado** en GitHub con autenticación SSH y push de `main`
- [x] Variables `R2_*` en `backend/.env.example` (reemplazan el bloque `AWS_*` que no se usaba)
- [x] Base separada para el e2e: perfil `e2e` + [bin/e2e.sh](backend/bin/e2e.sh). Verificado: 20/20 pasos OK y la base de desarrollo sin cambios
- [x] CI en [.github/workflows/ci.yml](.github/workflows/ci.yml) (backend: Postgres 16 + Pest + PHPStan; frontend: typecheck + Vitest + build), **en verde en GitHub**. El e2e no está incluido (se corre a mano con `bin/e2e.sh`)

### P1 — completar el MVP según el pedido original
- [x] Skeleton loaders (`loading.tsx`) en storefront y panel: Tailwind puro (`animate-pulse`, se detiene con `prefers-reduced-motion`), `role="status"` + texto sr-only, misma grilla/bordes que el contenido real para no saltar el layout
- [x] Fuente Geist (y Geist Mono) con `next/font`: autoalojada, sin pedidos a Google desde el navegador
- [x] SEO: `sitemap.xml` y `robots.txt` por tienda, Open Graph con logo/imagen del producto
- [x] Auditoría WCAG AA (axe + Lighthouse) y corrección de hallazgos
- [x] Decidir shadcn/ui y TanStack Query: adoptarlos o registrar formalmente que no se usan (decidido: ninguno por ahora)
- [x] Probar contra R2 real: configurar `R2_*`, `IMAGE_HOSTS` y CORS del bucket (`PUT` desde el dominio del admin). Probado en dev con la Public Development URL (`r2.dev`); el e2e corre sin R2
- [ ] Deploy: Hetzner (API, Postgres, worker con supervisor, backups), Vercel (frontend), DNS wildcard `*.dominio.com`, variables de producción. **Se hace al final, después de completar P2** (decisión 2026-10-25)

### P2 — SaaS y producto
En este orden (ver [Visión de producto](#visión-de-producto-saas)):
1. **Super panel, base** (primero porque sirve para operar todo lo demás y no depende de pagos), en dos entregas:
   - [x] **1a. Acceso:** `platform_admins`, login aparte en `/platform` con 2FA obligatoria (TOTP + códigos de recuperación), alta por consola, auditoría y panel base
   - [ ] **1b. Gestión de tiendas:** listado, alta manual (tienda + dueño), suspender/reactivar (con revalidación ISR), borrar (soft delete), auditoría de cada acción; reservar el slug `platform`
2. [ ] **Planes y límites:** `plans` + `subscriptions`, límites aplicados en las Actions, plan y uso visibles en el panel. Las tiendas existentes quedan en Gratis.
3. [ ] **Email transaccional:** recuperación de contraseña, verificación de email e invitaciones (requiere configurar mail).
4. [ ] **Registro público en 3 pasos** con Turnstile y plan Gratis.
5. [ ] **Mercado Pago:** checkout de planes pagos, webhook, cambio de plan, suspensión automática por falta de pago.
6. Resto:
   - [ ] Sync de variantes que conserve IDs (prerequisito del carrito)
   - [ ] Paginación en home/categorías y productos de subcategorías
   - [ ] Dominios propios (`custom_domain`), como función de plan
   - [ ] Limpieza de objetos huérfanos en R2 (job o lifecycle rule)
   - [ ] 2FA y registro de auditoría para usuarios de tienda (para la plataforma va en el punto 1)
   - [ ] Borrar la carpeta vacía `app/Domain/Category/Events`

### Futuro
- [ ] E-commerce completo: carrito, checkout, órdenes, pagos, stock transaccional

---

## Registro de sesiones

| Fecha | Qué se hizo |
|---|---|
| 2026-10-23 | Módulos Stores + Products (6 entregables iniciales), luego verificación en Docker, storefront API, revalidación ISR, CRUD de categorías, uploads a R2, configuración de tienda, frontend storefront, auth + panel admin, gestión de usuarios. Revisión del HANDOFF contra el código y reorganización de este archivo. Revisión de `.gitignore` por seguridad y `git init` local (sin commit). |
| 2026-10-24 | P0: variables R2 en `.env.example`, e2e aislado con base `catalogo_e2e` (20/20 OK, base de desarrollo intacta), CI de GitHub Actions escrito, bug del panel con respuestas 2xx sin JSON corregido. Identidad de git, primer commit y push a GitHub privado; el primer CI detectó que los tests llamaban al frontend real (corregido) y quedó en verde. **P0 completo.** |
| 2026-10-25 | P1: skeleton loaders en storefront (home, categoría, producto, búsqueda) y panel (dashboard, listados y formularios), accesibles; 5 tests nuevos de Vitest (38/38), build y e2e 20/20 OK. Rama `P1_skeleton_loaders`. P1: fuente Geist + Geist Mono con `next/font` (rama `P1_fuente_inter_geist_next_font`); typecheck, Vitest 38/38, build y e2e 20/20 OK. P1: SEO (robots, sitemap, canonical, Open Graph) en rama `P1_SEO_sitemap_robots_open_graph`; Vitest 43/43, build, curl y e2e 20/20 OK. P1: auditoría WCAG 2.2 AA con axe integrada al e2e (rama `P1_auditoria_WCAG`): primera corrida con violaciones de contraste, label y skip link; corregidas → a11y 27/27, e2e 20/20, Vitest 45/45, build OK. P1: decisión registrada: ni shadcn/ui ni TanStack Query por ahora (motivos y disparadores en Decisiones). P1: prueba contra R2 real (rama `P1_probar_contra_R2_real`): credenciales, URL pública r2.dev, CORS, subida de imagen de producto y logo desde el panel y visualización en la tienda vía `next/image`, todo OK; objetos y productos de prueba borrados. `IMAGE_HOSTS` en `frontend/.env.local`, e2e aislado de R2, login del e2e de a11y tolerante al throttle (fallaba en corridas rápidas); e2e 47/47 OK. Visión de producto SaaS registrada (registro en 3 pasos con Turnstile, planes en ARS, Mercado Pago, super panel con acceso aparte) y P2 reordenado; el deploy pasa al final. Plan anual con 40 % de descuento decidido, con precios por plan. |
| 2026-10-26 | P2.1a: acceso al super panel (rama `P2_alta_tiendas_y_panel_super_admin`): tabla `platform_admins` y `platform_audit_logs`, login en dos pasos con token de desafío, 2FA TOTP obligatoria con QR y 8 códigos de recuperación, throttle propio, comando `platform:admin`, páginas `/platform/*` con cookies propias y cliente HTTP común (`lib/server-api.ts`). Arreglos: reintento del login en a11y.e2e (el aviso viejo confundía la espera) y botón de cerrar sesión tapado por el indicador de Next en dev. Pest 108/108, PHPStan OK, Vitest 51/51, build OK, e2e 20 + 27 + 9 OK. |
