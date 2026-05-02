---
title: Finalize Reports
summary: Re-generate and lock in reports — only finalized reports are visible to participants
tags: [reports, finalize, distribution, publish, queue]
---

# Finalize Reports

This is **Step 3** of the three-step reporting flow:

1. **Analyze → Evaluate Responses** — score the responses.
2. **Analyze → Generate Reports** — produce draft reports for
   internal review.
3. **Analyze → Finalize Reports** *(this page)* — re-generate and
   lock the reports.

> **Only finalized reports are visible to participants.** Until you
> finalize, everything you've generated is internal. Once you
> finalize, the per-participant reports become downloadable from
> each participant's dashboard.

## How the page works

The structure mirrors the *Generate Reports* page: the top table
lists every PT Survey, clicking through loads the **Shipments
Under PT Survey ...** table below. The system remembers the last
survey you opened.

The shipments table shows the same response statistics as the
evaluate / generate pages — *No. of Samples / Participants /
Responses*, *Response %*, *Number Passed*, *Shipment Status* —
and an **Action** column tailored to finalization.

## Action column — what you'll see when

### Ready to finalize

- **Finalize** *(green ✓)* — opens the finalize confirmation page
  where you can review the comment, set the report to "approved",
  and queue the final report build. The button is enabled when
  reports have been generated and the shipment hasn't already
  been finalized.

### Already finalized

- **Finalized** *(red, disabled)* — visual indicator that the
  shipment has been finalized. Reports are now visible to
  participants.
- **Download Summary Report** *(green)* — direct download of the
  shipment's summary PDF (`<shipment-code>-summary.pdf`).
- **Download 1–50 Participants Report**, **Download 51–100
  Participants Report**, … *(blue)* — the bulk participant
  reports are split into batches of 50 to keep the file sizes
  manageable. One button per batch, generated automatically based
  on the response count.

### Not ready yet

- **Finalize** *(disabled)* — shown when reports haven't been
  generated yet, or some other prerequisite isn't met. Go back
  to *Generate Reports* and produce the drafts first.

## What "Finalize" actually does

Clicking **Finalize** moves the shipment to its terminal state:

1. Re-runs the report generator one last time with whatever the
   current state is (latest scores, latest comment, latest
   corrective action file).
2. Marks the shipment as **finalized**, stamping the
   `finalized_at` timestamp.
3. **Makes the per-participant reports visible on the
   participant's dashboard.** Participants can now download their
   own report.
4. Disables the Re-Evaluate, Generate Reports, and Finalize
   buttons across the rest of the admin UI for this shipment —
   the shipment is now read-only.

This is **irreversible** through the normal UI. Make sure the
draft reports look right *before* finalizing.

## Background queue — fire and forget

Just like *Generate Reports*, finalization runs in a background
queue:

1. The shipment is added to the queue.
2. The page shows a progress tracker that polls every couple of
   seconds.
3. **You don't have to wait.** Navigate away, come back later, or
   close the tab — the job keeps running.
4. When the job finishes, the buttons flip from *Finalize* to
   *Finalized* + *Download* links, and the participants' side
   starts serving the reports.

For very large surveys (thousands of participants), finalization
can take several minutes; kick it off and check back later.

## After finalize — sending reports out

Once a shipment is finalized, separate actions become available
elsewhere in the admin to actually push the reports to
participants — e.g. emailing PDFs, sending dashboard
notifications. Finalize itself only *enables* visibility; how
participants are notified depends on your follow-up workflow.

## Tips

- **Don't skip the review step.** *Generate Reports → review the
  drafts → Finalize* is the supported flow. There's no "undo
  finalize" button.
- **Re-evaluate before finalizing**, not after. Re-evaluation is
  blocked once finalized.
- **Comment and corrective action file edits made before
  finalizing are baked into the final PDFs.** Make sure they're
  the right values before clicking Finalize.
- **The Download buttons appear only after the queue job
  completes** — if you don't see them yet, the build is still
  running. Refresh in a minute or two.
