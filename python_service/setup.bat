@echo off
setlocal
cd /d "%~dp0"

if not exist ".venv\Scripts\python.exe" py -3 -m venv .venv
if errorlevel 1 exit /b 1

call ".venv\Scripts\activate.bat"
python -m pip install --upgrade pip
if errorlevel 1 exit /b 1
python -m pip install -r requirements-dev.txt --progress-bar off --timeout 30 --retries 2
if errorlevel 1 exit /b 1
if /i "%ATTENDPRO_FACE_BACKEND%"=="dlib" (
    echo Installing optional dlib face backend...
    python -m pip install -r requirements-dlib.txt --progress-bar off --timeout 60 --retries 2
    if errorlevel 1 exit /b 1
)
if /i not "%ATTENDPRO_FACE_BACKEND%"=="dlib" if /i not "%ATTENDPRO_FACE_BACKEND%"=="sface" (
    echo Installing InsightFace face backend (default)...
    python -m pip install -r requirements-insightface.txt --progress-bar off --timeout 60 --retries 2
    if errorlevel 1 exit /b 1
)
python -m attendpro_recognition.download_models
if errorlevel 1 exit /b 1

echo AttendPro Python service setup complete. Run start.bat
