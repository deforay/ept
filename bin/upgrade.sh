#!/bin/bash

# To use this script:
# sudo wget -O /usr/local/bin/ept-update https://raw.githubusercontent.com/deforay/ept/master/bin/upgrade.sh && sudo chmod +x /usr/local/bin/ept-update
# sudo ept-update
#
# Options:
#   -p PATH   Specify the EPT installation path (e.g., -p /var/www/ept)
#   -A        Auto-detect and update ALL EPT installations in /var/www
#   -i        Interactive instance selection (use with -A to pick specific instances)
#   -s        Skip Ubuntu system updates
#   -b        Skip backup prompts
#
# Examples:
#   sudo ept-update                      # Interactive single instance
#   sudo ept-update -p /var/www/ept      # Specific path
#   sudo ept-update -A                   # Update all instances in /var/www
#   sudo ept-update -A -i                # Detect instances, pick which to update
#   sudo ept-update -A -s -b             # Non-interactive, update all instances

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Need admin privileges for this script. Run sudo -s before running this script or run this script with sudo"
    exit 1
fi

# Download and update shared-functions.sh
SHARED_FN_PATH="/usr/local/lib/ept/shared-functions.sh"
SHARED_FN_URL="https://raw.githubusercontent.com/deforay/ept/master/bin/shared-functions.sh"

mkdir -p "$(dirname "$SHARED_FN_PATH")"

if wget -q -O "$SHARED_FN_PATH" "$SHARED_FN_URL"; then
    chmod +x "$SHARED_FN_PATH"
    echo "Downloaded shared-functions.sh."
else
    echo "Failed to download shared-functions.sh."
    if [ ! -f "$SHARED_FN_PATH" ]; then
        echo "shared-functions.sh missing. Cannot proceed."
        exit 1
    fi
fi

# Source the shared functions
# shellcheck disable=SC1090
source "$SHARED_FN_PATH"

prepare_system

DEFAULT_EPT_PATH="/var/www/ept"

# Semver-ish compare using sort -V: version_ge A B  => true if A >= B
version_ge() {
    [ "$(printf '%s\n' "$2" "$1" | sort -V | head -n1)" = "$2" ]
}

resolve_ept_path() {
    local provided="$1"
    if [ -n "$provided" ]; then
        echo "$(to_absolute_path "$provided")"
        return 0
    else
        echo "$DEFAULT_EPT_PATH"
    fi
}

# Detect all valid EPT installations in a directory
detect_ept_installations() {
    local search_dir="${1:-/var/www}"
    local found_paths=()

    for dir in "$search_dir"/*/; do
        [ -d "$dir" ] || continue
        # Remove trailing slash for cleaner paths
        dir="${dir%/}"
        if is_valid_application_path "$dir"; then
            found_paths+=("$dir")
        fi
    done

    printf '%s\n' "${found_paths[@]}"
}

# Strip surrounding quotes from secrets copied from INI files.
sanitize_ini_secret() {
    local val="${1-}"
    val="$(printf '%s' "$val" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
    if [ "${#val}" -ge 2 ]; then
        case "$val" in
            \"*\") val="${val#\"}"; val="${val%\"}" ;;
            \'*\') val="${val#\'}"; val="${val%\'}" ;;
        esac
    fi
    printf '%s' "$val"
}

# Initialize flags
skip_ubuntu_updates=false
skip_backup=false
auto_detect=false
interactive_select=false
ept_path=""
declare -a ept_paths=()

log_file="/tmp/ept-upgrade-$(date +'%Y%m%d-%H%M%S').log"

# Parse command-line options
while getopts ":sbAip:" opt; do
    case $opt in
        s) skip_ubuntu_updates=true ;;
        b) skip_backup=true ;;
        A) auto_detect=true ;;
        i) interactive_select=true ;;
        p) ept_path="$OPTARG" ;;
        *) : ;; # ignore
    esac
done

# Error trap
trap 'error_handling "${BASH_COMMAND}" "$LINENO" "$?"' ERR

# Save the current trap settings
current_trap=$(trap -p ERR)

# Disable the error trap temporarily
trap - ERR

# Handle path resolution based on mode
if [ "$auto_detect" = true ]; then
    # Warn if -p was also specified
    if [ -n "$ept_path" ]; then
        print warning "-A flag specified, ignoring -p ${ept_path}"
    fi
    # Auto-detect mode: scan /var/www for all valid installations
    print info "Scanning /var/www for EPT installations..."
    mapfile -t detected_paths < <(detect_ept_installations /var/www)

    if [ ${#detected_paths[@]} -eq 0 ]; then
        print error "No valid EPT installations found in /var/www"
        log_action "Auto-detect found no valid installations"
        exit 1
    fi

    # Show numbered list
    echo ""
    print info "Found ${#detected_paths[@]} installation(s):"
    for i in "${!detected_paths[@]}"; do
        echo "  $((i+1))) ${detected_paths[$i]}"
    done
    echo ""

    if [ "$interactive_select" = true ]; then
        # Interactive mode: let user pick which instances to update
        printf "Enter instance numbers to update (e.g., 1,2,3) or press Enter for all: "
        read -r selection < /dev/tty

        if [ -z "$selection" ]; then
            ept_paths=("${detected_paths[@]}")
        else
            IFS=',' read -ra selected_nums <<< "$selection"
            for num in "${selected_nums[@]}"; do
                num=$(echo "$num" | xargs)  # trim whitespace
                idx=$((num - 1))
                if [[ $idx -ge 0 ]] && [[ $idx -lt ${#detected_paths[@]} ]]; then
                    ept_paths+=("${detected_paths[$idx]}")
                fi
            done
        fi

        if [ ${#ept_paths[@]} -eq 0 ]; then
            print error "No valid instances selected"
            exit 1
        fi
    else
        # Non-interactive: use all detected instances
        ept_paths=("${detected_paths[@]}")
    fi

    print info "Will update ${#ept_paths[@]} instance(s):"
    for p in "${ept_paths[@]}"; do
        print info "  - $p"
    done
    log_action "Selected ${#ept_paths[@]} installations for update: ${ept_paths[*]}"
elif [ -n "$ept_path" ]; then
    # Single path provided via -p flag
    ept_path="$(resolve_ept_path "$ept_path")"
    if ! is_valid_application_path "$ept_path"; then
        print error "The specified path does not appear to be a valid EPT installation. Please check the path and try again."
        log_action "Invalid EPT path specified: $ept_path"
        exit 1
    fi
    ept_paths=("$ept_path")
    print info "EPT path is set to ${ept_path}"
    log_action "EPT path is set to ${ept_path}"
else
    # Interactive prompt for path (existing behavior)
    echo "Enter the EPT installation path [press enter for /var/www/ept]: "
    if read -t 60 ept_path && [ -n "$ept_path" ]; then
        : # user provided a value
    else
        ept_path=""
    fi
    ept_path="$(resolve_ept_path "$ept_path")"
    if ! is_valid_application_path "$ept_path"; then
        print error "The specified path does not appear to be a valid EPT installation. Please check the path and try again."
        log_action "Invalid EPT path specified: $ept_path"
        exit 1
    fi
    ept_paths=("$ept_path")
    print info "EPT path is set to ${ept_path}"
    log_action "EPT path is set to ${ept_path}"
fi

# For single-instance mode, set ept_path for backward compatibility with existing code
if [ ${#ept_paths[@]} -eq 1 ]; then
    ept_path="${ept_paths[0]}"
fi

# Restore the previous error trap
eval "$current_trap"

# Check for MySQL
if ! command -v mysql &>/dev/null; then
    print error "MySQL is not installed. Please first run the setup script."
    log_action "MySQL is not installed. Please first run the setup script."
    exit 1
fi

MYSQL_CONFIG_FILE="/etc/mysql/mysql.conf.d/mysqld.cnf"
backup_timestamp=$(date +%Y%m%d%H%M%S)
# Calculate total system memory in MB
total_mem_kb=$(grep MemTotal /proc/meminfo | awk '{print $2}')
total_mem_mb=$((total_mem_kb / 1024))
total_mem_gb=$((total_mem_mb / 1024))

# Calculate buffer pool size (70% of total RAM)
buffer_pool_size_gb=$((total_mem_gb * 70 / 100))

# Safety check for small RAM systems
if [ "$buffer_pool_size_gb" -lt 1 ]; then
    buffer_pool_size="512M"
else
    buffer_pool_size="${buffer_pool_size_gb}G"
fi

# Calculate other memory-related settings
if [ $total_mem_gb -lt 8 ]; then
    join_buffer="1M"
    sort_buffer="2M"
    read_rnd_buffer="2M"
    read_buffer="1M"
    tmp_table="32M"
    max_heap="32M"
    log_file_size="256M"
    log_buffer="8M"
elif [ $total_mem_gb -lt 16 ]; then
    join_buffer="2M"
    sort_buffer="2M"
    read_rnd_buffer="4M"
    read_buffer="1M"
    tmp_table="64M"
    max_heap="64M"
    log_file_size="512M"
    log_buffer="16M"
elif [ $total_mem_gb -lt 32 ]; then
    join_buffer="4M"
    sort_buffer="4M"
    read_rnd_buffer="8M"
    read_buffer="2M"
    tmp_table="128M"
    max_heap="128M"
    log_file_size="1G"
    log_buffer="32M"
else
    join_buffer="8M"
    sort_buffer="8M"
    read_rnd_buffer="16M"
    read_buffer="4M"
    tmp_table="256M"
    max_heap="256M"
    log_file_size="2G"
    log_buffer="64M"
fi

# Calculate max connections (cap at 1000)
max_connections=$((total_mem_gb * 100))
[ $max_connections -gt 1000 ] && max_connections=1000

# Detect SSD for io_capacity
if [ -d "/sys/block" ]; then
    ssd_detected=false
    for device in /sys/block/*/queue/rotational; do
        if [ -e "$device" ] && [ "$(cat "$device")" = "0" ]; then
            ssd_detected=true
            break
        fi
    done
    if $ssd_detected; then io_capacity=2000; else io_capacity=500; fi
else
    io_capacity=1000
fi

# Create directory for slow query logs
mkdir -p /var/log/mysql
touch /var/log/mysql/mysql-slow.log
chown mysql:mysql /var/log/mysql/mysql-slow.log

# Detect MySQL version (avoid grep -P)
mysql_version=$(mysql -V | awk '{for(i=1;i<=NF;i++) if ($i ~ /^[0-9]+\.[0-9]+(\.[0-9]+)?$/) {print $i; exit}}' | cut -d. -f1-2)
print info "MySQL version detected: ${mysql_version}"

# Detect server/client flavor + version
ver_out="$(mysql -V 2>/dev/null || true)"
is_mariadb=false
echo "$ver_out" | grep -qi 'mariadb' && is_mariadb=true

# First numeric group like 8.0.39 or 10.11.6 → keep major.minor (e.g., 8.0 or 10.11)
ver_num="$(printf '%s\n' "$ver_out" | grep -oE '[0-9]+(\.[0-9]+)+' | head -1 || echo '0.0')"
mysql_version="$(awk -F. '{printf "%d.%d\n",$1,$2}' <<<"$ver_num" 2>/dev/null || echo '0.0')"

print info "MySQL version detected: ${mysql_version} (MariaDB: $is_mariadb)"

# Collation: only MySQL 8.0+ supports utf8mb4_0900_ai_ci; MariaDB does not
if ! $is_mariadb && version_ge "$mysql_version" "8.0"; then
    mysql_collation="utf8mb4_0900_ai_ci"
    print info "Using MySQL 8.0+ optimized collation: utf8mb4_0900_ai_ci"
else
    mysql_collation="utf8mb4_unicode_ci"
    print info "Using utf8mb4_unicode_ci collation"
fi

# Desired MySQL settings
declare -A mysql_settings=(
    ["sql_mode"]=""
    ["innodb_strict_mode"]="0"
    ["character-set-server"]="utf8mb4"
    ["collation-server"]="${mysql_collation}"
    ["default_authentication_plugin"]="mysql_native_password"
    ["max_connect_errors"]="10000"
    ["innodb_buffer_pool_size"]="${buffer_pool_size}"
    ["innodb_file_per_table"]="1"
    ["innodb_flush_method"]="O_DIRECT"
    ["innodb_log_file_size"]="${log_file_size}"
    ["innodb_log_buffer_size"]="${log_buffer}"
    ["innodb_flush_log_at_trx_commit"]="2"
    ["innodb_io_capacity"]="${io_capacity}"
    ["join_buffer_size"]="${join_buffer}"
    ["sort_buffer_size"]="${sort_buffer}"
    ["read_rnd_buffer_size"]="${read_rnd_buffer}"
    ["read_buffer_size"]="${read_buffer}"
    ["tmp_table_size"]="${tmp_table}"
    ["max_heap_table_size"]="${max_heap}"
    ["max_connections"]="${max_connections}"
    ["thread_cache_size"]="16"
    ["slow_query_log"]="1"
    ["slow_query_log_file"]="/var/log/mysql/mysql-slow.log"
    ["long_query_time"]="2"
)

# Version-specific
if $is_mariadb || ! version_ge "$mysql_version" "8.0"; then
    mysql_settings["query_cache_type"]="0"
    mysql_settings["query_cache_size"]="0"
    mysql_settings["innodb_buffer_pool_instances"]="8"
    mysql_settings["innodb_read_io_threads"]="8"
    mysql_settings["innodb_write_io_threads"]="8"
else
    mysql_settings["innodb_dedicated_server"]="1"
    mysql_settings["innodb_buffer_pool_instances"]="16"
    mysql_settings["innodb_read_io_threads"]="16"
    mysql_settings["innodb_write_io_threads"]="16"
    mysql_settings["innodb_adaptive_hash_index"]="1"
    mysql_settings["performance_schema"]="1"
    mysql_settings["performance_schema_max_table_instances"]="1000"
fi

print info "RAM detected: ${total_mem_gb}GB - Configuring MySQL with buffer pool: ${buffer_pool_size}"

changes_needed=false
for setting in "${!mysql_settings[@]}"; do
    if ! grep -qE "^[[:space:]]*$setting[[:space:]]*=[[:space:]]*${mysql_settings[$setting]}" "$MYSQL_CONFIG_FILE"; then
        changes_needed=true
        break
    fi
done

if [ "$changes_needed" = true ]; then
    print info "Changes needed. Backing up and updating MySQL config..."
    cp "$MYSQL_CONFIG_FILE" "${MYSQL_CONFIG_FILE}.bak.${backup_timestamp}"

    for setting in "${!mysql_settings[@]}"; do
        if ! grep -qE "^[[:space:]]*$setting[[:space:]]*=[[:space:]]*${mysql_settings[$setting]}" "$MYSQL_CONFIG_FILE"; then
            if grep -qE "^[[:space:]]*$setting[[:space:]]*=" "$MYSQL_CONFIG_FILE"; then
                sed -i "/^[[:space:]]*$setting[[:space:]]*=.*/s/^/#/" "$MYSQL_CONFIG_FILE"
            fi
            echo "$setting = ${mysql_settings[$setting]}" >>"$MYSQL_CONFIG_FILE"
        fi
    done

    print info "Restarting MySQL service to apply changes..."
    restart_service mysql || {
        print error "Failed to restart MySQL. Restoring backup and exiting..."
        mv "${MYSQL_CONFIG_FILE}.bak.${backup_timestamp}" "$MYSQL_CONFIG_FILE"
        restart_service mysql
        exit 1
    }

    print success "MySQL configuration updated successfully."
else
    print success "MySQL configuration already correct. No changes needed."
fi

# --- Always clean up old .bak files ---
find "$(dirname "$MYSQL_CONFIG_FILE")" -maxdepth 1 -type f -name "$(basename "$MYSQL_CONFIG_FILE").bak.*" -exec rm -f {} \;
print info "Removed all MySQL backup files matching *.bak.*"

print info "Applying SET PERSIST sql_mode='' to override MySQL defaults..."

# Use first instance for config extraction (for multi-instance mode)
first_ept_path="${ept_paths[0]}"

# Determine which password to use (from INI or prompt)
if [ -n "$mysql_root_password" ]; then
    mysql_pw="$mysql_root_password"
    print info "Using user-provided MySQL root password"
elif [ -f "${first_ept_path}/application/configs/application.ini" ]; then
    mysql_pw="$(extract_mysql_password_from_config "${first_ept_path}/application/configs/application.ini" production || true)"
    mysql_pw="$(sanitize_ini_secret "$mysql_pw")"
    ini_user="$(extract_mysql_user_from_config "${first_ept_path}/application/configs/application.ini" production || true)"
    ini_user="$(sanitize_ini_secret "$ini_user")"
    if [ "$ini_user" != "root" ]; then
        print warning "application.ini contains DB user '${ini_user:-<empty>}', not 'root'. Prompting for MySQL root password..."
        read -r -sp "MySQL root password: " mysql_pw; echo
    elif [ -n "$mysql_pw" ]; then
        print info "Extracted MySQL root password from application.ini"
    else
        print warning "Could not extract a MySQL password from application.ini. Prompting for password..."
        read -r -sp "MySQL root password: " mysql_pw; echo
    fi
else
    print error "Could not find application.ini to extract MySQL password. Please provide the MySQL root password."
    read -r -sp "MySQL root password: " mysql_pw; echo
fi

# Preflight root auth; prompt if wrong
mysql_pw="$(sanitize_ini_secret "$mysql_pw")"
if ! MYSQL_PWD="${mysql_pw}" mysql -u root -e "SELECT 1" >/dev/null 2>&1; then
    print warning "Root authentication failed. Prompting for password..."
    read -r -sp "MySQL root password: " mysql_pw; echo
fi

persist_result=$(MYSQL_PWD="${mysql_pw}" mysql -u root -e "SET PERSIST sql_mode = '';" 2>&1)
persist_status=$?

if [ $persist_status -eq 0 ]; then
    print success "Successfully persisted sql_mode=''"
    log_action "Applied SET PERSIST sql_mode = '';"
else
    print warning "SET PERSIST failed: $persist_result"
    log_action "SET PERSIST sql_mode failed: $persist_result"
fi

chmod 644 "$MYSQL_CONFIG_FILE"

# Check for Apache
if ! command -v apache2ctl &>/dev/null; then
    print error "Apache is not installed. Please first run the setup script."
    log_action "Apache is not installed. Please first run the setup script."
    exit 1
fi

# Check for PHP
if ! command -v php &>/dev/null; then
    print error "PHP is not installed. Please first run the setup script."
    log_action "PHP is not installed. Please first run the setup script."
    exit 1
fi

# Check for PHP version 8.4.x
php_version=$(php -v | head -n 1 | grep -oE 'PHP [0-9]+\.[0-9]+' | awk '{print $2}')
desired_php_version="8.4"

# Download and install switch-php script
download_file "/usr/local/bin/switch-php" "https://raw.githubusercontent.com/deforay/utility-scripts/master/php/switch-php"
chmod u+x /usr/local/bin/switch-php

if [[ "${php_version}" != "${desired_php_version}" ]]; then
    print info "Current PHP version is ${php_version}. Switching to PHP ${desired_php_version}."
    switch-php ${desired_php_version}
    if [ $? -ne 0 ]; then
        print error "Failed to switch to PHP ${desired_php_version}. Please check your setup."
        exit 1
    fi
else
    print success "PHP version is already ${desired_php_version}."
fi


# Ensure OPCache is installed and enabled
ensure_opcache

# Ensure Composer is installed
ensure_composer

php_version="${desired_php_version}"
configure_php_ini "${php_version}"


# Validate Apache config and reload to apply PHP INI changes
if apache2ctl -t; then
    grep -q '^ServerName localhost$' /etc/apache2/conf-available/servername.conf 2>/dev/null || \
        { echo 'ServerName localhost' >/etc/apache2/conf-available/servername.conf && a2enconf -q servername; }
    systemctl reload apache2 || systemctl restart apache2
else
    print warning "apache2 config test failed; NOT reloading. Please fix and reload manually."
fi

# Check for Composer
if ! command -v composer &>/dev/null; then
    echo "Composer is not installed. Please first run the setup script."
    log_action "Composer is not installed. Please first run the setup script."
    exit 1
fi

print success "All system checks passed. Continuing with the update..."

# Update Ubuntu Packages
if [ "$skip_ubuntu_updates" = false ]; then
    print header "Updating Ubuntu packages"
    export DEBIAN_FRONTEND=noninteractive
    export NEEDRESTART_SUSPEND=1

    apt-get update --allow-releaseinfo-change
    apt-get -o Dpkg::Options::="--force-confdef" \
        -o Dpkg::Options::="--force-confold" \
        upgrade -y

    if ! grep -q "ondrej/apache2" /etc/apt/sources.list /etc/apt/sources.list.d/* 2>/dev/null; then
        add-apt-repository ppa:ondrej/apache2 -y
        apt-get upgrade apache2 -y
    fi

    print info "Configuring any partially installed packages..."
    dpkg --configure -a
fi

# Clean up
export DEBIAN_FRONTEND=noninteractive
apt-get -y autoremove

if [ "$skip_ubuntu_updates" = false ]; then
    print info "Installing basic packages..."
    apt-get install -y build-essential software-properties-common gnupg apt-transport-https ca-certificates lsb-release wget vim zip unzip curl acl snapd rsync git gdebi net-tools sed mawk magic-wormhole openssh-server mosh
fi

# SSH service enable/start
if ! systemctl is-enabled ssh >/dev/null 2>&1; then
    print info "Enabling SSH service..."
    systemctl enable ssh
else
    print success "SSH service is already enabled."
fi

if ! systemctl is-active ssh >/dev/null 2>&1; then
    print info "Starting SSH service..."
    systemctl start ssh
else
    print success "SSH service is already running."
fi

log_action "Ubuntu packages updated/installed."

# Set initial log permissions for all instances
for p in "${ept_paths[@]}"; do
    set_permissions "$p/logs" "full"
done

# Function to list databases and get the database list
get_databases() {
    print info "Fetching available databases..."
    local IFS=$'\n'
    databases=($(mysql -u root -p"${mysql_root_password}" -e "SHOW DATABASES;" | sed 1d | egrep -v 'information_schema|mysql|performance_schema|sys|phpmyadmin'))
    local -i cnt=1
    for db in "${databases[@]}"; do
        echo "$cnt) $db"
        ((cnt++))
    done
}

# Function to back up selected databases
backup_database() {
    local IFS=$'\n'
    local db_list=("${databases[@]}")
    local timestamp=$(date +%Y%m%d-%H%M%S)
    for i in "$@"; do
        local db="${db_list[$i]}"
        print info "Backing up database: $db"
        mysqldump -u root -p"${mysql_root_password}" "$db" | gzip >"${backup_location}/${db}_${timestamp}.sql.gz"
        if [[ $? -eq 0 ]]; then
            print success "Backup of $db completed successfully."
            log_action "Backup of $db completed successfully."
        else
            print error "Failed to backup database: $db"
            log_action "Failed to backup database: $db"
        fi
    done
}

if [ "$skip_backup" = false ]; then
    if ask_yes_no "Do you want to backup the database" "no"; then
        echo "Please enter your MySQL root password:"
        read -r -s mysql_root_password
        read -r -p "Enter the backup location [press enter to select /var/ept-backup/db/]: " backup_location
        backup_location="${backup_location:-/var/ept-backup/db/}"
        if [ ! -d "$backup_location" ]; then
            print info "Backup directory does not exist. Creating it now..."
            mkdir -p "$backup_location" || { print error "Failed to create backup directory."; exit 1; }
        fi
        cd "$backup_location" || exit
        get_databases
        echo "Enter the numbers of the databases you want to backup, separated by space or comma, or type 'all' for all databases:"
        read -r input_selections
        selected_indexes=()
        if [[ "$input_selections" == "all" ]]; then
            selected_indexes=("${!databases[@]}")
        else
            IFS=', ' read -ra selections <<<"$input_selections"
            for selection in "${selections[@]}"; do
                if [[ "$selection" =~ ^[0-9]+$ ]]; then
                    selected_indexes+=($(($selection - 1)))
                else
                    echo "Invalid selection: $selection. Ignoring."
                fi
            done
        fi
        backup_database "${selected_indexes[@]}"
        log_action "Database backup completed."
    else
        print info "Skipping database backup as per user request."
        log_action "Skipping database backup as per user request."
    fi

    # Ask the user if they want to backup the EPT folder(s)
    backup_prompt="Do you want to backup the EPT folder before updating?"
    if [ ${#ept_paths[@]} -gt 1 ]; then
        backup_prompt="Do you want to backup all ${#ept_paths[@]} EPT folders before updating?"
    fi

    if ask_yes_no "$backup_prompt" "no"; then
        timestamp=$(date +%Y%m%d-%H%M%S)
        for p in "${ept_paths[@]}"; do
            folder_name=$(basename "$p")
            backup_folder="/var/ept-backup/www/${folder_name}-backup-$timestamp"
            print info "Backing up $p..."
            mkdir -p "${backup_folder}"
            rsync -a --delete --exclude "public/temporary/" --inplace --whole-file --info=progress2 "$p/" "${backup_folder}/" &
            rsync_pid=$!
            spinner "${rsync_pid}"
            log_action "EPT folder $p backed up to ${backup_folder}"
        done
    else
        print info "Skipping EPT folder backup as per user request."
        log_action "Skipping EPT folder backup as per user request."
    fi
fi

# Track which instances were updated for summary
declare -a updated_instances=()
declare -a failed_instances=()

# Function to upgrade a single instance
upgrade_instance() {
    local ept_path="$1"
    local instance_num="$2"
    local total_instances="$3"
    local temp_dir="$4"

    print header "Upgrading instance ${instance_num}/${total_instances}: ${ept_path}"
    log_action "Starting upgrade for instance: ${ept_path}"

    # Remove old run-once directory
    [ -d "${ept_path}/run-once" ] && rm -rf "${ept_path}/run-once"

    # Calculate checksums of current composer files for this instance
    local CURRENT_COMPOSER_JSON_CHECKSUM="none"
    local CURRENT_COMPOSER_LOCK_CHECKSUM="none"

    if [ -f "${ept_path}/composer.json" ]; then
        CURRENT_COMPOSER_JSON_CHECKSUM=$(md5sum "${ept_path}/composer.json" | awk '{print $1}')
    fi
    if [ -f "${ept_path}/composer.lock" ]; then
        CURRENT_COMPOSER_LOCK_CHECKSUM=$(md5sum "${ept_path}/composer.lock" | awk '{print $1}')
    fi

    # Build symlink exclude list for this instance
    local exclude_file="$(mktemp)"
    local symlinks_found=0
    while IFS= read -r -d '' symlink; do
        local rel="${symlink#"$ept_path/"}"
        printf '%s\n' "$rel" >>"$exclude_file"
        symlinks_found=$((symlinks_found + 1))
    done < <(find "$ept_path" -type l -not -path "*/.*" -print0 2>/dev/null)

    if [ $symlinks_found -gt 0 ]; then
        print info "Preserving $symlinks_found symlinks."
    fi

    # Rsync from temp to this instance
    rsync -a --inplace --whole-file --info=progress2 \
        --exclude-from="$exclude_file" \
        "$temp_dir/ept-master/" "$ept_path/" &
    local rsync_pid=$!
    spinner "${rsync_pid}"
    wait ${rsync_pid}
    local rsync_status=$?

    rm -f "$exclude_file"

    if [ $rsync_status -ne 0 ]; then
        print error "Error occurred during rsync for $ept_path"
        log_action "Error during rsync operation. Path was: $ept_path"
        return 1
    fi

    print success "Files copied to ${ept_path}."
    log_action "Files copied to ${ept_path}."

    # Set proper permissions
    set_permissions "${ept_path}" "quick"

    # Run Composer Install as www-data
    print info "Running composer operations..."
    cd "${ept_path}" || return 1

    sudo -u www-data composer config process-timeout 30000 --no-interaction
    sudo -u www-data composer clear-cache --no-interaction

    local NEED_FULL_INSTALL=false

    if [ ! -d "${ept_path}/vendor" ]; then
        NEED_FULL_INSTALL=true
    else
        local NEW_COMPOSER_JSON_CHECKSUM="none"
        local NEW_COMPOSER_LOCK_CHECKSUM="none"

        if [ -f "${ept_path}/composer.json" ]; then
            NEW_COMPOSER_JSON_CHECKSUM=$(md5sum "${ept_path}/composer.json" 2>/dev/null | awk '{print $1}')
        else
            NEED_FULL_INSTALL=true
        fi

        if [ -f "${ept_path}/composer.lock" ] && [ "$NEED_FULL_INSTALL" = false ]; then
            NEW_COMPOSER_LOCK_CHECKSUM=$(md5sum "${ept_path}/composer.lock" 2>/dev/null | awk '{print $1}')
        else
            NEED_FULL_INSTALL=true
        fi

        if [ "$NEED_FULL_INSTALL" = false ]; then
            if [ "$CURRENT_COMPOSER_JSON_CHECKSUM" = "none" ] || [ "$CURRENT_COMPOSER_LOCK_CHECKSUM" = "none" ] ||
                [ "$NEW_COMPOSER_JSON_CHECKSUM" = "none" ] || [ "$NEW_COMPOSER_LOCK_CHECKSUM" = "none" ] ||
                [ "$CURRENT_COMPOSER_JSON_CHECKSUM" != "$NEW_COMPOSER_JSON_CHECKSUM" ] ||
                [ "$CURRENT_COMPOSER_LOCK_CHECKSUM" != "$NEW_COMPOSER_LOCK_CHECKSUM" ]; then
                NEED_FULL_INSTALL=true
            fi
        fi
    fi

    # Download and install vendor if needed
    if [ "$NEED_FULL_INSTALL" = true ]; then
        print info "Installing dependencies..."
        if curl --output /dev/null --silent --head --fail "https://github.com/deforay/ept/releases/download/vendor-latest/vendor.tar.gz"; then
            # Check if vendor.tar.gz already downloaded (shared across instances)
            if [ ! -f "/tmp/ept-vendor.tar.gz" ]; then
                download_file "/tmp/ept-vendor.tar.gz" "https://github.com/deforay/ept/releases/download/vendor-latest/vendor.tar.gz" "Downloading vendor packages..."
                download_file "/tmp/ept-vendor.tar.gz.sha256" "https://github.com/deforay/ept/releases/download/vendor-latest/vendor.tar.gz.sha256" "Downloading checksum..." 2>/dev/null || \
                    download_file "/tmp/ept-vendor.tar.gz.md5" "https://github.com/deforay/ept/releases/download/vendor-latest/vendor.tar.gz.md5" "Downloading checksum..."
            fi

            # Verify checksum
            if [ -f "/tmp/ept-vendor.tar.gz.sha256" ]; then
                if (cd /tmp && sha256sum -c ept-vendor.tar.gz.sha256 2>/dev/null); then
                    tar -xzf /tmp/ept-vendor.tar.gz -C "${ept_path}" &
                    local vendor_tar_pid=$!
                    spinner "${vendor_tar_pid}"
                    wait ${vendor_tar_pid}

                    chown -R www-data:www-data "${ept_path}/vendor" 2>/dev/null || true
                    chmod -R 755 "${ept_path}/vendor" 2>/dev/null || true

                    sudo -u www-data composer install --no-scripts --no-autoloader --prefer-dist --no-dev --no-interaction
                else
                    sudo -u www-data composer install --prefer-dist --no-dev --no-interaction
                fi
            elif [ -f "/tmp/ept-vendor.tar.gz.md5" ]; then
                if (cd /tmp && md5sum -c ept-vendor.tar.gz.md5 2>/dev/null); then
                    tar -xzf /tmp/ept-vendor.tar.gz -C "${ept_path}" &
                    local vendor_tar_pid=$!
                    spinner "${vendor_tar_pid}"
                    wait ${vendor_tar_pid}

                    chown -R www-data:www-data "${ept_path}/vendor" 2>/dev/null || true
                    chmod -R 755 "${ept_path}/vendor" 2>/dev/null || true

                    sudo -u www-data composer install --no-scripts --no-autoloader --prefer-dist --no-dev --no-interaction
                else
                    sudo -u www-data composer install --prefer-dist --no-dev --no-interaction
                fi
            else
                sudo -u www-data composer install --prefer-dist --no-dev --no-interaction
            fi
        else
            sudo -u www-data composer install --prefer-dist --no-dev --no-interaction
        fi
    fi

    sudo -u www-data composer dump-autoload -o --no-interaction
    print success "Composer operations completed."

    # Database connectivity and migrations
    print info "Checking database connectivity..."
    php "${ept_path}/vendor/bin/db-tools" db:test --all

    print info "Running database migrations..."
    sudo -u www-data composer post-update

    # Run run-once scripts
    print info "Running run-once scripts..."
    sudo -u www-data php "${ept_path}/bin/run-once.php"

    # Make runner script executable (only for first instance)
    if [ "$instance_num" -eq 1 ]; then
        chmod +x "${ept_path}/runner" 2>/dev/null
        sudo rm /usr/local/bin/runner 2>/dev/null || true
        sudo ln -s "${ept_path}/runner" /usr/local/bin/runner 2>/dev/null

        if [ -L "/usr/local/bin/runner" ]; then
            print success "ept runner command installed globally"
        fi
    fi

    # Cron job setup
    setup_cron "${ept_path}"

    print success "Instance ${ept_path} updated successfully."
    log_action "Instance ${ept_path} update complete."

    return 0
}

# Download EPT package ONCE (shared across all instances)
print header "Downloading EPT"

download_file "master.tar.gz" "https://codeload.github.com/deforay/ept/tar.gz/refs/heads/master" "Downloading EPT package..." || {
    print error "EPT download failed - cannot continue with update"
    log_action "EPT download failed - update aborted"
    exit 1
}

# Extract the tar.gz file into temporary directory ONCE
temp_dir=$(mktemp -d)
print info "Extracting files from master.tar.gz..."

tar -xzf master.tar.gz -C "$temp_dir" &
tar_pid=$!
spinner "${tar_pid}"
wait ${tar_pid}

print success "EPT package ready for deployment to ${#ept_paths[@]} instance(s)."

# Process each instance
total_instances=${#ept_paths[@]}
for i in "${!ept_paths[@]}"; do
    if upgrade_instance "${ept_paths[$i]}" "$((i+1))" "$total_instances" "$temp_dir"; then
        updated_instances+=("${ept_paths[$i]}")
    else
        failed_instances+=("${ept_paths[$i]}")
    fi
done

# Cleanup temp files
if [ -d "$temp_dir" ]; then
    rm -rf "$temp_dir"
fi
if [ -f master.tar.gz ]; then
    rm master.tar.gz
fi
if [ -f "/tmp/ept-vendor.tar.gz" ]; then
    rm /tmp/ept-vendor.tar.gz
fi
if [ -f "/tmp/ept-vendor.tar.gz.sha256" ]; then
    rm /tmp/ept-vendor.tar.gz.sha256
fi
if [ -f "/tmp/ept-vendor.tar.gz.md5" ]; then
    rm /tmp/ept-vendor.tar.gz.md5
fi

# Reload Apache
apache2ctl -k graceful || systemctl reload apache2 || systemctl restart apache2

# Print summary
print header "Upgrade Summary"
if [ ${#updated_instances[@]} -gt 0 ]; then
    print success "Successfully updated ${#updated_instances[@]} instance(s):"
    for p in "${updated_instances[@]}"; do
        print info "  ✓ $p"
    done
fi
if [ ${#failed_instances[@]} -gt 0 ]; then
    print error "Failed to update ${#failed_instances[@]} instance(s):"
    for p in "${failed_instances[@]}"; do
        print error "  ✗ $p"
    done
fi

log_action "Upgrade complete. Updated: ${#updated_instances[@]}, Failed: ${#failed_instances[@]}"
