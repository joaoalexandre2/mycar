#!/usr/bin/env bash
# Atualiza a produção. Rode no servidor, na pasta do projeto:
#   bash scripts/deploy.sh
# O workflow .github/workflows/deploy.yml chama este script por SSH.
set -euo pipefail

cd "$(dirname "$0")/.."

git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache

echo "Deploy concluído em $(git rev-parse --short HEAD)"
