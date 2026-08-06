# ePT Documentation

Welcome to the ePT (e-Proficiency Testing) documentation. This index provides an overview of the project and links to detailed documentation.

---

## Project overview

ePT is an open-source proficiency testing system for laboratory quality assurance. It enables organizations to:

- **Create and manage PT shipments** across multiple test schemes
- **Enroll participants** (laboratories) and track their responses
- **Evaluate results** against reference data with configurable scoring
- **Generate reports** (Excel, PDF) for analysis and certification

### Supported test schemes

Seven built-in schemes ship in the `scheme_list` table, plus any number of
user-configured custom tests. The name in the middle column is what the
admin panel and participant portal display.

| Code | Name in ePT | Description |
|--------|-------------|-------------|
| `dts` | Dried Tube Specimen - HIV Serology | Rapid HIV testing with multiple algorithms |
| `vl` | Dried Tube Specimen - HIV Viral Load | Quantitative viral load testing with Z-score analysis |
| `eid` | Dried Blood Spot - Early Infant Diagnosis | PCR-based infant HIV testing |
| `tb` | Dried Tube Specimen - Tuberculosis | Molecular (GeneXpert) and microscopy testing |
| `recency` | Rapid Test for Recent Infection (RTRI) | Recent infection testing |
| `covid19` | SARS-CoV-2 | Multi-platform PCR testing |
| `dbs` | Dried Blood Spot - HIV Serology | EIA and Western Blot testing |
| `generic` | Custom Tests | User-configured test types with dynamic fields |

### Technology stack

- **Framework**: Zend Framework 1 (PHP 8.4)
- **Database**: MySQL 8+
- **Web Server**: Apache 2 with mod_rewrite
- **Background Jobs**: Crunz scheduler
- **Reports**: PhpSpreadsheet (Excel), TCPDF (PDF)
- **Email**: Symfony Mailer

---

## Documentation index

### Training curriculum

| Document | Audience | Duration |
| -------- | -------- | -------- |
| [Training Overview](training/README.md) | All | — |
| [Part 1: System Setup & Participants](training/part1-setup-and-participants.md) | Admin | ~10 min |
| [Part 2: PT Survey & Shipment Management](training/part2-surveys-and-shipments.md) | Admin | ~10 min |
| [Part 3: Participant — Results & Reports](training/part3-participant.md) | Participant | ~5–10 min |
| [Part 4: Evaluation, Reports & Finalization](training/part4-evaluation-and-reports.md) | Admin | ~10 min |
| [Exercise: DTS HIV Serology](training/exercises/dts-hiv-serology.md) | All | ~20–30 min |

### Architecture and guides

| Document | Description |
|----------|-------------|
| [Setup Guide](setup.md) | Installing ePT on Ubuntu and Windows |
| [Backup, Recovery & Migration](backup-and-migration.md) | Taking backups, restoring them, and moving an instance to a new machine |
| [Infrastructure Planning](infrastructure.md) | Sizing, storage, networking, and security for procurement teams |
| [Architecture Guide](ARCHITECTURE.md) | High-level system architecture, request lifecycle, security, modules, and infrastructure |
| [Scheme Architecture](SchemeArchitecture.md) | Test scheme organization, data flow, evaluation logic, and report generation |
| [Admin Module Guide](AdminModuleGuide.md) | Admin panel workflows, controllers, AJAX patterns, and form validation |
| [Translation Guide](TranslationGuide.md) | Internationalization and adding new languages |
| [CLI Tools Reference](cli-tools.md) | Password resets, migrations, admin seeding, and other bin/ scripts for tech support |

### Quick links

#### For developers

- [Request Lifecycle](ARCHITECTURE.md#request-lifecycle-web) - How requests flow through the system
- [Service Layer](ARCHITECTURE.md#service-layer) - Business logic organization
- [Database Migrations](ARCHITECTURE.md#database-migrations) - How to manage schema changes
- [Adding a New Scheme](SchemeArchitecture.md#adding-a-new-scheme) - Step-by-step guide

#### For administrators

- [Scheduled Jobs](ARCHITECTURE.md#scheduled-jobs) - Background task configuration
- [Email Infrastructure](ARCHITECTURE.md#email-infrastructure) - Email queue and configuration
- [Shipment Lifecycle](SchemeArchitecture.md#shipment-lifecycle) - How shipments progress through states

#### For maintainers

- [Security](ARCHITECTURE.md#security) - Authentication, authorization, CSRF protection
- [Error Handling](ARCHITECTURE.md#error-handling-and-logging) - Logging and error management
- [Scoring Formula](SchemeArchitecture.md#scoring-formula) - How participant scores are calculated

---

## Directory structure

```
ept/
├── application/
│   ├── configs/          # Application configuration
│   ├── controllers/      # Default module controllers
│   ├── models/           # Database models and business logic
│   ├── modules/          # Admin, API, Reports modules
│   ├── services/         # Business logic layer
│   └── views/            # View templates
│
├── database/
│   ├── migrations/       # SQL migration files
│   └── schema/           # Database schema definitions
│
├── docs/                 # Documentation (you are here)
│
├── library/Pt/           # Custom library code
│
├── public/               # Web root
│   ├── index.php         # Entry point
│   └── assets/           # Static files (CSS, JS, images)
│
├── scheduled-jobs/       # Crunz background tasks
│
└── vendor/               # Composer dependencies
```

---

## Getting started

### Prerequisites

- Apache 2 with mod_rewrite
- MySQL 8+
- PHP 8.4
- Composer

### Installation

See the [Setup Guide](setup.md) for complete installation and configuration instructions.

### Configuration

Key configuration files:

| File | Purpose |
|------|---------|
| `application/configs/application.ini` | Database, sessions, modules |
| `application/configs/config.ini` | Domain-specific settings, thresholds |
| `constants.php` | Global paths and version |

---

## Contributing

When adding new features or modifying existing ones:

1. **Read the architecture docs** - Understand existing patterns before making changes
2. **Follow the service layer pattern** - Keep controllers thin, put logic in services
3. **Use existing utilities** - Check `library/Pt/Commons/` and `application/services/`
4. **Add migrations** - Database changes go in `database/migrations/`
5. **Update documentation** - Keep these docs current with your changes

### Documentation conventions

Existing pages do not all follow these yet. Apply them to anything you write
or substantially edit, rather than reformatting pages you are not otherwise
touching.

| Rule | Detail |
| --- | --- |
| Headings | Sentence case. Write "Supported test schemes", not "Supported Test Schemes". Keep product names and acronyms capitalised. |
| New filenames | Kebab-case, for example `backup-and-migration.md`. The older `ARCHITECTURE.md` and `SchemeArchitecture.md` keep their names because renaming them would break published URLs. |
| Callouts | Use `> **Title:**` blockquotes. MkDocs admonitions (`!!! warning`) render as literal text on GitHub, and these pages are read there as well as on the site. |
| Adding a page | Add it to the `nav` in `mkdocs.yml` **and** to the documentation index above. A page missing from either one is hard to find. |
| Scheme names | Match the `scheme_list` table. The supported test schemes table above is the reference. |

Run `mkdocs build --strict` before pushing. The deploy workflow uses it, so a
broken link or a bad reference fails the build rather than shipping.

---

## Support

For questions or issues, contact [amit@deforay.com](mailto:amit@deforay.com).

GitHub: [deforay/ept](https://github.com/deforay/ept)
