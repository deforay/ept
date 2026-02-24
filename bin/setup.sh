#!/bin/bash

# To use this script:
# cd ~;
# wget -O ept-setup.sh https://raw.githubusercontent.com/deforay/ept/master/bin/setup.sh
# sudo chmod u+x ept-setup.sh;
# sudo ./ept-setup.sh;

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

log_file="/tmp/ept-setup-$(date +'%Y%m%d-%H%M%S').log"

# Error trap
trap 'error_handling "${BASH_COMMAND}" "$LINENO" "$?"' ERR

# --- DB strategy resolution: env/flag/prompt ---
resolve_db_strategy() {
    local strategy="$1"        # from flag (optional)
    local env_strategy="${EPT_DB_STRATEGY:-}"
    local resolved=""

    # explicit CLI flag wins
    if [[ -n "$strategy" ]]; then
        resolved="$strategy"
    elif [[ -n "$env_strategy" ]]; then
        resolved="$env_strategy"
    fi

    # normalize
    case "$resolved" in
        drop|DROP)     resolved="drop"   ;;
        rename|RENAME) resolved="rename" ;;
        use|USE|keep|KEEP) resolved="use" ;;
        "") resolved="" ;;
        *)  echo "Unknown db strategy: $resolved"; resolved="";;
    esac

    echo "$resolved"
}

prompt_db_strategy() {
    local tty="/dev/tty"
    {
        echo
        echo "Existing ePT database detected. Choose what to do:"
        echo "  1) DROP   - delete current database and create a fresh one"
        echo "  2) RENAME - back up to ept_YYYYMMDD_HHMMSS and create fresh (default)"
        echo "  3) USE    - keep existing database as-is and skip import"
    } >"$tty"

    read -r -p "Enter choice [1=DROP, 2=RENAME(default), 3=USE]: " choice <"$tty"
    case "${choice:-2}" in
        1) echo "drop"   ;;
        2) echo "rename" ;;
        3) echo "use"    ;;
        *) echo "rename" ;;
    esac
}

mysql_exec() { mysql -e "$*"; }

handle_database_setup_and_import() {
    local db_name="$1"
    local sql_file="${2:-}"

    # Detect DB status
    local db_exists db_not_empty
    db_exists=$(mysql -sse "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='${db_name}';")
    db_not_empty=$(mysql -sse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${db_name}';")

    # Helper: rename + reset database
    perform_backup_rename() {
        echo "Backing up and resetting '${db_name}'..."
        log_action "Renaming existing '${db_name}' database to backup and recreating..."
        ts="$(date +%Y%m%d_%H%M%S)"
        new_db_name="${db_name}_${ts}"
        mysql_exec "CREATE DATABASE \`${new_db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"

        # Collect all base tables
        mapfile -t _tables < <(mysql -Nse "SELECT TABLE_NAME FROM information_schema.tables
                                        WHERE table_schema='${db_name}' AND TABLE_TYPE='BASE TABLE';")

        if ((${#_tables[@]})); then
            rename_sql="RENAME TABLE "
            sep=""
            for t in "${_tables[@]}"; do
                rename_sql+="${sep}\`${db_name}\`.\`${t}\` TO \`${new_db_name}\`.\`${t}\`"
                sep=", "
            done
            mysql_exec "SET FOREIGN_KEY_CHECKS=0; ${rename_sql}; SET FOREIGN_KEY_CHECKS=1;"
        fi

        # Recreate views in backup (strip DEFINER)
        while read -r view; do
            [[ -z "$view" ]] && continue
            def=$(mysql -Nse "SHOW CREATE VIEW \`${db_name}\`.\`${view}\`\G" | sed -n 's/^ *Create View: \(.*\)$/\1/p' | sed -E 's/DEFINER=`[^`]+`@`[^`]+` //')
            [[ -n "$def" ]] && mysql -D "${new_db_name}" -e "$def"
        done < <(mysql -Nse "SELECT TABLE_NAME FROM information_schema.views WHERE table_schema='${db_name}';")

        # Remove the now-empty schema and recreate fresh
        mysql_exec "DROP DATABASE \`${db_name}\`;"
        mysql_exec "CREATE DATABASE \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
        echo "Backup complete: ${new_db_name}"
    }

    local strategy
    strategy="$(resolve_db_strategy "$DB_STRATEGY_FLAG")"
    if [[ -z "$strategy" && "$db_exists" -eq 1 && "$db_not_empty" -gt 0 ]]; then
        strategy="$(prompt_db_strategy)"
    fi
    echo "Selected strategy: ${strategy:-create}"

    if [[ "$db_exists" -eq 1 && "$db_not_empty" -gt 0 ]]; then
        case "$strategy" in
            drop)
                echo "Dropping existing '${db_name}' database..."
                log_action "Dropping existing '${db_name}' database..."
                mysql_exec "SET FOREIGN_KEY_CHECKS=0; DROP DATABASE IF EXISTS \`${db_name}\`; SET FOREIGN_KEY_CHECKS=1;"
                mysql_exec "CREATE DATABASE \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
                ;;
            rename)
                perform_backup_rename
                ;;
            use)
                echo "Using existing '${db_name}' database as-is. Skipping schema import."
                log_action "Using existing ${db_name} database; skipping import."
                return 0
                ;;
            *)
                echo "No valid db strategy supplied; defaulting to RENAME."
                perform_backup_rename
                ;;
        esac
    else
        # Ensure DB exists if we got here with empty/non-existent db
        mysql -e "CREATE DATABASE IF NOT EXISTS \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
    fi

    # Import SQL file if provided
    if [[ -n "$sql_file" && -f "$sql_file" ]]; then
        echo "Importing base schema into '${db_name}' from: ${sql_file}"
        if [[ "$sql_file" == *".gz" ]]; then
            gunzip -c "$sql_file" | mysql "$db_name"
        elif [[ "$sql_file" == *".zip" ]]; then
            unzip -p "$sql_file" | mysql "$db_name"
        else
            mysql "$db_name" < "$sql_file"
        fi
        echo "Database import completed."
        log_action "Database import completed (strategy: ${strategy:-create})."
    elif [[ -f "${ept_path}/sql/init.sql" ]]; then
        echo "No SQL file provided. Using bundled init.sql..."
        mysql "$db_name" < "${ept_path}/sql/init.sql"
        echo "Database import completed (init.sql)."
        log_action "Database import completed using bundled init.sql (strategy: ${strategy:-create})."
    else
        echo "No SQL file provided and no bundled init.sql found. Database '${db_name}' created empty."
        echo "You can import the base SQL from https://github.com/deforay/ept/releases"
        log_action "Database ${db_name} created empty (no SQL file provided)."
    fi
}


# Save the current trap settings
current_trap=$(trap -p ERR)

# Disable the error trap temporarily
trap - ERR

echo "Enter the ePT installation path [press enter to select /var/www/ept]: "
read -t 60 ept_path

# Check if read command timed out or no input was provided
if [ $? -ne 0 ] || [ -z "$ept_path" ]; then
    ept_path="/var/www/ept"
    echo "Using default path: $ept_path"
else
    echo "ePT installation path is set to ${ept_path}."
fi

log_action "ePT installation path is set to ${ept_path}."

# Check if ePT is already installed at this path
if [ -d "${ept_path}" ] && [ -n "$(ls -A "${ept_path}" 2>/dev/null)" ]; then
    if [ -f "${ept_path}/composer.json" ] || [ -f "${ept_path}/constants.php" ]; then
        print warning "ePT installation detected at ${ept_path}"

        current_trap_temp=$(trap -p ERR)
        trap - ERR

        if ask_yes_no "An existing ePT installation was found. Do you want to proceed with setup (this will update/overwrite the installation)?" "yes"; then
            print info "Proceeding with setup. Existing installation will be backed up."
            log_action "User chose to proceed with setup over existing installation"
        else
            print info "Setup cancelled by user."
            log_action "Setup cancelled - existing installation found"
            exit 0
        fi

        eval "$current_trap_temp"
    fi
fi

# Restore the previous error trap
eval "$current_trap"

# Initialize variables
ept_sql_file=""
DB_STRATEGY_FLAG=""
ept_db_name="ept"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --database=*|--db=*)
            ept_sql_file="${1#*=}"
            shift
            ;;
        --database|--db)
            ept_sql_file="$2"
            shift 2
            ;;
        --db-name=*)
            ept_db_name="${1#*=}"
            shift
            ;;
        --db-name)
            ept_db_name="$2"
            shift 2
            ;;
        --db-strategy=*)
            DB_STRATEGY_FLAG="${1#*=}"
            shift
            ;;
        --db-strategy)
            DB_STRATEGY_FLAG="$2"
            shift 2
            ;;
        *)
            shift
            ;;
    esac
done

# Validate SQL file/URL if specified
if [[ -n "$ept_sql_file" ]]; then
    if [[ "$ept_sql_file" =~ ^https?:// ]]; then
        # Download the SQL file from URL
        sql_url="$ept_sql_file"
        sql_filename=$(basename "$sql_url" | sed 's/[?#].*//')
        # Preserve the original extension (.sql, .sql.gz, .gz, .zip)
        ept_sql_file="/tmp/${sql_filename}"

        print info "Downloading SQL file from: ${sql_url}"
        if ! download_file "$ept_sql_file" "$sql_url" "Downloading SQL file..."; then
            print error "Failed to download SQL file from: ${sql_url}"
            log_action "Failed to download SQL file from: ${sql_url}"
            exit 1
        fi
        print success "SQL file downloaded to: ${ept_sql_file}"
        log_action "SQL file downloaded from ${sql_url} to ${ept_sql_file}"
    else
        if [[ "$ept_sql_file" != /* ]]; then
            ept_sql_file="$(pwd)/$ept_sql_file"
        fi
        if [[ ! -f "$ept_sql_file" ]]; then
            echo "SQL file not found: $ept_sql_file. Please check the path."
            log_action "SQL file not found: $ept_sql_file. Please check the path."
            exit 1
        fi
    fi
fi

PHP_VERSION=8.4

#=============================================================================
# PHASE 1: LAMP STACK SETUP
#=============================================================================

print header "Setting up LAMP stack"

download_file "lamp-setup.sh" "https://raw.githubusercontent.com/deforay/utility-scripts/master/lamp/lamp-setup.sh" "Downloading lamp-setup.sh..." || {
    print error "LAMP setup file download failed - cannot continue"
    log_action "LAMP setup file download failed - setup aborted"
    exit 1
}

chmod u+x ./lamp-setup.sh
./lamp-setup.sh $PHP_VERSION
rm -f ./lamp-setup.sh

# Configure PHP INI settings (session timeout, opcache, security, etc.)
configure_php_ini "${PHP_VERSION}"

#=============================================================================
# PHASE 2: DOWNLOAD ePT
#=============================================================================

echo "Calculating checksums of current composer files..."
CURRENT_COMPOSER_JSON_CHECKSUM="none"
CURRENT_COMPOSER_LOCK_CHECKSUM="none"

if [ -f "${ept_path}/composer.json" ]; then
    CURRENT_COMPOSER_JSON_CHECKSUM=$(md5sum "${ept_path}/composer.json" | awk '{print $1}')
    echo "Current composer.json checksum: ${CURRENT_COMPOSER_JSON_CHECKSUM}"
fi

if [ -f "${ept_path}/composer.lock" ]; then
    CURRENT_COMPOSER_LOCK_CHECKSUM=$(md5sum "${ept_path}/composer.lock" | awk '{print $1}')
    echo "Current composer.lock checksum: ${CURRENT_COMPOSER_LOCK_CHECKSUM}"
fi

print header "Downloading ePT"

download_file "master.tar.gz" "https://codeload.github.com/deforay/ept/tar.gz/refs/heads/master" "Downloading ePT package..." || {
    print error "ePT download failed - cannot continue with setup"
    log_action "ePT download failed - setup aborted"
    exit 1
}

# Extract into temporary directory
temp_dir=$(mktemp -d)
print info "Extracting files from master.tar.gz..."

tar -xzf master.tar.gz -C "$temp_dir" &
tar_pid=$!
spinner "${tar_pid}"
wait ${tar_pid}

log_action "ePT downloaded."

# Create installation directory or backup existing installation
if [ ! -d "${ept_path}" ]; then
    mkdir -p "${ept_path}"
    log_action "Created fresh installation directory: ${ept_path}"
elif [ -n "$(ls -A "${ept_path}" 2>/dev/null)" ]; then
    print info "Existing installation detected. Creating selective backup..."
    backup_dir="${ept_path}-$(date +%Y%m%d-%H%M%S)"
    rsync -a \
        --exclude 'vendor/' \
        --exclude 'node_modules/' \
        --exclude 'application/cache/' \
        --exclude 'logs/' \
        --exclude 'public/temporary/' \
        --exclude 'public/uploads/' \
        --exclude 'downloads/' \
        --exclude 'backups/' \
        "${ept_path}/" "${backup_dir}/"
    log_action "Selective backup created: ${backup_dir}"
fi

# Copy the extracted content to the ePT path
rsync -a --info=progress2 "$temp_dir/ept-master/" "$ept_path/"

# Clean up
rm -rf "$temp_dir/ept-master/"
rm -f master.tar.gz

log_action "ePT copied to ${ept_path}."

# Set proper permissions
set_permissions "${ept_path}" "quick"

#=============================================================================
# PHASE 3: INSTALL DEPENDENCIES
#=============================================================================

print header "Running composer operations"
cd "${ept_path}"

sudo -u www-data composer config process-timeout 30000 --no-interaction
sudo -u www-data composer clear-cache --no-interaction

echo "Checking if composer dependencies need updating..."
NEED_FULL_INSTALL=false

if [ ! -d "${ept_path}/vendor" ]; then
    echo "Vendor directory doesn't exist. Full installation needed."
    NEED_FULL_INSTALL=true
else
    NEW_COMPOSER_JSON_CHECKSUM="none"
    NEW_COMPOSER_LOCK_CHECKSUM="none"

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
            echo "Composer files have changed. Full installation needed."
            NEED_FULL_INSTALL=true
        else
            echo "Composer files haven't changed. Skipping full installation."
        fi
    fi
fi

if [ "$NEED_FULL_INSTALL" = true ]; then
    print info "Dependency update needed. Checking for vendor packages..."

    if curl --output /dev/null --silent --head --fail "https://github.com/deforay/ept/releases/download/vendor-latest/vendor.tar.gz"; then
        download_file "vendor.tar.gz" "https://github.com/deforay/ept/releases/download/vendor-latest/vendor.tar.gz" "Downloading vendor packages..."

        # Download checksum
        download_file "vendor.tar.gz.sha256" "https://github.com/deforay/ept/releases/download/vendor-latest/vendor.tar.gz.sha256" "Downloading checksum..." 2>/dev/null || \
            download_file "vendor.tar.gz.md5" "https://github.com/deforay/ept/releases/download/vendor-latest/vendor.tar.gz.md5" "Downloading checksum..."

        # Verify checksum
        checksum_ok=false
        if [ -f "vendor.tar.gz.sha256" ]; then
            if sha256sum -c vendor.tar.gz.sha256 2>/dev/null; then
                checksum_ok=true
            fi
        elif [ -f "vendor.tar.gz.md5" ]; then
            if md5sum -c vendor.tar.gz.md5 2>/dev/null; then
                checksum_ok=true
            fi
        fi

        if [ "$checksum_ok" = true ]; then
            print success "Checksum verification passed"

            tar -xzf vendor.tar.gz -C "${ept_path}" &
            vendor_tar_pid=$!
            spinner "${vendor_tar_pid}" "Extracting vendor files..."
            wait ${vendor_tar_pid}

            chown -R www-data:www-data "${ept_path}/vendor" 2>/dev/null || true
            chmod -R 755 "${ept_path}/vendor" 2>/dev/null || true

            sudo -u www-data composer install --no-scripts --no-autoloader --prefer-dist --no-dev --no-interaction
        else
            print warning "Checksum verification failed. Running full composer install..."
            sudo -u www-data composer install --prefer-dist --no-dev --no-interaction
        fi

        rm -f vendor.tar.gz vendor.tar.gz.sha256 vendor.tar.gz.md5
    else
        print warning "Vendor package not found in GitHub releases. Running full composer install..."
        sudo -u www-data composer install --prefer-dist --no-dev --no-interaction
    fi
else
    print info "Dependencies are up to date. Skipping vendor download."
fi

sudo -u www-data composer dump-autoload -o --no-interaction
print success "Composer operations completed."
log_action "Composer operations completed."

#=============================================================================
# PHASE 4: APACHE VIRTUAL HOST
#=============================================================================

print header "Configuring Apache"

# Ask user for the hostname
read -r -p "Enter domain name (press enter to use 'ept'): " hostname

# Clean up the hostname
if [[ -n "$hostname" ]]; then
    hostname=$(echo "$hostname" | sed -E 's|^https?://||i')
    hostname=$(echo "$hostname" | sed -E 's|/*$||')
    hostname=$(echo "$hostname" | sed -E 's|:[0-9]+$||')
    hostname=$(echo "$hostname" | cut -d'/' -f1)
    if [[ -z "$hostname" ]]; then
        hostname="ept"
        print info "Using default hostname: $hostname"
    else
        print info "Using hostname: $hostname"
    fi
else
    hostname="ept"
    print info "Using default hostname: $hostname"
fi

log_action "Hostname: $hostname"

# Add hostname to /etc/hosts if not already present
if ! grep -q "127.0.0.1 ${hostname}" /etc/hosts; then
    print info "Adding ${hostname} to hosts file..."
    echo "127.0.0.1 ${hostname}" | tee -a /etc/hosts
    log_action "${hostname} entry added to hosts file."
else
    print info "${hostname} entry is already in the hosts file."
fi

# Create virtual host
vhost_file="/etc/apache2/sites-available/${hostname}.conf"

cat > "$vhost_file" <<VHOST
<VirtualHost *:80>
    ServerName ${hostname}
    DocumentRoot ${ept_path}/public

    <Directory ${ept_path}/public>
        AddDefaultCharset UTF-8
        Options -Indexes -MultiViews +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/ept-error.log
    CustomLog \${APACHE_LOG_DIR}/ept-access.log combined
</VirtualHost>
VHOST

a2ensite "${hostname}.conf"

# Restart Apache
restart_service apache || {
    print error "Failed to restart Apache. Please check the configuration."
    log_action "Failed to restart Apache."
    exit 1
}

# --- Optional SSL setup with Certbot ---
setup_ssl=false

# Check if hostname looks like a real domain (not localhost/ept/IP-only)
if [[ "$hostname" == *.* && "$hostname" != "localhost" && ! "$hostname" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    # Check if the server has a public IP by querying an external service
    public_ip=$(curl -s --max-time 5 https://ifconfig.me 2>/dev/null || echo "")

    if [[ -n "$public_ip" ]]; then
        print info "Public IP detected: ${public_ip}"

        current_trap_ssl=$(trap -p ERR)
        trap - ERR

        if ask_yes_no "Do you want to set up SSL (HTTPS) using Let's Encrypt for ${hostname}?" "no"; then
            setup_ssl=true
        fi

        eval "$current_trap_ssl"
    fi
fi

if [ "$setup_ssl" = true ]; then
    print info "Installing Certbot..."
    apt-get install -y certbot python3-certbot-apache >/dev/null 2>&1 || {
        print warning "Failed to install Certbot. Skipping SSL setup."
        setup_ssl=false
    }
fi

if [ "$setup_ssl" = true ]; then
    print info "Requesting SSL certificate for ${hostname}..."
    if certbot --apache -d "${hostname}" --non-interactive --agree-tos --register-unsafely-without-email --redirect; then
        print success "SSL certificate installed for ${hostname}"
        log_action "SSL certificate installed via Certbot for ${hostname}."
    else
        print warning "Certbot failed. You can retry later with: sudo certbot --apache -d ${hostname}"
        log_action "Certbot SSL setup failed for ${hostname}."
    fi
fi

#=============================================================================
# PHASE 5: CONFIGURE ePT
#=============================================================================

print header "Configuring ePT"

config_dir="${ept_path}/application/configs"

# application.ini
if [ ! -f "${config_dir}/application.ini" ]; then
    if [ -f "${config_dir}/application.dist.ini" ]; then
        print info "Creating application.ini from template..."
        cp "${config_dir}/application.dist.ini" "${config_dir}/application.ini"
        log_action "Created application.ini from template."
    else
        print error "application.dist.ini not found. Cannot create configuration."
        exit 1
    fi
else
    print info "application.ini already exists. Skipping."
fi

# config.ini
if [ ! -f "${config_dir}/config.ini" ]; then
    if [ -f "${config_dir}/config.dist.ini" ]; then
        print info "Creating config.ini from template..."
        cp "${config_dir}/config.dist.ini" "${config_dir}/config.ini"
        log_action "Created config.ini from template."
    else
        print warning "config.dist.ini not found. Skipping config.ini creation."
    fi
else
    print info "config.ini already exists. Skipping."
fi

# .env
if [ ! -f "${config_dir}/.env" ]; then
    print info "Generating .env with FORM_SECRET..."
    echo "FORM_SECRET=$(openssl rand -hex 32)" > "${config_dir}/.env"
    log_action "Generated .env with FORM_SECRET."
else
    print info ".env already exists. Skipping."
fi

# Ensure writable directories exist
mkdir -p "${ept_path}/application/cache"
mkdir -p "${ept_path}/logs"
mkdir -p "${ept_path}/downloads"
mkdir -p "${ept_path}/backups"
mkdir -p "${ept_path}/public/temporary"
mkdir -p "${ept_path}/public/uploads"

#=============================================================================
# PHASE 6: MYSQL SETUP
#=============================================================================

print header "Setting up MySQL"

# Extract MySQL root password or prompt
if [ -f ~/.my.cnf ]; then
    mysql_root_password=$(awk -F= '/^password[[:space:]]*=/{gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' ~/.my.cnf)
    if [ -n "$mysql_root_password" ]; then
        print info "Found existing MySQL credentials in ~/.my.cnf; verifying..."
        if MYSQL_PWD="${mysql_root_password}" mysql -u root -e "SELECT 1" >/dev/null 2>&1; then
            print success "MySQL root password verified."
        else
            print warning "Existing password did not work; prompting for a new one."
            mysql_root_password=""
        fi
    fi
fi

if [ -z "$mysql_root_password" ]; then
    while true; do
        read -r -sp "Enter MySQL root password: " mysql_root_password
        echo
        read -r -sp "Confirm MySQL root password: " mysql_root_password_confirm
        echo

        if [ "$mysql_root_password" != "$mysql_root_password_confirm" ]; then
            print error "Passwords do not match. Please try again."
        elif [ -z "$mysql_root_password" ]; then
            print error "Password cannot be empty. Please try again."
        else
            break
        fi
    done

    if ! MYSQL_PWD="${mysql_root_password}" mysqladmin ping -u root &>/dev/null; then
        print error "Unable to verify the password. Please check and try again."
        exit 1
    fi

    cat <<EOF >~/.my.cnf
[client]
user=root
password=${mysql_root_password}
host=localhost
EOF
    chmod 600 ~/.my.cnf
    print success "MySQL credentials saved."
fi

# Update application.ini with database credentials
app_ini="${config_dir}/application.ini"
if [ -f "$app_ini" ]; then
    # Update database settings
    sed -i "s|^resources.db.params.host\s*=.*|resources.db.params.host = 127.0.0.1|" "$app_ini"
    sed -i "s|^resources.db.params.username\s*=.*|resources.db.params.username = root|" "$app_ini"
    sed -i "s|^resources.db.params.password\s*=.*|resources.db.params.password = ${mysql_root_password}|" "$app_ini"
    sed -i "s|^resources.db.params.dbname\s*=.*|resources.db.params.dbname = ${ept_db_name}|" "$app_ini"

    # Update domain
    if [ "$setup_ssl" = true ]; then
        sed -i "s|^domain\s*=.*|domain = https://${hostname}/|" "$app_ini"
    else
        sed -i "s|^domain\s*=.*|domain = http://${hostname}/|" "$app_ini"
    fi

    # Generate a random security salt if still empty
    current_salt=$(grep "^security.salt" "$app_ini" | sed "s/^security.salt\s*=\s*//;s/^['\"]//;s/['\"]$//" | xargs)
    if [ -z "$current_salt" ]; then
        new_salt=$(openssl rand -hex 32)
        sed -i "s|^security.salt\s*=.*|security.salt = '${new_salt}'|" "$app_ini"
        print info "Generated security salt."
    fi

    print success "application.ini updated with database credentials and domain."
    log_action "application.ini updated."
fi

# Handle database setup and SQL import
handle_database_setup_and_import "$ept_db_name" "$ept_sql_file"

# MySQL configuration
mysql_cnf="/etc/mysql/mysql.conf.d/mysqld.cnf"
backup_timestamp=$(date +%Y%m%d%H%M%S)

declare -A mysql_settings=(
    ["sql_mode"]=""
    ["innodb_strict_mode"]="0"
    ["character-set-server"]="utf8mb4"
    ["collation-server"]="utf8mb4_0900_ai_ci"
    ["default_authentication_plugin"]="mysql_native_password"
    ["max_connect_errors"]="10000"
)

changes_needed=false
for setting in "${!mysql_settings[@]}"; do
    if ! grep -qE "^[[:space:]]*$setting[[:space:]]*=[[:space:]]*${mysql_settings[$setting]}" "$mysql_cnf"; then
        changes_needed=true
        break
    fi
done

if [ "$changes_needed" = true ]; then
    print info "Changes needed. Backing up and updating MySQL config..."
    cp "$mysql_cnf" "${mysql_cnf}.bak.${backup_timestamp}"

    for setting in "${!mysql_settings[@]}"; do
        if ! grep -qE "^[[:space:]]*$setting[[:space:]]*=[[:space:]]*${mysql_settings[$setting]}" "$mysql_cnf"; then
            if grep -qE "^[[:space:]]*$setting[[:space:]]*=" "$mysql_cnf"; then
                sed -i "/^[[:space:]]*$setting[[:space:]]*=.*/s/^/#/" "$mysql_cnf"
            fi
            echo "$setting = ${mysql_settings[$setting]}" >>"$mysql_cnf"
        fi
    done

    print info "Restarting MySQL service to apply changes..."
    restart_service mysql || {
        print error "Failed to restart MySQL. Restoring backup and exiting..."
        mv "${mysql_cnf}.bak.${backup_timestamp}" "$mysql_cnf"
        restart_service mysql
        exit 1
    }
    print success "MySQL configuration updated successfully."
else
    print success "MySQL configuration already correct. No changes needed."
fi

# Clean up old backup files
find "$(dirname "$mysql_cnf")" -maxdepth 1 -type f -name "$(basename "$mysql_cnf").bak.*" -exec rm -f {} \;

print info "Applying SET PERSIST sql_mode='' to override MySQL defaults..."
persist_result=$(MYSQL_PWD="${mysql_root_password}" mysql -u root -e "SET PERSIST sql_mode = '';" 2>&1)
persist_status=$?

if [ $persist_status -eq 0 ]; then
    print success "Successfully persisted sql_mode=''"
    log_action "Applied SET PERSIST sql_mode = '';"
else
    print warning "SET PERSIST failed: $persist_result"
    log_action "SET PERSIST sql_mode failed: $persist_result"
fi

chmod 644 "$mysql_cnf"

#=============================================================================
# PHASE 7: DATABASE MIGRATIONS & POST-INSTALL
#=============================================================================

print header "Running database migrations and post-install tasks"

# Test database connectivity
php "${ept_path}/vendor/bin/db-tools" db:test --all

# Run migrations
print info "Running database migrations..."
cd "${ept_path}"
sudo -u www-data composer post-update

# Run one-time scripts
print info "Running run-once scripts..."
sudo -u www-data php "${ept_path}/bin/run-once.php"

# Seed initial admin account (only if system_admin table is empty)
admin_count=$(mysql -Nse "SELECT COUNT(*) FROM \`${ept_db_name}\`.system_admin WHERE 1;" 2>/dev/null || echo "-1")
if [ "$admin_count" -eq 0 ]; then
    print info "No admin accounts found. Starting admin setup..."
    php "${ept_path}/bin/seed-admin.php"
elif [ "$admin_count" -eq -1 ]; then
    print warning "system_admin table not found. Skipping admin seed."
else
    print info "Admin accounts already exist. Skipping seed."
fi

#=============================================================================
# PHASE 8: CRON & PERMISSIONS
#=============================================================================

print header "Setting up scheduled jobs and permissions"

# Set up cron job
setup_cron "${ept_path}"

# Make runner script executable and install globally
if [ -f "${ept_path}/runner" ]; then
    chmod +x "${ept_path}/runner"
    rm -f /usr/local/bin/runner 2>/dev/null || true
    ln -s "${ept_path}/runner" /usr/local/bin/runner 2>/dev/null

    if [ -L "/usr/local/bin/runner" ]; then
        print success "ept runner command installed globally"
    fi
fi

# Install ept-update command
download_file "/usr/local/bin/ept-update" "https://raw.githubusercontent.com/deforay/ept/master/bin/upgrade.sh" "Installing ept-update command..."
chmod +x /usr/local/bin/ept-update

# Set final permissions
(print info "Setting final permissions in the background..." &&
    set_permissions "${ept_path}" "full" >/dev/null 2>&1) &
disown

restart_service apache

#=============================================================================
# DONE
#=============================================================================

print header "Setup Complete"
print success "ePT has been installed at ${ept_path}"
print info "Access the admin panel at: http://${hostname}/admin"
print info "To upgrade in the future, run: sudo ept-update"
log_action "ePT setup complete."
