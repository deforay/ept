---
title: Generate Reports
summary: Build draft per-participant and summary reports for internal review (not yet visible to participants)
tags: [reports, generate, distribution, draft, queue]
---

# Generate Reports

This is **Step 2** of the three-step reporting flow:

1. **Analyze → Evaluate Responses** — score the responses.
2. **Analyze → Generate Reports** *(this page)* — build draft
   reports for internal review.
3. **Analyze → Finalize Reports** — re-build and lock the
   reports. Participants only see them after this step.

Reports built here are **drafts**. They are for the PT admin to
check formatting, scores, comments, charts and any corrective
action notes before publishing. **Participants do not see them
until you finalize.**

## How the page works

The top table lists every PT Survey. Click the row or the
**View** action to load the **Shipments Under PT Survey ...**
table below — same pattern as the Evaluate page. The system
remembers the last survey you opened, so it re-opens when you
come back.

The shipments table shows the same numbers as the evaluate page —
*No. of Samples / Participants / Responses*, *Response %*,
*Number Passed*, *Shipment Status* — and an **Action** column
whose buttons depend on where the shipment is in the flow.

## Action column — what you'll see when

### Reports not yet built

- **Generate Reports** *(blue)* — starts report generation for
  this shipment in the background queue. Disabled if the PT
  Survey date is still in the future.

### Reports built, not finalized

- **View Reports** *(light blue eye)* — opens the draft reports
  so you can review the PDFs before finalizing. This is the
  whole point of the step — *look at the drafts first.*
- **Regenerate Reports** *(orange refresh)* — re-builds the
  reports. Use after changing comments, swapping a corrective
  action file, re-evaluating, or any other change.
- **Finalize** *(blue ✓)* — go to Step 3. Opens the finalize
  page where you confirm and lock the reports.

### Already finalized

- **View** *(blue eye)* — read-only view of the finalized
  shipment. Once finalized, this page no longer offers
  regenerate or finalize buttons. Use **Analyze → Finalize
  Reports** for download links and follow-up actions.

## Background queue — fire and forget

Report generation runs in the background. When you click
**Generate Reports** or **Regenerate Reports**:

1. The shipment is added to a queue.
2. The page shows a progress bar that checks every couple of
   seconds.
3. **You don't have to wait.** Go elsewhere, come back later, or
   close the tab — the queue keeps running. When you return,
   the buttons show the new state.
4. While a job is running, the buttons for that shipment are
   disabled to stop you from starting the same job twice.

For very large surveys (thousands of participants), generation
can take several minutes. Start jobs for several shipments in a
row and check back later.

## Date check

You can't generate reports on or before the PT Survey Date. If
the date is still in the future, the **Generate Reports** button
is disabled and a message explains why. Change the survey date
(via *Manage → PT Surveys → Edit*) if you need to build early.

## What gets produced

Each run writes:

- **Per-participant reports** — one PDF per participant, with
  their result, scores, comments and any corrective action
  attachment.
- **Summary report** — a shipment-wide summary PDF with overall
  stats, charts and pass / fail breakdowns.

These are stored under the shipment's reports folder until you
finalize. After finalize, participants can download their copy
from their dashboard.

## Tips

- **Generate first, then review, then finalize.** That is why
  this step exists — finalize cannot be undone.
- **Regenerate after every change.** Editing a comment, swapping
  a corrective action file, or re-evaluating does not rebuild
  the PDFs on its own. Click Regenerate for fresh drafts.
- **Don't start the same shipment twice in a row.** If a job is
  already running the buttons are disabled — wait for it to
  finish before trying again.
