# Installing ePT

---

## Installing on Ubuntu 22.04 or above (only Ubuntu LTS)

**Important:** This installation works exclusively on Ubuntu 22.04 or later LTS versions. Ubuntu 24.04 LTS is recommended.

### Installation Steps

Open your terminal and execute these commands sequentially:

```bash
cd ~;

sudo wget -O ept-setup.sh https://raw.githubusercontent.com/deforay/ept/master/bin/setup.sh

sudo chmod +x ept-setup.sh;

sudo ./ept-setup.sh;

sudo rm ept-setup.sh;

exit
```

**Critical:** When prompted during installation, provide the MySQL password and domain name with accuracy.

**SSL (optional):** If the server has a public IP and you enter a real domain name (e.g., `ept.example.org`), the setup script will offer to install a free SSL certificate via Let's Encrypt (Certbot). This is optional and defaults to no.

If you have a database SQL file to import, you can pass it as a local path or URL:

```bash
# Local file (supports .sql, .gz, .zip)
sudo ./ept-setup.sh --db /path/to/ept-base.sql.gz

# URL (supports .sql, .gz, .zip)
sudo ./ept-setup.sh --db https://example.com/ept-base.sql.gz
```

### Post-Installation

- Following successful setup completion, access ePT through `http://ept/admin` (or `https://yourdomain/admin` if SSL was configured) in your browser
- If no SQL file was provided, the setup will prompt you to create an initial admin account
- Change the default admin password immediately after first login
- Configure your organization settings, add participants, create PT surveys, and set up shipments

### Updating

To update an existing ePT installation:

```bash
sudo ept-update
```

Or specify a path: `sudo ept-update -p /var/www/ept`

To update all ePT instances: `sudo ept-update -A`

---

## Installing with Docker

Docker provides the simplest way to get ePT running with a single command. No need to manually install PHP, Apache, MySQL, or Node.js.

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) and [Docker Compose](https://docs.docker.com/compose/install/) installed on your system

### Quick Start

```bash
git clone https://github.com/deforay/ept.git
cd ept
docker compose up --build -d
```

Once the containers are running, create the initial admin account:

```bash
docker compose exec ept php bin/seed-admin.php
```

Then access ePT at [http://localhost/admin](http://localhost/admin).

To run on a custom port (e.g. 3456):

```bash
APP_PORT=3456 docker compose up --build -d
```

Then access ePT at `http://localhost:3456/admin`.

### Configuration

Copy the example environment file and adjust as needed:

```bash
cp .env.example .env
```

Available environment variables:

| Variable | Default | Description |
| --- | --- | --- |
| `DB_HOST` | `ept-db` | MySQL hostname (use the service name) |
| `DB_USER` | `root` | MySQL user |
| `DB_PASSWORD` | `ept_secret` | MySQL password |
| `DB_NAME` | `ept` | Database name |
| `APP_PORT` | `80` | Host port to expose the application on |
| `APP_DOMAIN` | `http://localhost/` | Application URL |
| `APP_HOSTNAME` | `localhost` | Domain name (used by nginx for SSL) |

!!! warning "Change the default password"
    Update `DB_PASSWORD` and `MYSQL_ROOT_PASSWORD` in `docker-compose.yml` before deploying to production.

### What's Included

The Docker setup runs everything in two containers:

- **ept** — PHP 8.2, Apache, Node.js (for chart rendering), Composer dependencies, and a cron job for the task scheduler
- **ept-db** — MySQL 8.0, seeded from `sql/init.sql`

On first startup, the entrypoint script automatically:

1. Generates `application.ini`, `config.ini`, and `.env` from the dist templates
2. Injects database credentials and domain from environment variables
3. Waits for MySQL to be ready
4. Runs database migrations
5. Starts the cron scheduler

### Persistent Data

The following data is stored in Docker volumes and survives container restarts:

- `mysql_data` — database files
- `uploads` — uploaded files
- `logs` — application logs
- `backups` — database backups
- `downloads` — generated downloads

### Common Commands

```bash
# Start in background
docker compose up --build -d

# View logs
docker compose logs -f ept

# Stop containers
docker compose down

# Stop and remove all data (including database)
docker compose down -v

# Run migrations manually
docker compose exec ept composer post-update

# Seed initial admin account (first-time setup)
docker compose exec ept php bin/seed-admin.php

# Access MySQL shell
docker compose exec ept-db mysql -u root -p ept

# Access app shell
docker compose exec ept bash
```

### Updating (Docker)

To update ePT to the latest version:

```bash
git pull && docker compose up --build -d && docker compose exec ept composer post-update
```

### Importing an Existing Database

To import an existing SQL file instead of the bundled `init.sql`:

```bash
# Stop containers and remove old data
docker compose down -v

# Replace the init file
cp /path/to/your-database.sql sql/init.sql

# Rebuild and start
docker compose up --build -d
```

### SSL with Let's Encrypt (Docker)

For production servers with a public IP and domain name, you can enable HTTPS with automatic Let's Encrypt certificates.

**Prerequisites:**

- A domain name (e.g. `ept.example.org`) pointed at your server's public IP
- Ports 80 and 443 open on the firewall

**First-time setup:**

```bash
# Initialize certificates (run once)
sudo ./docker/init-letsencrypt.sh ept.example.org admin@example.org

# Start with SSL enabled
APP_HOSTNAME=ept.example.org APP_DOMAIN=https://ept.example.org/ docker compose --profile ssl up -d
```

**How it works:**

The `--profile ssl` flag adds two extra containers:

- **nginx** — Reverse proxy that terminates SSL on ports 80/443 and forwards to the app
- **certbot** — Handles Let's Encrypt certificate issuance and renewal

**Certificate renewal:**

Certificates are valid for 90 days. To renew:

```bash
docker compose --profile ssl run --rm certbot renew
docker compose --profile ssl exec nginx nginx -s reload
```

!!! note "Dev mode"
    Without `--profile ssl`, the app runs on HTTP only (on `APP_PORT`, default 80) — no nginx or certbot containers are started. This is the recommended setup for local development.

---

## Installing on Windows

### 0. Download

- [Notepad++](https://notepad-plus-plus.org/) or [VS Code](https://code.visualstudio.com/)
- [WampServer](https://www.wampserver.com/en/) (select 32 or 64 bit based on your system)
- [VC Packages](https://wampserver.aviatechno.net/files/vcpackages/all_vc_redist_x86_x64.zip)

### 1. Install WAMP Server

- Ensure Windows system is fully updated
- Install VC Packages (all packages for 64-bit; only 32-bit packages for 32-bit systems)
- Reboot the machine
- Launch WampServer and verify the icon displays green

### 2. Configure PHP

- Download `cacert.pem` from https://curl.se/docs/caextract.html and place in `C:\wamp64\`
- Switch PHP version to 8.4: WampServer icon > PHP > version > 8.4.x
- Open `php.ini` via WampServer icon > PHP > php.ini and modify:
  - `memory_limit` = `2G` (or higher if available)
  - `post_max_size` = `500M`
  - `upload_max_filesize` = `500M`
  - `max_execution_time` = `1200`
  - `error_reporting` = `E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED & ~E_WARNING`
  - `;openssl.cafile=` to `openssl.cafile='C:\wamp64\cacert.pem'`
  - `;curl.cainfo =` to `curl.cainfo ='C:\wamp64\cacert.pem'`
- Repeat these edits in `C:\wamp64\bin\php\php8.4.x\php.ini`

### 3. Configure MySQL

Open WampServer icon > MySQL > my.ini and:

- Search for `sql_mode` and comment it out with `;` at line start
- Add these lines under `[mysqld]`:
  ```ini
  sql_mode =
  innodb_strict_mode = 0
  ```

**Set MySQL root password:**

- WampServer icon > MySQL > MySQL Console
- Username: `root`, Password: (blank - press Enter)
- Execute:
  ```sql
  ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'YOUR_PASSWORD';
  FLUSH PRIVILEGES;
  exit;
  ```

Restart all WampServer services. Then download and install [Composer](https://getcomposer.org/download/).

### 4. Set Up ePT

**Download and extract:**

- Download ePT from https://github.com/deforay/ept/releases (or clone the repo)
- Extract and place in `C:\wamp64\www\ept`

**Install dependencies:**

Open a terminal and run:

```
cd C:\wamp64\www\ept
set PATH=C:\wamp64\bin\php\php8.4.x;%PATH%
composer install --no-dev
composer dump-autoload -o
```

**Database setup:**

- Access phpMyAdmin at http://localhost/phpmyadmin
- Click SQL tab and execute:
  ```sql
  CREATE DATABASE `ept` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
  ```
- Import the base SQL file from the [releases page](https://github.com/deforay/ept/releases) into the `ept` database

**Configuration:**

- Rename `application/configs/application.dist.ini` to `application/configs/application.ini`
- Rename `application/configs/config.dist.ini` to `application/configs/config.ini`
- Edit `application.ini` with:
  - Domain: `domain = http://ept/`
  - Database credentials (`resources.db.params.*`)
  - Email SMTP settings
  - Security salt (set to a random string)
- Edit `config.ini` with your organization name and evaluation settings

**Virtual host setup:**

- Open `C:\Windows\System32\drivers\etc\hosts` as administrator and add:
  ```
  127.0.0.1 ept
  ```
- Edit `C:\wamp64\bin\apache\apache2.x.x\conf\extra\httpd-vhosts.conf` and add:
  ```apache
  <VirtualHost *:80>
      ServerName ept
      DocumentRoot "C:/wamp64/www/ept/public"
      <Directory "C:/wamp64/www/ept/public/">
          AddDefaultCharset UTF-8
          Options -Indexes +FollowSymLinks +MultiViews
          AllowOverride All
          Require local
      </Directory>
  </VirtualHost>
  ```
- Restart all WampServer services

**Run migrations:**

```
cd C:\wamp64\www\ept
set PATH=C:\wamp64\bin\php\php8.4.x;%PATH%
php bin\migrate.php -yq
```

### 5. Task Scheduler (Windows)

- Open Task Scheduler and create a new task named "ePT Task"
- Select "Run whether user is logged on or not"
- Under **Triggers** tab: Create new trigger
  - Select Daily
  - Check "Repeat task every" and set to **1 minute** indefinitely
- Under **Actions** tab: Create new action
  - Program: `C:\wamp64\bin\php\php8.4.x\php.exe`
  - Arguments: `C:\wamp64\www\ept\vendor\bin\crunz schedule:run`
- Enter Windows user password when prompted

### Post-Installation (Windows)

- Access ePT at http://ept/admin
- Log in with the admin credentials from the SQL import
- Change the default admin password immediately

---

## Support

- **GitHub**: [deforay/ept](https://github.com/deforay/ept)
- **Contact**: amit@deforay.com
- **License**: [AGPL-3.0](https://github.com/deforay/ept/blob/master/LICENSE.md)
