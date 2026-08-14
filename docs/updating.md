# Updating an ePT installation

This guide updates a running ePT installation to the latest code on `master`.

One command does the whole job: `bin/upgrade.sh`, installed on most boxes as `ept-update`. It refreshes the application code, dependencies, database schema, and cron entry for every instance you point it at.

## Before you start

| Requirement | Detail |
| --- | --- |
| Operating system | Ubuntu with Apache, MySQL, PHP, and Composer already installed by [`setup.sh`](setup.md) |
| Privileges | `root`, or a user who can run `sudo` |
| MySQL root password | Read from `application.ini` when the configured user is `root`. Otherwise the script prompts for it |
| Downtime | The site stays up during the file copy. Apache reloads at the end |

> **Take a backup first on anything you cannot lose:** the update offers its own pre-update backups, and they default to "no". See [Backups](#backups-before-an-update).

## Update every instance on the box

Run the script straight from GitHub:

```bash
curl -fsSL "https://raw.githubusercontent.com/deforay/ept/master/bin/upgrade.sh?v=$(date +%s)" \
  | sudo bash -s -- -A
```

Use this form when `ept-update` is missing, out of date, or you want to be certain you are running the current script. Two details make it work:

- `?v=$(date +%s)` defeats the raw.githubusercontent.com cache, which otherwise serves a stale copy of the script for several minutes after a push.
- The script reads every prompt from `/dev/tty`, not from standard input. Piping it into `bash` does not break the interactive questions.

Everything after `-s --` is passed to the script as flags. `-A` finds and updates every ePT installation under `/var/www`.

## Update from the installed command

If `ept-update` is present, call it directly:

```bash
# Prompt for the installation path, then update it
sudo ept-update

# Update one specific installation
sudo ept-update -p /var/www/ept

# Update every installation under /var/www
sudo ept-update -A

# Detect installations, then choose which ones to update
sudo ept-update -A -i
```

To install or refresh the command:

```bash
sudo wget -O /usr/local/bin/ept-update https://raw.githubusercontent.com/deforay/ept/master/bin/upgrade.sh
sudo chmod +x /usr/local/bin/ept-update
```

The full flag list is in the [CLI Tools Reference](cli-tools.md#update-existing-install).

> **How instances are detected:** `-A` treats a directory under `/var/www` as an ePT installation when it contains both `application/configs/application.ini` and `public/`. Nothing else is touched.

## Run an update unattended

```bash
sudo ept-update -A -s -b
```

`-s` skips the Ubuntu package upgrade. `-b` skips both backup prompts. Use this only when the MySQL root password is reachable from `application.ini`, otherwise the script still stops to ask for it.

Skip the Ubuntu package upgrade with `-s` when the box is patched on its own schedule, or when you want the shortest possible run.

## Update a Docker installation

`ept-update` does not apply to Docker. Rebuild the containers instead:

```bash
git pull && docker compose up --build -d
```

The entrypoint runs `composer post-update` on every container start, so migrations apply themselves. Run the verification commands below inside the container with `docker compose exec ept <command>`.

## Backups before an update

With backups enabled, the script asks two questions and both default to no:

1. Back up the database. Choose the databases by number, or type `all`. Dumps are gzipped to `/var/ept-backup/db/<database>_<timestamp>.sql.gz`.
2. Back up the ePT folder. Each instance is copied to `/var/ept-backup/www/<folder>-backup-<timestamp>`.

These dumps are a valid source for a restore or a machine move. See [Backup, Recovery & Migration](backup-and-migration.md).

## Verify the update

The run ends with an "Upgrade Summary" block listing the instances that succeeded and the instances that failed. Check three things after it prints.

Confirm the code and database agree:

```bash
cd /var/www/ept
sudo -u www-data php bin/check-version-sync.php
```

A healthy install prints:

```text
Version in sync: 7.6.12
```

Confirm the deployed commit:

```bash
cat /var/www/ept/VERSION.txt
```

Read the run log if anything looked wrong. Each run writes to `/tmp/ept-upgrade-<YYYYMMDD-HHMMSS>.log`.

Then load the site in a browser and sign in.

## Fix a version mismatch

`check-version-sync.php` reports a mismatch when migrations did not fully apply. Re-run them against the affected instance:

```bash
cd /var/www/ept
sudo -u www-data composer migrate
```

Read the output for the failing statement. Migrations are idempotent, so re-running a partially applied version is safe. For migration options, see [Run migrations](cli-tools.md#run-migrations).

If the message says the database is ahead of the code, the update did not deliver the newer code. Re-run the update.

## Roll back

Restore the folder backup taken before the update:

```bash
sudo rsync -a --delete /var/ept-backup/www/ept-backup-<timestamp>/ /var/www/ept/
```

Then restore the matching database dump:

```bash
zcat /var/ept-backup/db/<database>_<timestamp>.sql.gz | mysql -u root -p <database>
```

Restore the database whenever the failed update applied migrations. Code alone cannot run against a newer schema.

## What an update changes

| Area | Change |
| --- | --- |
| Application code | Copied from a shallow git mirror of `master` at `/usr/local/lib/ept/src`. Symlinks inside the instance are preserved |
| Dependencies | `composer install` runs only when `composer.json` or `composer.lock` changed. Node packages are installed for chart rendering |
| Database | `composer post-update` runs migrations, then regenerates the salt file if missing |
| One-time scripts | `bin/run-once.php` runs any pending scripts |
| Cron | The per-minute `cron.sh` entry is added to the root crontab if absent |
| PHP | Switched to 8.4 if the box is on another version. OPcache is enabled |
| MySQL | `mysqld.cnf` is re-tuned for the box's memory, then MySQL restarts |
| Ubuntu packages | Upgraded unless you pass `-s` |

## Further reading

- [CLI Tools Reference](cli-tools.md) — every `bin/` script and its flags
- [Backup, Recovery & Migration](backup-and-migration.md) — backups, restores, and moving to a new machine
- [Setup Guide](setup.md) — fresh installation
