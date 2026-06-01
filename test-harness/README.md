# DTS Algorithm Test Harness

Dev-only tool. Provisions a synthetic DTS shipment, fills bulk responses (correct + aberrant), runs the real evaluator as a subprocess, and asserts each participant's verdict against an independent expected verdict declared up-front.

Lives in `test-harness/` at the repo root and is **architecturally independent** of the app: it does not load any class from `application/` or `library/`. It reads `application/configs/application.ini` as plain text, opens its own PDO connection, writes synthetic rows by raw SQL, and shells out to `scheduled-jobs/evaluate-shipments.php` for evaluation.

## Run

```bash
APPLICATION_ENV=development php test-harness/bin/dts-algo
```

The harness refuses to run unless `APPLICATION_ENV` is `development` or `testing`. There is no override.

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
  php test-harness/bin/dts-algo --cleanup-all
  ```

## Supported algorithms

Phase 1: **Vietnam (NIHE)** DTS. Adding a new variant = drop one file in `src/Aberrations/` + one file in `expectations/` + one entry in `src/Variants.php`. No changes outside `test-harness/`.

## Files

```
test-harness/
├── README.md
├── bin/dts-algo                 — entry point
├── src/
│   ├── Config.php               — application.ini parser, env gate
│   ├── Db.php                   — PDO wrapper
│   ├── Variants.php             — algorithm registry
│   ├── Provisioner.php          — DB writes
│   ├── Evaluator.php            — subprocess to evaluate-shipments.php
│   ├── Asserter.php             — compare to expectations
│   ├── Cleanup.php              — DELETE cascade
│   └── Aberrations/
│       └── Vietnam.php          — seven apply_* response generators
└── expectations/
    └── vietnam.php              — independent expected verdicts (from NIHE workbook)
```
