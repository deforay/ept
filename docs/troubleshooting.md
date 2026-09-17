# How to investigate operational problems

[Français](fr/troubleshooting.md)

Identify the failing component before changing an ePT installation.
These procedures target ePT 7.6.21 and require access appropriate to the affected component.

## Capture the incident

1. Record the time, timezone, affected URL, application version and visible error.
2. Record the shipment code and participant identifier when relevant.
3. Record whether one user, one shipment or the whole service is affected.
4. Collect relevant log entries without passwords, tokens or unrelated participant records.

## Follow the symptom

| Symptom | First checks | Next action | Verification |
| --- | --- | --- | --- |
| Site unavailable | Web service, database connectivity, disk space and certificate | Restore the failed infrastructure component through the hosting procedure | Load the site and sign in |
| Code/database mismatch | `php bin/check-version-sync.php` | Follow [update recovery](updating.md#fix-a-version-mismatch) | Version check succeeds |
| Missing participants or shipments | Account status, participant mapping and enrollment | Correct the assignment through authorized administration | Affected account sees the intended records only |
| Response cannot be edited | Shipment status, response switch, deadline and account permissions | Ask the programme administrator to review collection state | A permitted test response saves and reopens |
| Late response awaiting review | Supported submission path and late-submission state | Have an authorized administrator review the held response | Approved result appears in the intended workflow |
| Evaluation or report remains queued | Scheduler, job status, PHP errors and disk space | Investigate the failing job before requesting another run | Queue completes and the output belongs to the expected shipment |
| Email not delivered | Queue outcome, SMTP configuration, recipient address and provider rejection | Correct the specific delivery failure | A test message reaches the intended test mailbox |
| Report unavailable | Generation status, finalization, mapping and generated files | Complete the authorized reporting workflow | Correct account downloads the expected report |
| Backup missing | Scheduler, destination permissions, disk space and backup configuration | Restore backup execution and off-host copying | New archive passes verification and a restore test |

## Inspect application evidence

1. Review recent files under `logs/` for the incident time.
2. Review the web-server error log through the hosting operator.
3. Check the configured scheduler and its execution output.
4. Review job tracking or email outcomes in the application where available.

Use `php bin/console.php list` to locate supported administrative tools.
Use [CLI tools](cli-tools.md) for their arguments.
Do not edit queue or evaluation rows directly to conceal a failed job.

## Escalate an unresolved incident

1. Supply the recorded version and sanitized reproduction steps.
2. Attach the relevant error and job identifiers.
3. State the affected workflow and operational impact.
4. Record the owner, next action and resolution check in the local incident log.

After a repair, repeat the affected workflow using controlled data.
For report defects, inspect the final PDF, not only the generating script's exit status.
