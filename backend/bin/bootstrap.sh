#!/usr/bin/env bash
# Genera el skeleton de Laravel 13 alrededor de los módulos ya escritos.
# `tar -k` no pisa ningún archivo existente: los archivos del repo tienen prioridad sobre el skeleton.
set -euo pipefail

cd "$(dirname "$0")/.."

export UID GID="${GID:-$(id -g)}"

docker compose build app
docker compose up -d pgsql

docker compose run --rm --no-deps app sh -c '
  set -e
  composer create-project laravel/laravel /tmp/skeleton "^13.0" --no-interaction --prefer-dist
  tar -C /tmp/skeleton -cf - . | tar -C /app -xkf - || true
  composer require laravel/sanctum spatie/laravel-permission league/flysystem-aws-s3-v3 --no-interaction
  composer config allow-plugins.pestphp/pest-plugin true
  composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan --with-all-dependencies --no-interaction
  php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --tag=sanctum-migrations
'

docker compose up -d
docker compose exec app php artisan migrate:fresh --seed

echo "Listo. API en http://localhost:8000 — tests: docker compose exec app ./vendor/bin/pest"
