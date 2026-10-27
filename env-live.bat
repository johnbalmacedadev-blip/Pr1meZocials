@echo off
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0switch-env.ps1" live
pause
