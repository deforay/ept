#!/bin/bash

# Accept the environment configuration parameter from the command line
# If not provided, default to 'production'
APPLICATION_ENV=${1:-production}

# Export the environment configuration as an environment variable
export APPLICATION_ENV

# Get the directory where the script is located
SCRIPT_DIR=$(dirname "$0")

# Run the composer script using the project root as working directory
composer --working-dir="$SCRIPT_DIR" tasks