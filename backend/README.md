# Catálogo SaaS — Backend (Laravel 13)

API multi-tenant: cada comercio (`Store`) aísla sus categorías, productos, variantes e imágenes por `store_id`.

## Puesta en marcha

Requiere Docker con el plugin Compose.

```bash
./bin/bootstrap.sh          # genera el skeleton de Laravel 13 sin pisar los módulos, instala paquetes, migra y hace seed
docker compose exec app ./vendor/bin/pest
docker compose exec app ./vendor/bin/phpstan analyse
```

Usuario demo: `owner@demo.test` / `password` (tienda `demo`).

## Capas

| Capa | Contenido | Depende de |
|---|---|---|
| `app/Domain` | Modelos (aggregates), enums, contratos de repositorio, excepciones, eventos, tenancy | — |
| `app/Application` | DTOs, Actions (una clase = un caso de uso), servicios de aplicación | Domain |
| `app/Infrastructure` | Repositorios Eloquent, bindings | Domain, Application |
| `app/UI/Http` | Controllers, Form Requests, API Resources, middleware | Application |

Los modelos Eloquent viven en Domain como aggregates (DDD pragmático): no se duplican entidades POPO.
Excepción documentada: los contratos de repositorio reciben los DTOs de Application.

## Multi-tenancy (defensa en profundidad)

1. **Middleware** fija el `TenantContext`: `tenant.user` (admin, tienda del usuario autenticado) o `tenant.header` (storefront, header `X-Store-Slug`).
2. **Global scope `StoreScope`** en todo modelo con `BelongsToStore`; si no hay tenant lanza `TenantNotResolvedException` (falla cerrado).
3. **Repositorios** filtran y asignan `store_id` explícitamente.
4. **PostgreSQL**: FKs compuestas `(x_id, store_id)` impiden referenciar filas de otra tienda.

Fuera de un request (seeders, jobs, comandos): `app(TenantContext::class)->runAs($store, fn () => ...)`.

## Endpoints

`/api/v1/admin/products` (`auth:sanctum`, `tenant.user`, `can:products.manage`): `GET`, `POST`, `GET {id}`, `PUT {id}`, `DELETE {id}`.

Precios en centavos (`price_amount: 1850000` = $18.500,00). Variantes: `options` libre (`{"talle":"M","color":"Negro"}`).
En `PUT`, omitir `variants`/`images` las deja intactas; enviarlas las reemplaza.
