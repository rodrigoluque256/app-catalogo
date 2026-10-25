# HANDOFF — Catálogo SaaS Multi-tenant

> Archivo de seguimiento. Actualizar al cerrar cada sesión de trabajo (sección [Registro de sesiones](#registro-de-sesiones)).

## Estado general

| | |
|---|---|
| Última actualización | 2026-10-25 |
| Fase | **MVP funcional de punta a punta**: API admin + storefront + panel de administración + revalidación ISR |
| Backend | Laravel 13 · PHP 8.4 · PostgreSQL 16 · Sanctum · Spatie Permission (teams) · Pest · Larastan nivel 8 |
| Frontend | Next.js 16 · React 19 · Tailwind 4 · Vitest · Playwright (e2e) |
| Tenancy | Global Scope custom, una sola base, columna `store_id` |
| Entorno local | Docker Compose ([backend/docker-compose.yml](backend/docker-compose.yml)): `app`, `queue`, `frontend`, `pgsql` |
| Control de versiones | GitHub privado [`rodrigoluque256/app-catalogo`](https://github.com/rodrigoluque256/app-catalogo) (rama `main`, SSH). Identidad local del repo: Rodrigo Luque &lt;rodrigoluque256@gmail.com&gt; |
| CI | GitHub Actions en verde (backend + frontend) desde el commit `6b7aae2` |

**Última verificación (2026-10-23):**

| Chequeo | Resultado |
|---|---|
| `migrate:fresh --seed` | OK |
| Pest (backend) | 88/88 (374 assertions) |
| PHPStan nivel 8 | sin errores |
| Vitest (frontend) | 38/38 |
| e2e de navegador del panel (Playwright, 20 pasos) | OK |
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

Rutas: [backend/routes/api.php](backend/routes/api.php). Middleware del grupo admin: `auth:sanctum`, `tenant.user`, `password.changed`.

Roles (globales, asignados por tienda): **owner** = todos los permisos · **editor** = `products.manage`. Seeder: [RolesAndPermissionsSeeder](backend/database/seeders/RolesAndPermissionsSeeder.php). En bases existentes, después de agregar permisos: `php artisan db:seed --class=RolesAndPermissionsSeeder`.

### Frontend — `frontend/`

| Área | Rutas | Notas |
|---|---|---|
| Storefront | `/s/[store]` (inicio con destacados), `/c/[slug]`, `/p/[slug]`, `/buscar` | Se reescribe desde `{slug}.dominio` vía [proxy.ts](frontend/proxy.ts). ISR con tags; `/buscar` sin caché. Tema por tienda con contraste automático. Botón de WhatsApp (`wa.me`) con mensaje precargado desde el producto/variante. |
| Panel admin | `/admin/login`, `/admin`, `/admin/products[/new\|/{id}]`, `/admin/categories[/new\|/{id}]`, `/admin/settings`, `/admin/users`, `/admin/account` | Sesión con token Sanctum guardado del lado servidor ([lib/admin/session.ts](frontend/lib/admin/session.ts)). Primer ingreso forzado a cambiar contraseña. |
| Revalidación | `POST /api/revalidate` | Valida `x-revalidate-secret` y llama `revalidateTag` por cada tag. |

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
| [000002](backend/database/migrations/2026_10_23_000002_add_store_id_to_users_table.php) | `users` | `store_id` nullable (null = super admin) |
| [000003](backend/database/migrations/2026_10_23_000003_create_categories_table.php) | `categories` | árbol `parent_id` en la misma tienda (FK compuesta), slug único por tienda |
| [000004](backend/database/migrations/2026_10_23_000004_create_products_table.php) | `products` | precio en centavos, FK compuesta a categoría, slug/SKU únicos por tienda, `search_vector` full-text español + GIN |
| [000005](backend/database/migrations/2026_10_23_000005_create_product_variants_table.php) | `product_variants` | `options` jsonb + GIN, precio y stock opcionales (null = hereda / sin control) |
| [000006](backend/database/migrations/2026_10_23_000006_create_product_images_table.php) | `product_images` | key de R2, una sola principal por producto, `variant_id` opcional |
| [000007](backend/database/migrations/2026_10_23_000007_create_permission_tables.php) | Spatie | `team_id` UUID (team = store) |
| [000008](backend/database/migrations/2026_10_23_000008_add_must_change_password_to_users_table.php) | `users` | `must_change_password` |
| [190444](backend/database/migrations/2026_10_23_190444_create_personal_access_tokens_table.php) | Sanctum | tokens de API |

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

### Errores
Errores de dominio → 422 con el mismo formato que la validación de Laravel. Recurso inexistente o de otra tienda → 404. 5xx sin mensaje interno. Ver [bootstrap/app.php](backend/bootstrap/app.php).

### Usuarios y contraseñas
Alta con contraseña temporal aleatoria (14 caracteres, se muestra una sola vez) → `must_change_password`; el middleware `password.changed` bloquea la API admin con 403 `password_change_required` hasta cambiarla. No se puede eliminar ni cambiar el rol de uno mismo; la tienda conserva siempre al menos un dueño. Email único en toda la plataforma. Borrar o resetear revoca tokens. Contraseñas: ≥ 10 caracteres con letras y números. Login: token de 12 h, mensaje genérico, throttle 5/min por email+IP.

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
| — | `ExampleTest` (Unit + Feature, del skeleton) | 2 |
| **Backend total** | | **88** |
| Frontend | [lib.test.ts](frontend/tests/lib.test.ts), [admin-forms.test.ts](frontend/tests/admin-forms.test.ts), [skeletons.test.ts](frontend/tests/skeletons.test.ts) | 38 |
| e2e | [admin.e2e.mjs](frontend/e2e/admin.e2e.mjs) (Playwright, navegador real) | 20 pasos |

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
- e2e: `./bin/e2e.sh` (o `--keep` para dejarlo levantado). Stack aislado del perfil `e2e` en docker-compose (`app-e2e`, `frontend-e2e`, runner `e2e`) con base propia `catalogo_e2e`: parte del seed en cada corrida y **no toca la base de desarrollo**. Capturas y resultados en `frontend/e2e/output/`; sale con código 1 si falla un paso.

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
| shadcn/ui | [ ] **no instalado**, componentes propios |
| TanStack Query | [ ] **no instalado** (el panel usa Server Actions) |
| Skeleton loaders | [x] `loading.tsx` en las 4 rutas del storefront y las 9 del panel; primitivas en [components/Skeleton.tsx](frontend/components/Skeleton.tsx) y [components/admin/skeletons.tsx](frontend/components/admin/skeletons.tsx) |
| Tipografía Inter o Geist | [x] Geist + Geist Mono con `next/font/google` en [app/layout.tsx](frontend/app/layout.tsx), expuestas a Tailwind como `font-sans`/`font-mono` |
| WCAG AA | [~] hay `aria-*` y contraste automático, **sin auditoría** (axe/Lighthouse) |
| SEO | [~] `generateMetadata` en páginas; **faltan `sitemap`, `robots`, Open Graph por tienda** |
| Hosting Hetzner + Vercel | [ ] sin configuración de deploy |

---

## Decisiones y deuda técnica

- **DDD pragmático:** los modelos Eloquent viven en Domain como aggregates, y los contratos de repositorio reciben DTOs de Application (Domain depende de una capa externa). Corregible moviendo los DTOs a Domain.
- **Variantes en el `PUT`:** se borran y recrean (cambian los IDs). Hay que pasar a un sync que conserve IDs antes del carrito.
- **Slug explícito repetido:** se le agrega sufijo en vez de 422.
- **SKUs:** sin normalizar mayúsculas/espacios. Con soft delete del producto, sus variantes retienen los SKUs.
- **R2:** objetos huérfanos (subidos sin asociar, o reemplazados) no se limpian. El HEAD de validación se repite por imagen en cada guardado (≤ 20).
- **Storefront:** home y categorías muestran hasta 60 productos (sin paginación en páginas cacheadas); una categoría no incluye productos de sus subcategorías; `custom_domain` no soportado (solo `X-Store-Slug`).
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
- [ ] SEO: `sitemap.xml` y `robots.txt` por tienda, Open Graph con logo/imagen del producto
- [ ] Auditoría WCAG AA (axe + Lighthouse) y corrección de hallazgos
- [ ] Decidir shadcn/ui y TanStack Query: adoptarlos o registrar formalmente que no se usan
- [ ] Probar contra R2 real: configurar `R2_*`, `IMAGE_HOSTS` y CORS del bucket (`PUT` desde el dominio del admin)
- [ ] Deploy: Hetzner (API, Postgres, worker con supervisor, backups), Vercel (frontend), DNS wildcard `*.dominio.com`, variables de producción

### P2 — producto
- [ ] Alta de tiendas (hoy solo por seed/consola) y panel de super admin
- [ ] Recuperación de contraseña por email / invitaciones (requiere configurar mail)
- [ ] Sync de variantes que conserve IDs (prerequisito del carrito)
- [ ] Paginación en home/categorías y productos de subcategorías
- [ ] Dominios propios (`custom_domain`)
- [ ] Limpieza de objetos huérfanos en R2 (job o lifecycle rule)
- [ ] 2FA y registro de auditoría
- [ ] Borrar la carpeta vacía `app/Domain/Category/Events`

### Futuro
- [ ] E-commerce completo: carrito, checkout, órdenes, pagos, stock transaccional

---

## Registro de sesiones

| Fecha | Qué se hizo |
|---|---|
| 2026-10-23 | Módulos Stores + Products (6 entregables iniciales), luego verificación en Docker, storefront API, revalidación ISR, CRUD de categorías, uploads a R2, configuración de tienda, frontend storefront, auth + panel admin, gestión de usuarios. Revisión del HANDOFF contra el código y reorganización de este archivo. Revisión de `.gitignore` por seguridad y `git init` local (sin commit). |
| 2026-10-24 | P0: variables R2 en `.env.example`, e2e aislado con base `catalogo_e2e` (20/20 OK, base de desarrollo intacta), CI de GitHub Actions escrito, bug del panel con respuestas 2xx sin JSON corregido. Identidad de git, primer commit y push a GitHub privado; el primer CI detectó que los tests llamaban al frontend real (corregido) y quedó en verde. **P0 completo.** |
| 2026-10-25 | P1: skeleton loaders en storefront (home, categoría, producto, búsqueda) y panel (dashboard, listados y formularios), accesibles; 5 tests nuevos de Vitest (38/38), build y e2e 20/20 OK. Rama `P1_skeleton_loaders`. P1: fuente Geist + Geist Mono con `next/font` (rama `P1_fuente_inter_geist_next_font`); typecheck, Vitest 38/38, build y e2e 20/20 OK. |
