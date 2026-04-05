#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
VENV_DIR="$PROJECT_DIR/.venv-markitdown"

echo "Setting up MarkItDown in $VENV_DIR..."

if [[ ! -d "$VENV_DIR" ]]; then
    python3 -m venv "$VENV_DIR"
    echo "Created Python venv at $VENV_DIR"
fi

"$VENV_DIR/bin/pip" install --quiet --upgrade pip
"$VENV_DIR/bin/pip" install --quiet 'markitdown[pdf,docx,pptx,xlsx]'

echo "MarkItDown installed. Binary at: $VENV_DIR/bin/markitdown"
echo ""
echo "Verify: $VENV_DIR/bin/markitdown --help"
