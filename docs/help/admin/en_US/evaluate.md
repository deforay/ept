---
title: Evaluate PT Survey
summary: Evaluate participant responses, generate reports, and finalize shipments
tags: [evaluate, evaluation, reports, finalize, re-evaluate]
---

# Evaluate PT Survey

Once participants have started responding to a shipment, you come
here to **evaluate** their responses and generate the per-participant
reports. Evaluation runs the scoring rules over the submitted
responses and computes pass/fail, scores, and any flags. There is
**no limit** on how many times you can evaluate — re-evaluating just
re-runs the rules with the latest responses and any rule changes
you've made. **Evaluation is internal to the admin and is not
revealed to participants** until you generate and finalize the
reports.

## Step 1 — Pick a PT Survey

The top table lists every PT Survey in the system.

- Sort or page through to find the survey you want to work on.
- Click **View** in the Action column.
- The selected survey row is highlighted, and the **Shipments
  Under PT Survey ...** table appears below.

The system remembers the last survey you selected (via a cookie),
so when you come back to this page it re-opens the same survey's
shipments automatically.

## Step 2 — The shipments table

The bottom table shows every shipment under the selected survey,
with response statistics:

- **Shipment Code** / **Scheme** — identifies the shipment.
- **No. of Samples** — samples in the panel.
- **No. of Participants** — labs mapped to this shipment.
- **No. of Responses** — how many of those have responded so far.
- **Response %** — quick read on response rate.
- **Number Passed** — pass count after the most recent evaluation.
  Zero until you've evaluated.
- **Shipment Status** — derived from milestone timestamps:
  *Pending response* → *Evaluated* → *Reports Generated* →
  *Finalized*.
- **Action** — context-aware buttons (see below).

## The Action column — what you'll see when

The buttons in the Action column **change based on the shipment's
current status**. The general state machine is:

> **Responses received** → **Evaluate** → **Generate Reports** →
> **Finalize**

**Once a shipment is finalized, it cannot be re-evaluated.** The
Re-Evaluate, Generate Reports, Regenerate Reports, and Finalize
buttons all disappear — only **View** remains. Make sure you're
happy with the reports *before* finalizing, because that step
locks the shipment and pushes reports out to participants.

### Initial — at least one response in, never evaluated

- **View** *(green eye)* — open the per-participant breakdown for
  this shipment. See "View page" below.
- **Evaluate** *(green pencil)* — schedules an evaluation job. The
  shipment goes into a queue and is evaluated shortly. You're
  alerted that it's been queued and the page reloads.
- **Mail N Not Responded Participants** *(orange envelope)* —
  shown when at least one mapped participant hasn't responded.
  Sends a reminder email to just those labs. The button label
  shows the actual count (e.g. *Mail 24 Not Responded
  Participants*).

If no participant has responded yet, you'll only see a single
disabled **View** button.

### After Evaluate — *evaluated_at* is set

- **View** — same as above; now reflects the latest evaluation.
- **Re-Evaluate** — re-runs the evaluation. Use this after fixing
  rules, after late responses come in, or any time you want a
  fresh pass. There is no limit.
- **Generate Reports** *(blue ✓)* — produces the per-participant
  PDF reports for this shipment. Disabled if the shipment date is
  in the future.
- **VL Range** — shown only for **VL** scheme shipments. Opens the
  manual reference-range editor for VL samples.
- Mail-non-responders button continues to show while there are
  outstanding participants.

### After Generate Reports — *reports_generated_at* is set

- **View Reports** *(light blue eye)* — opens the generated
  reports for review.
- **Regenerate Reports** *(orange refresh)* — replaces the
  Generate Reports button. Re-runs the report generator with the
  latest evaluation. Use this any time scores or rules change.
- **Finalize** *(blue ✓)* — locks in the current set of reports
  and makes them visible to participants. Disabled until the
  shipment date has been reached.
- Re-Evaluate / View / VL Range / Mail buttons remain available.

### After Finalize — *finalized_at* is set

- **View** — only View remains. The shipment is locked: no more
  re-evaluation, regeneration, or finalization. **Reports are now
  visible to participants** on their dashboard.

## Ephemeral statuses

While an evaluation or report-generation job is running, the
shipment shows a temporary status (*draft*, *ready*, *queued*,
*processing*) and **all action buttons are disabled** — this
prevents you from kicking off duplicate jobs. The page will pick
up the new status on the next reload.

If a *queued* shipment seems stuck, the **Evaluate** button is
re-enabled automatically after 15 minutes so you can retry.

## Tips

- **Re-evaluate freely.** Each re-evaluation overwrites the
  previous scores. There's no audit/history written to participants
  — they only ever see what was finalized.
- **Generate Reports before Finalize** so you can review the PDFs.
  Finalize is the irreversible step that pushes reports out.
- For VL shipments, set the **VL Range** before generating
  reports — manual ranges affect the pass/fail computation.
- Use the **Mail Not Responded Participants** button judiciously;
  participants get one email per click.
