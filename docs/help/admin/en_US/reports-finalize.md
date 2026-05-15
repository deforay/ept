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

- **Finalize** *(green ✓)* — opens the finalize page where you
  can review the comment, mark the report as "approved", and
  queue the final report build. Available once reports have been
  generated and the shipment is not already finalized.

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

- **Finalize** *(disabled)* — shown when reports have not been
  generated yet, or some other step is missing. Go back to
  *Generate Reports* and build the drafts first.

## What "Finalize" actually does

Clicking **Finalize** moves the shipment to its final state:

1. Re-builds the reports one last time with the latest scores,
   the latest comment, and the latest corrective action file.
2. Marks the shipment as **finalized** and saves the time.
3. **Makes the per-participant reports visible on the
   participant's dashboard.** Participants can now download
   their own report.
4. Disables Re-Evaluate, Generate Reports, and Finalize on the
   rest of the admin pages for this shipment — the shipment is
   now read-only.

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
