# ePT Documentation

ePT is an open-source proficiency testing (PT) system. A PT provider uses it to send test panels to laboratories, collect their results, score them and return a report to each laboratory.

## Start here

Pick the card that describes you.

<div class="grid cards" markdown>

-   :material-flask-outline:{ .lg .middle } __I test PT panels at a laboratory__

    ---

    Submit your results and download your report.

    - [Submit results](user-guide.md#submit-results-as-a-data-manager)
    - [Find your report](user-guide.md#find-reports-and-account-information)
    - [Can't sign in or can't find a shipment?](troubleshooting.md#fix-problems-participants-report)

-   :material-clipboard-check-outline:{ .lg .middle } __I run PT rounds__

    ---

    Set up participants, ship panels, evaluate results and send reports.

    - [Training](training/README.md)
    - [User guide](user-guide.md)
    - [Manage user access](user-management.md)

-   :material-server-outline:{ .lg .middle } __I look after the server__

    ---

    Install, update, back up and keep ePT running.

    - [Install](setup.md)
    - [Update](updating.md)
    - [Backup & recovery](backup-and-migration.md)
    - [Troubleshooting](troubleshooting.md)

-   :material-code-tags:{ .lg .middle } __I work on the code__

    ---

    Learn how ePT is built and how to change it.

    - [Architecture](ARCHITECTURE.md)
    - [Schemes](SchemeArchitecture.md)
    - [Engineering standards](engineering-standards.md)

</div>

## All documentation

### Using ePT

| Your goal | Read |
| --- | --- |
| Understand what ePT is for | [About ePT](about-ept.md) |
| Complete common administrator and participant tasks | [User guide](user-guide.md) |
| Learn the admin workflow end to end | [Training](training/README.md) |
| Create, change and review account access | [User management](user-management.md) |
| Fix a problem someone reported | [Troubleshooting](troubleshooting.md) |

### Running a server

| Your goal | Read |
| --- | --- |
| Size hardware before buying it | [Infrastructure](infrastructure.md) |
| Install ePT on Ubuntu, Docker, or Windows | [Install](setup.md) |
| Update a running installation | [Update](updating.md) |
| Back up, restore, or move an instance | [Backup & recovery](backup-and-migration.md) |
| Keep outgoing email and bounce handling working | [Email delivery](email.md) |
| Check a server's health, or run maintenance with the `ept` command | [CLI tools](cli-tools.md#the-ept-command) |
| Reset a password, run a migration, refresh translations | [CLI tools](cli-tools.md) |
| Repair missing tables or columns | [Repair missing tables](heal-schema.md) |
| Review storage locations and cleanup rules | [Storage and retention](data-lifecycle.md) |
| Assemble a deployment evidence package | [Deployment handover](deployment-handover.md) |

### System reference

These pages use ePT 7.6.21 as their baseline. Deployment facts, approved policies and executed validation evidence remain installation-specific.

| Your goal | Read |
| --- | --- |
| Review functional requirements and use cases | [Requirements baseline](functional-requirements.md) |
| Review security controls and non-functional requirements | [Security](security.md) |
| Understand core records and business fields | [Data model and core dictionary](data-model.md) |
| Look up SQL types, defaults and nullability | [Physical column reference](physical-schema.md) |
| Identify implemented integration routes | [API inventory](api-reference.md) |
| Review locked software packages and license metadata | [Software and licenses](software-licenses.md) |
| Prepare acceptance tests and record evidence | [Validation package](validation.md) |

### Development

| Your goal | Read |
| --- | --- |
| Understand how the code fits together | [Architecture](ARCHITECTURE.md) |
| Add or change a test scheme | [Schemes](SchemeArchitecture.md) |
| Translate the interface or add a language | [Translation](TranslationGuide.md) |
| Get a change reviewed and merged | [Engineering standards](engineering-standards.md) |

## Support

Email [amit@deforay.com](mailto:amit@deforay.com), or open an issue at [deforay/ept](https://github.com/deforay/ept).
