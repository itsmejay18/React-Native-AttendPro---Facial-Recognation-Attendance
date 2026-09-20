#!/usr/bin/env sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
cd "$SCRIPT_DIR"

if [ ! -x .venv/bin/python ]; then
    echo "Python environment is missing. Run ./setup.sh first." >&2
    exit 1
fi

exec .venv/bin/python -m attendpro_recognition
