@echo off
setlocal
cd /d "%~dp0"

set "PYTHON_READY=1"
if not exist ".venv\Scripts\python.exe" set "PYTHON_READY=0"
if "%PYTHON_READY%"=="1" (
    ".venv\Scripts\python.exe" -c "import cv2, fastapi, uvicorn" >nul 2>&1
    if errorlevel 1 set "PYTHON_READY=0"
)
if /i not "%ATTENDPRO_FACE_BACKEND%"=="dlib" if /i not "%ATTENDPRO_FACE_BACKEND%"=="sface" (
    ".venv\Scripts\python.exe" -c "import onnxruntime" >nul 2>&1
    if errorlevel 1 set "PYTHON_READY=0"
)
if /i "%ATTENDPRO_FACE_BACKEND%"=="sface" (
    if not exist "models\face_detection_yunet_2023mar.onnx" set "PYTHON_READY=0"
    if not exist "models\face_recognition_sface_2021dec.onnx" set "PYTHON_READY=0"
)

if "%PYTHON_READY%"=="0" (
    echo Preparing the Python recognition service for the first time...
    call setup.bat
    if errorlevel 1 goto :failed
)

php "..\artisan" attendpro:recognition-setup --no-interaction
if errorlevel 1 goto :failed

".venv\Scripts\python.exe" -m attendpro_recognition
exit /b %errorlevel%

:failed
echo.
echo AttendPro Python recognition could not start. Review the error above.
pause
exit /b 1
