# Backup, Recovery & Migration

This page covers three related operations for an ePT instance: taking **backups**, **restoring** them (disaster recovery), and **moving an instance to a new machine**.

ePT is designed to run as a **single central instance**, so the tooling here favours simple, operator-driven procedures over fleet automation. The building blocks are:

| Tool | Role |
| --- | --- |
| `ept` | Everyday commands: back up, list, verify, restore, point-in-time recovery |
| `vendor/bin/db-tools` (`amitdugar/db-tools`) | The engine behind `ept`: create, restore, verify, and point-in-time-recover database backups |
| `bin/setup.sh` | Fresh install on a new machine, with an optional database import |
| `bin/upgrade.sh` (`ept update`) | Offers pre-upgrade database and folder backups |

> **What is and isn't automated:** db-tools handles the **database** end-to-end. Application **code** is always fetched fresh from GitHub by `setup.sh`. **Uploaded files** (`public/uploads/`), **generated reports** (`downloads/`), **certificate templates** and **config secrets** are never carried automatically. Copy them by hand during a move. See [Migrating to a new machine](#migrating-to-a-new-machine).

---

## Backups

### Quick backup

From any directory on the server:

```bash
# Database + a snapshot of application.ini (config)
ept backup

# List archives, and check they are intact
ept backup list
ept backup verify
```

`ept backup` runs as the account that owns the installation, so the archives stay readable by the app. Archives are written to `<ept>/backups` with a 15-archive retention by default.

> **Backups are encrypted by default:** `db-tools backup` encrypts archives (`.gpg`) using an encryption password unless you pass `--no-encrypt`. **You need the same password to restore.** For a backup you intend to restore on a *different* machine, either record the encryption password or create an unencrypted archive with `--no-encrypt` (see [Migrating](#migrating-to-a-new-machine)).

### Direct db-tools usage

Use db-tools directly for options `ept backup` doesn't expose. Run these from the ePT directory:

```bash
# Encrypted, compressed archive into the default backups dir
php vendor/bin/db-tools backup

# Choose compression backend and a filename label
php vendor/bin/db-tools backup --compression=zstd --label=nightly

# Unencrypted archive (e.g. for a same-day migration)
php vendor/bin/db-tools backup --no-encrypt

# List existing archives / check integrity
php vendor/bin/db-tools show
php vendor/bin/db-tools verify
```

The active profile (host, database, output dir, retention, encryption) is shown with:

```bash
php vendor/bin/db-tools config:show
```

### Scheduling

The bundled Crunz schedule defines a daily database backup at 00:45 in the application
timezone. It also defines a Sunday 01:00 snapshot of `application.ini`.
These jobs require a working cron/Crunz runner. Verify execution before adding another
backup schedule. Old archives beyond the retention count are pruned automatically.

The configuration snapshot writes two files to `backups/config/`. One is a copy of `application.ini`. The other is `templates.<timestamp>.zip`, which holds the uploaded certificate templates, report letterheads and logos. The snapshot skips the zip when no template changed. Restore the zip from the installation directory, because its paths are relative to it.

The snapshot does not cover `application/configs/.env`, `public/uploads/` or `downloads/`. File backups and off-host copying need separate arrangements.
See [storage and retention](data-lifecycle.md) for scope and default retention.

> **Pre-upgrade backups are optional:** `ept update` offers database and folder backups.
> Both prompts default to no. Database dumps go to `/var/ept-backup/db/` and folder
> copies to `/var/ept-backup/www/`. Confirm a successful backup before relying on it for recovery.

---

## Restore & disaster recovery

To restore onto the **same machine** (or to rebuild after data loss):

```bash
# Interactive: pick an archive from the backups dir
ept restore

# Restore a specific archive
ept restore /path/to/ept-YYYYMMDD-HHMMSS.sql.zst.gpg

# Encrypted archive — supply the password
ept restore <archive> --encryption-password='...'
```

`ept restore` passes every option to `db-tools restore`.

db-tools takes a **safety backup before restoring** by default (skip with `--skip-safety-backup`), and prompts for confirmation unless you pass `--force`.

### Point-in-time recovery

If binary logging is enabled, db-tools can roll forward past the last full backup:

```bash
ept pitr                                          # inspect available binlogs
ept pitr restore                                  # apply binlogs to a target time
php vendor/bin/db-tools purge-binlogs             # housekeeping (from the ePT directory)
```

---

## Migrating to a new machine

Use these steps to move ePT from one Ubuntu server to another. You pack one folder on the old server, copy it across, and unpack it on the new server. Plan for one hour of downtime.

### Prerequisites

Have all of these ready before you start.

#### The new server

1. A fresh install of Ubuntu 26.04 LTS, with nothing else installed. Setup installs Apache, MySQL 8 and PHP 8.4 itself.
2. At least 2 GB of RAM if ePT is the only application on the server. 4 GB is better.
3. At least 50 GB of disk space.
4. Internet access. Setup downloads packages and ePT from GitHub.
5. Ports 22 (SSH), 80 (HTTP) and 443 (HTTPS) open on the firewall.
6. SSH enabled, with a user account that has sudo access.

#### Logins and passwords

1. The SSH username and password for the new server.
2. The SSH username and password for the old server, for a user with sudo access.
3. The MySQL root password of the old server.

#### Other

1. Access to the domain's DNS settings, or the contact of the person who manages them.
2. A network path from the old server to the new server over SSH. If there is none, a USB drive large enough to hold the ePT data.
3. A one-hour maintenance window. Tell participants and data managers in advance that ePT is unavailable during the move.

The commands assume ePT is installed at `/var/www/ept`. If your installation is somewhere else, change that path in every command.

### Step 1. Pack everything on the old server

Run these commands on the old server:

```bash
# Stop the website and scheduled jobs so nothing changes during the move.
# They stay stopped after a reboot.
sudo systemctl disable --now apache2 cron

# Create the folder that holds everything you move
sudo mkdir -p /var/ept-move

# Back up the database into that folder
cd /var/www/ept
sudo php vendor/bin/db-tools backup --no-encrypt --compression=gzip --output-dir=/var/ept-move

# Pack the settings, uploads, reports and certificate templates into that folder
sudo tar --ignore-failed-read -czf /var/ept-move/ept-files.tar.gz \
  application/configs/application.ini \
  application/configs/.env \
  public/uploads \
  downloads \
  scheduled-jobs/certificate-templates

# Let your own account copy the folder
sudo chown -R "$USER" /var/ept-move
```

Run `ls -lh /var/ept-move`. The folder holds two files: a database backup ending in `.sql.gz` and `ept-files.tar.gz`.

### Step 2. Copy the folder to the new server

```bash
scp -r /var/ept-move USER@NEW-SERVER:/tmp/
```

Replace `USER` with your login on the new server and `NEW-SERVER` with its IP address. If you use a USB drive instead, put the folder at `/tmp/ept-move` on the new server.

### Step 3. Install ePT on the new server

Run these commands on the new server:

```bash
cd ~
wget -O ept-setup.sh https://raw.githubusercontent.com/deforay/ept/master/bin/setup.sh
chmod +x ept-setup.sh
sudo ./ept-setup.sh --db /tmp/ept-move/*.sql.gz
```

If the old database is not named `ept`, add `--db-name OLD_NAME` to the last command, so the database keeps its name. The old settings file expects it. Check the name on the old server with `grep '^resources.db.params.dbname' /var/www/ept/application/configs/application.ini`.

Setup installs ePT and loads the old database. Answer its questions like this:

| Setup asks for | Answer |
| --- | --- |
| Installation path | Press Enter to accept `/var/www/ept`. |
| Domain name | Enter the domain the old server used. |
| SSL (HTTPS) with Let's Encrypt | Answer no. You set up SSL in step 6. |
| MySQL root password | Enter the old server's MySQL root password. |

Use the old MySQL root password because the settings file from the old server contains it.

### Step 4. Unpack the files on the new server

```bash
sudo tar -xzf /tmp/ept-move/ept-files.tar.gz -C /var/www/ept
cd /var/www/ept
sudo chown -R www-data:www-data application/configs public/uploads downloads scheduled-jobs
grep '^resources.db.params' application/configs/application.ini
```

The restored settings file still holds the old server's database settings. Check that `host`, `username`, `password` and `dbname` match the new server. Correct any line that differs. Then run:

```bash
ept post-update
ept check
```

Do not skip this step. Setup creates a new security salt and form secret. The old ones in `ept-files.tar.gz` replace them. Without the old salt, temporary passwords and download links that ePT already sent stop working.

`ept check` lists any problem it finds. Fix each one before you continue.

### Step 5. Point the domain to the new server

Change the domain's DNS A record to the new server's IP address. The change can take up to a few hours to reach every user. If users reach ePT by IP address, give them the new address instead.

### Step 6. Turn on HTTPS

After the domain points to the new server, run:

```bash
sudo certbot --apache -d your-domain.org
```

Replace `your-domain.org` with your domain. Skip this step if the old server did not use HTTPS.

### Step 7. Confirm the move worked

1. Open `https://your-domain.org/admin` and log in with your usual password.
2. Open the participant list and a shipment. Confirm the data matches the old server.
3. Open a report that was finalized on the old server.
4. Generate one new report.

If all four work, the move is complete. Keep the old server as it is for two weeks. Then delete `/var/ept-move` on the old server and `/tmp/ept-move` on the new server, because they hold passwords.

If the move fails, run `sudo systemctl enable --now apache2 cron` on the old server and point the domain back to it. Nothing on the old server changed.

---

## Command reference

```bash
ept backup                         # database + config snapshot → <ept>/backups
ept db-backup                      # database backup only
ept backup list                    # list archives
ept backup verify                  # integrity check
ept restore                        # restore (interactive; safety backup first)
ept pitr [restore]                 # point-in-time recovery from binlogs
ept check                          # includes the age of the latest backup
php vendor/bin/db-tools export     # plain SQL export (from the ePT directory)
php vendor/bin/db-tools import     # plain SQL import
php vendor/bin/db-tools config:show   # active profile
sudo ./ept-setup.sh --db <archive>    # fresh install + DB import (new machine)
sudo ept update                       # update in place (optional pre-upgrade backup)
```
