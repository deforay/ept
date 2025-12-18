# Report Template Context Contract

This folder contains `.phtml` templates that are executed by CLI jobs (not rendered by Zend view).
Historically these templates relied on “ambient” variables being present in the caller scope.

To make report generation reliable (especially when templates are included inside closures and/or
worker processes), the report generator now includes templates using an explicit context array:

- `scheduled-jobs/generate-shipment-reports.php` uses `includeWithContext($file, $context)`
- `extract($context)` is performed inside an isolated scope before `include`

This document describes the **expected variables** report templates may use.

## Context Shape

### Participant Report Templates

Participant templates live under `scheduled-jobs/report-layouts/participant-layouts/`.

#### Required (always provided)

These keys should be present for all participant report templates:

- `reportService` (`Application_Service_Reports`)
- `schemeService` (`Application_Service_Schemes`)
- `shipmentService` (`Application_Service_Shipments`)
- `commonService` (`Application_Service_Common`)
- `evalRow` (array; the current shipment metadata row)
- `totParticipantsRes` (array; shipment-level participant counts and scheme metadata)
- `resultArray` (array; output of `getIndividualReportsDataForPDF()`)
- `reportsPath` (string; base reports output directory)
- `layout` (string; report layout key, e.g. `zimbabwe`)
- `resultStatus` (string; `generateReport` / `finalized` / etc.)
- `header` (string; report header HTML/text)
- `instituteAddressPosition` (string; `header` / `footer` / etc.)
- `reportComment` (string; optional report footer/comment text from config)
- `logo` (string; left logo filename)
- `logoRight` (string; right logo filename)
- `templateTopMargin` (int|string|null; used by some templates)
- `instance` (string; instance name from config)
- `passPercentage` (int|float; pass threshold)
- `watermark` (string|null; training watermark text)
- `customField1` (string|null)
- `customField2` (string|null)
- `haveCustom` (string; `yes`/`no`)
- `config` (`Zend_Config_Ini`; usually `config.ini` already loaded by the job)
- `reportFormat` (string|null; report format template filename)
- `recencyAssay` (array; recency assay lookup list)
- `downloadDirectory` (string; resolved downloads folder path)
- `trainingInstance` (string|null; `yes`/`no`)

#### Optional (may be null or absent depending on scheme/layout)

- `bulkfileNameVal` (string; chunk label like `0-499` for bulk processing)
- `shipmentsUnderDistro` (array|null; only used by some Zimbabwe layouts)
- `allGeneTypes` (array|null; only for `covid19`)

### Summary Report Templates

Summary templates live under `scheduled-jobs/report-layouts/summary-layouts/`.

#### Required (always provided)

Summary templates share many of the participant keys (services, shipment metadata, branding, etc),
and also receive:

- `evalService` (`Application_Service_Evaluation`)
- `trainingInstance` (string|null; `yes`/`no`)
- `resultArray` (array; output of `getSummaryReportsDataForPDF()`)
- `responseResult` (array; output of `getResponseReports()`)
- `participantPerformance` (array; output of `getParticipantPerformanceReportByShipmentId()`)
- `correctivenessArray` (array; output of `getCorrectiveActionReportByShipmentId()`)
- `shipmentsUnderDistro` (array|null; only used by some Zimbabwe layouts)

#### Optional

- `panelTestType` (string|null; present only when DTS summary is generated per panel type)

## Template Guidelines

### 1) Prefer local derivations over hidden dependencies

Templates should compute values they need from `resultArray`/`evalRow` rather than assuming a
helper variable exists (e.g. derive `$attributes = json_decode($result['attributes'] ?? '{}', true)`).

### 1.1) Helper partials are not standalone templates

Some files under `participant-layouts/` are **partials** meant to be included by another participant
template (for example Malawi’s `participant-layouts/malawi/summary-statistics.phtml`), and may expect
additional locals like `$pdf`, `$result`, or `$schemeType` to already exist in the parent template.

These partials are intentionally **not** part of the “must-run standalone” contract enforced by the
generator context.

### 2) Use `?? null` only for truly optional inputs

When adding new context keys in `generate-shipment-reports.php`, do **not** default everything to
`null`. Required values should fail loudly if missing. Reserve `?? null` (or `isset(...) ? ... : null`)
for data that legitimately does not exist for some schemes/layouts.

### 3) Keep templates side-effect predictable

Templates are executed in CLI and typically write PDFs to disk. Avoid modifying global state or relying
on global variables. Prefer using the context variables listed above.

## Updating the Contract

If you add a new variable dependency to a template:

1. Prefer computing it inside the template from existing data.
2. If it must be injected, add it to the base context in `scheduled-jobs/generate-shipment-reports.php`.
3. Update this document so future changes remain safe.
