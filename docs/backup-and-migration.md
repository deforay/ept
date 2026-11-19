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

`setup.sh` installs fresh code and **imports your database**, but it does **not** carry over uploads or config from the old machine. A complete move has four parts: the database, uploaded files, generated reports, and config secrets.

> **Preserve the salt and form secret:** Two values must survive the move, or existing hashed/encrypted data and logged-in sessions will break:
>
> - `application/configs/application.ini` → `security.salt`
> - `application/configs/.env` → `FORM_SECRET`
>
> A fresh `setup.sh` **regenerates both**. Copy the originals from the old machine *after* setup completes.

### 1. On the old machine

```bash
# Database: unencrypted and gzip-compressed, because setup.sh reads only .sql, .gz and .zip
php vendor/bin/db-tools backup --no-encrypt --compression=gzip

# Uploaded files and config secrets — copy these off the box
#   application/configs/application.ini   (contains security.salt + DB creds)
#   application/configs/.env              (FORM_SECRET)
#   public/uploads/                       (participant uploads, report formats, logos)
#   downloads/                            (generated reports and participant files)
#   scheduled-jobs/certificate-templates/ (certificate designs)
```

Transfer the archive and those files and folders to the new server with scp, rsync or a USB drive.

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
rsync -a /path/to/old/downloads/  /var/www/ept/downloads/
rsync -a /path/to/old/scheduled-jobs/certificate-templates/  /var/www/ept/scheduled-jobs/certificate-templates/

# Restore config secrets (review DB host/creds for the new box first)
cp /path/to/old/application.ini  /var/www/ept/application/configs/application.ini
cp /path/to/old/.env             /var/www/ept/application/configs/.env

# Fix ownership/permissions if needed, then run any pending migrations
ept post-update
ept check
```

> **Check DB credentials after copying application.ini:** `application.ini` carries the **old** machine's database host/user/password. If the new machine's MySQL differs, update `resources.db.params.*` before loading the app.

Finally, log in at `http://<host>/admin`, confirm participants and shipments render, and open one report that was finalized on the old machine. Then generate one new report to confirm uploads and the salt and secret came across intact.

---

## What setup.sh carries vs. what you carry

| Item | Carried by `setup.sh` | You handle |
| --- | --- | --- |
| Application code | ✅ fresh from GitHub master | — |
| Database | ✅ via `--db=<archive>` | Produce the archive with db-tools |
| `public/uploads/` | ❌ | Copy manually |
| `downloads/` | ❌ | Copy manually |
| `scheduled-jobs/certificate-templates/` | ❌ | Copy manually |
| `application.ini` / `.env` | ❌ regenerated from templates | Copy manually |
| `security.salt` / `FORM_SECRET` | ❌ regenerated | **Must** copy from old machine |

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
