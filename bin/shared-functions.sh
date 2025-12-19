#!/bin/bash
# shared-functions.sh - Common functions for EPT scripts
# Unified print function for colored output
print() {
    local type=$1
    local message=$2
    local header_char="="

    case $type in
        error)
            printf "\033[1;91m❌ Error:\033[0m %s\n" "$message"
        ;;
        success)
            printf "\033[1;92m✅ Success:\033[0m %s\n" "$message"
        ;;
        warning)
            printf "\033[1;93m⚠️ Warning:\033[0m %s\n" "$message"
        ;;
        info)
            printf "\033[1;96mℹ️ Info:\033[0m %s\n" "$message"
        ;;
        debug)
            printf "\033[1;95m🐛 Debug:\033[0m %s\n" "$message"
        ;;
        header)
            local term_width
            term_width=$( [ -t 1 ] && tput cols 2>/dev/null || echo 80 )
            local msg_length=${#message}
            local padding=$(((term_width - msg_length) / 2))
            ((padding < 0)) && padding=0
            local pad_str
            pad_str=$(printf '%*s' "$padding" '')
            printf "\n\033[1;96m%*s\033[0m\n" "$term_width" '' | tr ' ' "$header_char"
            printf "\033[1;96m%s%s\033[0m\n" "$pad_str" "$message"
            printf "\033[1;96m%*s\033[0m\n\n" "$term_width" '' | tr ' ' "$header_char"
        ;;
        *)
            printf "%s\n" "$message"
        ;;
    esac
}

# Install required packages
install_packages() {
    local required_pkgs=(aria2 wget lsb-release bc pigz gpg fzf zstd)
    # Map package names to their actual command names
    declare -A pkg_to_cmd=(
        ["aria2"]="aria2c"
        ["wget"]="wget"
        ["lsb-release"]="lsb_release"
        ["bc"]="bc"
        ["pigz"]="pigz"
        ["gpg"]="gpg"
        ["fzf"]="fzf"
        ["zstd"]="zstd"
    )
    
    local missing_pkgs=()
    for pkg in "${required_pkgs[@]}"; do
        local cmd="${pkg_to_cmd[$pkg]}"
        if ! command -v "$cmd" &>/dev/null; then
            missing_pkgs+=("$pkg")
        fi
    done

    if [ "${#missing_pkgs[@]}" -gt 0 ]; then
        apt-get update
        apt-get install -y "${missing_pkgs[@]}"
        
        # Re-check all required packages with correct command names
        for pkg in "${required_pkgs[@]}"; do
            local cmd="${pkg_to_cmd[$pkg]}"
            if ! command -v "$cmd" &>/dev/null; then
                print error "Failed to install required package: $pkg (command: $cmd). Exiting."
                exit 1
            fi
        done
    fi
}

prepare_system() {
    install_packages
    check_ubuntu_version "22.04"

    if ! command -v needrestart &>/dev/null; then
        print info "Installing needrestart..."
        apt-get install -y needrestart
    fi

    export NEEDRESTART_MODE=a # Auto-restart services non-interactively

    # Configure needrestart to non-interactive
    local conf_file="/etc/needrestart/needrestart.conf"
    if [ -f "$conf_file" ]; then
        sed -i "s/^\(\$nrconf{restart}\s*=\s*\).*/\1'a';/" "$conf_file" || echo "\$nrconf{restart} = 'a';" >>"$conf_file"
    else
        echo "\$nrconf{restart} = 'a';" >"$conf_file"
    fi

    print success "System preparation complete with non-interactive restarts configured."
}
spinner() {
    # BC signature: spinner <pid> [message]
    local pid="${1:-}"
    local message="${2:-Processing...}"
    local delay=0.2
    local status=1
    local is_tty=0

    # Basic validation
    [[ "$pid" =~ ^[0-9]+$ ]] || {
        printf "[FAIL] %s (invalid pid)\n" "$message"
        return 1
    }

    # TTY check (no locale/tput usage; set -u safe)
    [ -t 1 ] && is_tty=1

    # One-line start
    if (( is_tty )); then
        # Print message and then dots while we wait
        printf "%s " "$message"
    fi

    # First try to 'wait' if it's our child; else fall back to polling
    if wait "$pid" 2>/dev/null; then
        status=0
    else
        status=$?
        if [[ $status -eq 127 ]]; then
            # Not our child → poll existence until it exits
            status=0
            while kill -0 "$pid" 2>/dev/null; do
                (( is_tty )) && printf "."
                sleep "$delay"
            done
            # Can't know true exit code here; treat as success unless caller checks otherwise
        fi
    fi

    # Line end for TTY
    (( is_tty )) && printf "\n"

    # BC: print a clear success/fail line with the same message
    if (( status == 0 )); then
        printf "\033[1;92m✅ Success:\033[0m %s\n" "$message"
    else
        printf "\033[1;91m❌ Error:\033[0m %s (exit code: %d)\n" "$message" "$status"
    fi

    return "$status"
}


download_file() {
    local output_file="$1"
    local url="$2"
    local default_msg="Downloading $(basename "$output_file")..."
    local message="${3:-$default_msg}"
    # Slow network toggle: export DOWNLOAD_MODE=slow or SLOW_NETWORK=1
    local slow_mode=false
    local mode_lower
    mode_lower="$(printf '%s' "${DOWNLOAD_MODE:-}" | awk '{print tolower($0)}')"
    if [ "$mode_lower" = "slow" ] || [ "${SLOW_NETWORK:-0}" = "1" ]; then
        slow_mode=true
    fi

    local aria_conns
    if [ "$slow_mode" = true ]; then
        aria_conns="${ARIA2_CONNECTIONS:-4}"
    else
        aria_conns="${ARIA2_CONNECTIONS:-12}"
    fi

    # Get output directory and filename
    local output_dir
    output_dir=$(dirname "$output_file")
    local filename
    filename=$(basename "$output_file")

    # Create the directory if it doesn't exist
    if [ ! -d "$output_dir" ]; then
        mkdir -p "$output_dir" || {
            print error "Failed to create directory $output_dir"
            return 1
        }
    fi

    # For slow/resume mode, keep existing partial to allow resume; otherwise start clean
    if [ "$slow_mode" = true ]; then
        if [ -f "$output_file" ]; then
            print info "Resuming existing download for ${filename} (slow mode)."
        fi
    else
        [ -f "$output_file" ] && rm -f "$output_file"
    fi

    print info "$message"

    local log_file
    log_file=$(mktemp)

    # Try aria2c first
    if command -v aria2c &>/dev/null; then
        aria2c \
            --max-connection-per-server="$aria_conns" \
            --split="$aria_conns" \
            --min-split-size=1M \
            --file-allocation=none \
            --auto-file-renaming=false \
            --allow-overwrite=true \
            --continue=true \
            --max-tries=$([ "$slow_mode" = true ] && printf '12' || printf '5') \
            --retry-wait=$([ "$slow_mode" = true ] && printf '5' || printf '2') \
            --timeout=$([ "$slow_mode" = true ] && printf '60' || printf '30') \
            --connect-timeout=$([ "$slow_mode" = true ] && printf '30' || printf '15') \
            --summary-interval=0 \
            --console-log-level=error \
            --no-conf \
            --conditional-get=false \
            --remote-time=false \
            -d "$output_dir" \
            -o "$filename" \
            "$url" >"$log_file" 2>&1 &
        
        local download_pid=$!
        spinner "$download_pid" "$message"
        
        # Check if file downloaded successfully
        if [ -f "$output_file" ] && [ -s "$output_file" ]; then
            print success "Download completed: $filename"
            rm -f "$log_file"
            return 0
        fi
        
        # aria2c failed, try wget
        print warning "aria2c failed, trying wget..."
        rm -f "$output_file"
    fi

    # Fallback to wget
    if command -v wget &>/dev/null; then
        wget --progress=bar:force \
            --tries=$([ "$slow_mode" = true ] && printf '8' || printf '5') \
            --waitretry=$([ "$slow_mode" = true ] && printf '5' || printf '2') \
            --timeout=$([ "$slow_mode" = true ] && printf '60' || printf '30') \
            --read-timeout=$([ "$slow_mode" = true ] && printf '60' || printf '30') \
            --retry-connrefused \
            ${slow_mode:+--continue} \
            -O "$output_file" \
            "$url" >"$log_file" 2>&1 &
        
        local download_pid=$!
        spinner "$download_pid" "$message"
        
        # Check if wget succeeded
        if [ -f "$output_file" ] && [ -s "$output_file" ]; then
            print success "Download completed: $filename"
            rm -f "$log_file"
            return 0
        fi
    fi

    # Fallback to curl if wget unavailable or failed
    if command -v curl &>/dev/null; then
        curl -L --fail \
            --retry $([ "$slow_mode" = true ] && printf '8' || printf '5') \
            --retry-delay $([ "$slow_mode" = true ] && printf '5' || printf '2') \
            --connect-timeout $([ "$slow_mode" = true ] && printf '30' || printf '15') \
            --max-time $([ "$slow_mode" = true ] && printf '900' || printf '300') \
            ${slow_mode:+--continue-at -} \
            -o "$output_file" "$url" >"$log_file" 2>&1 &

        local download_pid=$!
        spinner "$download_pid" "$message"

        if [ -f "$output_file" ] && [ -s "$output_file" ]; then
            print success "Download completed: $filename"
            rm -f "$log_file"
            return 0
        fi
    fi

    # Both failed
    print error "Download failed for: $filename"
    print info "Detailed download logs:"
    cat "$log_file"
    rm -f "$log_file"
    return 1
}


# Download a file only if the remote version has changed
download_if_changed() {
    local output_file="$1"
    local url="$2"

    local tmpfile
    tmpfile=$(mktemp)

    if ! wget -q -O "$tmpfile" "$url"; then
        print error "Failed to download $(basename "$output_file") from $url"
        rm -f "$tmpfile"
        return 1
    fi

    if [ -f "$output_file" ]; then
        local new_checksum old_checksum
        new_checksum=$(md5sum "$tmpfile" | awk '{print $1}')
        old_checksum=$(md5sum "$output_file" | awk '{print $1}')

        if [ "$new_checksum" = "$old_checksum" ]; then
            print info "$(basename "$output_file") is already up-to-date."
            rm -f "$tmpfile"
            return 0
        fi
    fi

    mv "$tmpfile" "$output_file"
    chmod +x "$output_file"
    print success "Downloaded and updated $(basename "$output_file")"
    return 0
}



error_handling() {
    local last_cmd=$1
    local last_line=$2
    local last_error=$3
    echo "Error on or near line ${last_line}; command executed was '${last_cmd}' which exited with status ${last_error}"
    log_action "Error on or near line ${last_line}; command executed was '${last_cmd}' which exited with status ${last_error}"
    exit 1
}

# Ubuntu version check
check_ubuntu_version() {
    local min_version=$1
    local current_version=$(lsb_release -rs)

    # Check if version is greater than or equal to min_version
    if [[ "$(printf '%s\n' "$min_version" "$current_version" | sort -V | head -n1)" != "$min_version" ]]; then
        print error "This script requires Ubuntu ${min_version} or newer."
        exit 1
    fi

    # Check if it's an LTS release
    local description=$(lsb_release -d)
    if ! echo "$description" | grep -q "LTS"; then
        print error "This script requires an Ubuntu LTS release."
        exit 1
    fi

    print success "Ubuntu version check passed: Running Ubuntu ${current_version} LTS."
}

# Validate EPT application path
is_valid_application_path() {
    local path=$1
    if [ -f "$path/application/configs/application.ini" ] && [ -d "$path/public" ]; then
        return 0
    else
        return 1
    fi
}

# Convert to absolute path
to_absolute_path() {
    local p="$1"

    # empty → echo empty (caller decides fallback)
    [ -z "$p" ] && { echo ""; return 0; }

    # expand leading "~" → $HOME
    [[ "$p" == "~"* ]] && p="${p/#\~/$HOME}"

    if command -v realpath >/dev/null 2>&1; then
        # -m: canonicalize even if components don’t exist; "." works too
        realpath -m -- "$p"
        return $?
    fi

    # GNU readlink: prefer -m if available, else -f (requires existing path)
    if readlink -m / >/dev/null 2>&1; then
        readlink -m -- "$p"
        return $?
    fi

    case "$p" in
        /*) printf '%s\n' "$p" ;;
        *)  printf '%s\n' "$(pwd)/$p" ;;
    esac
}


# Set ACL-based permissions (async by default; pass third arg "sync" to wait)
set_permissions() {
    local path=$1
    local mode=${2:-"full"}          # full | quick | minimal
    local wait_mode=${3:-"async"}    # async | sync

    # Who to grant (robust under sudo/non-interactive)
    local who="${SUDO_USER:-${USER:-root}}"

    if ! command -v setfacl &>/dev/null; then
        print warning "setfacl not found. Falling back to chown/chmod..."
        chown -R "$who":www-data "$path"
        chmod -R u+rwX,g+rwX "$path"
        return
    fi

    # Tunables
    local PARALLEL=${PARALLEL:-$(nproc)}
    local ACL_TIMEOUT_SEC=${ACL_TIMEOUT_SEC:-3}      # per-file timeout
    local CPU_NICE="nice -n 10"
    local IO_NICE=""
    command -v ionice >/dev/null 2>&1 && IO_NICE="ionice -c3"
    command -v timeout >/dev/null 2>&1 || ACL_TIMEOUT_SEC=0  # if no timeout, disable

    print info "Setting permissions for ${path} (${mode}, ${wait_mode})..."

    # Export env so subshells (xargs sh -c) can use them
    export ACL_TIMEOUT_SEC CPU_NICE IO_NICE who

    # Helper executed in subshell (sh -c), single file per invocation
    _acl_apply_cmd='
        target="$1"; perms="$2";
        if [ -n "$ACL_TIMEOUT_SEC" ] && [ "$ACL_TIMEOUT_SEC" -gt 0 ]; then
            $CPU_NICE $IO_NICE timeout "${ACL_TIMEOUT_SEC}s" setfacl -m "$perms" "$target" 2>/dev/null \
            || printf "%s\t%s\n" "ACL_TIMEOUT_OR_FAIL" "$target" >>/tmp/acl_failures.log
        else
            $CPU_NICE $IO_NICE setfacl -m "$perms" "$target" 2>/dev/null \
            || printf "%s\t%s\n" "ACL_FAIL" "$target" >>/tmp/acl_failures.log
        fi
    '

    local pids=()

    case "$mode" in
        full)
            # Directories: rwx to user + www-data
            find "$path" -type d -not -path "*/.git*" -not -path "*/node_modules*" -print0 \
            | xargs -0 -P "$PARALLEL" -I{} sh -c "$_acl_apply_cmd" _ {} "u:${who}:rwx,u:www-data:rwx" &
            pids+=($!)

            # Files: rw to user + www-data
            find "$path" -type f -not -path "*/.git*" -not -path "*/node_modules*" -print0 \
            | xargs -0 -P "$PARALLEL" -I{} sh -c "$_acl_apply_cmd" _ {} "u:${who}:rw,u:www-data:rw" &
            pids+=($!)
        ;;
        quick)
            find "$path" -type d -print0 \
            | xargs -0 -P "$PARALLEL" -I{} sh -c "$_acl_apply_cmd" _ {} "u:${who}:rwx,u:www-data:rwx" &
            pids+=($!)

            find "$path" -type f -name "*.php" -print0 \
            | xargs -0 -P "$PARALLEL" -I{} sh -c "$_acl_apply_cmd" _ {} "u:${who}:rw,u:www-data:rw" &
            pids+=($!)
        ;;
        minimal)
            find "$path" -type d -print0 \
            | xargs -0 -P "$PARALLEL" -I{} sh -c "$_acl_apply_cmd" _ {} "u:${who}:rwx,u:www-data:rwx" &
            pids+=($!)
        ;;
      *)
        print warning "Unknown mode '${mode}', using 'full'."
        "$FUNCNAME" "$path" full "$wait_mode"
        return
        ;;
    esac

    if [[ "$wait_mode" == "sync" ]]; then
        for pid in "${pids[@]}"; do wait "$pid"; done
        [[ -s /tmp/acl_failures.log ]] && print warning "Some ACL operations timed out/failed. See /tmp/acl_failures.log"
        print success "Permissions applied (sync)."
    else
        print info "ACLs applying in background (async)."
    fi
}

# Function to restart a service (MySQL or Apache)
restart_service() {
    local service_type=$1

    case "$service_type" in
        apache)
            if systemctl list-unit-files apache2.service >/dev/null 2>&1; then
                print info "Restarting Apache (apache2)..."
                log_action "Restarting apache2"
                systemctl restart apache2 || return 1
            elif systemctl list-unit-files httpd.service >/dev/null 2>&1; then
                print info "Restarting Apache (httpd)..."
                log_action "Restarting httpd"
                systemctl restart httpd || return 1
            else
                print warning "Apache/httpd service not found"
                log_action "Apache/httpd not found"
                return 1
            fi
            ;;
        mysql)
            print info "Restarting MySQL..."
            log_action "Restarting MySQL"
            systemctl restart mysql || return 1
        ;;
      *)
        print error "Unknown service type: $service_type"
        log_action "Unknown service type: $service_type"
        return 1
        ;;
    esac

    print success "$service_type restarted successfully"
    return 0
}


# Function to restart a service (MySQL or Apache)
restart_service() {
    local service_type=$1

    case "$service_type" in
        apache)
            if systemctl list-unit-files apache2.service >/dev/null 2>&1; then
                print info "Restarting Apache (apache2)..."
                log_action "Restarting apache2"
                systemctl restart apache2 || return 1
            elif systemctl list-unit-files httpd.service >/dev/null 2>&1; then
                print info "Restarting Apache (httpd)..."
                log_action "Restarting httpd"
                systemctl restart httpd || return 1
            else
                print warning "Apache/httpd service not found"
                log_action "Apache/httpd not found"
                return 1
            fi
            ;;
        mysql)
            print info "Restarting MySQL..."
            log_action "Restarting MySQL"
            systemctl restart mysql || return 1
        ;;
      *)
        print error "Unknown service type: $service_type"
        log_action "Unknown service type: $service_type"
        return 1
        ;;
    esac

    print success "$service_type restarted successfully"
    return 0
}


# Ask user yes/no
ask_yes_no() {
    local prompt="$1"
    local default="${2:-no}"
    local timeout=15
    local answer

    # Normalize default
    default=$(echo "$default" | awk '{print tolower($0)}')
    [[ "$default" != "yes" && "$default" != "no" ]] && default="no"

    # If stdin is not a terminal, fallback to default
    if [ ! -t 0 ]; then
        [[ "$default" == "yes" ]] && return 0 || return 1
    fi

    echo -n "$prompt (y/n) [default: $default, auto in ${timeout}s]: "

    read -t "$timeout" answer
    if [ $? -ne 0 ]; then
        print info "No input received in ${timeout} seconds. Using default: $default"
        [[ "$default" == "yes" ]] && return 0 || return 1
    fi

    # Treat empty input (Enter) as choosing default
    answer=$(echo "$answer" | awk '{print tolower($0)}')
    if [ -z "$answer" ]; then
        print info "Using default: $default"
        [[ "$default" == "yes" ]] && return 0 || return 1
    fi

    case "$answer" in
        y | yes) return 0 ;;
        n | no)  return 1 ;;
        *)
            print warning "Invalid input. Using default: $default"
            [[ "$default" == "yes" ]] && return 0 || return 1
        ;;
    esac
}

# Generic INI getter for dotted keys (with section variants + production fallback)
ini_get_value() {
  local config_file="$1" section="${2:-production}" dotted_key="$3"

  if [ -z "$config_file" ] || [ ! -f "$config_file" ]; then
      print error "Config file not found: $config_file"; return 1
  fi

  php -r '
    error_reporting(0);
    $f=$argv[1]; $want=$argv[2]; $key=$argv[3];

    $lines = @file($f, FILE_IGNORE_NEW_LINES);
    if($lines===false){ exit(1); }

    $candidates = [$want, "$want : production", "$want:production", strtolower($want), "production"];
    $current = "";

    foreach($lines as $line){
      $trimmed = trim($line);
      if($trimmed === "" || $trimmed[0] === ";" || $trimmed[0] === "#"){ continue; }

      if(preg_match("/^\\[(.+?)\\]/", $trimmed, $m)){
        $current = trim($m[1]);
        continue;
      }

      if(!in_array($current, $candidates, true)){ continue; }
      if(!preg_match("/^([A-Za-z0-9_.-]+)\\s*=\\s*(.*)$/", $trimmed, $m)){ continue; }
      if($m[1] !== $key){ continue; }

      $v = trim($m[2]);

      // Prefer quoted values as-is (don’t treat ;/# inside quotes as comments).
      if ($v !== "" && ($v[0] === chr(34) || $v[0] === chr(39))) {
        $q = $v[0];
        $len = strlen($v);
        $out = "";
        $escaped = false;
        for ($i = 1; $i < $len; $i++) {
          $ch = $v[$i];
          if ($escaped) { $out .= $ch; $escaped = false; continue; }
          if ($ch === "\\\\") { $escaped = true; continue; }
          if ($ch === $q) { $v = $out; break; }
          $out .= $ch;
        }
      } else {
        // strip inline comments only when preceded by whitespace
        $v = preg_replace("/\\s[;#].*$/", "", $v);
        $v = rtrim($v);

        // remove surrounding single/double quotes (simple case)
        if (strlen($v) >= 2) {
          $dq = chr(34);  // double-quote
          $sq = chr(39);  // single-quote
          if ( ($v[0]===$dq && substr($v,-1)===$dq) || ($v[0]===$sq && substr($v,-1)===$sq) ) {
            $v = substr($v, 1, -1);
          }
        }
      }
      echo $v; exit(0);
    }
    exit(2);
  ' -- "$config_file" "$section" "$dotted_key"
}



extract_mysql_host_from_config()     { ini_get_value "$1" "${2:-production}" "resources.db.params.host"; }
extract_mysql_user_from_config()     { ini_get_value "$1" "${2:-production}" "resources.db.params.username"; }
extract_mysql_dbname_from_config()   { ini_get_value "$1" "${2:-production}" "resources.db.params.dbname"; }
extract_mysql_password_from_config() { ini_get_value "$1" "${2:-production}" "resources.db.params.password"; }

# Log action to log file
log_action() {
    local message=$1
    local logfile="${log_file:-/tmp/ept-$(date +'%Y%m%d').log}"

    # Rotate if larger than 10MB
    if [ -f "$logfile" ] && [ $(stat -c %s "$logfile") -gt 10485760 ]; then
        mv "$logfile" "${logfile}.old"
    fi

    echo "$(date +'%Y-%m-%d %H:%M:%S') - $message" >>"$logfile"
}

# Helper for idempotent file writing
write_if_different() {
    local target="$1"
    local tmp
    tmp="$(mktemp)"
    cat >"$tmp"
    if [[ -f "$target" ]] && cmp -s "$tmp" "$target"; then
        rm -f "$tmp"
        return 1  # unchanged
    fi
    install -D -m 0644 "$tmp" "$target"
    rm -f "$tmp"
    return 0  # written/changed
}


# Setup ept cron job (classic crontab, idempotent)
setup_cron() {
    local ept_path="$1"
    local cron_job="* * * * * cd ${ept_path} && ./cron.sh"

    # Ensure cron.sh is executable
    chmod +x "${ept_path}/cron.sh"

    # Load current root crontab without failing if none exists
    local current_crontab
    current_crontab="$(crontab -l 2>/dev/null || true)"

    # Already present?
    if printf '%s\n' "$current_crontab" | grep -Fxq "$cron_job"; then
        print info "Cron job for EPT already active. Skipping."
        log_action "Cron job for EPT already active. Skipped."
        return 0
    fi

    # Remove any existing (active or commented) similar entry
    local updated_crontab
    updated_crontab="$(
        printf '%s\n' "$current_crontab" |
        sed -E "/^[[:space:]]*#?[[:space:]]*\*[[:space:]]\*[[:space:]]\*[[:space:]]\*[[:space:]]\*[[:space:]]+cd[[:space:]]+$(printf '%s' "${ept_path}" | sed 's|/|\\/|g')[[:space:]]+&&[[:space:]]+\\./cron\\.sh$/d"
    )"

    # Write back crontab with our job appended
    {
        printf '%s\n' "$updated_crontab"
        printf '%s\n' "$cron_job"
    } | crontab -

    print success "Cron job for EPT added/replaced in root's crontab."
    log_action "Cron job for EPT added/replaced in root's crontab."
}


ensure_path() {
    case ":$PATH:" in
        *":/usr/local/bin:"*) ;; # already present
        *) export PATH="/usr/local/bin:$PATH" ;;
    esac
}


ensure_switch_php() {
    if command -v switch-php >/dev/null 2>&1; then
        return 0
    fi
    echo "switch-php not found; installing…"
    download_file "/usr/local/bin/switch-php" "https://raw.githubusercontent.com/deforay/utility-scripts/master/php/switch-php"
    chmod +x /usr/local/bin/switch-php
}


ensure_composer() {
    ensure_path

    if command -v composer >/dev/null 2>&1; then
        echo "✓ Composer found: $(command -v composer)"
        return 0
    fi

    echo "Composer not on PATH. Using switch-php to install it…"
    ensure_switch_php

    TARGET_PHP="${TARGET_PHP:-8.4}"
    switch-php "$TARGET_PHP"

    # Re-check PATH; some cron envs miss /usr/local/bin, so add a safety symlink
    if ! command -v composer >/dev/null 2>&1; then
        if [ -x /usr/local/bin/composer ] && [ -w /usr/bin ]; then
            if [ ! -e /usr/bin/composer ] || [ "$(readlink -f /usr/bin/composer)" != "/usr/local/bin/composer" ]; then
            ln -sf /usr/local/bin/composer /usr/bin/composer
            fi
        fi
    fi

    # Fallback: verified install if still missing after switch-php
    if ! command -v composer >/dev/null 2>&1; then
    print warning "Composer still missing after switch-php; installing verified global composer…"

    sig="$(curl -fsSL https://composer.github.io/installer.sig)" || {
        print error "Failed to fetch Composer installer signature."; exit 1; }

    installer="$(mktemp)"
    curl -fsSL https://getcomposer.org/installer -o "$installer" || {
        print error "Failed to download Composer installer."; rm -f "$installer"; exit 1; }

    actual="$(php -r "echo hash_file('sha384', '${installer}');")"
    if [ "$sig" != "$actual" ]; then
        print error "Composer installer signature mismatch."; rm -f "$installer"; exit 1
    fi

    php "$installer" --no-ansi --quiet --install-dir=/usr/local/bin --filename=composer || {
        print error "Composer installation failed."; rm -f "$installer"; exit 1; }
    rm -f "$installer"
    fi
    print success "✓ Composer installed: $(command -v composer)"
    export COMPOSER_ALLOW_SUPERUSER=1
}

# --- Ensure OPcache is installed and enabled for Apache (don’t rely on php -m) ---
ensure_opcache() {
    local ver="${desired_php_version:-8.4}"
    local pkg="php${ver}-opcache"
    local apache_ini_glob="/etc/php/${ver}/apache2/conf.d/*opcache.ini"
    local installed enabled

    # Is the package installed?
    if dpkg-query -W -f='${Status}\n' "$pkg" 2>/dev/null | grep -q "install ok installed"; then
        installed=true
    else
        installed=false
    fi

    # Is it enabled for Apache (conf.d link/file exists)?
    if ls $apache_ini_glob >/dev/null 2>&1; then
        enabled=true
    else
        enabled=false
    fi

    if $installed && $enabled; then
        print success "OPcache already installed and enabled for PHP ${ver} (Apache); skipping."
        return 0
    fi

    if ! $installed; then
        print info "Installing OPcache for PHP ${ver}…"
        apt-get update -y
        apt-get install -y "$pkg" || true
    fi

    if ! $enabled; then
        print info "Enabling OPcache for PHP ${ver} (Apache)…"
        phpenmod -v "$ver" -s apache2 opcache 2>/dev/null || phpenmod opcache 2>/dev/null || true
    fi

    print success "OPcache is ready for PHP ${ver} (Apache)."
}


setup_mysql_config() {
    local config_file="$1"
    local mysql_cnf="/root/.my.cnf"
    
    if [ ! -f "$mysql_cnf" ] && [ -f "$config_file" ]; then
        local pw=$(php -r "error_reporting(0);\$c=@include '$config_file';echo isset(\$c['database']['password'])?trim(\$c['database']['password']):'';")
        if [ -n "$pw" ]; then
            cat > "$mysql_cnf" << 'EOF'
[client]
user=root
EOF
            printf "password=%s\n" "$pw" >> "$mysql_cnf"
            chmod 600 "$mysql_cnf"
            return 0
        fi
    fi
    return 1
}
