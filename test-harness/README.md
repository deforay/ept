# EPT Test Harnesses

Dev-only tools. Each provisions a synthetic shipment, fills bulk responses (correct + aberrant), runs the real evaluator as a subprocess, and asserts each participant's verdict against an independent expected verdict declared up-front.

Lives in `test-harness/` at the repo root and is **architecturally independent** of the app: it does not load any class from `application/` or `library/`. It reads `application/configs/application.ini` as plain text, opens its own PDO connection, writes synthetic rows by raw SQL, and shells out to `scheduled-jobs/evaluate-shipments.php` for evaluation.

Two entry points:

- `bin/dts-algo` — DTS schemes (algorithm-driven; Vietnam + updated-3-tests).
- `bin/custom-test` — qualitative custom (user-configured) tests. You pick an **existing** scheme at startup (HBV, HCV, SYP, …); it provisions a shipment against that scheme using its own FINAL result codes, fills correct/incorrect responses, and asserts per-sample correctness from `response_result_generic_test.calculated_score`. It never creates or alters a scheme.

## Run

```bash
APPLICATION_ENV=development php test-harness/bin/dts-algo
APPLICATION_ENV=development php test-harness/bin/custom-test
```

Both refuse to run unless `APPLICATION_ENV` is `development` or `testing`. There is no override.

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
  php test-harness/bin/dts-algo --cleanup-all
  ```

## Supported algorithms

- **Vietnam (NIHE)** — tier-aware (screening/confirmatory), qualitative, consensus-driven. The `consensus_group_passes` aberration needs ≥10 labs to clear the peer threshold, so run it with ~100+ labs.
- **Updated 3-tests** — single-tier serial confirmatory algorithm (`dtsSchemeType=updated-3-tests`, `algorithm=dts-3-tests`). Scored (95% pass, 10% documentation); a sample is Unacceptable if the final result is wrong OR the 3-test algorithm is violated.

When more than one variant exists the harness prompts you to pick one at startup.

Adding a new variant = drop one file in `src/Aberrations/` + one file in `expectations/` + one entry in `src/Variants.php`. No changes outside `test-harness/`. The `Provisioner` dispatches through the variant's registered aberrations class, so it stays variant-agnostic.

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
│   ├── Aberrations/
│   │   ├── Vietnam.php          — seven apply_* response generators
│   │   └── UpdatedThreeTests.php — apply_* generators for the 3-test algorithm
│   └── CustomTest/             — qualitative custom-test harness (own Provisioner/Asserter/Cleanup)
│       ├── Aberrations.php
│       ├── Provisioner.php
│       ├── Asserter.php
│       └── Cleanup.php
├── bin/custom-test             — entry point for the custom-test harness
└── expectations/
    ├── vietnam.php              — independent expected verdicts (from NIHE workbook)
    ├── updated-3-tests.php      — independent expected verdicts (from the algorithm spec)
    └── custom-test.php          — scheme-agnostic panel pattern + aberration flip-sets
```
