#!/usr/bin/env bash
set -euo pipefail

php -S "${GIIKEN_DEV_HOST:-127.0.0.1}:${GIIKEN_DEV_PORT:-8080}" -t public public/index.php &
PHP_PID=$!

npm run dev &
VITE_PID=$!

cleanup() {
    kill "$PHP_PID" "$VITE_PID" 2>/dev/null || true
}

trap cleanup EXIT INT TERM

wait -n "$PHP_PID" "$VITE_PID"
