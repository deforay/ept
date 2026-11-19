---
title: Job Tracking
summary: Follow background jobs such as evaluation and report generation, and cancel a job that is stuck
tags: [jobs, queue, background, reports, evaluation, stuck, cancel]
---

# Job Tracking

This page lists the jobs that ePT runs in the background. These
include evaluation, report generation, certificates and email
sending. The list refreshes every 5 seconds.

## Find out why a job failed

1. Open **Configure → Job Tracking**.
2. Under **Status**, choose **Failed**.
3. Under **Job Type**, choose the kind of job.
4. Hover over **Failed** in the **Status** column. A report
   generation job shows the reason.
5. Fix the cause.
6. Start the job again from the page where you started it.

Other job types show no reason here. To find it, open
**Configure → Log Viewer**. Choose the day the job ran, and
choose the **ERROR** level.

## Cancel a stuck job

A job is stuck when its status stays **Pending**, **Processing**,
**Generating** or **Finalizing** and its progress does not move.

1. Find the job in the list.
2. Click **Cancel** in the **Actions** column.
3. Confirm.

**Cancel** marks the job **Cancelled**. It does not stop work
that is already running on the server.

Cancelling a report generation job releases the shipment, so you
can generate its reports again. Cancelling a TB forms job lets
you generate the forms again.

ePT also stops a report generation job that shows no progress for
5 minutes. It marks the job **Failed** and releases the shipment.

You cannot cancel **Send Emails** rows. To stop a scheduled
email, use **Pause** or **Cancel** on the **Scheduled** tab of
**Email Participants**.

## Filters

| Filter | What it does |
| --- | --- |
| **Job Type** | Shows one kind of job |
| **Status** | Shows **Pending**, **Processing**, **Completed** or **Failed** jobs |
| **Date Range** | Shows jobs started in this period. The default is the last 6 months |
| **Reset** | Clears the filters and restores the default period |

Click **Refresh Now** to reload the list at once.

## Job types

| Job Type | What the job does |
| --- | --- |
| **Report Generation** | Generates or finalizes the reports for a shipment |
| **Evaluation** | Evaluates a shipment |
| **Certificate Generation** | Generates certificates |
| **Email Reports** | Emails reports to participants |
| **Send Emails** | Sends the emails queued on one day. Each row is one day |
| **TB Forms Generation** | Generates the TB forms for a shipment |
| **Bulk Password Reset** | Resets many passwords at once |
| **Scheduled Job** | Any other background job |

Hover over the job type to see more detail.

## Columns

| Column | What it shows |
| --- | --- |
| **Job Type** | The kind of job |
| **Reference** | The shipment code, or the day for **Send Emails**. For report generation, click it to open the shipment's reports |
| **Status** | The job's status. A spinning icon means the job is running |
| **Progress** | For report generation, the participant reports done and the summary report. For **Send Emails**, the emails sent and how many failed |
| **Requested By** | The admin who started the job, or **System** |
| **Started At** | How long ago the job started. Hover to see the date and time |
| **Ended At** | How long ago the job ended |
| **Duration** | How long the job ran |
| **Actions** | **Cancel**, for a job that has not finished |

## Statuses

| Status | Meaning |
| --- | --- |
| **Pending** | The job waits to start |
| **Processing**, **Generating**, **Finalizing** | The job is running |
| **Completed**, **Reports Generated**, **Finalized** | The job finished |
| **Failed** | The job stopped with an error |
| **Cancelled** | An admin cancelled the job |

If **Send Emails** shows failed emails, open **Configure → Failed
Emails** to see why.
