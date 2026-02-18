# ePT Documentation

Welcome to the ePT (e-Proficiency Testing) documentation. This index provides an overview of the project and links to detailed documentation.

---

## Project Overview

ePT is an open-source proficiency testing system for laboratory quality assurance. It enables organizations to:

- **Create and manage PT shipments** across multiple test schemes
- **Enroll participants** (laboratories) and track their responses
- **Evaluate results** against reference data with configurable scoring
- **Generate reports** (Excel, PDF) for analysis and certification

### Supported Test Schemes

| Scheme | Description |
|--------|-------------|
| **DTS** | HIV Serology (Dried Tube Specimen) - Rapid HIV testing with multiple algorithms |
| **VL** | HIV Viral Load - Quantitative viral load testing with Z-score analysis |
| **EID** | Early Infant Diagnosis - PCR-based infant HIV testing |
| **TB** | Tuberculosis - Molecular (GeneXpert) and microscopy testing |
| **Recency** | HIV Recency - RTRI-based recent infection testing |
| **COVID-19** | COVID-19 testing - Multi-platform PCR testing |
| **DBS** | Dried Blood Spot - EIA and Western Blot testing |
| **Custom Tests** | User-configurable test types with dynamic fields |

### Technology Stack

- **Framework**: Zend Framework 1 (PHP 8.4)
- **Database**: MySQL 8+
- **Web Server**: Apache 2 with mod_rewrite
- **Background Jobs**: Crunz scheduler
- **Reports**: PhpSpreadsheet (Excel), TCPDF (PDF)
- **Email**: Symfony Mailer

---

## Documentation Index

### Training Curriculum

| Document | Audience | Duration |
| -------- | -------- | -------- |
| [Training Overview](training/README.md) | All | — |
| [Part 1: System Setup & Participants](training/part1-setup-and-participants.md) | Admin | ~10 min |
| [Part 2: PT Survey & Shipment Management](training/part2-surveys-and-shipments.md) | Admin | ~10 min |
| [Part 3: Participant — Results & Reports](training/part3-participant.md) | Participant | ~5–10 min |
| [Part 4: Evaluation, Reports & Finalization](training/part4-evaluation-and-reports.md) | Admin | ~10 min |
| [Exercise: DTS HIV Serology](training/exercises/dts-hiv-serology.md) | All | ~20–30 min |

### Architecture & Guides

| Document | Description |
|----------|-------------|
| [Architecture Guide](ARCHITECTURE.md) | High-level system architecture, request lifecycle, security, modules, and infrastructure |
| [Scheme Architecture](SchemeArchitecture.md) | Test scheme organization, data flow, evaluation logic, and report generation |
| [Admin Module Guide](AdminModuleGuide.md) | Admin panel workflows, controllers, AJAX patterns, and form validation |
| [Translation Guide](TranslationGuide.md) | Internationalization and adding new languages |

### Quick Links

#### For Developers

- [Request Lifecycle](ARCHITECTURE.md#request-lifecycle-web) - How requests flow through the system
- [Service Layer](ARCHITECTURE.md#service-layer) - Business logic organization
- [Database Migrations](ARCHITECTURE.md#database-migrations) - How to manage schema changes
- [Adding a New Scheme](SchemeArchitecture.md#adding-a-new-scheme) - Step-by-step guide

#### For Administrators

- [Scheduled Jobs](ARCHITECTURE.md#scheduled-jobs) - Background task configuration
- [Email Infrastructure](ARCHITECTURE.md#email-infrastructure) - Email queue and configuration
- [Shipment Lifecycle](SchemeArchitecture.md#shipment-lifecycle) - How shipments progress through states

#### For Maintainers

- [Security](ARCHITECTURE.md#security) - Authentication, authorization, CSRF protection
- [Error Handling](ARCHITECTURE.md#error-handling-and-logging) - Logging and error management
- [Scoring Formula](SchemeArchitecture.md#scoring-formula) - How participant scores are calculated

---

## Directory Structure (High-Level)

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

## Getting Started

### Prerequisites

- Apache 2 with mod_rewrite
- MySQL 8+
- PHP 8.4
- Composer

### Installation

See the main [README](https://github.com/deforay/ept#readme) for installation instructions.

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

---

## Support

For questions or issues, contact: amit (at) deforay (dot) com

GitHub: [deforay/ept](https://github.com/deforay/ept)
