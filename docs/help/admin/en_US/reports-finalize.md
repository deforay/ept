---
title: Finalize Reports
summary: Re-build and lock the reports — only finalized reports are visible to participants
tags: [reports, finalize, distribution, publish, queue]
---

# Finalize Reports

This is **Step 3** of the three-step reporting flow:

1. **Analyze → Evaluate Responses** — score the responses.
2. **Analyze → Generate Reports** — produce draft reports for
   internal review.
3. **Analyze → Finalize Reports** *(this page)* — re-build and
   lock the reports.

> **Only finalized reports are visible to participants.** Until
> you finalize, everything you have generated is for your eyes
> only. Once you finalize, the per-participant reports can be
> downloaded from each participant's dashboard.

## How the page works

The page mirrors *Generate Reports*. The top table lists every PT
Survey. Click **View** in the Action column to expand the
**Shipments Under PT Survey ...** panel **inline directly under
the row**.

- Click **View** again on the same row to collapse the panel.
- Click **View** on a different row to swap the open panel —
  only one survey is expanded at a time.
- The system remembers the last survey you opened, so the panel
  re-opens automatically when you come back.

The shipments panel shows the same numbers as the evaluate /
generate pages — *No. of Samples / Participants / Responses*,
*Response %*, *Number Passed*, *Shipment Status* — and an
**Action** column made for finalization.

## Action column — what you'll see when

### Ready to finalize

- **Finalize** *(green ✓)* — opens the finalize page. Available
  once reports are generated and the shipment is not finalized.
  On that page:
  1. Check **Comment for all Participants of this shipment**.
  2. Fill in **Date of Approval** and **Approved By**. Leave both
     blank to record you, as of now.
  3. Click **Finalize** to queue the final report build.

### Already finalized

- **Finalized** *(red, disabled)* — confirms the shipment is
  finalized. Reports are now visible to participants.
- **Download Summary Report** *(green)* — direct download of the
  summary PDF (`<shipment-code>-summary.pdf`).
- **Download 1–50 Participants Report**, **Download 51–100
  Participants Report**, … *(blue)* — the bulk participant
  reports are split into batches of 50 to keep file sizes
  manageable. One button per batch.

### Not ready yet

- **Finalize** *(disabled)* — one of these is missing:
  - **Reports are not generated.** Go to **Analyze → Generate
    Reports** and generate them first.
  - **The feedback form does not exist.** If the shipment
    collects participant feedback, a **Create Feedback Form**
    button shows instead. Click it, build the form, then come
    back and finalize.
  - **A job is queued or running.** Wait, then refresh the
    page.

## What "Finalize" actually does

Clicking **Finalize** moves the shipment to its final state:

1. Re-builds the reports one last time with the latest scores,
   the latest comment, and the latest corrective action file.
2. Marks the shipment as **finalized** and saves the time.
3. **Makes the per-participant reports visible on the
   participant's dashboard.** Participants can now download
   their own report.
4. Disables Re-Evaluate, Generate Reports and Finalize for this
   shipment. You can still change the comment with **Update
   Comment**. To replace the summary report PDF, click **Replace
   Summary Report** on the shipment in **Analyze → View Finalized
   Shipments**. It shows only to
   admins with the privilege to replace finalized summary
   reports.

This **cannot be undone** through the normal UI. Be sure the
draft reports look right *before* you finalize.

## Background queue — fire and forget

Like *Generate Reports*, finalization runs in the background:

1. The shipment is added to the queue.
2. The page shows a progress bar that checks every couple of
   seconds.
3. **You don't have to wait.** Go elsewhere, come back later, or
   close the tab — the job keeps running.
4. When it finishes, the buttons change from *Finalize* to
   *Finalized* + *Download* links, and participants can start
   seeing the reports.

For very large surveys (thousands of participants), finalization
can take several minutes. Start it and check back later.

## After finalize — sending reports out

Once a shipment is finalized, separate actions become available
elsewhere in the admin to actually push the reports to
participants — for example, emailing PDFs or sending dashboard
notifications. Finalize itself only *makes the reports visible*.
How participants are notified is up to your follow-up.

## Tips

- **Don't skip the review step.** *Generate Reports → review the
  drafts → Finalize* is the supported flow. There is no "undo
  finalize" button.
- **Re-evaluate before finalizing**, not after. Re-evaluation
  is blocked once the shipment is finalized.
- **Comment and corrective action file changes are baked into
  the final PDFs.** Make sure they are right before clicking
  Finalize.
- **The Download buttons only appear after the queue job
  finishes** — if you don't see them yet, the build is still
  running. Reload in a minute or two.
