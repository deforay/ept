@echo off
REM Wrapper to run the PowerShell script
pwsh -ExecutionPolicy Bypass -File "%~dp0crunz-run.ps1" %*
