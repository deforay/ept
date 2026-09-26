# Keeping email delivery working

This guide sets up outgoing email, turns on bounce processing, and fixes mail that stops sending.

All mail settings live in `application/configs/application.ini` on the server. The database holds none of them. A copy of the production database restored on another machine therefore cannot send email to participants.

## Before you start

| Requirement | Detail |
| --- | --- |
| Admin account | Access to **Configure → ePT Global Settings** |
| Server access | A shell on the server, as `root` or with `sudo`, for the bounce and queue sections |
| Mail account | An SMTP account. For Gmail or Google Workspace, an [app password](https://support.google.com/accounts/answer/185833) and IMAP turned on |
| ePT version | 7.6.23 or later |

## Set or change the SMTP login

1. Open **Configure → ePT Global Settings**.
2. Open the **Email Settings** section.
3. Fill in **SMTP Host**, **SMTP Port**, **Encryption**, **Username** and **Authentication Type**.
4. Enter the new password in **Password**. To keep the current password, leave the field blank.
5. Fill in **From Name** and **From Email**. If **From Email** is empty, mail goes out from the SMTP username.
6. Click **Update**.

Before saving, the page logs in to the mail server with the new details. If the server refuses the login, a red message shows the server's reply and nothing is saved. The previous settings keep working.

To set the values on the server instead, edit these lines in the `[production]` section of `application.ini`:

```ini
email.host = "smtp.gmail.com"
email.config.port = "587"
email.config.ssl = "tls"
email.config.auth = "login"
email.config.username = "support@example.org"
email.config.password = "abcdabcdabcdabcd"
email.fromName = "ePT Support"
email.fromEmail = ""
email.cc = ""
email.bcc = ""
```

A value cannot contain a double quote, a backslash, `${` or a line break.

If the page reports that `application.ini` is not writable, run `sudo ept update`. The updater makes the file writable by the web server.

## Turn on bounce processing

Bounce processing reads delivery-failure reports from an IMAP mailbox. It marks each address that no longer exists, so shipment emails, the copy-email buttons and **Send Email to Participants** skip it.

Bounces of mail that ePT sends come back to the SMTP account. Bounces of mail you send from your own Gmail, after copying addresses from ePT, come back to your own inbox.

1. In the SMTP account's Gmail, create a filter. Set **From** to `mailer-daemon@googlemail.com` and choose **Apply the label** `ept-bounces`.
2. If you also send from your own Gmail, create a filter there too. Set **From** to `mailer-daemon@googlemail.com` and choose **Forward it to** the SMTP account.
3. Open **Configure → ePT Global Settings → Email Settings** and fill in the **Bounce Inbox** part:

    | Field | Value |
    | --- | --- |
    | **IMAP Host** | `imap.gmail.com` |
    | **IMAP Port** | `993` |
    | **Encryption** | SSL |
    | **Folder or Gmail Label** | `ept-bounces` |
    | **Username** and **Password** | Leave empty to use the SMTP login. To read a different mailbox, fill them in |

    Click **Update**. The page opens the mailbox and the folder before saving. If that fails, a red message shows why and nothing is saved.

    The same values can be set in the `[production]` section of `application.ini` as `email.bounce.host`, `email.bounce.port`, `email.bounce.ssl`, `email.bounce.folder`, `email.bounce.username` and `email.bounce.password`.

4. Preview what the processor would mark:

    ```bash
    cd /var/www/ept
    sudo -u www-data php bin/process-bounces.php --dry-run --reset-state
    ```

    Each line starting with `HARD` names an address the processor would mark as bounced. `--reset-state` makes this first run read the whole folder.

5. Run it once for real, or wait up to 30 minutes for the scheduler. Saving a new mailbox or folder makes the next run read the whole folder.

    ```bash
    sudo -u www-data php bin/process-bounces.php --reset-state
    ```

After this, the scheduler runs the processor every 30 minutes. It reads only new messages. It does not delete or move them unless you set `email.bounce.markSeen = "yes"` or `email.bounce.moveTo`.

A full mailbox, a spam block or a rate limit does not mark the address. Only a report that the mailbox does not exist does.

## Find participants and data managers who cannot receive email

1. Open **Configure → PT Participants**, or **Configure → Data Manager (Participant Login)**.
2. Click **Unusable Emails**.
3. Open the downloaded workbook. It has a **Participants** sheet and a **Data Managers** sheet.
4. Filter **Reachable By Email** to `No`. These accounts get no email at all.

| Problem shown | Meaning | Fix |
| --- | --- | --- |
| No email | The address is empty | Add an address |
| Login-only address (made up at import) | Bulk import generated the address so the account can sign in. No mailbox exists | Add a real address, or leave it if the account only signs in |
| Bounced | The mail server reported that the mailbox does not exist. **Bounce Reason** shows the reply | Get a new address from the laboratory |
| Domain does not accept mail | The domain has no mail server | Correct the domain |
| Not a valid email address | The address is malformed | Correct the address |

To fix an account, edit its email address in ePT. A changed address loses its old problem and is checked again on the next validation run.

## Fix mail that sends sometimes and fails sometimes

1. Open **Configure → Failed Emails**.
2. Read the **Failure Reason** of the recent rows. Use this table to find the cause:

    | Text in **Failure Reason** or the log | Cause | Fix |
    | --- | --- | --- |
    | `535` or `Username and Password not accepted` | The mail server refuses the SMTP login | Create a new app password. Enter it in **Email Settings** |
    | `Invalid emails` for one recipient | The address was skipped as undeliverable | See [Find participants and data managers who cannot receive email](#find-participants-and-data-managers-who-cannot-receive-email) |
    | `No valid To recipients` | Every recipient was skipped | Correct the recipient's address |
    | `mail settings are still in global_config.mail` in the log | The update did not finish moving the old settings | Run `ept run-once` |

3. Search today's log for mail errors:

    ```bash
    grep -iE "smtp|mail" /var/www/ept/logs/$(date +%F)-logfile.log | tail -20
    ```

4. Find the queue IDs of the failed messages in MySQL:

    ```sql
    SELECT temp_id, to_email, subject, updated_at
      FROM temp_mail WHERE status = 'failed' ORDER BY temp_id DESC LIMIT 20;
    ```

5. After fixing the cause, put those messages back in the queue:

    ```sql
    UPDATE temp_mail SET status = 'pending', failure_reason = NULL
     WHERE status = 'failed' AND temp_id IN (10746);
    ```

    Replace `10746` with the `temp_id` values from step 4.

## Check that mail works

1. Send yourself an email from **Send Email to Participants**, or trigger a password reset for a test account.
2. Wait up to one minute for the queue to run.
3. Confirm the message is not listed on **Configure → Failed Emails**.
4. Confirm the message arrived in the test mailbox.
