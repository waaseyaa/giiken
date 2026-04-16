#!/usr/bin/env bash
set -euo pipefail

php bin/giiken serve &
PHP_PID=$!

npm run dev &
VITE_PID=$!

cleanup() {
    kill "$PHP_PID" "$VITE_PID" 2>/dev/null || true
}

trap cleanup EXIT INT TERM

wait -n "$PHP_PID" "$VITE_PID"
