@echo off
setlocal
cd /d "%~dp0"

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\start-attendpro.ps1"

if errorlevel 1 (
    echo.
    echo AttendPro could not start. Review the error above.
    pause
)
