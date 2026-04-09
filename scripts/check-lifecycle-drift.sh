#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

LIFECYCLE_DOC="docs/architecture/lifecycle.md"

if [[ ! -f "$LIFECYCLE_DOC" ]]; then
  echo "FAIL: $LIFECYCLE_DOC is missing."
  exit 1
fi

# Files that usually imply lifecycle behavior changes.
WATCH_PATTERNS=(
  "^public/index\\.php$"
  "^src/GiikenServiceProvider\\.php$"
  "^src/Http/Controller/.*\\.php$"
  "^src/Http/Middleware/.*\\.php$"
  "^src/Entity/.*\\.php$"
  "^src/Query/.*\\.php$"
  "^src/Pipeline/.*\\.php$"
)

if [[ "${GITHUB_EVENT_NAME:-}" == "pull_request" ]]; then
  BASE_REF="${GITHUB_BASE_REF:-main}"
  git fetch --no-tags --depth=1 origin "${BASE_REF}"
  CHANGED_FILES="$(git diff --name-only "origin/${BASE_REF}...HEAD")"
else
  if git rev-parse HEAD~1 >/dev/null 2>&1; then
    CHANGED_FILES="$(git diff --name-only HEAD~1..HEAD)"
  else
    CHANGED_FILES=""
  fi
fi

if [[ -z "${CHANGED_FILES}" ]]; then
  echo "OK: No changed files detected for lifecycle drift check."
  exit 0
fi

DOC_UPDATED=0
if echo "${CHANGED_FILES}" | rg -q "^${LIFECYCLE_DOC}$"; then
  DOC_UPDATED=1
fi

WATCH_HIT=0
for pattern in "${WATCH_PATTERNS[@]}"; do
  if echo "${CHANGED_FILES}" | rg -q "${pattern}"; then
    WATCH_HIT=1
    break
  fi
done

if [[ "${WATCH_HIT}" -eq 1 && "${DOC_UPDATED}" -eq 0 ]]; then
  echo "FAIL: Lifecycle-impacting files changed without updating ${LIFECYCLE_DOC}."
  echo "Changed files:"
  echo "${CHANGED_FILES}" | sed 's/^/  - /'
  echo
  echo "Update ${LIFECYCLE_DOC} (or include a note that no lifecycle behavior changed)."
  exit 1
fi

echo "OK: Lifecycle doc drift check passed."
