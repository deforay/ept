# CLI Tools Reference

This guide covers the command-line tools available in the `bin/` directory. These are intended for the **tech support team** to troubleshoot access issues, manage admin accounts, apply database updates, and maintain ePT installations.

All commands below assume you are in the ePT installation directory (e.g., `/var/www/ept`).

---

## Password Reset

### Reset Data Manager Password

Resets the password for a data manager (participant-facing user). You can look up the user by email or by participant code.

```bash
# Interactive — prompts for email or participant code, then password
php bin/reset-password.php

# By email
php bin/reset-password.php -e user@example.com

# By participant code
php bin/reset-password.php --input PART-12345

# Generate a random password
php bin/reset-password.php -e user@example.com --generate

# Set a specific password and force reset on next login
php bin/reset-password.php -e user@example.com -p "NewPassword123" --force-reset
```

| Flag | Description |
|------|-------------|
| `-e` / `--email` / `--input` | Email address or participant code |
| `-p` / `--password` | Set a specific password (min 6 characters) |
| `-g` / `--generate` | Generate a random 12-character password |
| `--force-reset` | Require the user to change password on next login |

### Reset Admin Password

Resets the password for a system admin (back-office user). If no email is provided, the script lists all admins to choose from.

```bash
# Interactive — lists all admins, pick one, then set password
php bin/reset-admin-password.php

# By email
php bin/reset-admin-password.php -e admin@example.com

# Generate a random password
php bin/reset-admin-password.php -e admin@example.com --generate

# Set a specific password and force reset on next login
php bin/reset-admin-password.php -e admin@example.com -p "NewPassword123" --force-reset
```

| Flag | Description |
|------|-------------|
| `-e` / `--email` | Admin email address |
| `-p` / `--password` | Set a specific password (min 6 characters) |
| `-g` / `--generate` | Generate a random 12-character password |
| `--force-reset` | Require the admin to change password on next login |

---

## Admin Account Management

### Seed Admin

Creates a new admin account interactively. The new admin is given all privileges and will be required to change their password on first login.

```bash
php bin/seed-admin.php
```

The script prompts for first name, last name, email, and password.

> **Note:** During `setup.sh`, this script is called automatically only if no admin accounts exist. When run manually, it can be used any number of times to create additional admins.

---

## Database

### Run Migrations

Applies pending database migrations from `database/migrations/`. Migrations are versioned and run in order — only versions newer than the current `app_version` in `system_config` are applied.

```bash
# Standard run — prompts on errors
php bin/migrate.php

# Auto-continue on errors (non-interactive)
php bin/migrate.php -y

# Quiet mode (suppress output)
php bin/migrate.php -q

# Auto-continue and quiet (used by setup.sh)
php bin/migrate.php -yq

# Dry run — show what would be executed without making changes
php bin/migrate.php -d

# Start from a specific version
php bin/migrate.php -v 7.2.0
```

| Flag | Description |
|------|-------------|
| `-y` | Auto-continue on errors (don't prompt) |
| `-q` | Quiet mode — suppress progress output |
| `-d` | Dry run — print SQL without executing |
| `-v VERSION` | Override starting version (run migrations from this version onward) |

### Run Once Scripts

Executes one-time PHP scripts from the `run-once/` directory. Each script runs only once — execution is tracked in the `run_once_scripts` table.

```bash
php bin/run-once.php
```

This is called automatically during setup and upgrades. Typically you don't need to run it manually unless instructed.

---

## Installation & Updates

### Setup (Fresh Install)

Automated installation script for Ubuntu. Downloads ePT, installs the LAMP stack, configures Apache, MySQL, cron jobs, and optionally sets up SSL.

```bash
sudo wget -O ept-setup.sh https://raw.githubusercontent.com/deforay/ept/master/bin/setup.sh
sudo chmod u+x ept-setup.sh
sudo ./ept-setup.sh
```

```bash
# With a database SQL file
sudo ./ept-setup.sh --db /path/to/ept-base.sql

# With a custom database name
sudo ./ept-setup.sh --db-name myept

# With a specific database strategy for existing databases
sudo ./ept-setup.sh --db-strategy rename
```

| Flag | Description |
|------|-------------|
| `--db FILE` | SQL file to import into the database |
| `--db-name NAME` | Database name (default: `ept`) |
| `--db-strategy` | What to do if the database already exists: `drop`, `rename` (default), or `use` |

During setup, if the server has a public IP and the domain name looks like a real domain (e.g., `ept.example.org`), the script will offer to set up SSL via Let's Encrypt. This is optional and defaults to no.

See the [Setup Guide](setup.md) for full installation instructions.

### SSL Setup (Post-Install)

If you skipped SSL during setup or want to add it later, you can set it up manually with Certbot:

```bash
# Install Certbot
sudo apt-get install -y certbot python3-certbot-apache

# Request a certificate (replace with your domain)
sudo certbot --apache -d yourdomain.example.org

# Verify auto-renewal is active
sudo certbot renew --dry-run
```

After installing the certificate, update `application/configs/application.ini`:

```ini
domain = https://yourdomain.example.org/
```

### Update (Existing Install)

Updates an existing ePT installation to the latest version.

The `ept-update` command is installed automatically during setup. If it's not available, install it manually:

```bash
sudo wget -O /usr/local/bin/ept-update https://raw.githubusercontent.com/deforay/ept/master/bin/upgrade.sh
sudo chmod +x /usr/local/bin/ept-update
```

```bash
# Default — prompts for the installation path
sudo ept-update

# Specific path
sudo ept-update -p /var/www/ept

# Update all ePT instances in /var/www
sudo ept-update -A

# Auto-detect instances, pick which to update
sudo ept-update -A -i

# Non-interactive — update all, skip system updates and backup prompts
sudo ept-update -A -s -b
```

| Flag | Description |
|------|-------------|
| `-p PATH` | Specify the ePT installation path |
| `-A` | Auto-detect and update all ePT installations in `/var/www` |
| `-i` | Interactive instance selection (use with `-A`) |
| `-s` | Skip Ubuntu system updates |
| `-b` | Skip backup prompts |
