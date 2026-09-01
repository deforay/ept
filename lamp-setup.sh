#!/bin/bash

# To use this script:
# sudo -s
# cd /tmp
# wget -O ./lamp-setup.sh https://raw.githubusercontent.com/deforay/utility-scripts/master/lamp/lamp-setup.sh
# chmod +x ./lamp-setup.sh
# ./lamp-setup.sh [PHP_VERSION]
# ./lamp-setup.sh [PHP_VERSION] -f
# ./lamp-setup.sh [PHP_VERSION] --force

#=============================================================================
# UTILITY FUNCTIONS
#=============================================================================

# Define a unified print function that colors the entire message
print() {
    local type=$1
    local message=$2

    case $type in
    error)
        echo -e "\033[0;31mError: $message\033[0m"
        ;;
    success)
        echo -e "\033[0;32mSuccess: $message\033[0m"
        ;;
    warning)
        echo -e "\033[0;33mWarning: $message\033[0m"
        ;;
    info)
        echo -e "\033[0;36mInfo: $message\033[0m"
        ;;
    debug)
        echo -e "\033[1;36mDebug: $message\033[0m"
        ;;
    header)
        echo -e "\033[1;36m==== $message ====\033[0m"
        ;;
    *)
        echo "$message"
        ;;
    esac
}

ask_yes_no() {
    local timeout=15
    local default=${2:-"no"}
    local answer=""

    while true; do
        echo -n "$1 (y/n): "
        read -t $timeout answer
        if [ $? -ne 0 ]; then
            answer=$default
        fi

        answer=$(echo "$answer" | awk '{print tolower($0)}')
        case "$answer" in
        "yes" | "y") return 0 ;;
        "no" | "n") return 1 ;;
        *)
            if [ -z "$answer" ]; then
                if [ "$default" == "yes" ] || [ "$default" == "y" ]; then
                    return 0
                else
                    return 1
                fi
            else
                echo "Invalid response. Please answer 'yes/y' or 'no/n'."
            fi
            ;;
        esac
    done
}

# Function to log messages
log_action() {
    local message=$1
    echo "$(date +'%Y-%m-%d %H:%M:%S') - $message" >>~/logsetup.log
}

error_handling() {
    local last_cmd=$1
    local last_line=$2
    local last_error=$3
    print error "Error on or near line ${last_line}; command executed was '${last_cmd}' which exited with status ${last_error}"
    log_action "Error on or near line ${last_line}; command executed was '${last_cmd}' which exited with status ${last_error}"

    if [ "$last_error" -eq 1 ]; then
        print error "This error is critical, exiting..."
        exit 1
    else
        print info "This error is not critical, continuing..."
    fi
}

#=============================================================================
# VALIDATION FUNCTIONS
#=============================================================================

check_root_privileges() {
    if [ "$EUID" -ne 0 ]; then
        print error "Need admin privileges for this script. Run sudo -s before running this script or run this script with sudo"
        exit 1
    fi
}

is_valid_ubuntu() {
    if ! lsb_release -d | grep -q "LTS"; then
        print error "This script only runs on Ubuntu LTS releases."
        return 1
    fi

    local min_version="22.04"
    local current_version=$(lsb_release -rs)

    if [[ "$(printf '%s\n' "$min_version" "$current_version" | sort -V | head -n1)" != "$min_version" ]]; then
        print error "This script requires Ubuntu ${min_version} LTS or newer."
        return 1
    fi

    return 0
}

# Pick the default PHP major.minor for the running OS when the caller didn't
# pass a version. Ubuntu <=24.04 carries (and ondrej's PPA still builds) PHP
# 8.4; Ubuntu 26.04 dropped 8.4 and ships 8.5, so default to 8.5 there. Any
# explicit version passed on the command line always wins over this.
default_php_version_for_os() {
    local ubuntu_version
    ubuntu_version=$(lsb_release -rs 2>/dev/null || echo "")

    # Can't detect the release -> stick with the long-standing baseline.
    if [[ -z "$ubuntu_version" ]]; then
        echo "8.4"
        return
    fi

    # 26.04 and newer no longer provide PHP 8.4; use 8.5.
    if [[ "$(printf '%s\n' "26.04" "$ubuntu_version" | sort -V | head -n1)" == "26.04" ]]; then
        echo "8.5"
    else
        echo "8.4"
    fi
}

check_dependencies() {
    for cmd in "apt-get"; do
        if ! command -v $cmd &>/dev/null; then
            print error "$cmd is not installed. Exiting..."
            exit 1
        fi
    done
}

#=============================================================================
# SYSTEM SETUP FUNCTIONS
#=============================================================================

setup_system() {
    print header "Setting up system basics..."

    # Update Ubuntu Packages (always safe to run)
    print info "Updating Ubuntu packages..."
    apt-get update && apt-get upgrade -y

    # Configure any packages that were not fully installed (always safe)
    print info "Configuring any partially installed packages..."
    dpkg --configure -a

    # Clean up (always safe)
    apt-get autoremove -y

    # Check if basic packages are already installed
    print info "Checking and installing basic packages..."
    local packages_to_install=()
    local basic_packages=(build-essential software-properties-common gnupg apt-transport-https ca-certificates lsb-release wget vim zip unzip curl acl snapd rsync git gdebi net-tools sed mawk magic-wormhole openssh-server libsodium-dev mosh aria2 wget lsb-release bc pigz gpg certbot python3-certbot-apache)

    for package in "${basic_packages[@]}"; do
        if ! dpkg -l | grep -q "^ii  $package "; then
            packages_to_install+=("$package")
        fi
    done

    if [ ${#packages_to_install[@]} -gt 0 ]; then
        print info "Installing missing packages: ${packages_to_install[*]}"
        apt-get install -y "${packages_to_install[@]}"
    else
        print info "All basic packages are already installed"
    fi

    # Add ondrej/apache2 only if Launchpad publishes the current codename.
    # Cross-codename installs break due to ABI mismatches (libxml2 t64 etc.),
    # so on unsupported Ubuntu releases we stick with the distro apache2.
    local apache_ppa="ondrej/apache2"
    local codename
    codename=$(lsb_release -cs)
    local apache_list="/etc/apt/sources.list.d/ondrej-ubuntu-apache2-${codename}.list"
    local apache_sources="/etc/apt/sources.list.d/ondrej-ubuntu-apache2-${codename}.sources"

    if [[ -f "${apache_list}.disabled" || -f "${apache_sources}.disabled" ]]; then
        print info "${apache_ppa} previously disabled on '${codename}'; using distro apache2."
    elif grep -q "ondrej/apache2" /etc/apt/sources.list /etc/apt/sources.list.d/* 2>/dev/null; then
        print info "${apache_ppa} PPA already added"
        disable_apache2_ppa_if_unsupported "$codename" "$apache_list" "$apache_sources"
    elif ! ppa_has_codename "$apache_ppa" "$codename"; then
        print warning "${apache_ppa} has no build for '${codename}'; skipping PPA, using distro apache2."
    else
        print info "Adding ${apache_ppa} PPA..."
        add-apt-repository ppa:ondrej/apache2 -y
        apt-get update
    fi
}

ppa_has_codename() {
    local ppa_path="$1" cn="$2"
    curl -fsSLI -o /dev/null --max-time 10 \
        "https://ppa.launchpadcontent.net/${ppa_path}/ubuntu/dists/${cn}/Release" 2>/dev/null
}

# If ondrej/apache2 doesn't publish $codename, disable its source files so apt
# falls back to the distro archive. Heals a previous noble-pin by restoring
# the codename inside the file to match its filename suffix before disabling.
disable_apache2_ppa_if_unsupported() {
    local codename="$1" apache_list="$2" apache_sources="$3"

    if ppa_has_codename "ondrej/apache2" "$codename"; then
        [[ -f "$apache_sources" ]] && sed -i -E "s/^(Suites:[[:space:]]*).*/\1${codename}/" "$apache_sources" 2>/dev/null || true
        [[ -f "$apache_list" ]]    && sed -i -E "s|(ubuntu[[:space:]]+)[a-zA-Z]+([[:space:]]+main)|\1${codename}\2|" "$apache_list" 2>/dev/null || true
        return 0
    fi

    print warning "ondrej/apache2 has no build for '${codename}'; disabling PPA, falling back to distro apache2."
    [[ -f "$apache_sources" ]] && sed -i -E "s/^(Suites:[[:space:]]*).*/\1${codename}/" "$apache_sources" 2>/dev/null || true
    [[ -f "$apache_list" ]]    && sed -i -E "s|(ubuntu[[:space:]]+)[a-zA-Z]+([[:space:]]+main)|\1${codename}\2|" "$apache_list" 2>/dev/null || true
    [[ -f "$apache_sources" ]] && mv "$apache_sources" "${apache_sources}.disabled"
    [[ -f "$apache_list" ]]    && mv "$apache_list" "${apache_list}.disabled"
    apt-get update || true
}

setup_locale() {
    print header "Setting up locale..."

    # Check if locale is already generated
    if locale -a | grep -q "en_US.utf8"; then
        print info "en_US.UTF-8 locale already generated"
    else
        print info "Generating en_US.UTF-8 locale..."
        locale-gen en_US en_US.UTF-8
    fi

    # Set environment variables (always safe to do)
    export LANG=en_US.UTF-8
    export LC_ALL=en_US.UTF-8

    # Update locale configuration (idempotent)
    update-locale LANG=en_US.UTF-8 LC_ALL=en_US.UTF-8
}

setup_ssh() {
    print header "Setting up SSH..."

    if ! systemctl is-enabled ssh >/dev/null 2>&1; then
        print info "Enabling SSH service..."
        systemctl enable ssh
    else
        print info "SSH service is already enabled."
    fi

    if ! systemctl is-active ssh >/dev/null 2>&1; then
        print info "Starting SSH service..."
        systemctl start ssh
    else
        print info "SSH service is already running."
    fi
}

#=============================================================================
# APACHE SETUP FUNCTIONS
#=============================================================================

install_apache() {
    print header "Setting up Apache..."

    if command -v apache2 &>/dev/null; then
        print info "Apache is already installed. Checking configuration..."
    else
        print info "Installing Apache..."
        apt-get install -y apache2
    fi

    # Check and configure modules (idempotent)
    if apache2ctl -M | grep -q "mpm_event_module"; then
        print info "Disabling mpm_event module..."
        a2dismod mpm_event
    fi

    # Enable required modules if not already enabled
    local required_modules=(rewrite headers deflate env mpm_prefork)
    for module in "${required_modules[@]}"; do
        if ! apache2ctl -M | grep -q "${module}_module"; then
            print info "Enabling Apache module: $module"
            a2enmod $module
        else
            print info "Apache module $module already enabled"
        fi
    done

    # Restart only if needed
    if ! systemctl is-active apache2 >/dev/null 2>&1; then
        print info "Starting Apache service..."
        service apache2 start
    else
        print info "Restarting Apache to apply any changes..."
        service apache2 restart || {
            print error "Failed to restart Apache2. Exiting..."
            exit 1
        }
    fi
}

setup_brotli() {
    print info "Setting up Brotli compression..."

    if ! apache2ctl -M | grep -q 'brotli_module'; then
        if dpkg -l | grep -q '^ii  libapache2-mod-brotli '; then
            print info "libapache2-mod-brotli already installed"
        else
            print info "Installing libapache2-mod-brotli..."
            log_action "Installing libapache2-mod-brotli..."
            # Refresh package lists and confirm availability before install
            apt-get update -y || true
            if ! apt-cache show libapache2-mod-brotli >/dev/null 2>&1; then
                print warning "libapache2-mod-brotli not available in current repos. Skipping Brotli setup."
                log_action "libapache2-mod-brotli not available in current repos. Skipping Brotli setup."
                return
            fi

            apt-get install -y libapache2-mod-brotli || {
                print warning "Failed to install libapache2-mod-brotli. Continuing without Brotli support..."
                log_action "Failed to install libapache2-mod-brotli. Continuing without Brotli support..."
                return
            }
        fi

        print info "Enabling Brotli module..."
        a2enmod brotli
        service apache2 restart || {
            print error "Failed to restart Apache after enabling Brotli. Exiting..."
            exit 1
        }
    else
        print info "Brotli module is already installed and enabled."
        log_action "Brotli module is already installed and enabled."
    fi
}

configure_basic_performance() {
    print header "Configuring basic Apache performance..."

    # Check and enable modules only if not already enabled
    for module in deflate expires headers; do
        if ! apache2ctl -M | grep -q "${module}_module"; then
            print info "Enabling Apache module: $module"
            a2enmod $module
        else
            print info "Apache module $module already enabled"
        fi
    done

    # Check if performance config already exists and is enabled
    config_file="/etc/apache2/conf-available/basic-performance.conf"

    if [ ! -f "$config_file" ]; then
        print info "Creating basic performance configuration..."
        cat >"$config_file" <<'EOF'
# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
</IfModule>

# Basic security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options SAMEORIGIN
</IfModule>
EOF
    else
        print info "Basic performance configuration already exists"
    fi

    # Check if config is enabled
    if [ ! -f "/etc/apache2/conf-enabled/basic-performance.conf" ]; then
        print info "Enabling basic performance configuration..."
        a2enconf basic-performance
    else
        print info "Basic performance configuration already enabled"
    fi

    print success "Basic Apache performance configured"
}

setup_web_permissions() {
    print info "Setting up web directory permissions..."

    # Check if directory exists and has proper permissions
    if [ -d "/var/www" ]; then
        # Only set permissions if they're not already correct
        if ! getfacl /var/www 2>/dev/null | grep -q "user:www-data:rwx"; then
            print info "Setting ACL permissions for /var/www..."
            setfacl -R -m u:$USER:rwx,u:www-data:rwx /var/www
        else
            print info "Web directory permissions already configured"
        fi
    else
        print warning "/var/www directory does not exist yet"
    fi
}

#=============================================================================
# MYSQL SETUP FUNCTIONS
#=============================================================================

get_mysql_password() {
    print header "Setting up MySQL credentials..."

    mysql_root_password=""
    mysql_root_password_confirm=""

    # Accept a password supplied by the caller (e.g. intelis setup.sh, which has
    # already collected it) via the MYSQL_ROOT_PASSWORD env var, so we don't
    # prompt a second time. If MySQL is already running and the supplied password
    # fails to authenticate, fall through to the normal prompt rather than
    # trusting it blindly; pre-install (no mysqld yet) we accept it and the
    # install step sets root to this value.
    if [ -n "${MYSQL_ROOT_PASSWORD:-}" ]; then
        mysql_root_password="${MYSQL_ROOT_PASSWORD}"
        if command -v mysqladmin &>/dev/null && ! mysqladmin ping -u root -p"${mysql_root_password}" &>/dev/null; then
            print warning "Caller-supplied MySQL password did not verify against the running server; prompting."
            mysql_root_password=""
        else
            print info "Using MySQL root password supplied by the caller."
            print info "Storing MySQL root password securely..."
            cat <<EOF >~/.my.cnf
[client]
user=root
password=${mysql_root_password}
host=localhost
EOF
            chmod 600 ~/.my.cnf
            return
        fi
    fi

    # Reuse existing password from ~/.my.cnf when valid
    if [ -f ~/.my.cnf ]; then
        mysql_root_password=$(awk -F= '/^password[[:space:]]*=/{gsub(/^[ \t]+|[ \t]+$/,"",$2); print $2; exit}' ~/.my.cnf)
        if [ -n "$mysql_root_password" ]; then
            print info "Found existing MySQL credentials in ~/.my.cnf; verifying..."
            if command -v mysqladmin &>/dev/null && mysqladmin ping -u root -p"${mysql_root_password}" &>/dev/null; then
                print success "Reusing MySQL root password from ~/.my.cnf"
                return
            else
                print warning "Existing ~/.my.cnf password did not work; prompting for a new one."
                mysql_root_password=""
            fi
        fi
    fi

    while :; do
        while [ -z "${mysql_root_password}" ] || [ "${mysql_root_password}" != "${mysql_root_password_confirm}" ]; do
            read -sp "Please enter the MySQL root password (cannot be blank): " mysql_root_password
            echo
            read -sp "Please confirm the MySQL root password: " mysql_root_password_confirm
            echo

            if [ -z "${mysql_root_password}" ]; then
                echo "Password cannot be blank."
            elif [ "${mysql_root_password}" != "${mysql_root_password_confirm}" ]; then
                echo "Passwords do not match. Please try again."
            fi
        done

        print info "Storing MySQL root password securely..."
        cat <<EOF >~/.my.cnf
[client]
user=root
password=${mysql_root_password}
host=localhost
EOF
        chmod 600 ~/.my.cnf

        # Verify MySQL password
        if command -v mysql &>/dev/null; then
            print info "MySQL is already installed. Verifying password..."
            if mysqladmin ping -u root -p"${mysql_root_password}" &>/dev/null; then
                print success "Password verified."
                break
            else
                print warning "Password incorrect or MySQL server unreachable. Please try again."
                mysql_root_password=""
                mysql_root_password_confirm=""
            fi
        else
            break # MySQL not installed, continue to installation
        fi
    done
}

# Wait until the local mysqld accepts connections (socket, as OS root).
wait_for_mysql() {
    local i
    for i in $(seq 1 30); do
        if mysqladmin ping &>/dev/null; then
            return 0
        fi
        sleep 1
    done
    return 1
}

# Idempotently switch root@localhost to password authentication and VERIFY it
# over TCP — the way the application connects.
#
# Fresh MySQL 8 ships root on the auth_socket plugin, which authenticates by the
# OS peer user over the unix socket. That makes every check run here as OS root
# (mysqladmin ping, mysql -u root) succeed even though the app — connecting with
# a password as www-data — gets "Access denied for user 'root'@'localhost'". So
# we run the ALTER as OS root via the socket (works whether root is still
# auth_socket or already password-auth via ~/.my.cnf), pin it to
# mysql_native_password (what PHP/LIS uses), and then confirm with a real TCP
# password login, which auth_socket cannot satisfy.
enforce_mysql_root_password() {
    print info "Ensuring MySQL root uses password authentication..."
    if ! wait_for_mysql; then
        print error "MySQL is not accepting connections; cannot configure root auth. Exiting..."
        exit 1
    fi

    # Pick the auth plugin by server flavor:
    #   - MySQL 8.x: caching_sha2_password — the modern default and future-proof
    #     (mysql_native_password is deprecated and disabled by default on 8.4+).
    #     PHP 7.4+/mysqlnd speaks it over both socket and TCP.
    #   - MariaDB: has no caching_sha2_password, so use mysql_native_password.
    # Within MySQL we fall back to native_password if caching_sha2 can't be set.
    # (Longer term the app should use a dedicated non-root user and leave root on
    # auth_socket for local admin.)
    local plugins="caching_sha2_password mysql_native_password"
    if mysql --version 2>/dev/null | grep -qi mariadb; then
        plugins="mysql_native_password"
    fi

    # Verify as a NON-root OS user over the socket — exactly how the app (www-data)
    # connects. The socket is a secure transport, so caching_sha2 needs no
    # public-key exchange, native_password works too, and auth_socket correctly
    # FAILS this check (it would only pass for OS user root). This is what every
    # OS-root/socket check in this script missed.
    local verify_user="nobody"
    local run_as_verify="runuser -u ${verify_user} --"
    command -v runuser >/dev/null 2>&1 || run_as_verify="sudo -u ${verify_user}"

    local plugin attempt
    for plugin in $plugins; do
        for attempt in 1 2 3; do
            if mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH ${plugin} BY '${mysql_root_password}'; FLUSH PRIVILEGES;" &>/dev/null \
                && $run_as_verify mysql -u root -p"${mysql_root_password}" -e "SELECT 1;" &>/dev/null; then
                print success "MySQL root password authentication verified (${plugin}, non-root over socket)."
                return 0
            fi
            sleep 2
        done
        print warning "Could not set root via ${plugin}; trying the next option..."
    done

    print error "Could not configure MySQL root for password authentication."
    print error "The application connects with a password and will fail until this is resolved."
    exit 1
}

install_mysql() {
    if ! command -v mysql &>/dev/null; then
        print header "Installing MySQL..."
        apt-get install -y mysql-server
        service mysql start &>/dev/null || systemctl start mysql &>/dev/null || true
    fi

    # Always (re-)assert password auth, even when MySQL was already installed, so
    # a prior run that left root on auth_socket self-heals on the next run.
    enforce_mysql_root_password
}

configure_mysql() {
    print header "Configuring MySQL..."

    desired_sql_mode="sql_mode ="
    desired_innodb_strict_mode="innodb_strict_mode = 0"
    desired_charset="character-set-server=utf8mb4"
    desired_collation="collation-server=utf8mb4_general_ci"
    config_file="/etc/mysql/mysql.conf.d/mysqld.cnf"

    # Check if config already has all our desired settings
    sql_mode_exists=$(grep -c "^${desired_sql_mode}" ${config_file} || true)
    innodb_mode_exists=$(grep -c "^${desired_innodb_strict_mode}" ${config_file} || true)
    charset_exists=$(grep -c "^${desired_charset}" ${config_file} || true)
    collation_exists=$(grep -c "^${desired_collation}" ${config_file} || true)
    legacy_auth_plugin=$(grep -c "^default_authentication_plugin" ${config_file} || true)

    if [ $sql_mode_exists -gt 0 ] && [ $innodb_mode_exists -gt 0 ] && [ $charset_exists -gt 0 ] && [ $collation_exists -gt 0 ] && [ $legacy_auth_plugin -eq 0 ]; then
        print info "MySQL already has all the required configurations. Skipping configuration."
    else
        print info "Some MySQL configurations need to be updated."
        cp ${config_file} ${config_file}.bak

        awk -v dsm="${desired_sql_mode}" -v dism="${desired_innodb_strict_mode}" \
            -v dcharset="${desired_charset}" -v dcollation="${desired_collation}" \
            'BEGIN { sql_mode_added=0; innodb_strict_mode_added=0; charset_added=0; collation_added=0; }
                        /default_authentication_plugin[[:space:]]*=/ {
                            # Removed in MySQL 8.4; comment out any pre-existing entry.
                            print ";" $0;
                            next;
                        }
                        /sql_mode[[:space:]]*=/ {
                            if ($0 ~ dsm) {sql_mode_added=1;}
                            else {print ";" $0;}
                            next;
                        }
                        /innodb_strict_mode[[:space:]]*=/ {
                            if ($0 ~ dism) {innodb_strict_mode_added=1;}
                            else {print ";" $0;}
                            next;
                        }
                        /character-set-server[[:space:]]*=/ {
                            if ($0 ~ dcharset) {charset_added=1;}
                            else {print ";" $0;}
                            next;
                        }
                        /collation-server[[:space:]]*=/ {
                            if ($0 ~ dcollation) {collation_added=1;}
                            else {print ";" $0;}
                            next;
                        }
                        /skip-external-locking|mysqlx-bind-address/ {
                            print;
                            if (sql_mode_added == 0) {print dsm; sql_mode_added=1;}
                            if (innodb_strict_mode_added == 0) {print dism; innodb_strict_mode_added=1;}
                            if (charset_added == 0) {print dcharset; charset_added=1;}
                            if (collation_added == 0) {print dcollation; collation_added=1;}
                            next;
                        }
                        { print; }' ${config_file} >tmpfile && mv tmpfile ${config_file}

        service mysql restart || {
            mv ${config_file}.bak ${config_file}
            print error "Failed to restart MySQL. Exiting..."
            exit 1
        }
    fi
}

secure_mysql() {
    print header "Securing MySQL installation..."

    # Test if we can connect (validates our setup is working)
    if ! mysql -u root -p"${mysql_root_password}" -e "SELECT 1;" &>/dev/null; then
        print error "Cannot connect to MySQL. Skipping security setup."
        return 1
    fi

    # These operations are idempotent - MySQL ignores if already done
    mysql -u root -p"${mysql_root_password}" <<EOF 2>/dev/null
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
EOF

    if [ $? -eq 0 ]; then
        print success "MySQL secured successfully"
        log_action "MySQL installation secured"
    else
        print warning "MySQL security setup had some issues, but continuing..."
        log_action "MySQL security setup completed with warnings"
    fi
}

#=============================================================================
# PHP SETUP FUNCTIONS
#=============================================================================

install_php() {
    local php_version=${1:-$(default_php_version_for_os)}
    local force_flag=${2:-}
    print header "Installing and configuring PHP $php_version..."

    # Download switch-php script if not already present or in force mode
    if [ ! -f "/usr/local/bin/switch-php" ] || [ "$force_flag" = "-f" ] || [ "$force_flag" = "--force" ]; then
        print info "Downloading switch-php script..."
        wget https://raw.githubusercontent.com/deforay/utility-scripts/master/php/switch-php -O /usr/local/bin/switch-php
        chmod u+x /usr/local/bin/switch-php
    else
        print info "switch-php script already exists"
    fi

    # Check if the specific PHP version is already active and not in force mode
    if [ "$force_flag" != "-f" ] && [ "$force_flag" != "--force" ]; then
        if command -v php &>/dev/null; then
            current_version=$(php -v | head -n1 | grep -oP 'PHP \K[0-9]+\.[0-9]+')
            if [ "$current_version" == "$php_version" ]; then
                print info "PHP $php_version is already active"
                return 0
            fi
        fi
    fi

    # Run switch-php with or without force flag
    if [ "$force_flag" = "-f" ] || [ "$force_flag" = "--force" ]; then
        print info "Switching to PHP $php_version (force mode)..."
        switch-php $php_version --force
    else
        print info "Switching to PHP $php_version..."
        switch-php $php_version
    fi
}

#=============================================================================
# PHPMYADMIN SETUP FUNCTIONS
#=============================================================================

install_phpmyadmin() {
    local force_flag=${1:-}
    print header "Setting up phpMyAdmin..."

    # Check if force reinstall is requested
    if [ "$force_flag" = "-f" ] || [ "$force_flag" = "--force" ]; then
        if [ -d "/var/www/phpmyadmin" ]; then
            print info "Force mode: Removing existing phpMyAdmin installation..."
            rm -rf /var/www/phpmyadmin
        fi
    fi

    if [ ! -d "/var/www/phpmyadmin" ] || [ -z "$(ls -A /var/www/phpmyadmin 2>/dev/null)" ]; then
        print info "Downloading and setting up phpMyAdmin..."

        mkdir -p /var/www/phpmyadmin

        wget -q --show-progress --progress=dot:giga https://www.phpmyadmin.net/downloads/phpMyAdmin-latest-all-languages.zip

        unzip -q phpMyAdmin-latest-all-languages.zip -d /var/www/phpmyadmin || {
            print error "Extraction failed"
            exit 1
        }

        rm phpMyAdmin-latest-all-languages.zip

        # Move files up one level
        PHPMYADMIN_DIR=$(ls /var/www/phpmyadmin)
        mv /var/www/phpmyadmin/$PHPMYADMIN_DIR/* /var/www/phpmyadmin/
        mv /var/www/phpmyadmin/$PHPMYADMIN_DIR/.[!.]* /var/www/phpmyadmin/ 2>/dev/null
        rmdir /var/www/phpmyadmin/$PHPMYADMIN_DIR
        
        print success "phpMyAdmin installed successfully"
    else
        print info "phpMyAdmin is already installed. Skipping installation. (Use --force to reinstall)"
    fi
}

configure_phpmyadmin_apache() {
    print info "Checking Apache configuration for phpMyAdmin..."

    desired_alias="Alias /phpmyadmin /var/www/phpmyadmin"
    config_file="/etc/apache2/sites-available/000-default.conf"

    if grep -q "$desired_alias" ${config_file}; then
        print info "phpMyAdmin alias already exists in Apache configuration."
    else
        print info "Adding phpMyAdmin alias to Apache configuration..."
        awk -v da="$desired_alias" \
            'BEGIN {added=0; alias_added=0}
        /Alias \/phpmyadmin[[:space:]]/ {
            if ($0 !~ da) {print ";" $0} else {alias_added=1; print $0}
            next;
        }
        /ServerAdmin|DocumentRoot/ {
            print;
            if (added == 0 && alias_added == 0) {
                print da;
                added=1;
            }
            next;
        }
        { print }' ${config_file} >tmpfile && mv tmpfile ${config_file}

        service apache2 restart || {
            print error "Failed to restart Apache2. Exiting..."
            log_action "Failed to restart Apache2. Exiting..."
            exit 1
        }
    fi
}

#=============================================================================
# MAIN FUNCTION
#=============================================================================

main() {
    # Error trap
    trap 'error_handling "${BASH_COMMAND}" "$LINENO" "$?"' ERR

    # Parse arguments
    local PHP_VERSION=${1:-$(default_php_version_for_os)}
    local FORCE_FLAG=""

    # Check for force flag in any position
    for arg in "$@"; do
        if [ "$arg" = "-f" ] || [ "$arg" = "--force" ]; then
            FORCE_FLAG="--force"
            print info "Force mode enabled: will reinstall PHP, extensions, Composer, and phpMyAdmin"
            break
        fi
    done

    # If force flag is present and first arg is the flag, use the OS default.
    if [ "$1" = "-f" ] || [ "$1" = "--force" ]; then
        PHP_VERSION="$(default_php_version_for_os)"
    fi

    print header "Starting LAMP Setup Script"
    log_action "LAMP Setup started with PHP version: $PHP_VERSION (Force: ${FORCE_FLAG:-no})"

    # Phase 1: Validation
    check_root_privileges
    is_valid_ubuntu || exit 1
    check_dependencies

    # Phase 2: System Setup
    setup_system
    setup_locale
    setup_ssh

    # Phase 3: Apache Setup
    install_apache
    setup_brotli
    configure_basic_performance
    setup_web_permissions

    # Phase 4: MySQL Setup
    get_mysql_password
    install_mysql
    configure_mysql
    secure_mysql

    # Phase 5: PHP Setup
    install_php $PHP_VERSION $FORCE_FLAG

    # Phase 6: phpMyAdmin Setup
    install_phpmyadmin $FORCE_FLAG
    configure_phpmyadmin_apache

    # Final restart and completion
    service apache2 restart || {
        print error "Failed to restart Apache2. Exiting..."
        log_action "Failed to restart Apache2. Exiting..."
        exit 1
    }

    log_action "LAMP Setup complete."
    print success "LAMP Setup complete successfully!"
    print info "You can access phpMyAdmin at: http://your-server/phpmyadmin"
}

#=============================================================================
# SCRIPT EXECUTION
#=============================================================================

# Only execute main if script is run directly (not sourced)
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi
