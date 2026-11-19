---
title: Failed Emails
summary: Find emails that ePT could not send, and see why each one failed
tags: [email, failed, bounce, smtp, queue, delivery]
---

# Failed Emails

This page lists the emails that ePT could not send. Each row is
one email in the mail queue. The page shows what went wrong. It
does not resend or delete emails.

## Find out why an email did not arrive

1. Open **Configure → Failed Emails**.
2. Type the recipient's address in the search box.
3. Read the **Failure Type** and **Failure Reason** of the row.
4. Use the table below to find the cause.
5. Fix the cause.

If the email is not listed, set **Status** to **All** and search
again. The row shows whether the email is still waiting, was
sent, or was stopped.

## What the failure types mean

| Failure Type | Cause | What to do |
| --- | --- | --- |
| **Smtp Auth** | The mail server refused ePT's login | Correct the login in the **Email Settings** section of **Configure → ePT Global Settings**. Then click **Verify Email Setup** |
| **Connectivity** | ePT could not reach the mail server | Check **SMTP Host** and **SMTP Port** in **Email Settings**. Ask your server administrator to check the network |
| **Invalid Dsn** | The saved mail settings cannot be used | Correct the mail settings in **Email Settings** |
| **Bad Recipient** | The mail server rejected the recipient's address | Correct the address on the participant or data manager |
| **Rate Limit** | The mail account reached its sending limit | Wait, or ask your mail provider to raise the limit |
| **Content** | The mail server blocked the email as spam | Ask your mail provider why the email was blocked |
| **Expired** | The email waited in the queue for more than 7 days, so ePT did not send it | Send the email again from the page that created it |
| **Dev Trap Block** | A test mail setting on the server blocks all email | Ask your server administrator to fix the test mail setting |
| **Other** | Any other error | Read the **Failure Reason**. Ask your server administrator if it is unclear |

## Filter by status

By default the list shows **Failed & Skipped**. Choose another
value under **Status** to see other emails.

| Status | Meaning |
| --- | --- |
| **Failed** | ePT tried to send the email and the mail server refused it |
| **Skipped** | ePT did not try to send the email, for example because the mail settings are unusable or the email expired |
| **Pending** | The email is waiting in the queue |
| **Sent** | The mail server accepted the email |
| **Held (paused)** | The email belongs to a paused email in **Email Participants** |
| **Cancelled** | The email belongs to a cancelled email in **Email Participants** |
| **All** | Every email in the queue |

## Columns

| Column | What it shows |
| --- | --- |
| **To Email** | The recipient's address |
| **Subject** | The email's subject |
| **Message** | The start of the message. Click **View** to read the whole email |
| **Status** | The email's status |
| **Failure Type** | The kind of problem |
| **Failure Reason** | The error from the mail server, or ePT's reason for skipping the email |
| **Updated On** | When the status last changed |

## After you fix the mail settings

ePT does not resend failed emails from this page. Send the email
again from the page that created it, such as **Email
Participants** or the password reset on a data manager. Your
server administrator can also put failed emails back in the
queue.

If ePT cannot send email at all, the **Dashboard** shows a bar
that says so. Click **Open Email Settings** in the bar to fix the
settings.
