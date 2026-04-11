#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

LIFECYCLE_DOC="docs/architecture/lifecycle.md"
# Anchor path as literals in ERE: unescaped "." would match any character.
LIFECYCLE_DOC_PATTERN="^${LIFECYCLE_DOC//./\\.}$"

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

# Prefer ripgrep when available; otherwise use POSIX grep (no extra CI dependency).
if command -v rg >/dev/null 2>&1; then
  matches_pattern() {
    echo "${CHANGED_FILES}" | rg -q "$1"
  }
else
  matches_pattern() {
    echo "${CHANGED_FILES}" | grep -qE "$1"
  }
fi

if [[ "${GITHUB_EVENT_NAME:-}" == "pull_request" ]]; then
  BASE_REF="${GITHUB_BASE_REF:-main}"
  if git remote get-url origin >/dev/null 2>&1; then
    if ! git fetch --no-tags --depth=1 origin "${BASE_REF}"; then
      echo "FAIL: Could not git fetch origin ${BASE_REF}. Check network, credentials, and that the remote ref exists." >&2
      exit 2
    fi
    CHANGED_FILES="$(git diff --name-only "origin/${BASE_REF}...HEAD")"
  else
    echo "WARN: Git remote 'origin' not configured; using HEAD~1..HEAD for lifecycle drift (PR context)." >&2
    if git rev-parse HEAD~1 >/dev/null 2>&1; then
      CHANGED_FILES="$(git diff --name-only HEAD~1..HEAD)"
    else
      CHANGED_FILES=""
    fi
  fi
else
  # Local / pre-commit path: inspect staged changes, not the previous commit.
  # Using HEAD~1..HEAD here meant a drifty commit would permanently block the
  # next commit (and a clean staged change would pass just because the last
  # commit happened to touch the lifecycle doc). See waaseyaa/giiken#56.
  CHANGED_FILES="$(git diff --cached --name-only)"
fi

if [[ -z "${CHANGED_FILES}" ]]; then
  echo "OK: No changed files detected for lifecycle drift check."
  exit 0
fi

DOC_UPDATED=0
if matches_pattern "${LIFECYCLE_DOC_PATTERN}"; then
  DOC_UPDATED=1
fi

WATCH_HIT=0
for pattern in "${WATCH_PATTERNS[@]}"; do
  if matches_pattern "${pattern}"; then
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
