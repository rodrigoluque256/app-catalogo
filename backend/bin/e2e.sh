#!/usr/bin/env bash
# Test de navegador del panel contra un stack aislado (base catalogo_e2e, API y frontend propios).
# Cada corrida parte del seed: no deja datos en la base de desarrollo.
#   ./bin/e2e.sh          corre y apaga el stack e2e al terminar
#   ./bin/e2e.sh --keep   lo deja levantado para inspeccionarlo
# Capturas y resultados: frontend/e2e/output/
set -euo pipefail

cd "$(dirname "$0")/.."

export UID GID="${GID:-$(id -g)}"
compose=(docker compose --profile e2e)

cleanup() {
  if [[ "${1:-}" != "--keep" ]]; then
    "${compose[@]}" stop app-e2e frontend-e2e >/dev/null
  fi
}
trap 'cleanup "${1:-}"' EXIT

"${compose[@]}" up -d --wait pgsql

# El script de init de Postgres solo corre con el volumen vacío: en bases existentes se crea acá.
if ! "${compose[@]}" exec -T pgsql psql -U catalogo -d catalogo -tAc \
  "SELECT 1 FROM pg_database WHERE datname = 'catalogo_e2e'" | grep -q 1; then
  "${compose[@]}" exec -T pgsql createdb -U catalogo catalogo_e2e
fi

"${compose[@]}" up -d app-e2e frontend-e2e
# Sin URL de revalidación durante el seed: el frontend e2e todavía puede estar arrancando y la cola es sync.
"${compose[@]}" exec -T -e STOREFRONT_REVALIDATE_URL= app-e2e php artisan migrate:fresh --seed --force

echo "Esperando al frontend e2e…"
for _ in $(seq 1 60); do
  if "${compose[@]}" exec -T frontend-e2e wget -qO /dev/null http://localhost:3000/admin/login 2>/dev/null; then
    break
  fi
  sleep 2
done

# El dev server compila cada ruta en su primera visita: se precalientan para que no venzan los timeouts del test.
for path in /admin /admin/products /admin/products/new /admin/categories /admin/categories/new /admin/settings /admin/users /admin/account; do
  "${compose[@]}" exec -T frontend-e2e wget -qO /dev/null "http://localhost:3000${path}" 2>/dev/null || true
done

mkdir -p ../frontend/e2e/output
"${compose[@]}" run --rm e2e
