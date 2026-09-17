# How to prepare a deployment handover

Assemble a versioned evidence package for an ePT installation.

## Before you start

Use an operator account authorized to inspect the target installation.
This guide targets ePT 7.6.21 with PHP 8.4 and MySQL 8 or later.
Obtain the programme owner, hosting owner and acceptance reviewer names.

## Record the baseline

1. Run the version check from the installation directory:

   ```bash
   php bin/check-version-sync.php
   ```

2. Record the deployment identifier from `VERSION.txt`, where present.
3. For a Git checkout, record `git rev-parse HEAD` and `git status --short`.
4. Record the installed operating system, PHP, MySQL and web-server versions.
5. Record the active schemes, algorithms, instance setting, timezone and report layouts.

Keep secrets out of the handover copy. Describe configuration values by purpose when
the actual value is a password, token, encryption key or connection credential.

## Capture the physical schema

Use a restricted output directory and a database account authorized for schema export.
Replace `ept_database` with the actual database name.

```bash
mysqldump --no-data --skip-comments --no-tablespaces \
  --user=SCHEMA_READER --password ept_database > ept-schema.sql
```

Inspect the export before sharing. Record the database version, capture date and
application version alongside it. Include separate definitions for required views,
routines or events if the deployment uses them and the export omits them.

Use the [core data dictionary](data-model.md) to explain the principal entities.
Record unresolved field definitions as gaps instead of guessing their meaning.

## Assemble the package

| Deliverable | Existing basis | Completion evidence |
| --- | --- | --- |
| Functional requirements | [Requirements baseline](functional-requirements.md) | Programme review and approved local differences |
| Technical design | [Architecture](ARCHITECTURE.md), [data model](data-model.md), [API inventory](api-reference.md) | Actual deployment diagram, schema and enabled integration contracts |
| Security requirements | [Security reference](security.md) | Local control evidence and assessment results |
| User manual | [User guide](user-guide.md) and training | Reviewed local navigation and screenshots |
| User administration | [User management](user-management.md) | Approved assignments and access review record |
| Installation and maintenance | [Setup](setup.md), [updating](updating.md), [troubleshooting](troubleshooting.md) | Named operators and maintenance schedule |
| Recovery | [Backup and migration](backup-and-migration.md) | Backup inventory, off-host location and successful restore exercise |
| Validation | [Validation package](validation.md) | Executed cases, defects, retests and approval |
| Software inventory | [Software and licenses](software-licenses.md) | Actual installed versions and locally supplied assets |
| Source and release record | Versioned repository reference | Deployed commit, change summary and known issues |
| Training | [Curriculum](training/README.md) | Delivery dates, trainers, attendance and competency checks |

## Complete local operational records

1. Record server or VM resources, storage volumes and network boundaries.
2. Record the hypervisor and VM backup configuration when virtualization is used.
3. Assign backup monitoring, restore execution and escalation owners.
4. Agree recovery time and recovery point objectives with the programme owner.
5. Record a continuity procedure for collecting responses during an outage.
6. Define reconciliation of outage records after service restoration.
7. Obtain the approved retention and disposition policy from the data owner.
8. Attach support contacts, service agreements, contract dates and renewal responsibilities.
9. Attach the deployment workplan, site assessment and facility inventory.

## Verify the handover

1. Execute the agreed [acceptance cases](validation.md) in the target test environment.
2. Check every supplied document against the recorded release and local configuration.
3. Record each missing document with an owner and target date.
4. Obtain the programme and hosting owners' acceptance decisions.

Keep the completed package under local document control with a revision, owner,
review date and approval status for each document.
