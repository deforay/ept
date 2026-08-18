# ePT test harnesses

Dev-only tools. Each provisions a synthetic shipment, fills bulk responses (correct + aberrant), runs the real evaluator as a subprocess, and asserts each participant's verdict against an independent expected verdict declared up-front.

Lives in `test-harness/` at the repo root and is **architecturally independent** of the app: it does not load any class from `application/` or `library/`. It reads `application/configs/application.ini` as plain text, opens its own PDO connection, writes synthetic rows by raw SQL, and shells out to `scheduled-jobs/evaluate-shipments.php` for evaluation.

Three entry points:

- `bin/dts` — DTS schemes (algorithm-driven; Vietnam + updated-3-tests). Provisions its own synthetic shipment + asserts against declared expectations.
- `bin/workbook` — Vietnam only, no database. Checks the NIHE accepted-interpretation matrix (every row of the assessment workbook, verdict **and** feedback text) directly against `algoVietnam`, in about a second. This is the one place the harness loads a class from `application/`; see **Spec coverage** below for why that exception is drawn where it is.
- `bin/custom-test` — qualitative custom (user-configured) tests. You pick an **existing** scheme at startup (HBV, HCV, SYP, …); it provisions a shipment against that scheme using its own FINAL result codes, fills correct/incorrect responses, and asserts per-sample correctness from `response_result_generic_test.calculated_score`. It never creates or alters a scheme.

Both bins also accept **`--shipment <id|code>`** (attach mode): instead of provisioning a synthetic shipment, attach to one **you already created** and do the rest — enroll participants if none, fill responses against the shipment's own reference results (mostly pass, some fail), evaluate, and generate reports. Only participants without a response are filled (existing responses are never touched), and no assertions/cleanup run since it's your shipment. `bin/dts --shipment` handles DTS `updated-3-tests`; `bin/custom-test --shipment` handles custom qualitative. Pass the wrong kind and it points you at the other bin.

## Run

```bash
APPLICATION_ENV=development php test-harness/bin/dts
APPLICATION_ENV=development php test-harness/bin/custom-test
# attach mode — fill a shipment you already created:
APPLICATION_ENV=development php test-harness/bin/dts --shipment <id|code>
APPLICATION_ENV=development php test-harness/bin/custom-test --shipment <id|code>
```

Both refuse to run unless `APPLICATION_ENV` is `development` or `testing`. There is no override.

```bash
# Vietnam workbook matrix only — no DB, no provisioning, ~1s:
php test-harness/bin/workbook
php test-harness/bin/workbook --sheet screening
```

`bin/dts` follows `application.ini`, so the database it points at must be migrated up to date — provisioning reads `report_config` (added in 7.2.2) and fails with a "table not found" if the dev database is behind.

The custom-test harness writes ATEST-CT-* rows the same way; clean up with `--cleanup <id|code>` or `--cleanup-all`. It only removes its own shipments — never the real schemes — and leaves the shared ATEST participants in place.

## What it writes

All synthetic rows are namespaced with the prefix `AUTOTEST-` so cleanup is safe:

- `participant.unique_identifier` like `AUTOTEST-pNNN`
- `distributions.distribution_code` like `AUTOTEST-DIST-{ts}`
- `shipment.shipment_code` like `AUTOTEST-DTS-{variant}-{ts}`
- corresponding `reference_result_dts`, `shipment_participant_map`, `response_result_dts` rows

## Cleanup

- **Pass** → automatic delete of the shipment, its responses, and the AUTOTEST participants.
- **Fail** → left in place for inspection (shipment_id printed).
- Manual sweep:
  ```bash
  php test-harness/bin/dts --cleanup-all
  ```

## Spec coverage — the NIHE workbook matrix

`expectations/vietnam-workbook.php` is the NIHE accepted-interpretation matrix, transcribed by hand from `Assesment_1.1_Amit_21_May_2026.xlsx` (sheets `Confirmatory ` and `Screening `, both with a trailing space). One entry per row: the test results, the repeat of Test 1, the reported interpretation, the verdict, and the exact Feedback/NOTE string — with `''` meaning the workbook leaves that cell blank and nothing may be printed.

It drives both entry points:

- **`bin/dts`** registers each row as a spec-coverage aberration (`wb_conf_16`, `wb_scr_09`, …) and gives it exactly one lab, taken off the top before any distribution. A row with no lab assigned is a row nobody checked, so these are never proportional and never optional. The row's combination is written onto a sample of the matching class and the rest of that lab's panel is filled correctly, so a failure names one row of the sheet. `php test-harness/bin/dts --list-coverage` prints them.
- **`bin/workbook`** runs the same rows straight through `algoVietnam` with no shipment, no evaluator subprocess and no PDFs.

Use `bin/workbook` while editing the algorithm and `bin/dts` to prove the verdicts survive the real evaluator and reach the report. Both report **verdict** differences separately from **feedback** differences: a wrong verdict means a laboratory is graded wrongly, a wrong note means it is graded correctly and told the wrong thing. Only one of those changes a score, and they need different responses from whoever reads the output.

The expectations file is the spec. Never regenerate it from `algoVietnam`, or the harness is testing the code against itself. Edit it by hand when NIHE revises the workbook.

## Supported algorithms

- **Vietnam (NIHE)** — tier-aware (screening/confirmatory), qualitative, consensus-driven. The `consensus_group_passes` aberration needs ≥10 labs to clear the peer threshold, so run it with ~100+ labs.
- **Updated 3-tests** — single-tier serial confirmatory algorithm (`dtsSchemeType=updated-3-tests`, `algorithm=dts-3-tests`). Scored (95% pass, 10% documentation); a sample is Unacceptable if the final result is wrong OR the 3-test algorithm is violated.

When more than one variant exists the harness prompts you to pick one at startup.

Adding a new variant = drop one file in `src/Aberrations/` + one file in `expectations/` + one entry in `src/Variants.php`. No changes outside `test-harness/`. The `Provisioner` dispatches through the variant's registered aberrations class, so it stays variant-agnostic.

## Files

```
test-harness/
├── README.md
├── bin/dts                      — entry point (synthetic mode + --shipment attach mode)
├── src/
│   ├── Config.php               — application.ini parser, env gate
│   ├── Db.php                   — PDO wrapper
│   ├── Variants.php             — algorithm registry
│   ├── Provisioner.php          — DB writes
│   ├── Evaluator.php            — subprocess to evaluate-shipments.php
│   ├── Asserter.php             — compare to expectations
│   ├── Cleanup.php              — DELETE cascade
│   ├── Aberrations/
│   │   ├── Vietnam.php          — seven apply_* response generators
│   │   └── UpdatedThreeTests.php — apply_* generators for the 3-test algorithm
│   ├── CustomTest/             — custom-test harness over existing schemes (Provisioner/Asserter/Cleanup)
│   │   ├── Provisioner.php
│   │   ├── Asserter.php
│   │   └── Cleanup.php
│   └── Filler/                 — shared attach mode (used by both bins via --shipment)
│       ├── ShipmentFiller.php  — enroll + fill responses against an existing shipment
│       └── ExistingShipment.php — interactive orchestration (fill → evaluate → reports)
├── bin/custom-test             — entry point for the custom-test harness
└── expectations/
    ├── vietnam.php              — independent expected verdicts (from NIHE workbook)
    ├── updated-3-tests.php      — independent expected verdicts (from the algorithm spec)
    └── custom-test.php          — scheme-agnostic panel pattern + aberration flip-sets
```
