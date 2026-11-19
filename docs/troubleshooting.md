# How to investigate operational problems

Start with the first table when a participant or data manager reports a problem.
For a server fault, capture the incident first. Identify the failing component before changing an ePT installation.
These procedures target ePT 7.6.21 and require access appropriate to the affected component.

## Fix problems participants report

Most calls come from participants and data managers. Check these causes first.

| Problem | Cause to check | Fix |
| --- | --- | --- |
| Can't sign in | Wrong password, or the account is inactive | Click **Forgot Password?** on the login page. If no email arrives, an admin resets the password on **Configure → Data Manager (Participant Login)**. On the same page, an admin can set an inactive account back to **Active** |
| Shipment missing from **PT Result Submission** | The PT Survey is not shipped, the participant is not on the shipment, or the data manager is not mapped to the participant | Ship it on **Manage → PT Survey**. Add the participant with **Add Participants**. Map the data manager on **Configure → Participant Login Map** |
| Response can't be changed | The response deadline has passed, the shipment is finalized, or the account has view-only access | Ask the PT provider to extend the deadline or accept a late response |
| Shipment still shows **Enter Response** after **Submit** | The participant did not click **Confirm Response** on the review page | Click **Enter Response** again and finish with **Confirm Response** |
| No report under **View PT Result → Individual Report** | Reports are not generated, or the shipment is not finalized | Generate the reports, then finalize the shipment on **Analyze → Finalize Reports** |

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
| Page says "ePT requires PHP 8.4.27 or later" | `php -v` on the server | Run `sudo ept update`, which upgrades PHP. On Windows, switch WampServer to PHP 8.4.27 or later | The site loads and `ept check` passes |
| Missing participants or shipments | Account status, participant mapping and enrollment | Correct the assignment through authorized administration | Affected account sees the intended records only |
| Response cannot be edited | Shipment status, response switch, deadline and account permissions | Ask the programme administrator to review collection state | A permitted test response saves and reopens |
| Late response awaiting review | Supported submission path and late-submission state | Have an authorized administrator review the held response | Approved result appears in the intended workflow |
| Evaluation or report remains queued | Scheduler, job status, PHP errors and disk space | Investigate the failing job before requesting another run | Queue completes and the output belongs to the expected shipment |
| Email not delivered | Queue outcome, SMTP configuration, recipient address and provider rejection | Correct the specific delivery failure. See [Keeping email delivery working](email.md) | A test message reaches the intended test mailbox |
| Report unavailable | Generation status, finalization, mapping and generated files | Complete the authorized reporting workflow | Correct account downloads the expected report |
| Backup missing | Scheduler, destination permissions, disk space and backup configuration | Restore backup execution and off-host copying | New archive passes verification and a restore test |

## Inspect application evidence

1. Review recent files under `logs/` for the incident time.
2. Review the web-server error log through the hosting operator.
3. Check the configured scheduler and its execution output.
4. Review job tracking or email outcomes in the application where available.

Run `ept check` on the server first. It checks the database, schema version, latest backup, disk space, writable folders and cron in one pass.
Use `ept scripts` to locate supported administrative tools.
Use [CLI tools](cli-tools.md) for their arguments.
Do not edit queue or evaluation rows directly to conceal a failed job.

## Escalate an unresolved incident

1. Supply the recorded version and sanitized reproduction steps.
2. Attach the relevant error and job identifiers.
3. State the affected workflow and operational impact.
4. Record the owner, next action and resolution check in the local incident log.

After a repair, repeat the affected workflow using controlled data.
For report defects, inspect the final PDF, not only the generating script's exit status.
