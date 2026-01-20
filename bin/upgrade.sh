#!/bin/bash

# To use this script:
# sudo wget -O /usr/local/bin/ept-update https://raw.githubusercontent.com/deforay/ept/master/bin/upgrade.sh && sudo chmod +x /usr/local/bin/ept-update
# sudo ept-update

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
ept_path=""

log_file="/tmp/ept-upgrade-$(date +'%Y%m%d-%H%M%S').log"

# Parse command-line options
while getopts ":sbp:" opt; do
    case $opt in
        s) skip_ubuntu_updates=true ;;
        b) skip_backup=true ;;
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

# Prompt for the EPT path if not provided via the command-line argument
if [ -z "$ept_path" ]; then
    echo "Enter the EPT installation path [press enter for /var/www/ept]: "
    if read -t 60 ept_path && [ -n "$ept_path" ]; then
        :
    else
        ept_path=""
    fi
fi

# Resolve EPT path
ept_path="$(resolve_ept_path "$ept_path")"

print info "EPT path is set to ${ept_path}"
log_action "EPT path is set to ${ept_path}"

# Check if the EPT path is valid
if ! is_valid_application_path "$ept_path"; then
    print error "The specified path does not appear to be a valid EPT installation. Please check the path and try again."
    log_action "Invalid EPT path specified: $ept_path"
    exit 1
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

# Determine which password to use (from INI or prompt)
if [ -n "$mysql_root_password" ]; then
    mysql_pw="$mysql_root_password"
    print info "Using user-provided MySQL root password"
elif [ -f "${ept_path}/application/configs/application.ini" ]; then
    mysql_pw="$(extract_mysql_password_from_config "${ept_path}/application/configs/application.ini" production || true)"
    mysql_pw="$(sanitize_ini_secret "$mysql_pw")"
    ini_user="$(extract_mysql_user_from_config "${ept_path}/application/configs/application.ini" production || true)"
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
php_version="${desired_php_version}"

# Modify php.ini as needed
print header "Configuring PHP"

desired_error_reporting="error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING"
desired_post_max_size="post_max_size = 1G"
desired_upload_max_filesize="upload_max_filesize = 1G"
desired_strict_mode="session.use_strict_mode = 1"
desired_opcache_enable="opcache.enable=1"
desired_opcache_enable_cli="opcache.enable_cli=0"
desired_opcache_memory="opcache.memory_consumption=256"
desired_opcache_max_files="opcache.max_accelerated_files=40000"
desired_opcache_validate="opcache.validate_timestamps=0"
desired_opcache_jit="opcache.jit=disable"
desired_opcache_interned="opcache.interned_strings_buffer=16"
desired_opcache_override="opcache.enable_file_override=1"

update_php_ini() {
    local ini_file=$1
    local timestamp
    timestamp=$(date +%Y%m%d%H%M%S)
    local backup_file="${ini_file}.bak.${timestamp}"
    local changes_needed=false

    print info "Checking PHP settings in $ini_file..."

    local er_set pms_set umf_set sm_set
    local opcache_enable_set opcache_enable_cli_set opcache_memory_set opcache_max_files_set opcache_validate_set opcache_jit_set
    local opcache_interned_set opcache_override_set

    er_set=$(grep -q "^${desired_error_reporting}$" "$ini_file" && echo true || echo false)
    pms_set=$(grep -q "^${desired_post_max_size}$" "$ini_file" && echo true || echo false)
    umf_set=$(grep -q "^${desired_upload_max_filesize}$" "$ini_file" && echo true || echo false)
    sm_set=$(grep -q "^${desired_strict_mode}$" "$ini_file" && echo true || echo false)
    opcache_enable_set=$(grep -q "^${desired_opcache_enable}$" "$ini_file" && echo true || echo false)
    opcache_enable_cli_set=$(grep -q "^${desired_opcache_enable_cli}$" "$ini_file" && echo true || echo false)
    opcache_memory_set=$(grep -q "^${desired_opcache_memory}$" "$ini_file" && echo true || echo false)
    opcache_max_files_set=$(grep -q "^${desired_opcache_max_files}$" "$ini_file" && echo true || echo false)
    opcache_validate_set=$(grep -q "^${desired_opcache_validate}$" "$ini_file" && echo true || echo false)
    opcache_jit_set=$(grep -q "^${desired_opcache_jit}$" "$ini_file" && echo true || echo false)
    opcache_interned_set=$(grep -q "^${desired_opcache_interned}$" "$ini_file" && echo true || echo false)
    opcache_override_set=$(grep -q "^${desired_opcache_override}$" "$ini_file" && echo true || echo false)

    if [ "$er_set" = false ] || [ "$pms_set" = false ] || [ "$umf_set" = false ] || [ "$sm_set" = false ] \
      || [ "$opcache_enable_set" = false ] || [ "$opcache_enable_cli_set" = false ] || [ "$opcache_memory_set" = false ] \
      || [ "$opcache_max_files_set" = false ] || [ "$opcache_validate_set" = false ] || [ "$opcache_jit_set" = false ] \
      || [ "$opcache_interned_set" = false ] || [ "$opcache_override_set" = false ]; then
        changes_needed=true
        cp "$ini_file" "$backup_file"
        print info "Changes needed. Backup created at $backup_file"
    fi

    if [ "$changes_needed" = true ]; then
        local temp_file
        temp_file=$(mktemp)

        while IFS= read -r line; do
            if [[ "$line" =~ ^[[:space:]]*error_reporting[[:space:]]*= ]] && [ "$er_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_error_reporting" >>"$temp_file"; er_set=true
            elif [[ "$line" =~ ^[[:space:]]*post_max_size[[:space:]]*= ]] && [ "$pms_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_post_max_size" >>"$temp_file"; pms_set=true
            elif [[ "$line" =~ ^[[:space:]]*upload_max_filesize[[:space:]]*= ]] && [ "$umf_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_upload_max_filesize" >>"$temp_file"; umf_set=true
            elif [[ "$line" =~ ^[[:space:]]*session\.use_strict_mode[[:space:]]*= ]] && [ "$sm_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_strict_mode" >>"$temp_file"; sm_set=true
            elif [[ "$line" =~ ^[[:space:]]*opcache\.enable[[:space:]]*= ]] && [ "$opcache_enable_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_opcache_enable" >>"$temp_file"; opcache_enable_set=true
            elif [[ "$line" =~ ^[[:space:]]*opcache\.enable_cli[[:space:]]*= ]] && [ "$opcache_enable_cli_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_opcache_enable_cli" >>"$temp_file"; opcache_enable_cli_set=true
            elif [[ "$line" =~ ^[[:space:]]*opcache\.memory_consumption[[:space:]]*= ]] && [ "$opcache_memory_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_opcache_memory" >>"$temp_file"; opcache_memory_set=true
            elif [[ "$line" =~ ^[[:space:]]*opcache\.max_accelerated_files[[:space:]]*= ]] && [ "$opcache_max_files_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_opcache_max_files" >>"$temp_file"; opcache_max_files_set=true
            elif [[ "$line" =~ ^[[:space:]]*opcache\.validate_timestamps[[:space:]]*= ]] && [ "$opcache_validate_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_opcache_validate" >>"$temp_file"; opcache_validate_set=true
            elif [[ "$line" =~ ^[[:space:]]*opcache\.jit[[:space:]]*= ]] && [ "$opcache_jit_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_opcache_jit" >>"$temp_file"; opcache_jit_set=true
            elif [[ "$line" =~ ^[[:space:]]*opcache\.interned_strings_buffer[[:space:]]*= ]] && [ "$opcache_interned_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_opcache_interned" >>"$temp_file"; opcache_interned_set=true
            elif [[ "$line" =~ ^[[:space:]]*opcache\.enable_file_override[[:space:]]*= ]] && [ "$opcache_override_set" = false ]; then
                echo ";$line" >>"$temp_file"; echo "$desired_opcache_override" >>"$temp_file"; opcache_override_set=true
            else
                echo "$line" >>"$temp_file"
            fi
        done <"$ini_file"

        # Append any directives that were entirely missing
        [ "$er_set" = true ] || echo "$desired_error_reporting" >>"$temp_file"
        [ "$pms_set" = true ] || echo "$desired_post_max_size" >>"$temp_file"
        [ "$umf_set" = true ] || echo "$desired_upload_max_filesize" >>"$temp_file"
        [ "$sm_set" = true ] || echo "$desired_strict_mode" >>"$temp_file"
        [ "$opcache_enable_set" = true ] || echo "$desired_opcache_enable" >>"$temp_file"
        [ "$opcache_enable_cli_set" = true ] || echo "$desired_opcache_enable_cli" >>"$temp_file"
        [ "$opcache_memory_set" = true ] || echo "$desired_opcache_memory" >>"$temp_file"
        [ "$opcache_max_files_set" = true ] || echo "$desired_opcache_max_files" >>"$temp_file"
        [ "$opcache_validate_set" = true ] || echo "$desired_opcache_validate" >>"$temp_file"
        [ "$opcache_jit_set" = true ] || echo "$desired_opcache_jit" >>"$temp_file"
        [ "$opcache_interned_set" = true ] || echo "$desired_opcache_interned" >>"$temp_file"
        [ "$opcache_override_set" = true ] || echo "$desired_opcache_override" >>"$temp_file"

        mv "$temp_file" "$ini_file"
        print success "Updated PHP settings in $ini_file"

        # Remove backup once successful
        [ -f "$backup_file" ] && rm "$backup_file" && print info "Removed backup file $backup_file"
    else
        print info "PHP settings are already correctly set in $ini_file"
    fi
}

# Ensure opcache is present & enabled for mod_php
if ! php -m | grep -qi '^opcache$'; then
    print info "Installing/enabling OPcache for PHP ${desired_php_version} (mod_php)..."
    apt-get update -y
    apt-get install -y "php${desired_php_version}-opcache" || true
    phpenmod -v "${desired_php_version}" -s ALL opcache 2>/dev/null || phpenmod opcache 2>/dev/null || true
fi

# Apply changes to PHP configuration files
for phpini in /etc/php/${php_version}/apache2/php.ini /etc/php/${php_version}/cli/php.ini; do
    if [ -f "$phpini" ]; then
        update_php_ini "$phpini"
    else
        print warning "PHP configuration file not found: $phpini"
    fi
done

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

# set_permissions "${ept_path}" "quick"
set_permissions "${ept_path}/logs" "full"

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

    if ask_yes_no "Do you want to backup the EPT folder before updating?" "no"; then
        print info "Backing up old EPT folder..."
        timestamp=$(date +%Y%m%d-%H%M%S)
        backup_folder="/var/ept-backup/www/ept-backup-$timestamp"
        mkdir -p "${backup_folder}"
        rsync -a --delete --exclude "public/temporary/" --inplace --whole-file --info=progress2 "${ept_path}/" "${backup_folder}/" &
        rsync_pid=$!
        spinner "${rsync_pid}"
        log_action "EPT folder backed up to ${backup_folder}"
    else
        print info "Skipping EPT folder backup as per user request."
        log_action "Skipping EPT folder backup as per user request."
    fi
fi

[ -d "${ept_path}/run-once" ] && rm -rf "${ept_path}/run-once"

print info "Calculating checksums of current composer files..."
CURRENT_COMPOSER_JSON_CHECKSUM="none"
CURRENT_COMPOSER_LOCK_CHECKSUM="none"

if [ -f "${ept_path}/composer.json" ]; then
    CURRENT_COMPOSER_JSON_CHECKSUM=$(md5sum "${ept_path}/composer.json" | awk '{print $1}')
    print info "Current composer.json checksum: ${CURRENT_COMPOSER_JSON_CHECKSUM}"
fi
if [ -f "${ept_path}/composer.lock" ]; then
    CURRENT_COMPOSER_LOCK_CHECKSUM=$(md5sum "${ept_path}/composer.lock" | awk '{print $1}')
    print info "Current composer.lock checksum: ${CURRENT_COMPOSER_LOCK_CHECKSUM}"
fi

print header "Downloading EPT"

download_file "master.tar.gz" "https://codeload.github.com/deforay/ept/tar.gz/refs/heads/master" "Downloading EPT package..." || {
    print error "EPT download failed - cannot continue with update"
    log_action "EPT download failed - update aborted"
    exit 1
}

# Extract the tar.gz file into temporary directory
temp_dir=$(mktemp -d)
print info "Extracting files from master.tar.gz..."

tar -xzf master.tar.gz -C "$temp_dir" &
tar_pid=$!
spinner "${tar_pid}"
wait ${tar_pid}

# Build symlink exclude list (safe, no eval; handles spaces)
exclude_file="$(mktemp)"
symlinks_found=0
while IFS= read -r -d '' symlink; do
    rel="${symlink#"$ept_path/"}"
    printf '%s\n' "$rel" >>"$exclude_file"
    symlinks_found=$((symlinks_found + 1))
done < <(find "$ept_path" -type l -not -path "*/.*" -print0 2>/dev/null)

print info "Found $symlinks_found symlinks that will be preserved."

# Sync files, preserving symlinks in destination
rsync -a --inplace --whole-file --info=progress2 \
    --exclude-from="$exclude_file" \
    "$temp_dir/ept-master/" "$ept_path/" &
rsync_pid=$!
spinner "${rsync_pid}"
wait ${rsync_pid}; rsync_status=$?

rm -f "$exclude_file"

if [ $rsync_status -ne 0 ]; then
    print error "Error occurred during rsync. Logging and continuing..."
    log_action "Error during rsync operation. Path was: $ept_path"
else
    print success "Files copied successfully, preserving symlinks where necessary."
    log_action "Files copied successfully."
fi

# Cleanup temp & tar
[ -d "$temp_dir/ept-master/" ] && rm -rf "$temp_dir/ept-master/"
[ -d "$temp_dir" ] && rm -rf "$temp_dir"
[ -f master.tar.gz ] && rm master.tar.gz

print success "EPT copied to ${ept_path}."
log_action "EPT copied to ${ept_path}."

# Set proper permissions
set_permissions "${ept_path}" "quick"

# Run Composer Install as www-data
print header "Running composer operations"
cd "${ept_path}" || exit 1

sudo -u www-data composer config process-timeout 30000
sudo -u www-data composer clear-cache

echo "Checking if composer dependencies need updating..."
NEED_FULL_INSTALL=false

if [ ! -d "${ept_path}/vendor" ]; then
    print info "Vendor directory doesn't exist. Full installation needed."
    NEED_FULL_INSTALL=true
else
    NEW_COMPOSER_JSON_CHECKSUM="none"
    NEW_COMPOSER_LOCK_CHECKSUM="none"

    if [ -f "${ept_path}/composer.json" ]; then
        NEW_COMPOSER_JSON_CHECKSUM=$(md5sum "${ept_path}/composer.json" 2>/dev/null | awk '{print $1}')
        print info "New composer.json checksum: ${NEW_COMPOSER_JSON_CHECKSUM}"
    else
        print warning "composer.json is missing after extraction. Full installation needed."
        NEED_FULL_INSTALL=true
    fi

    if [ -f "${ept_path}/composer.lock" ] && [ "$NEED_FULL_INSTALL" = false ]; then
        NEW_COMPOSER_LOCK_CHECKSUM=$(md5sum "${ept_path}/composer.lock" 2>/dev/null | awk '{print $1}')
        print info "New composer.lock checksum: ${NEW_COMPOSER_LOCK_CHECKSUM}"
    else
        print warning "composer.lock is missing after extraction. Full installation needed."
        NEED_FULL_INSTALL=true
    fi

    if [ "$NEED_FULL_INSTALL" = false ]; then
        if [ "$CURRENT_COMPOSER_JSON_CHECKSUM" = "none" ] || [ "$CURRENT_COMPOSER_LOCK_CHECKSUM" = "none" ] ||
           [ "$NEW_COMPOSER_JSON_CHECKSUM" = "none" ] || [ "$NEW_COMPOSER_LOCK_CHECKSUM" = "none" ] ||
           [ "$CURRENT_COMPOSER_JSON_CHECKSUM" != "$NEW_COMPOSER_JSON_CHECKSUM" ] ||
           [ "$CURRENT_COMPOSER_LOCK_CHECKSUM" != "$NEW_COMPOSER_LOCK_CHECKSUM" ]; then
            print info "Composer files have changed or were missing. Full installation needed."
            NEED_FULL_INSTALL=true
        else
            print info "Composer files haven't changed. Skipping full installation."
            NEED_FULL_INSTALL=false
        fi
    fi
fi

# Download vendor.tar.gz if needed
if [ "$NEED_FULL_INSTALL" = true ]; then
    print info "Dependency update needed. Checking for vendor packages..."

    if curl --output /dev/null --silent --head --fail "https://github.com/deforay/ept/releases/download/vendor-latest/vendor.tar.gz"; then
        # Download tar + checksums (with cache-bust)
        download_file "vendor.tar.gz" "https://github.com/deforay/ept/releases/download/vendor-latest/vendor.tar.gz" "Downloading vendor packages..." || { print error "Failed to download vendor.tar.gz"; exit 1; }

        if download_file "vendor.tar.gz.sha256" "https://github.com/deforay/ept/releases/download/vendor-latest/vendor.tar.gz.sha256" "Downloading SHA-256..." ; then
            print info "Verifying SHA-256..."
            if ! sha256sum -c vendor.tar.gz.sha256; then
                print error "SHA-256 verification failed"; exit 1
            fi
            rm -f vendor.tar.gz.sha256
        else
            print warning "SHA-256 not available; falling back to MD5."
            download_file "vendor.tar.gz.md5" "https://github.com/deforay/ept/releases/download/vendor-latest/vendor.tar.gz.md5" "Downloading MD5..." || { print error "Failed to download vendor.tar.gz.md5"; exit 1; }
            print info "Verifying MD5..."
            if ! md5sum -c vendor.tar.gz.md5; then
                print error "MD5 verification failed"; exit 1
            fi
            rm -f vendor.tar.gz.md5
        fi
        print success "Checksum verification passed"

        print info "Extracting files from vendor.tar.gz..."
        tar -xzf vendor.tar.gz -C "${ept_path}" &
        vendor_tar_pid=$!
        spinner "${vendor_tar_pid}" "Extracting vendor files..."
        wait ${vendor_tar_pid}; vendor_tar_status=$?

        if [ $vendor_tar_status -ne 0 ]; then
            print error "Failed to extract vendor.tar.gz"
            exit 1
        fi

        rm -f vendor.tar.gz

        # Fix permissions on the vendor directory
        print info "Setting permissions on vendor directory..."
        chown -R www-data:www-data "${ept_path}/vendor" 2>/dev/null || true
        chmod -R 755 "${ept_path}/vendor" 2>/dev/null || true

        print success "Vendor files successfully installed"

        # Finalize composer (no network build)
        print info "Finalizing composer installation..."
        sudo -u www-data composer install --no-scripts --no-autoloader --prefer-dist --no-dev
    else
        print warning "Vendor package not found in GitHub releases. Proceeding with regular composer install."
        print info "Running full composer install (this may take a while)..."
        sudo -u www-data composer install --prefer-dist --no-dev
    fi
else
    print info "Dependencies are up to date. Skipping vendor download."
fi

# Always generate the optimized autoloader
sudo -u www-data composer dump-autoload -o

print success "Composer operations completed."
log_action "Composer operations completed."

apache2ctl -k graceful || systemctl reload apache2

# Setup db-tools with config from application.ini
print header "Testing database connection"
php "${ept_path}/vendor/bin/db-tools" db:test --all

# Run the database migrations and other post-update tasks
print header "Running database migrations and other post-update tasks"
sudo -u www-data composer post-update &
pid=$!
spinner "$pid"
wait $pid

print success "Database migrations and post-update tasks completed."
log_action "Database migrations and post-update tasks completed."

# Run any run-once scripts for this EPT path
print header "Running run-once scripts"
sudo -u www-data php "${ept_path}/bin/run-once.php"

# Make the runner script executable
chmod +x "${ept_path}/runner" 2>/dev/null
sudo rm /usr/local/bin/runner 2>/dev/null || true
sudo ln -s "${ept_path}/runner" /usr/local/bin/runner 2>/dev/null

if [ -L "/usr/local/bin/runner" ]; then
    print success "✅ ept runner command installed successfully!"
    print info "You can now use: runner migrate, runner tasks etc."
fi

# Cron job setup
setup_cron "${ept_path}"

apache2ctl -k graceful || systemctl reload apache2

print success "EPT update complete."
log_action "EPT update complete."
