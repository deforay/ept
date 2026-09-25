# Updating an ePT installation

This guide updates a running ePT installation to the latest code on `master`.

On a server where setup or a previous update has run, `ept` does the whole job. `sudo ept update` downloads the current updater (`bin/upgrade.sh`, installed as `ept-update`) and runs it against this installation. The updater refreshes the application code, dependencies, database schema, and cron entry.

To check `ept` is installed, type `ept` and press Enter. A numbered menu headed **ePT** means it is. Type `8` and press Enter to leave the menu. If you see `command not found`, follow [If `ept` is not installed yet](#if-ept-is-not-installed-yet).

## Before you start

| Requirement | Detail |
| --- | --- |
| Operating system | Ubuntu with Apache, MySQL, PHP, and Composer already installed by [`setup.sh`](setup.md) |
| Privileges | `root`, or a user who can run `sudo` |
| MySQL root password | Read from `application.ini` when the configured user is `root`. Otherwise the script prompts for it |
| Downtime | The site stays up during the file copy. Apache reloads at the end |

## Update the installation

1. Take a fresh backup:

    ```bash
    ept backup
    ```

    Wait for the backup to finish without errors. Don't update a server whose backup fails.

2. Start the update:

    ```bash
    sudo ept update
    ```

    Enter your password if asked. The updater asks whether to upgrade Ubuntu packages and whether to take its own pre-update backups. See [Backups before an update](#backups-before-an-update).

3. When the "Upgrade Summary" prints, check the server:

    ```bash
    ept check
    ```

    Every line should show ✅. See [Verify the update](#verify-the-update).

Flags after `update` go to the updater. To skip the Ubuntu package upgrade and both backup prompts:

```bash
sudo ept update -s -b
```

Use `-s -b` only when the MySQL root password is reachable from `application.ini`. Otherwise the updater still stops to ask for it.

## If `ept` is not installed yet

Servers that haven't been updated since `ept` was introduced still have the old `runner` command. On those servers, `runner update` runs Composer's update, which is not an ePT update. Run the updater straight from GitHub once:

```bash
curl -fsSL "https://raw.githubusercontent.com/deforay/ept/master/bin/upgrade.sh?v=$(date +%s)" \
  | sudo bash -s -- -A
```

The update installs `ept`, so this is needed only once per server. Two details make the command work:

- `?v=$(date +%s)` defeats the raw.githubusercontent.com cache, which otherwise serves a stale copy of the script for several minutes after a push.
- The script reads every prompt from `/dev/tty`, not from standard input. Piping it into `bash` does not break the interactive questions.

Everything after `-s --` is passed to the script as flags. `-A` finds and updates every ePT installation under `/var/www`.

## Update several installations on one server

`ept update` updates the installation `ept` belongs to. To update more than one installation at once, call the updater directly:

```bash
# Update every installation under /var/www
sudo ept-update -A

# Detect installations, then choose which ones to update
sudo ept-update -A -i

# Update every installation without prompts
sudo ept-update -A -s -b
```

The full flag list is in the [CLI Tools Reference](cli-tools.md#update-existing-install).

> **How instances are detected:** `-A` treats a directory under `/var/www` as an ePT installation when it contains both `application/configs/application.ini` and `public/`. Nothing else is touched.

## Update a Docker installation

`ept update` does not apply to Docker. Rebuild the containers instead:

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

The run ends with an "Upgrade Summary" block listing the instances that succeeded and the instances that failed. Then check the server:

```bash
ept check
```

A healthy server prints a ✅ line for each check, including:

```text
✅ Database is reachable
✅ Schema is up to date — Version in sync: 7.6.22
```

Confirm the deployed commit:

```bash
cat /var/www/ept/VERSION.txt
```

Read the run log if anything looked wrong. Each run writes to `/tmp/ept-upgrade-<YYYYMMDD-HHMMSS>.log`.

Then load the site in a browser and sign in.

## Fix a version mismatch

`ept check` prints `Schema and code disagree` when migrations did not fully apply. Re-run them:

```bash
ept migrate
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
