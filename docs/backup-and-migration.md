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

# List archives
ept backup list

# Check one archive is intact (an encrypted archive needs the database password)
ept backup verify backups/<archive>.sql.zst.gpg --db-password='<database password>'
```

Archives are written to `<ept>/backups` with a 15-archive retention by default.

> **Backups are encrypted by default:** `db-tools backup` encrypts archives (`.gpg`) unless you pass `--no-encrypt`. The encryption password is built from the database password in `application.ini` and the random part of the archive's filename. `ept restore` on the same server works it out by itself. `ept backup verify` needs the database password passed as `--db-password`. To restore on a *different* server, pass `--encryption-password` set to the old server's database password followed by the 32-character random part of the archive's filename. An unencrypted archive made with `--no-encrypt` needs no password (see [Migrating](#migrating-to-a-new-machine)).

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
php vendor/bin/db-tools verify backups/<archive> --db-password='<database password>'
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

Use these steps to move ePT from one Ubuntu server to another. You install ePT on the new server first and confirm it works. Then you bring across the files and the database. Plan for one to two hours of downtime.

The steps work for every ePT version, including installs several years old. Where an older install differs, the step says so.

### Prerequisites

Have all of these ready before you start.

#### The new server

1. A fresh install of Ubuntu 26.04 or 24.04 LTS, with nothing else installed. Setup installs Apache, MySQL and PHP itself.
2. At least 2 GB of RAM if ePT is the only application on the server. 4 GB is better.
3. At least 50 GB of disk space.
4. Internet access. Setup downloads packages and ePT from GitHub.
5. Ports 22 (SSH), 80 (HTTP) and 443 (HTTPS) open on the firewall.
6. Root SSH access, or a user account with sudo access.

#### Logins and passwords

1. SSH access to the new server.
2. SSH access to the old server, for a user with sudo access.

#### Other

1. Access to the domain's DNS settings, or the contact of the person who manages them.
2. A one-to-two-hour maintenance window. Tell participants and data managers in advance that ePT is unavailable during the move.

### Step 1. Find the installation on the old server

1. Find the folder the website serves:

   ```bash
   grep -rh DocumentRoot /etc/apache2/sites-enabled/
   ```

   The installation folder is that path without `/public`. The rest of this guide calls it `OLD`. Replace `OLD` with that folder in every command. A server can hold several old copies of ePT. Only the folder Apache serves is live.

2. Note the database name, user and password:

   ```bash
   sudo grep '^resources.db.params' OLD/application/configs/application.ini
   ```

### Step 2. Pack everything on the old server

1. Stop the website and scheduled jobs, so nothing changes during the move. They stay stopped after a reboot.

   ```bash
   sudo systemctl disable --now apache2 cron
   ```

2. Create the folder that holds everything you move:

   ```bash
   sudo mkdir -p /var/ept-move
   sudo chmod 700 /var/ept-move
   ```

   The folder holds passwords and the whole database, so only root can read it.

3. Back up the database into that folder. If `OLD/vendor/bin/db-tools` exists, run:

   ```bash
   cd OLD
   sudo php vendor/bin/db-tools backup --no-encrypt --compression=gzip --output-dir=/var/ept-move
   ```

   Older installs have no `db-tools`, and some older `db-tools` versions reject these options. In either case, run `mysqldump` with the database user from Step 1 instead, and enter its password when asked:

   ```bash
   mysqldump -u DB_USER -p --single-transaction --no-tablespaces --default-character-set=utf8mb4 DB_NAME | gzip | sudo tee /var/ept-move/ept-database.sql.gz >/dev/null
   ```

4. Pack the settings, uploads, reports and certificate templates:

   ```bash
   cd OLD
   sudo tar --ignore-failed-read -czf /var/ept-move/ept-files.tar.gz \
     application/configs/application.ini \
     application/configs/.env \
     application/configs/config.ini \
     public/uploads \
     downloads \
     scheduled-jobs/certificate-templates \
     scheduled-jobs/certificate \
     scheduled-jobs/certificates
   ```

   Some of these files do not exist on every install. `tar` skips the missing ones. Older installs keep certificates in `scheduled-jobs/certificate` or `scheduled-jobs/certificates`.

Run `ls -lh /var/ept-move`. The folder holds a database backup ending in `.sql.gz` and `ept-files.tar.gz`.

### Step 3. Copy the folder to the new server

In these commands, replace `NEW` with the new server's IP address.

1. On the old server, let it log in to the new server. Enter the new server's password when asked:

   ```bash
   sudo test -f /root/.ssh/id_ed25519 || sudo ssh-keygen -t ed25519 -N "" -f /root/.ssh/id_ed25519
   sudo ssh-copy-id -i /root/.ssh/id_ed25519.pub root@NEW
   ```

   If `ssh-copy-id` fails with `Permission denied (publickey)`, the new server accepts only SSH keys. Add the contents of `/root/.ssh/id_ed25519.pub` to `/root/.ssh/authorized_keys` on the new server instead.

2. Copy the folder:

   ```bash
   sudo scp -rp /var/ept-move root@NEW:/root/
   ```

If the old server cannot reach the new one, copy the folder through your own computer, or use a USB drive. Put it at `/root/ept-move` on the new server. Do not use `/tmp`, because the server clears it when it restarts.

### Step 4. Install ePT on the new server

Run these commands on the new server.

1. Download and run setup, without the old database:

   ```bash
   cd ~
   wget -O ept-setup.sh https://raw.githubusercontent.com/deforay/ept/master/bin/setup.sh
   chmod +x ept-setup.sh
   sudo ./ept-setup.sh
   ```

2. Answer the setup questions:

   | Setup asks for | Answer |
   | --- | --- |
   | Installation path | Press Enter to accept `/var/www/ept`. |
   | MySQL root password | Choose a new strong password. Write it down. It also encrypts this server's backups. |
   | Domain name | Enter the domain the old server used. |
   | SSL (HTTPS) with Let's Encrypt | Answer no. Step 8 sets up HTTPS. |
   | First admin account: name, email and password | Enter any details. This account is temporary. The old database replaces it in Step 7. |

3. Confirm the new server works before you bring any data across:

   ```bash
   php -v | head -1
   systemctl is-active apache2 mysql
   ept check
   ```

   Expect PHP 8.4.27 or later, or 8.5.12 or later, and `active` twice. Ubuntu 26.04's own PHP 8.5.4 also passes, because Ubuntu patched it. `ept check` warns that no database backups exist. That warning is expected until Step 9. If any other check fails, stop here. The old server is unchanged, so you can turn it back on with `sudo systemctl enable --now apache2 cron`.

### Step 5. Restore the files

1. Stop scheduled jobs on the new server until the move is finished:

   ```bash
   sudo systemctl stop cron
   ```

2. Unpack the files. Keep the old settings file aside, because setup already wrote a current one:

   ```bash
   cd /var/www/ept
   sudo tar -xzf /root/ept-move/ept-files.tar.gz -C /var/www/ept --exclude=application/configs/application.ini
   sudo tar -xzf /root/ept-move/ept-files.tar.gz -C /root/ept-move application/configs/application.ini
   ```

3. Remove write access for everyone else. Old servers sometimes have files open to everyone (777), and `tar` keeps those modes.

   ```bash
   sudo chmod -R o-w application/configs public/uploads downloads scheduled-jobs
   ```

   Do not reset the files to 755 or 644. Setup grants access through ACLs, and a full `chmod` would take write access away from the app and from sudo users.

### Step 6. Copy the server's own settings

Do not copy the old `application.ini` over the new one. An older file lacks settings that current ePT needs. Copy across only the values that belong to this server.

1. Show the old settings to carry across:

   ```bash
   sudo grep -E '^(email\.|security\.salt|timezone|whatsapp\.)' /root/ept-move/application/configs/application.ini
   ```

2. Open the new settings file:

   ```bash
   sudo nano /var/www/ept/application/configs/application.ini
   ```

3. Set each `email.` line to its old value. This covers the mail server, login, password and sender. Older settings files have no `email.fromEmail` line. If the new `email.fromEmail` is empty, set it to the mail login address. Most mail servers, Microsoft 365 among them, accept only the login address as sender.
4. If the old file has a `security.salt` line with a value, set the new `security.salt` line to that value. Without the old salt, temporary passwords and download links that ePT already sent stop working.
5. Set `timezone` to the old value. ePT uses it for timestamps, deadlines and the schedule of its jobs. Setup writes `UTC`.
6. If the old file has `whatsapp.` lines with values, copy them too.
7. Leave the `resources.db.params` lines as setup wrote them.

### Step 7. Load the old database

1. Replace the empty database that setup created with the old one:

   ```bash
   DB=$(grep -m1 '^resources.db.params.dbname' /var/www/ept/application/configs/application.ini | sed 's/.*= *//')
   sudo mysql -e "DROP DATABASE \`$DB\`; CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
   gunzip -c /root/ept-move/*.sql.gz | sudo mysql "$DB"
   ```

2. Upgrade the database to the current version:

   ```bash
   ept post-update
   ```

   This step repairs and upgrades the database, and moves the old `config.ini` settings into the database. On installs several years old it can take a long time. Old versions stored passwords as plain text, and this step encrypts each one, at about one second per account. Do not interrupt it.

3. Check for email that the old server queued but never sent:

   ```bash
   sudo mysql "$DB" -e "SELECT status, COUNT(*) FROM temp_mail GROUP BY status"
   sudo mysql "$DB" -e "SELECT subject, COUNT(*) FROM temp_mail WHERE status = 'pending' GROUP BY subject"
   ```

   When scheduled jobs start, the new server sends `pending` messages that are less than seven days old. On an install upgraded from an older version, the upgrade dates every queued message to the day of the move, so old messages count as new. If the queue holds old messages, such as stale password resets, cancel them:

   ```bash
   sudo mysql "$DB" -e "UPDATE temp_mail SET status = 'cancelled' WHERE status = 'pending'"
   ```

4. Check for participant emails that were scheduled to go out during the move. Scheduled jobs send each one whose time has passed:

   ```bash
   sudo mysql "$DB" -e "SELECT id, subject, scheduled_at FROM email_participants WHERE status = 'scheduled'"
   ```

   Pause or reschedule any you do not want sent, from the admin pages, after the move.

5. Start scheduled jobs, and check the server:

   ```bash
   sudo systemctl start cron
   ept check
   ```

   Fix each problem `ept check` lists before you continue. The warning about missing backups is expected until Step 9.

### Step 8. Point the domain to the new server

1. Change the domain's DNS A record to the new server's IP address.

2. Check the domain's own name servers. They answer with the new address within minutes:

   ```bash
   dig +short NS example.org
   dig +short your-domain.org @NAME-SERVER
   ```

   In the first command, use the domain that holds the DNS records. For `ept.example.org`, that is `example.org`. Replace `NAME-SERVER` with one of the names it returns.

   Other resolvers keep the old address until the record's TTL runs out, usually up to an hour. If your own computer still reaches the old server, clear its DNS cache.

3. When the name servers answer with the new address, turn on HTTPS:

   ```bash
   sudo certbot --apache -d your-domain.org
   ```

   Let's Encrypt asks the domain's own name servers, so this works before every resolver has the new address. Skip this step if the old server did not use HTTPS.

4. In `/var/www/ept/application/configs/application.ini`, set every `domain` line to the address users open, for example `domain = https://your-domain.org/`. The file has one in each section.

If users reach ePT by IP address, give them the new address instead.

### Step 9. Confirm the move worked

1. Open `https://your-domain.org/admin` and log in with your usual password.
2. Open the participant list and a shipment. Confirm the data matches the old server.
3. Open a report that was finalized on the old server.
4. Generate one new report.
5. On the participant login page, use **Forgot password** for a data manager account whose mailbox you can read. Confirm the email arrives. If it does not, read the reason the mail server gave:

   ```bash
   DB=$(grep -m1 '^resources.db.params.dbname' /var/www/ept/application/configs/application.ini | sed 's/.*= *//')
   sudo mysql "$DB" -e "SELECT subject, failure_reason FROM temp_mail WHERE status IN ('failed', 'skipped') ORDER BY temp_id DESC LIMIT 3"
   ```

   An empty sender points back to Step 6. A Microsoft 365 reply of `535 5.7.139` means the mailbox is not allowed to sign in over SMTP. The mail administrator must turn on Authenticated SMTP for that mailbox.

6. Take the first backup on the new server:

   ```bash
   ept backup
   ```

If all six work, the move is complete.

If the move fails, run `sudo systemctl enable --now apache2 cron` on the old server and point the domain back to it. Nothing on the old server changed.

### After the move

1. Keep the old server as it is for two weeks.
2. Then delete `/var/ept-move` on the old server and `/root/ept-move` on the new server, because they hold passwords.
3. On the new server, remove the old server's key from `/root/.ssh/authorized_keys`.

---

## Command reference

```bash
ept backup                         # database + config snapshot → <ept>/backups
ept db-backup                      # database backup only
ept backup list                    # list archives
ept backup verify <archive> --db-password='...'   # integrity check
ept restore                        # restore (interactive; safety backup first)
ept pitr [restore]                 # point-in-time recovery from binlogs
ept check                          # includes the age of the latest backup
php vendor/bin/db-tools export     # plain SQL export (from the ePT directory)
php vendor/bin/db-tools import     # plain SQL import
php vendor/bin/db-tools config:show   # active profile
sudo ./ept-setup.sh --db <archive>    # fresh install + DB import (new machine)
sudo ept update                       # update in place (optional pre-upgrade backup)
```
