# Storage and retention reference

This reference describes ePT 7.6.21 storage and cleanup implementation.
Software defaults are not an approved programme retention or disposition policy.

## Storage locations

Paths are relative to the installation unless stated otherwise.

| Location | Contents | Recovery or confidentiality concern |
| --- | --- | --- |
| MySQL database | Accounts, participants, shipments, responses, settings and queue records | Requires a database backup and tested restore |
| `application/configs/application.ini` | Runtime configuration and connection secrets | Requires restricted backup and preserved settings |
| `application/configs/config.ini` | Application configuration defaults | Must match the recovered installation |
| `application/configs/.env` | Environment secrets where configured | Requires restricted backup |
| `public/uploads/` | Uploaded files and application-generated supporting files | Must be included in file backup scope |
| `public/uploads/track-api/` | Compressed API request and response bodies | May contain credentials or tokens |
| `downloads/` | Generated report outputs | Recovery must preserve or deliberately regenerate required outputs |
| `public/temporary/` | Temporary exports and scratch files | Subject to cleanup |
| `logs/` | Application logs | May contain operational and account information |
| `backups/` | Default database archive destination | Same-host storage alone does not protect against host loss |
| `backups/config/` | Snapshots of `application.ini` | Contains secrets and is not a full configuration backup |

Report-specific locations are described in [scheme architecture](SchemeArchitecture.md#file-output-locations).
Actual configured paths and off-host destinations belong in the deployment record.

## Scheduled operations

The schedule uses the configured application timezone and depends on a functioning
cron/Crunz runner. Definitions alone do not prove that jobs execute successfully.

| Operation | Defined schedule | Source |
| --- | --- | --- |
| Database backup | Daily 00:45 | `scheduled-jobs/ScheduledTasks.php` |
| Configuration snapshot | Sunday 01:00 | `bin/backup-config.php --quiet` |
| Housekeeping | Daily 03:30 | `bin/housekeeping.php --quiet` |
| Binary-log purge | Daily 04:05, with a 7-day argument | `db-tools purge-binlogs --days=7` |

The default database archive retention in `db-tools.php` is 15 archives.
Configuration snapshots default to retaining 26 copies with a minimum-keep setting of 4.
The snapshot tool skips unchanged files.

## Housekeeping rules

| Target | Eligibility | Retention cutoff |
| --- | --- | --- |
| `track_api_requests` | Request metadata rows | 90 days by `requested_on` |
| `temp_mail` | Terminal statuses `sent`, `failed`, `failure`, `fail` | 30 days by available sent, updated or queued timestamp |
| `push_notification` | Status is null or is not `pending` | 90 days by `created_on` |
| `audit_log` | Older recorded activity | 730 days before the table's newest `created_on` |
| `user_login_history` | Older login activity | 730 days before the table's newest login timestamp |
| `logs/*.log` | Matching top-level log files | 30 days by modification time |
| `public/temporary/` | Temporary filesystem entries | 7 days by modification time |

Audit cutoffs use the newest row in each table, not the current date.
An idle installation therefore retains its final activity window.
The housekeeping targets do not include PT response tables or generated report directories.
Removal of API request metadata does not remove the corresponding tracking payload files.

## Policy decisions outside the code

The data owner supplies retention periods for PT records, reports, account information,
API payloads, backups and audit evidence. The policy also needs legal-hold handling,
disposal authorization, deletion verification and treatment of off-host copies.

Recovery procedures are in [backup and migration](backup-and-migration.md).
The [handover guide](deployment-handover.md) records owners and recovery targets.
