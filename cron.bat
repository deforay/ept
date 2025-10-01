REM cron.bat
@echo off
REM Wrapper to run the PowerShell script
pwsh -ExecutionPolicy Bypass -File "%~dp0cron.ps1" %*
