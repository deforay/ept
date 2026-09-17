# Validation package

[Français](fr/validation.md)

This reference provides a proposed acceptance baseline for ePT 7.6.21.
It contains test definitions and an evidence format. It does not record executed tests
or programme approval.

## Existing automated coverage

| Tool | Coverage | Limit |
| --- | --- | --- |
| `test-harness/bin/workbook` | Vietnam accepted-interpretation matrix | Selected algorithm behaviour, without a database or report generation |
| `test-harness/bin/dts` | Vietnam and updated-three-test DTS cases | Selected algorithm variants, not every national configuration |
| `test-harness/bin/custom-test` | Qualitative custom-test evaluation | Selected existing custom scheme |
| `composer phpstan` | Static analysis | Does not prove workflow or scoring correctness |
| `composer cs-check` | Coding-style checks | Does not constitute functional validation |

Database harnesses create synthetic records and invoke evaluation.
They require `APPLICATION_ENV=development` or `testing`.
Attach mode has different behaviour and does not provide the same assertion coverage.
The authoritative execution instructions are in
[the harness README](https://github.com/deforay/ept/blob/master/test-harness/README.md).

The Vietnam matrix records known interpretation divergences. Those cases are not
evidence of acceptance by another country's programme.

## Proposed acceptance cases

All cases below have status **Not executed in this documentation review**.
Expected outcomes are proposed acceptance criteria, not claims that the current code passes.
Tests use synthetic accounts, participant records and PT samples in an isolated environment.

| ID | Requirements | Test scenario | Expected outcome |
| --- | --- | --- | --- |
| VAL-01 | FR-01 | Import valid participants, then invalid and duplicate rows | Valid records are identifiable. Rejected rows have actionable errors without unintended duplicates |
| VAL-02 | FR-02 | Sign in with two accounts mapped to different participants | Each account accesses only its permitted records, including direct URLs and API calls |
| VAL-03 | FR-03, FR-04 | Create a survey, shipment, reference panel and enrollments | Saved records match the approved test dataset |
| VAL-04 | FR-05, FR-10 | Evaluate known correct and incorrect responses for each active local scheme | Scores and outcomes match independently approved expected results |
| VAL-05 | FR-06, FR-07 | Submit and reopen a complete response before closure | Values persist and remain associated with the correct participant and shipment |
| VAL-06 | FR-08, FR-11 | Compare not-tested declarations, missing responses and tested failures | Categories remain distinct in evaluation and reports |
| VAL-07 | FR-09 | Cross the configured deadline with automatic closure enabled | Closure follows the configured timezone and queues the intended evaluation |
| VAL-08 | FR-09 | Exercise a supported late-submission path with grace enabled and disabled | Allowed submissions are held for review. Approval preserves late history |
| VAL-09 | FR-09 | Attempt writes to finalized or cancelled shipments | Unauthorized writes are rejected through each enabled submission path |
| VAL-10 | FR-12, FR-13 | Generate, review, finalize and download reports | Final PDF content, participant identity, scores, layout and signatories match expected results |
| VAL-11 | FR-14 | Compare summary totals with a known dataset | Responded, not-tested, passed, failed and excluded totals reconcile |
| VAL-12 | FR-15 | Queue messages using a test mail destination | Delivery outcomes and failures are visible without duplicate unintended mail |
| VAL-13 | FR-16 | Test each enabled API with valid, missing and invalid credentials and malformed data | Responses follow the agreed contract and access boundaries |
| VAL-14 | FR-17 | Restore a database, configuration and files on an isolated host | Login, historical reports and a synthetic PT cycle work within agreed recovery targets |
| VAL-15 | FR-18 | Perform selected account, response and administrative actions | Expected audit entries identify the action and actor |
| VAL-16 | FR-02 | Test disabled accounts, session expiry, CSRF bypass paths and privilege changes | Results meet the approved security requirements. Deviations are recorded |
| VAL-17 | FR-14, FR-17 | Run the agreed concurrent workload and report volume | Measured response and processing times meet agreed thresholds |

## Execution record

Each executed case needs the following fields. An empty record is not evidence of a pass.

| Field | Required content |
| --- | --- |
| Case ID | Acceptance case and local variant |
| Baseline | Application version, commit or release identifier, database version |
| Environment | Test instance, runtime versions, active scheme settings and timezone |
| Preconditions | Accounts, roles, mappings and shipment state |
| Test data | Synthetic inputs and independently approved expected outcomes |
| Execution | Steps performed, executor and timestamp |
| Actual result | Observed values and behaviour |
| Evidence | Sanitized screenshots, logs, exported reports and test output |
| Result | Pass, fail or blocked, with reason |
| Defect | Tracking reference, severity and retest result |
| Review | Reviewer, date and acceptance decision |

## Release acceptance

The programme owner approves the active schemes, scoring expectations, report content
and workflow acceptance. The hosting owner approves operational and recovery evidence.
Security findings require an explicit disposition by the responsible owner.

Open defects, exceptions and unexecuted cases remain visible in the acceptance record.
A technical test run is not a substitute for user acceptance or an independent security assessment.
