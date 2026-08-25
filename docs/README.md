# ePT Documentation

ePT is an open-source proficiency testing system for laboratory quality assurance. Organisations use it to run PT shipments, collect results from participating laboratories, evaluate those results against reference data, and issue reports.

## Where to start

| Your goal | Read |
| --- | --- |
| Understand what ePT is for | [About ePT](about-ept.md) |
| Install ePT on Ubuntu, Docker, or Windows | [Install](setup.md) |
| Update a running installation | [Update](updating.md) |
| Back up, restore, or move an instance | [Backup & recovery](backup-and-migration.md) |
| Size hardware before buying it | [Infrastructure](infrastructure.md) |
| Reset a password, run a migration, refresh translations | [CLI tools](cli-tools.md) |
| Learn the admin workflow end to end | [Training](training/README.md) |
| Understand how the code fits together | [Architecture](ARCHITECTURE.md) |
| Add or change a test scheme | [Schemes](SchemeArchitecture.md) |
| Translate the interface or add a language | [Translation](TranslationGuide.md) |
| Get a change reviewed and merged | [Engineering standards](engineering-standards.md) |

## Supported test schemes

Seven built-in schemes ship in the `scheme_list` table, alongside any number of user-configured custom tests. The middle column is the name shown in the admin panel and the participant portal.

| Code | Name in ePT | Description |
| --- | --- | --- |
| `dts` | Dried Tube Specimen - HIV Serology | Rapid HIV testing with multiple algorithms |
| `vl` | Dried Tube Specimen - HIV Viral Load | Quantitative viral load testing with Z-score analysis |
| `eid` | Dried Blood Spot - Early Infant Diagnosis | PCR-based infant HIV testing |
| `tb` | Dried Tube Specimen - Tuberculosis | Molecular (GeneXpert) and microscopy testing |
| `recency` | Rapid Test for Recent Infection (RTRI) | Recent infection testing |
| `covid19` | SARS-CoV-2 | Multi-platform PCR testing |
| `dbs` | Dried Blood Spot - HIV Serology | EIA and Western Blot testing |
| `generic` | Custom Tests | User-configured test types with dynamic fields |

## Training curriculum

| Document | Audience | Duration |
| --- | --- | --- |
| [Overview](training/README.md) | All | — |
| [Part 1: Setup and participants](training/part1-setup-and-participants.md) | Admin | ~10 min |
| [Part 2: Surveys and shipments](training/part2-surveys-and-shipments.md) | Admin | ~10 min |
| [Part 3: Participant results and reports](training/part3-participant.md) | Participant | ~5–10 min |
| [Part 4: Evaluation, reports, and finalization](training/part4-evaluation-and-reports.md) | Admin | ~10 min |
| [Exercise: DTS HIV Serology](training/exercises/dts-hiv-serology.md) | All | ~20–30 min |

## Key configuration files

| File | Purpose |
| --- | --- |
| `application/configs/application.ini` | Database, sessions, modules |
| `application/configs/config.ini` | Domain-specific settings and thresholds |
| `constants.php` | Global paths and `APP_VERSION` |

For the technology stack and hardware sizing, see [Infrastructure](infrastructure.md). For the directory layout, see [Architecture](ARCHITECTURE.md#directory-map). For documentation and code conventions, see [Engineering standards](engineering-standards.md).

## Support

Email [amit@deforay.com](mailto:amit@deforay.com), or open an issue at [deforay/ept](https://github.com/deforay/ept).
