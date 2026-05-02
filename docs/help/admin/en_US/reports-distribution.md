---
title: Generate Reports
summary: Generate draft per-participant and summary reports for internal review (not yet visible to participants)
tags: [reports, generate, distribution, draft, queue]
---

# Generate Reports

This is **Step 2** of the three-step reporting flow:

1. **Analyze → Evaluate Responses** — score the responses.
2. **Analyze → Generate Reports** *(this page)* — produce draft
   reports for your internal review.
3. **Analyze → Finalize Reports** — re-generate and lock in;
   participants only see reports after this step.

Reports generated here are **draft** — they're for the PT admin to
verify formatting, scores, comments, charts, and any corrective
action notes before publishing. **Participants do not see
generated reports until you finalize them.**

## How the page works

The top table lists every PT Survey. Click the highlight row /
**View** action to load the **Shipments Under PT Survey ...**
table below it — same pattern as the Evaluate page. The system
remembers the last survey you opened (via a cookie) so it
re-opens automatically when you come back.

The shipments table shows the same response statistics as the
evaluate page — *No. of Samples / Participants / Responses*,
*Response %*, *Number Passed*, *Shipment Status* — and an
**Action** column whose buttons depend on where the shipment is
in the workflow.

## Action column — what you'll see when

### Reports not yet generated

- **Generate Reports** *(blue)* — kicks off report generation for
  this shipment in the background queue. Disabled if the PT
  Survey date is still in the future.

### Reports generated, not finalized

- **View Reports** *(light blue eye)* — opens the generated
  report set so you can review the PDFs before finalizing. This
  is the whole point of this step — *look at the drafts first*.
- **Regenerate Reports** *(orange refresh)* — re-runs the
  generator. Use after changing comments, fixing a corrective
  action file, re-evaluating, or any other input.
- **Finalize** *(blue ✓)* — proceed to Step 3. Opens the finalize
  page where you confirm and lock the reports.

### Already finalized

- **View** *(blue eye)* — read-only view of the finalized
  shipment. Once finalized, this page no longer offers regenerate
  or finalize buttons; use **Analyze → Finalize Reports** for
  download links and follow-up actions.

## Background queue — fire and forget

Report generation is **async**. When you click **Generate
Reports** or **Regenerate Reports**:

1. The shipment is added to a background processing queue.
2. The page shows a progress tracker that polls every couple of
   seconds.
3. **You don't have to wait.** Navigate away to other pages, come
   back later, or close the tab — the queue keeps running. When
   you return, the buttons reflect the new state.
4. While a job is running, the action buttons for that shipment
   are disabled to prevent duplicate jobs.

For very large surveys (thousands of participants), generation
can take several minutes — kick off jobs for several shipments
in succession and check back when convenient.

## Date guards

You can't generate reports on or before the PT Survey Date — if
the date is still in the future, the **Generate Reports** button
is disabled and a message explains why. Adjust the survey date
(via *Manage → PT Surveys → Edit*) if you need to generate early.

## What gets produced

Each generation pass writes:

- **Per-participant reports** — one PDF per participant in the
  shipment, with their result, scores, comments, and any
  corrective action attachment.
- **Summary report** — a shipment-wide summary PDF with
  aggregate stats, charts, and pass/fail breakdowns.

These are stored under the shipment's reports folder until you
finalize. After finalize, participants can download their own
copy from their dashboard.

## Tips

- **Generate first, then review, then finalize.** That's why this
  step exists — finalize is irreversible.
- **Regenerate after every change.** Editing a comment, replacing
  a corrective action file, or re-evaluating doesn't auto-rebuild
  PDFs. Click Regenerate to get fresh drafts.
- **Don't kick off the same shipment twice in a row.** If a job
  is already running, the buttons are disabled — wait for it to
  finish before retrying.
