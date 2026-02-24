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

If you have a database SQL file to import, you can pass it as an argument:

```bash
sudo ./ept-setup.sh --db /path/to/ept-base.sql
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
- **License**: [AGPL-3.0](../LICENSE.md)
