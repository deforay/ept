#!/usr/bin/env pwsh
# crunz-run.ps1

param(
    [string]$EnvName = "production"
)

# Set the environment variable for this process and child processes
$env:APPLICATION_ENV = $EnvName

# Get the directory where this script is located
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

# Run the composer script using the project root as working directory
composer --working-dir="$ScriptDir" tasks
