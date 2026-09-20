#!/usr/bin/env sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
cd "$SCRIPT_DIR"

PYTHON_COMMAND=${PYTHON_COMMAND:-python3}
"$PYTHON_COMMAND" -m venv .venv
. .venv/bin/activate
python -m pip install --upgrade pip
python -m pip install -r requirements-dev.txt
if [ "${ATTENDPRO_FACE_BACKEND:-insightface}" = "dlib" ]; then
    echo "Installing optional dlib face backend..."
    python -m pip install -r requirements-dlib.txt
elif [ "${ATTENDPRO_FACE_BACKEND:-insightface}" != "sface" ]; then
    echo "Installing InsightFace face backend (default)..."
    python -m pip install -r requirements-insightface.txt
fi
python -m attendpro_recognition.download_models

echo "AttendPro Python service setup complete. Run ./start.sh"
