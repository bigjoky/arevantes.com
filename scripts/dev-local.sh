#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

# Una sola URL de aplicación; Vite es su servidor auxiliar de recarga.
for port in 8000 5173; do
    if lsof -nP -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1; then
        echo "El puerto $port ya está ocupado. Reutiliza la instancia existente; no abras otro puerto." >&2
        exit 1
    fi
done

exec node_modules/.bin/concurrently --kill-others --names=app,vite \
    'php artisan serve --host=localhost --port=8000 --tries=1' \
    'npm run dev -- --host localhost --port 5173 --strictPort'
