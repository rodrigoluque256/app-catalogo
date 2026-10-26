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

**Última verificación (2026-10-26, P2.3):**

| Chequeo | Resultado |
|---|---|
| `migrate:fresh --seed` | OK |
| Pest (backend) | 143/143 (669 assertions) |
| PHPStan nivel 8 | sin errores |
| Vitest (frontend) | 57/57 |
| e2e de navegador del panel (Playwright, 21 pasos; invitación, olvidé mi contraseña y link del dueño leídos desde Mailpit) | OK |
| Auditoría WCAG 2.2 AA con axe (tienda escritorio/móvil + panel, 31 pasos) | OK, 0 violaciones |
| e2e del super panel: login + 2FA + recuperación + gestión de tiendas + cambio de plan, con axe (15 pasos) | OK |
| Prueba real en dev: crear → suspender (404) → reactivar (200) → borrar (404) con la cola y el ISR de verdad | OK |
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
| Super panel: tiendas | `GET/POST /api/v1/platform/stores`, `GET/PATCH/DELETE /platform/stores/{id}`, `POST /platform/stores/{id}/restore`, `PATCH /platform/stores/{id}/plan` | `platform.admin` | 9 |
| Planes | `GET /api/v1/plans` (**público**, throttle 60/min), `GET /api/v1/admin/plan` (plan, suscripción y uso); `auth/me` incluye `plan` | público / `store.manage` | 12 |
| Accesos por email | `POST /api/v1/auth/forgot-password`, `POST /auth/reset-password`, `GET /auth/invitation`, `POST /auth/invitation/accept`; `POST /admin/users/{id}/reset-password` reenvía invitación o manda link; `POST /platform/stores/{id}/resend-invitation` | público / `users.manage` / `platform.admin` | 12 + ajustes |

Rutas: [backend/routes/api.php](backend/routes/api.php). Middleware del grupo admin: `auth:sanctum`, `tenant.user`, `password.changed`. Super panel: `auth:sanctum`, `platform.admin`, `abilities:platform` (o `abilities:platform:challenge` en el 2FA).

Roles (globales, asignados por tienda): **owner** = todos los permisos · **editor** = `products.manage`. Seeder: [RolesAndPermissionsSeeder](backend/database/seeders/RolesAndPermissionsSeeder.php). En bases existentes, después de agregar permisos: `php artisan db:seed --class=RolesAndPermissionsSeeder`.

### Frontend — `frontend/`

| Área | Rutas | Notas |
|---|---|---|
| Storefront | `/s/[store]` (inicio con destacados), `/c/[slug]`, `/p/[slug]`, `/buscar` | Se reescribe desde `{slug}.dominio` vía [proxy.ts](frontend/proxy.ts). ISR con tags; `/buscar` sin caché. Tema por tienda con contraste automático. Botón de WhatsApp (`wa.me`) con mensaje precargado desde el producto/variante. |
| Panel admin | `/admin/login`, `/admin/recuperar`, `/admin/restablecer`, `/admin/invitacion`, `/admin`, `/admin/products[/new\|/{id}]`, `/admin/categories[/new\|/{id}]`, `/admin/settings`, `/admin/plan`, `/admin/users`, `/admin/account` | Sesión con token Sanctum guardado del lado servidor ([lib/admin/session.ts](frontend/lib/admin/session.ts)). Primer ingreso forzado a cambiar contraseña. |
| Revalidación | `POST /api/revalidate` | Valida `x-revalidate-secret` y llama `revalidateTag` por cada tag. |
| Super panel | `/platform/login`, `/platform/two-factor`, `/platform/recovery-codes`, `/platform` (contadores), `/platform/stores[/new\|/{id}]` | Cookies propias limitadas a `/platform` ([lib/platform/session.ts](frontend/lib/platform/session.ts)). Listado con búsqueda y filtro por estado, alta con subdominio sugerido ([lib/slug.ts](frontend/lib/slug.ts)) y contraseña temporal del dueño, detalle con suspender/reactivar, borrar (escribiendo el subdominio) y restaurar. Cliente HTTP común de ambos paneles: [lib/server-api.ts](frontend/lib/server-api.ts). |

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
| [2026_10_26_000003](backend/database/migrations/2026_10_26_000003_create_plans_table.php) | `plans` | **inserta los 5 planes** (datos de referencia: llegan a producción con `migrate`); precios en centavos ARS, `max_products` null = sin límite |
| [2026_10_26_000005](backend/database/migrations/2026_10_26_000005_add_invitation_accepted_at_to_users_table.php) | `users` | `invitation_accepted_at` (null = invitación pendiente; los existentes quedan aceptados) |
| [2026_10_26_000004](backend/database/migrations/2026_10_26_000004_create_subscriptions_table.php) | `subscriptions` | una por tienda (`store_id` único); **backfill: las tiendas existentes quedan en Gratis**; `mercadopago_preapproval_id` reservado para P2.6 |

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
**Altas por invitación** (desde 2026-10-26, P2.3): el usuario creado por el dueño (o el dueño de una tienda creada desde el super panel) recibe un email con un link de 72 h para elegir su contraseña; hasta aceptar, su contraseña es aleatoria y no puede entrar (`invitation_accepted_at` null, "Invitación pendiente" en el panel). Ya no se muestran contraseñas temporales: `must_change_password` queda solo para usuarios viejos que tenían una. No se puede eliminar ni cambiar el rol de uno mismo; la tienda conserva siempre al menos un dueño. Email único en toda la plataforma. Borrar o enviar link de contraseña revoca tokens. Contraseñas: ≥ 10 caracteres con letras y números (mensajes en español en [PasswordMessages](backend/app/UI/Http/Requests/Auth/PasswordMessages.php)). Login: token de 12 h, mensaje genérico, throttle 5/min por email+IP.

### Super panel: acceso
- **Identidad:** `PlatformAdmin` ([modelo](backend/app/Domain/Platform/Models/PlatformAdmin.php)), tabla `platform_admins`, sin tienda ni roles. Un token de plataforma en `/api/v1/admin/*` da 403 (`tenant.user` exige `User`); un token de tienda en `/api/v1/platform/*` da 403 ([EnsurePlatformAdmin](backend/app/UI/Http/Middleware/EnsurePlatformAdmin.php)), aunque tenga habilidades `*`.
- **Login en dos pasos:** contraseña → token de **desafío** (5 min, habilidad `platform:challenge`, uno por admin) → `setup` (secreto nuevo + QR SVG) y `confirm` la primera vez, `verify` las siguientes (código TOTP o código de recuperación) → token de **sesión** (8 h, habilidad `platform`). El desafío se borra al usarse.
- **2FA:** TOTP RFC 6238 (`pragmarx/google2fa`, ±1 paso), secreto cifrado con `APP_KEY`, sin reutilizar códigos (`two_factor_last_step`, fila bloqueada al verificar). 8 códigos de recuperación de un solo uso, guardados como SHA-256 cifrado.
- **Throttle:** login 5/min por email+IP y 20/min por IP; `confirm`/`verify` 5/min por admin+IP. Mensaje genérico y mismo tiempo con email inexistente.
- **Auditoría** ([PlatformAuditLogger](backend/app/Application/Platform/Services/PlatformAuditLogger.php)): `login`, `login_failed`, `two_factor_enabled`, `two_factor_failed`, `recovery_code_used`, `logout`, y desde consola `admin_created`, `password_reset`, `two_factor_reset`.
- **Alta solo por consola:** `php artisan platform:admin <email> [--name=] [--reset-2fa]` (pide la contraseña oculta dos veces; resetear revoca sesiones). Seed de desarrollo: `admin@plataforma.test` / `password`, sin 2FA (el primer ingreso lo configura).
- **Frontend:** cookies httpOnly `platform_token` y `platform_challenge` (path `/platform`, `SameSite=Strict`); los códigos de recuperación se muestran en `/platform/recovery-codes` desde una cookie de 10 min limitada a esa ruta, que se borra al confirmar "Ya los guardé" (una Server Action que escribe cookies hace que Next vuelva a renderizar la página actual, y la del 2FA ya no tendría desafío).

### Super panel: gestión de tiendas
- **Listado** ([EloquentPlatformStoreRepository](backend/app/Infrastructure/Persistence/Eloquent/EloquentPlatformStoreRepository.php)): todas las tiendas sin tenant; `status` = `active`/`suspended` (por defecto, ambas) o `deleted` (solo borradas); búsqueda por nombre, slug o email del dueño; dueños vía `Store::owners()` (consulta directa a `model_has_roles` con `team_id = users.store_id`, porque `roles()` de Spatie filtra por el team del request); conteo de productos sin el `StoreScope`.
- **Alta** ([CreateStoreAction](backend/app/Application/Platform/Actions/Stores/CreateStoreAction.php)): tienda + dueño (`owner`) en una transacción, reutilizando `CreateUserAction` dentro de `TenantContext::runAs()` + `setPermissionsTeamId()`. Contraseña temporal devuelta una sola vez. Slug: formato DNS, 3–63, único **también contra borradas** y no reservado ([ReservedSlugs](backend/app/Domain/Store/ReservedSlugs.php): `www`, `api`, `admin`, `platform`, `s`, `registro`…).
- **Suspender / reactivar:** `PATCH {status}`; idempotente; suspender revoca los tokens de los usuarios de la tienda. **Borrar:** soft delete con `confirm_slug` obligatorio (422 si no coincide), revoca tokens; el slug sigue ocupado. **Restaurar:** vuelve como **suspendida** (se reactiva a propósito). Una tienda borrada no cambia de estado hasta restaurarla.
- **ISR:** [StoreAvailabilityChanged](backend/app/Domain/Store/Events/StoreAvailabilityChanged.php) implementa `RevalidatesRegardlessOfStatus` (trae el slug): `RevalidateStorefront` revalida `store:{slug}` aunque la tienda ya no esté activa. Como el layout del storefront depende de ese tag, toda la tienda pasa a 404 (o vuelve) en el próximo pedido. Los demás eventos siguen ignorando tiendas inactivas. Ojo: los eventos que revalidan se registran en una lista explícita en [DomainServiceProvider](backend/app/Infrastructure/Providers/DomainServiceProvider.php).
- **Auditoría:** `store_created` (con email del dueño), `store_suspended`, `store_reactivated`, `store_deleted`, `store_restored`, con `store_id` y `{slug}`.

### Planes y límites
- **Límites** (confirmados 2026-10-26): Gratis 25 productos / 3 fotos / 1 usuario · Basic 250 / 5 / 2 · Intermediate 500 / 10 / 3 · Advanced 1.000 / 15 / 5 · Unlimited sin límite / 20 / 20. Sin prueba gratuita (el Gratis cumple ese papel). Topes técnicos que siguen: 20 fotos y 100 variantes por producto.
- **Dónde se aplican** ([PlanLimits](backend/app/Application/Billing/Services/PlanLimits.php), detrás de la interfaz `PlanLimitGuard`): `CreateProductAction` (productos no borrados + fotos), `UpdateProductAction` (fotos, solo si vienen `images`), `CreateUserAction` (usuarios; también el dueño creado desde el super panel). Error: 422 con mensaje para subir de plan (`errors.plan` o `errors.images`).
- **Bajar de plan no borra nada:** solo se frena lo que se agrega. Las fotos se comparan contra las que el producto ya tiene (`new <= límite` o `new <= actuales`).
- **Fallback:** una tienda sin suscripción se trata como **Gratis** (falla cerrado).
- **Altas:** `Subscriptions::startFree()` en el alta desde el super panel (y en el registro, P2.5). El cambio de plan es **manual desde el super panel** (`ChangeStorePlanAction`, auditado como `plan_changed` con `from`/`to`/`billing_cycle`) hasta Mercado Pago (P2.6). `billing_cycle` es obligatorio en planes pagos y null en Gratis.
- **Tienda demo:** el seeder la crea en **Intermediate** (3 usuarios: el e2e crea y borra una editora). En la base de desarrollo existente se cambió a mano.
- **Tests:** `StoreFactory` crea las tiendas en **Unlimited** por defecto para que los tests de funcionalidades no dependan de límites; `onPlan('free')` para probarlos ([PlansTest](backend/tests/Feature/Api/Billing/PlansTest.php)).
- **Frontend:** [PlanCards](frontend/components/PlanCards.tsx) (toggle mensual/anual, reutilizable en landing y registro), "Mi plan" (`/admin/plan`: plan, barras de uso, tarjetas), tarjeta de plan en el dashboard, `ProductForm` oculta la subida al llegar al límite de fotos y `UsersManager` reemplaza el alta por un aviso al llegar al límite de usuarios. Super panel: columna Plan y formulario de cambio de plan.

### Email transaccional
- **Qué se envía** (notificaciones en cola, en español, [app/Application/Auth/Notifications](backend/app/Application/Auth/Notifications)): `UserInvitation` (link 72 h a `/admin/invitacion`) y `ResetPasswordLink` (link 60 min a `/admin/restablecer`). Ambos links los arma [AccessLinks](backend/app/Application/Auth/Services/AccessLinks.php) con `PANEL_URL`, sobre la tabla `password_reset_tokens` con dos brokers (`invitations` 72 h, `users` 60 min): un token vigente por email, pedir uno nuevo invalida el anterior.
- **Olvidé mi contraseña:** respuesta 202 idéntica exista o no el email; solo envía a usuarios de una tienda activa; 1 email por minuto por persona y throttle 3/min por email+IP. Restablecer cierra todas las sesiones; si era un invitado pendiente, cuenta como aceptar.
- **Invitación:** `GET /auth/invitation` valida el link sin consumirlo (muestra la tienda); aceptar guarda la contraseña, marca `invitation_accepted_at` y `email_verified_at` y devuelve un token de sesión (entra directo). Tienda suspendida → link inválido. Desde el panel, "Reenviar invitación" / "Enviar link de contraseña" según el estado; el super panel reenvía la del dueño (que todavía no puede entrar).
- **Plantillas:** textos de la plantilla de Laravel traducidos en [lang/es.json](backend/lang/es.json); el encabezado enlaza al panel ([resources/views/vendor/mail](backend/resources/views/vendor/mail), solo `message.blade.php`). `APP_NAME="Catálogo"` es el remitente y el nombre de la plantilla.
- **Desarrollo y e2e:** servicio `mailpit` del compose (UI y API en http://localhost:8025, SMTP 1025). El e2e lee los emails por la API de Mailpit y sigue los links.
- **Producción (Resend, decidido 2026-10-26):** gratis hasta 3.000/mes (100/día). Pasos del deploy: comprar el dominio, verificarlo en Resend agregando los registros DNS que indica (SPF, DKIM y DMARC), crear una API key y configurar `MAIL_HOST=smtp.resend.com`, `MAIL_PORT=465`, `MAIL_SCHEME=smtps`, `MAIL_USERNAME=resend`, `MAIL_PASSWORD=<API key>`, `MAIL_FROM_ADDRESS=no-reply@<dominio>` y `PANEL_URL=https://<dominio del panel>`. Sin paquetes extra (SMTP).

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
| Feature | [StoreManagementTest](backend/tests/Feature/Api/Platform/StoreManagementTest.php) | 9 |
| Feature | [PlansTest](backend/tests/Feature/Api/Billing/PlansTest.php) | 12 |
| Feature | [AccessLinksTest](backend/tests/Feature/Api/AccessLinksTest.php) | 12 |
| — | `ExampleTest` (Unit + Feature, del skeleton) | 2 |
| **Backend total** | | **143** |
| Frontend | [lib.test.ts](frontend/tests/lib.test.ts), [admin-forms.test.ts](frontend/tests/admin-forms.test.ts), [skeletons.test.ts](frontend/tests/skeletons.test.ts), [seo.test.ts](frontend/tests/seo.test.ts), [server-api.test.ts](frontend/tests/server-api.test.ts), [slug.test.ts](frontend/tests/slug.test.ts), [plans.test.ts](frontend/tests/plans.test.ts) | 57 |
| e2e | [admin.e2e.mjs](frontend/e2e/admin.e2e.mjs) (Playwright, navegador real) | 21 pasos |
| a11y | [a11y.e2e.mjs](frontend/e2e/a11y.e2e.mjs) (axe-core, WCAG 2.2 AA, corre en `bin/e2e.sh` después del e2e) | 31 pasos |
| Super panel | [platform.e2e.mjs](frontend/e2e/platform.e2e.mjs) (login, 2FA con TOTP calculado en Node, recuperación, separación de sesiones; tiendas: alta, subdominio reservado/repetido, login del dueño, suspender/reactivar con 404/200 real, borrar y restaurar, cambio de plan; axe) | 15 pasos |

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
| `MAIL_*` | backend | email transaccional (dev: Mailpit; prod: Resend por SMTP) | ✅ |
| `PANEL_URL` | backend | base de los links de los emails | ✅ |

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

#### Comparación con vercatalogo (que cobra en USD)
Tipo de cambio del 25/10/2026 (Ámbito, El Cronista): **oficial BNA $1.550** (venta), MEP ≈ $1.562, **tarjeta ≈ $2.015** (lo que paga de verdad un comercio argentino que contrata un servicio en dólares con tarjeta, con percepciones).

| Plan | vercatalogo | En ARS al oficial ($1.550) | En ARS con tarjeta ($2.015) | **Precio propio** | vs. oficial | vs. tarjeta |
|---|---|---|---|---|---|---|
| Gratis | US$0 (25 productos, 500 visitas) | $0 | $0 | **$0** (25 productos) | — | — |
| Basic | US$10 | $15.500 | $20.150 | **$10.000** | −35 % | −50 % |
| Intermediate | US$19 | $29.450 | $38.285 | **$19.000** | −35 % | −50 % |
| Advanced | US$36 | $55.800 | $72.540 | **$36.000** | −35 % | −50 % |
| Unlimited | US$72 | $111.600 | $145.080 | **$72.000** | −35 % | −50 % |

- Anual: vercatalogo Basic queda en US$6/mes (≈ $9.300 al oficial); el propio, en $6.000/mes equivalente.
- Los precios propios equivalen a tomar el dólar a $1.000 (Basic ≈ US$6,45 al oficial): ~35 % más baratos al oficial y ~50 % frente a pagar en dólares con tarjeta. Es coherente: vercatalogo incluye IA, carrito, pagos online, revendedores y multimoneda, que acá no existen.
- **Ventaja a comunicar:** precios en pesos, sin dólar tarjeta ni percepciones, cobrados por Mercado Pago.
- Los valores de referencia cambian rápido: revisar esta tabla antes de publicar precios.

### Dónde se ven los planes
Una sola fuente de datos: endpoint público `GET /api/v1/plans` (P2.2), que alimenta:
1. **Landing** (`/#planes` en el dominio raíz): selector mensual/anual (−40 %), tarjetas con precio, productos y qué incluye.
2. **Registro, paso 3:** elección del plan.
3. **Panel de la tienda:** "Mi plan", con el plan actual, el uso (ej. 18/25 productos) y el botón para cambiar.
4. **Super panel:** plan y estado de la suscripción en el listado y el detalle de cada tienda.

### Landing page (dominio raíz)
Basada en la estructura de vercatalogo.com, pero **solo con lo que el producto hace hoy**. Va en `/` del dominio raíz, que hoy no tiene contenido (solo `/admin` y `/platform`; el proxy no lo reescribe).

1. **Nav:** Inicio, Funcionalidades, Planes, Preguntas frecuentes · "Iniciar sesión" · "Crear catálogo".
2. **Hero:** "Creá tu catálogo online y recibí pedidos por WhatsApp", subtítulo, CTA "Crear mi catálogo" y "Ver planes", ilustración flat o mockup de la tienda demo en un celular.
3. **Cómo funciona (3 pasos):** registrate → cargá tus productos → compartí tu link.
4. **Funcionalidades actuales** (tarjetas con ícono): catálogo con categorías, variantes (talle/color) y fotos; botón de WhatsApp con mensaje precargado; subdominio propio (`tutienda.dominio.com`); logo y colores de marca; buscador; rápida y pensada para celular; preparada para Google (SEO); accesible; usuarios con roles (dueño/editor).
5. **Planes:** toggle mensual/anual, destacando "precios en pesos"; CTA a registro.
6. **Preguntas frecuentes:** solo respuestas verdaderas hoy (cómo recibo pedidos, si puedo empezar gratis, si necesito programar, si puedo cambiar de plan, cómo pago).
7. **CTA final y footer:** navegación, Términos y condiciones, Política de privacidad, contacto.

- **Fuera por ahora (no existe):** asistente IA, revendedores, carrito y pagos de clientes, multimoneda, carga desde Google Sheets, Analytics/Pixel, dominio propio (entra como función de plan cuando exista), blog, marca de agua, API.
- **Sin cifras ni testimonios inventados** ("más de 100.000 negocios", reseñas de "cliente verificado"): se agregan cuando haya clientes reales y con su permiso.
- **Requisitos:** WCAG 2.2 AA (axe en el e2e), SEO del dominio raíz (metadatos, Open Graph, sitemap y robots), mobile-first, paleta "Quiet Modern".
- **Páginas legales** `/terminos` y `/privacidad`: necesarias para el registro (aceptar términos) y para Mercado Pago. Se deja una base marcada como borrador; el texto final lo valida el usuario o un profesional.

#### Ilustraciones flat gratuitas

| Fuente | Licencia | Atribución | Por qué |
|---|---|---|---|
| **unDraw** (undraw.co) | uso comercial gratuito | no | **recomendada:** SVG con color elegible (se pinta con el color de marca), estilo flat vigente |
| Open Peeps (openpeeps.com) | CC0 | no | personas dibujadas a mano, combinables |
| Humaaans (humaaans.com) | CC0 | no | personas modulares flat |
| DrawKit (drawkit.com, packs gratis) | uso comercial gratuito | no | packs temáticos (compras, e-commerce) |
| Storyset (Freepik) | gratis con atribución | **sí** | animables, pero obliga a poner el crédito |

- **Propuesta:** unDraw como base, recoloreada con el color de marca, más **capturas reales de la tienda demo dentro de un marco de celular** (lo que mejor muestra un catálogo y siempre es fiel al producto).
- Los SVG se **guardan en el repo** (`frontend/public/illustrations/`), sin hotlinking, con un README que anota fuente y licencia de cada uno. Las decorativas llevan `alt=""`.

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
- Dominio propio como función de plan (propuesta: desde Advanced) cuando exista `custom_domain`.
- **Actualización de precios en ARS** por inflación: revisión periódica (ej. trimestral) o referencia al dólar oficial con redondeo; aviso previo a los suscriptores antes de un aumento.
- **Contenido legal** de Términos y condiciones y Política de privacidad (lo valida el usuario o un profesional).
- Falta de pago: suspender o pasar a Gratis; cuántos días de gracia.
- ¿Se publica la tienda antes de verificar el email?
- Retención de datos de una tienda borrada.
- Dominio del super panel: ruta en el dominio raíz o subdominio propio (`admin.dominio.com`).
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
   - [x] **1b. Gestión de tiendas:** listado con búsqueda y filtros, alta manual (tienda + dueño con contraseña temporal), suspender/reactivar (con revalidación ISR), borrar (soft delete, confirmando el subdominio) y restaurar, auditoría de cada acción, slugs reservados
   - [ ] Pendiente derivado: purga definitiva de tiendas borradas (datos + objetos en R2) según la retención que se decida; ver Decisiones pendientes
2. [x] **Planes y límites:** `plans` + `subscriptions`, API pública `GET /api/v1/plans`, límites aplicados en las Actions, "Mi plan" (plan y uso) en el panel de la tienda, plan y cambio manual en el super panel. Las tiendas existentes quedan en Gratis.
3. [x] **Email transaccional:** recuperación de contraseña e invitaciones por email (reemplazan a la contraseña temporal), Mailpit en dev/e2e, Resend para producción (se configura en el deploy). La verificación de email pasa al registro (punto 5).
4. [ ] **Landing page + páginas legales** (ver [Landing page](#landing-page-dominio-raíz)): hero, cómo funciona, funcionalidades actuales, planes desde la API, FAQ, términos y privacidad, ilustraciones unDraw y capturas de la demo. Antes del registro porque es la puerta de entrada y el registro enlaza a los términos; hasta que haya registro, el CTA lleva a contacto.
5. [ ] **Registro público en 3 pasos** con Turnstile, plan Gratis y verificación de email (reutiliza las notificaciones y Mailpit de P2.3).
6. [ ] **Mercado Pago:** checkout de planes pagos, webhook, cambio de plan, suspensión automática por falta de pago.
7. Resto:
   - [ ] Sync de variantes que conserve IDs (prerequisito del carrito)
   - [ ] Paginación en home/categorías y productos de subcategorías
   - [ ] Dominios propios (`custom_domain`), como función de plan
   - [ ] Limpieza de objetos huérfanos en R2 (job o lifecycle rule)
   - [ ] 2FA y registro de auditoría para usuarios de tienda (para la plataforma va en el punto 1)
   - [ ] Borrar la carpeta vacía `app/Domain/Category/Events`
   - [ ] Mensajes de validación genéricos en español (hoy la app no tiene traducciones: los formularios nuevos traen mensajes propios, pero los demás campos muestran el texto de Laravel en inglés)

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
| 2026-10-26 | P2.1b: gestión de tiendas en el super panel: listado (búsqueda, filtros, dueños y conteos), alta de tienda + dueño con contraseña temporal, suspender/reactivar, borrar con confirmación del subdominio y restaurar (vuelve suspendida), slugs reservados, auditoría. Corregido: `RevalidateStorefront` ignoraba tiendas inactivas, así que suspender no habría apagado la tienda cacheada (nuevo `RevalidatesRegardlessOfStatus`). Pest 117/117, PHPStan OK, Vitest 54/54, build OK, e2e 20 + 27 + 14 OK, prueba real en dev 200 → 404 → 200 → 404. |
| 2026-10-26 | Planificación (sin código): comparación de precios con vercatalogo convertida a ARS (oficial $1.550 y tarjeta ≈ $2.015 al 25/10/2026: los propios quedan −35 % / −50 %), dónde se ven los planes (API pública única), landing page del dominio raíz basada en vercatalogo pero solo con funciones reales y sin testimonios inventados, páginas legales, ilustraciones flat gratuitas (unDraw recomendada). P2 reordenado: la landing entra como punto 4, antes del registro. |
| 2026-10-26 | P2.2: planes y límites. Límites confirmados (Unlimited con 20 usuarios), tablas `plans` (datos en la migración) y `subscriptions` (backfill a Gratis), `GET /api/v1/plans` público, límites de productos/fotos/usuarios en las Actions (bajar de plan conserva todo), "Mi plan" con uso y tarjetas mensual/anual, avisos de límite en producto y usuarios, plan y cambio manual auditado en el super panel. Demo en Intermediate. Pest 129/129, PHPStan OK, Vitest 57/57, build OK, e2e 21 + 28 + 15 OK. |
| 2026-10-26 | P2.3: email transaccional. Resend elegido para producción (se conecta en el deploy por SMTP); Mailpit en dev y e2e. Altas por invitación (link 72 h) en lugar de contraseña temporal, "olvidé mi contraseña" (link 60 min, respuesta genérica, cierra sesiones), reenvío de invitación desde el panel y desde el super panel, plantillas en español. Mensajes de contraseña y email duplicado en español. e2e reescrito para leer los emails desde Mailpit. Pest 143/143, PHPStan OK, Vitest 57/57, build OK, e2e 21 + 31 + 15 OK. |
