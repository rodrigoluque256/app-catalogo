# Storefront (Next.js 16, App Router)

Tienda pública multi-tenant: cada tienda vive en un subdominio (`demo.localhost:3000`, `tienda.dominio.com`).

## Cómo funciona
- `proxy.ts` toma el subdominio del `Host` y reescribe internamente a `/s/[store]/...`. La URL del navegador no cambia.
- Todas las lecturas a la API (`lib/api.ts`) usan `cache: 'force-cache'` + tags + `revalidate: 3600` como red de seguridad. Las páginas se generan en la primera visita y se cachean (ISR): `generateStaticParams` devuelve `[]` a propósito.
- Cuando el admin cambia algo, el backend (listener `RevalidateStorefront`) hace `POST /api/revalidate` con `x-revalidate-secret` y `{ tags }`; el route handler llama `revalidateTag(tag, { expire: 0 })`.
- Tags (deben coincidir con `StorefrontTags` del backend, ver `lib/tags.ts`): `store:{slug}`, `store:{slug}:products`, `store:{slug}:categories`, `store:{slug}:product:{productSlug}`.
- La búsqueda (`/buscar`) no se cachea. El botón de WhatsApp arma un link `wa.me` con la plantilla de la tienda y las opciones elegidas.

## Panel de administración (`/admin`)
- Vive en el dominio raíz (`http://localhost:3000/admin`), no en el subdominio de una tienda. Login: `owner@demo.test` / `password` (seed).
- Es un BFF: el token de Sanctum queda en una cookie `httpOnly` (nunca llega al JavaScript del navegador); los formularios usan Server Actions que llaman a la API con ese token. Un 401 redirige a `/admin/login`.
- Secciones según permisos (`/auth/me`): **Productos** y **Categorías** (`products.manage`: owner y editor), **Mi tienda** (`store.manage`) y **Usuarios** (`users.manage`): solo el dueño. **Mi cuenta** (cambiar contraseña) para todos. Inicio con contadores y checklist de primeros pasos.
- Productos: precios en pesos (`18.500,50`), estado, destacado, categoría, imágenes (subida directa a R2 con URL firmada, orden y principal) y variantes por opciones (talle, color…).
- Usuarios: el dueño da de alta personas (rol dueño/editor) y obtiene una **contraseña temporal que se muestra una sola vez**. Esa persona, al ingresar, solo puede ver `/admin/account` hasta elegir una contraseña propia (lo exige también la API: 403 `password_change_required`). Resetear la clave cierra todas las sesiones de esa persona.
- Los 5xx del backend nunca muestran su mensaje interno al usuario.

## Desarrollo
```bash
cd ../backend && docker compose up -d     # levanta API, cola, Postgres y este frontend (puerto 3000)
open http://demo.localhost:3000           # *.localhost resuelve a 127.0.0.1 en Chrome/Firefox
```
Sin Docker: `cp .env.example .env.local && npm install && npm run dev`.

## Comandos
`npm test` (Vitest) · `npm run typecheck` · `npm run build` (`NEXT_DIST_DIR=.next-build` para no pisar el `.next` del dev server)

**Test de navegador del panel** (Playwright en Docker, contra un stack aislado con su propia base `catalogo_e2e`):
```bash
cd ../backend && ./bin/e2e.sh          # parte del seed, corre el test y apaga el stack e2e
cd ../backend && ./bin/e2e.sh --keep   # lo deja levantado para inspeccionar
```
No toca la base de desarrollo. Resultados y capturas en `e2e/output/`; sale con código 1 si algún paso falla.
La subida de imágenes falla en dev (R2 sin configurar): el test lo registra como INFO.

## Variables
Ver `.env.example`. `REVALIDATE_SECRET` debe ser igual a `STOREFRONT_REVALIDATE_SECRET` del backend.
`IMAGE_HOSTS` lista los hosts de R2/CDN permitidos por `next/image`.
