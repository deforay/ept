#!/usr/bin/env bash
# bin/dev/setup-mailpit.sh — catch all outgoing ePT mail on a dev box (dev-only).
#
# Installs Mailpit (a local SMTP catcher with a web inbox), keeps it running as a
# service, points application.ini's email.devTrapDsn at it, and proves the trap
# works by sending a test mail through the app and finding it in Mailpit.
#
# Run this on every dev box, and again after restoring a live database: mail
# queued in that database (temp_mail) then lands in Mailpit, not real inboxes.
#
# Usage:
#   bin/dev/setup-mailpit.sh            install + start Mailpit, set the trap, verify
#   bin/dev/setup-mailpit.sh --check    only verify (no install, no ini change)
#   bin/dev/setup-mailpit.sh --block    no Mailpit: set the trap to "block" so the
#                                       app drops all mail instead of catching it
#
# Mailpit inbox: http://localhost:8025   SMTP: 127.0.0.1:1025

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
# shellcheck source=../shared-functions.sh
source "$ROOT_DIR/bin/shared-functions.sh"

TRAP_DSN="smtp://127.0.0.1:1025"
MAILPIT_API="http://127.0.0.1:8025/api/v1"
MODE="setup"

for arg in "$@"; do
    case "$arg" in
        --check) MODE="check" ;;
        --block) MODE="block" ;;
        -h | --help) sed -n '2,17p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) print error "Unknown option: $arg"; exit 1 ;;
    esac
done

# --- Mailpit install + service ---------------------------------------------------

install_mailpit() {
    if command -v mailpit >/dev/null 2>&1; then
        print info "Mailpit already installed: $(mailpit version 2>/dev/null | head -1)"
        return
    fi
    print info "Installing Mailpit..."
    case "$(uname -s)" in
        Darwin)
            command -v brew >/dev/null 2>&1 || { print error "Homebrew is required on macOS (https://brew.sh)."; exit 1; }
            brew install mailpit
            ;;
        Linux)
            curl -fsSL https://raw.githubusercontent.com/axllent/mailpit/develop/install.sh | sudo bash
            ;;
        *)
            print error "Unsupported OS. Run Mailpit with Docker instead:"
            echo "  docker run -d --restart unless-stopped -p 127.0.0.1:1025:1025 -p 127.0.0.1:8025:8025 axllent/mailpit"
            exit 1
            ;;
    esac
}

start_mailpit() {
    case "$(uname -s)" in
        Darwin)
            brew services restart mailpit >/dev/null
            ;;
        Linux)
            local bin
            bin="$(command -v mailpit)"
            # Bind to localhost only: the inbox holds copies of real people's mail.
            sudo tee /etc/systemd/system/mailpit.service >/dev/null <<EOF
[Unit]
Description=Mailpit (ePT dev mail trap)
After=network.target

[Service]
ExecStart=$bin --listen 127.0.0.1:8025 --smtp 127.0.0.1:1025
Restart=always
DynamicUser=yes

[Install]
WantedBy=multi-user.target
EOF
            sudo systemctl daemon-reload
            sudo systemctl enable --now mailpit >/dev/null 2>&1
            sudo systemctl restart mailpit
            ;;
    esac

    for _ in $(seq 1 20); do
        curl -fs "$MAILPIT_API/info" >/dev/null 2>&1 && { print success "Mailpit is running (inbox: http://localhost:8025)"; return; }
        sleep 0.5
    done
    print error "Mailpit did not come up on 127.0.0.1:8025."
    exit 1
}

# --- application.ini --------------------------------------------------------------

set_trap_dsn() {
    local value="$1"
    php -r '
        require $argv[1] . "/cli-bootstrap.php";
        exit(Application_Service_Common::writeProductionIniValues(["email.devTrapDsn" => $argv[2]]) ? 0 : 1);
    ' "$ROOT_DIR" "$value" || { print error "Could not write application/configs/application.ini (is it writable?)."; exit 1; }
    print success "Set email.devTrapDsn = \"$value\" in application.ini"
}

# Prints the trap value the app actually sees (APPLICATION_ENV section, inheritance applied).
effective_trap_dsn() {
    php -r '
        require $argv[1] . "/constants.php";
        require $argv[1] . "/vendor/autoload.php";
        set_include_path($argv[1] . "/library" . PATH_SEPARATOR . get_include_path());
        $c = new Zend_Config_Ini(APPLICATION_PATH . "/configs/application.ini", APPLICATION_ENV);
        echo trim((string) ($c->email->devTrapDsn ?? ""));
    ' "$ROOT_DIR"
}

# --- verification -----------------------------------------------------------------

verify() {
    local dsn
    dsn="$(effective_trap_dsn)"
    if [[ -z "$dsn" ]]; then
        print error "email.devTrapDsn is NOT set. This box will send mail to real recipients."
        exit 1
    fi
    if [[ "$dsn" != smtp://* ]]; then
        print success "email.devTrapDsn = \"$dsn\": all outgoing mail is blocked."
        return
    fi
    print info "email.devTrapDsn = \"$dsn\""

    curl -fs "$MAILPIT_API/info" >/dev/null 2>&1 || { print error "Mailpit is not reachable at http://127.0.0.1:8025. Start it, or rerun without --check."; exit 1; }

    local subject="ePT mail trap check $(date +%s)"
    php -r '
        require $argv[1] . "/cli-bootstrap.php";
        $ok = (new Application_Service_Common())->sendMail(
            "trap-check@example.com", "", "", $argv[2],
            "If you can read this in Mailpit, the ePT dev mail trap works.",
            "ept-dev@example.com", "ePT dev"
        );
        exit($ok ? 0 : 1);
    ' "$ROOT_DIR" "$subject" || { print error "The app could not send the test mail. Check the PHP error log."; exit 1; }

    sleep 1
    local found
    found="$(curl -fs -G "$MAILPIT_API/search" --data-urlencode "query=subject:\"$subject\"" \
        | php -r '$d = json_decode(stream_get_contents(STDIN), true); echo $d["messages"][0]["ID"] ?? "";')"
    if [[ -n "$found" ]]; then
        print success "Test mail sent by the app was caught by Mailpit. No mail from this box reaches real people."
        print info "Open it: http://localhost:8025/view/$found"
        print info "Running php scheduled-jobs/send-emails.php by hand prints a link like this for each queued mail it sends."
    else
        print error "The test mail did not arrive in Mailpit. Do not trust this box with a live database yet."
        exit 1
    fi
}

# --- main ---------------------------------------------------------------------------

case "$MODE" in
    setup)
        print header "ePT dev mail trap (Mailpit)"
        install_mailpit
        start_mailpit
        set_trap_dsn "$TRAP_DSN"
        verify
        ;;
    block)
        print header "ePT dev mail trap (block all mail)"
        set_trap_dsn "block"
        verify
        ;;
    check)
        verify
        ;;
esac
