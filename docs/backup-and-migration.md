# Backup, Recovery & Migration

This page covers three related operations for an ePT instance: taking **backups**, **restoring** them (disaster recovery), and **moving an instance to a new machine**.

ePT is designed to run as a **single central instance**, so the tooling here favours simple, operator-driven procedures over fleet automation. The building blocks are:

| Tool | Role |
| --- | --- |
| `vendor/bin/db-tools` (`amitdugar/db-tools`) | Create, restore, verify, and point-in-time-recover database backups |
| `composer` scripts | Convenience wrappers around db-tools + config snapshot |
| `bin/setup.sh` | Fresh install on a new machine, with an optional database import |
| `bin/upgrade.sh` (`ept-update`) | Takes pre-upgrade safety backups automatically |

> **What is and isn't automated:** db-tools handles the **database** end-to-end. Application **code** is always fetched fresh from GitHub by `setup.sh`. **Uploaded files** (`public/uploads/`) and **config secrets** are never carried automatically — you copy those by hand during a move (see [Migrating to a new machine](#migrating-to-a-new-machine)).

---

## Backups

### Quick backup

From the ePT directory:

```bash
# Database only
composer db-backup

# Database + a snapshot of application.ini (config)
composer backup
```

Both wrap `vendor/bin/db-tools`. Archives are written to `<ept>/backups` with a 15-archive retention by default.

> **Backups are encrypted by default:** `db-tools backup` encrypts archives (`.gpg`) using an encryption password unless you pass `--no-encrypt`. **You need the same password to restore.** For a backup you intend to restore on a *different* machine, either record the encryption password or create an unencrypted archive with `--no-encrypt` (see [Migrating](#migrating-to-a-new-machine)).

### Direct db-tools usage

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

Run `composer db-backup` (or `php vendor/bin/db-tools backup`) from cron on whatever cadence the site needs. Old archives beyond the retention count are pruned automatically.

> **Pre-upgrade backups are automatic:** `ept-update` (`bin/upgrade.sh`) offers to back up the **database** to `/var/ept-backup/db/` and the **ePT folder** to `/var/ept-backup/www/` before every update. Those pre-upgrade dumps are a valid source for a restore or a migration — you don't have to take a fresh one just to move.

---

## Restore & disaster recovery

To restore onto the **same machine** (or to rebuild after data loss):

```bash
# Interactive: pick an archive from the backups dir
php vendor/bin/db-tools restore

# Restore a specific archive
php vendor/bin/db-tools restore /path/to/ept-YYYYMMDD-HHMMSS.sql.zst.gpg

# Encrypted archive — supply the password
php vendor/bin/db-tools restore <archive> --encryption-password='...'
```

db-tools takes a **safety backup before restoring** by default (skip with `--skip-safety-backup`), and prompts for confirmation unless you pass `--force`.

### Point-in-time recovery

If binary logging is enabled, db-tools can roll forward past the last full backup:

```bash
php vendor/bin/db-tools pitr-info                 # inspect available binlogs
php vendor/bin/db-tools pitr-restore              # apply binlogs to a target time
php vendor/bin/db-tools purge-binlogs             # housekeeping
```

---

## Migrating to a new machine

`setup.sh` installs fresh code and **imports your database**, but it does **not** carry over uploads or config from the old machine. A complete move is therefore three parts: database, uploaded files, and config secrets.

> **Preserve the salt and form secret:** Two values must survive the move, or existing hashed/encrypted data and logged-in sessions will break:
>
> - `application/configs/application.ini` → `security.salt`
> - `application/configs/.env` → `FORM_SECRET`
>
> A fresh `setup.sh` **regenerates both**. Copy the originals from the old machine *after* setup completes.

### 1. On the old machine

```bash
# Database — unencrypted for a straightforward migration
php vendor/bin/db-tools backup --no-encrypt
# (or keep it encrypted and carry the --encryption-password to the new box)

# Uploaded files and config secrets — copy these off the box
#   application/configs/application.ini   (contains security.salt + DB creds)
#   application/configs/config.ini        (org / evaluation settings)
#   application/configs/.env              (FORM_SECRET)
#   public/uploads/                       (participant uploads, assets)
```

Transfer the archive plus those files/folders to the new server (scp/rsync/USB — your choice).

### 2. On the new machine

Run setup, pointing `--db` at the transferred archive (local path **or** URL; `.sql`, `.gz`, `.zip` supported):

```bash
cd ~
sudo wget -O ept-setup.sh https://raw.githubusercontent.com/deforay/ept/master/bin/setup.sh
sudo chmod +x ept-setup.sh
sudo ./ept-setup.sh --db /path/to/ept-backup.sql.gz
```

This builds the LAMP stack, downloads ePT, and imports your database instead of the bundled `sql/init.sql`.

### 3. Restore uploads and config, then verify

```bash
# Put the uploaded files back
rsync -a /path/to/old/public/uploads/  /var/www/ept/public/uploads/

# Restore config secrets (review DB host/creds for the new box first)
cp /path/to/old/application.ini  /var/www/ept/application/configs/application.ini
cp /path/to/old/config.ini       /var/www/ept/application/configs/config.ini
cp /path/to/old/.env             /var/www/ept/application/configs/.env

# Fix ownership/permissions if needed, then run any pending migrations
cd /var/www/ept && composer post-update
```

> **Check DB credentials after copying application.ini:** `application.ini` carries the **old** machine's database host/user/password. If the new machine's MySQL differs, update `resources.db.params.*` before loading the app.

Finally, log in at `http://<host>/admin`, confirm participants/shipments/reports render, and generate one report end-to-end to be sure uploads and the salt/secret came across intact.

---

## What setup.sh carries vs. what you carry

| Item | Carried by `setup.sh` | You handle |
| --- | --- | --- |
| Application code | ✅ fresh from GitHub master | — |
| Database | ✅ via `--db=<archive>` | Produce the archive with db-tools |
| `public/uploads/` | ❌ | Copy manually |
| `application.ini` / `config.ini` / `.env` | ❌ regenerated from templates | Copy manually |
| `security.salt` / `FORM_SECRET` | ❌ regenerated | **Must** copy from old machine |

---

## Command reference

```bash
composer backup                    # db-tools backup + config snapshot
composer db-backup                 # database backup only
php vendor/bin/db-tools backup     # backup (encrypted, compressed) → <ept>/backups
php vendor/bin/db-tools restore    # restore (interactive; safety backup first)
php vendor/bin/db-tools show       # list archives
php vendor/bin/db-tools verify     # integrity check
php vendor/bin/db-tools export     # plain SQL export
php vendor/bin/db-tools import     # plain SQL import
php vendor/bin/db-tools config:show   # active profile
sudo ./ept-setup.sh --db <archive>    # fresh install + DB import (new machine)
sudo ept-update                       # update in place (auto pre-upgrade backup)
```
