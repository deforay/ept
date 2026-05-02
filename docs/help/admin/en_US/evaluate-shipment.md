---
title: View / Re-Evaluate Shipment
summary: Per-participant breakdown for a single shipment, with re-evaluation, manual overrides, and shared comments
tags: [evaluate, re-evaluate, view, participants, manual-override]
---

# View / Re-Evaluate Shipment

This page is the per-shipment detail view, opened by clicking
**View** on a shipment in **Evaluate PT Survey**. From here you can
inspect every participant's response to the shipment, re-evaluate
the whole shipment, override individual results, attach a corrective
action file, and write a shared comment that all participants see.

## Header

The top of the page shows what you're working on:

- **You are currently evaluating** *(shipment code)* — confirms
  the shipment in scope.
- **Re-Evaluate** *(red)* — schedules a fresh evaluation pass for
  this shipment. Same as clicking *Re-Evaluate* on the previous
  page; the page reloads when the job is queued. **Disabled once
  the shipment is finalized** — finalized shipments cannot be
  re-evaluated.
- **Back** — returns to the **Evaluate PT Survey** index.
- A summary table shows **Scheme Type**, **PT Survey Code**, and
  **PT Survey/Shipment Date**.

## Corrective Action File and shared comment

Below the header is a small form for shipment-wide content that
appears on every participant's report:

- **Corrective Action Files** — upload a PDF/document with
  guidance for participants who didn't pass. The file becomes part
  of every participant's report for this shipment.
- **Comment for all Participants of this shipment** — free-text
  note that's printed on every participant's report (e.g.
  explaining a problematic sample, summarising overall performance,
  flagging a known issue).
- **Update File & Comment** — saves both. You can change them and
  resave at any time before finalizing.

## Manual override filter

The **Show only manually overridden results** dropdown above the
participants table lets you narrow the list to rows whose result
was changed by hand (more on overrides below). Useful when you
want to review just the rows you've adjusted before regenerating
reports.

## Participants table

One row per participant who is mapped to this shipment. The
columns:

- **Participant/Tester** — lab/clinic name and ID.
- **Response Score** — score from the actual PT response data.
- **Documentation Score** — score from the documentation/metadata
  questions (where applicable).
- **Result** — Pass / Fail / Excluded for this participant. Can
  be manually overridden — overridden rows are visually flagged.
- **Response Status** — whether the participant responded, didn't
  respond, was excluded, etc.
- **Responded On** — timestamp of the participant's submission.
- **Last Modified** — timestamp of the last edit (response or
  override).
- **Action** — per-row actions (see below).

## Action column — per row

Buttons in each row let you work on a single participant's
response:

- **View** — open the participant's response in read-only mode to
  see exactly what they submitted.
- **Edit** — open the response form pre-filled with the
  participant's submission so you can correct typos or fix
  obvious entry errors on their behalf. Changes are tagged as
  manual overrides.
- **Override Result** — flip the computed Pass/Fail or mark the
  participant as Excluded with a reason. Overridden rows show up
  when the *Show only manually overridden results* filter is
  set to **Yes**.
- **Delete Response** — wipes the participant's response for this
  shipment so they can re-submit (or so you can mark them as
  non-responder). A type-to-confirm modal protects against
  accidental clicks.

## Workflow tips

- **Re-Evaluate after any manual change.** Editing a response or
  flipping a result doesn't re-score the shipment by itself —
  click **Re-Evaluate** afterwards so the aggregate counts
  (Number Passed, Response %) on the previous page reflect your
  changes.
- **Use the comment + corrective action file generously** for
  shipments where many participants struggled. Both appear on the
  generated PDFs so participants get the context with their
  result.
- **Once finalized, this page becomes read-only** — Re-Evaluate
  is disabled and the per-row Edit / Override / Delete actions
  no longer change anything that participants will see.
