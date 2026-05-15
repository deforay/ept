---
title: Evaluate PT Survey
summary: Score participant responses, then generate and finalize reports
tags: [evaluate, evaluation, reports, finalize, re-evaluate]
---

# Evaluate PT Survey

Once participants start sending in their responses, come here to
**evaluate** them. Evaluation applies the scoring rules and works
out pass / fail and scores for every participant.

You can re-evaluate as many times as you like. Each pass uses the
latest responses and the latest rules. Evaluation is internal —
**participants do not see anything until you generate and finalize
the reports.**

## Step 1 — Pick a PT Survey

The top table lists every PT Survey in the system.

- Find the survey you want.
- Click **View** in the Action column.
- The row highlights and the **Shipments Under PT Survey ...**
  panel expands **inline directly under the row**.
- Click **View** again on the same row to collapse it. Clicking
  **View** on a different row swaps the open panel — only one
  survey is expanded at a time.

The system remembers the last survey you picked, so when you come
back, the same shipments panel re-opens automatically.

## Step 2 — The shipments panel

The expanded panel lists every shipment in that survey, with:

- **Shipment Code** / **Scheme** — what the shipment is.
- **No. of Samples** — samples in the panel.
- **No. of Participants** — labs in this shipment.
- **No. of Responses** — how many have responded so far.
- **Response %** — quick read on the response rate.
- **Number Passed** — pass count from the latest evaluation.
  Zero until you have evaluated.
- **Shipment Status** — the stage the shipment is at:
  *Pending response* → *Evaluated* → *Reports Generated* →
  *Finalized*.
- **Action** — buttons that change with the status (see below).

## The Action column — what you'll see when

The buttons here **change with the shipment's status**. The path
is:

> **Responses received** → **Evaluate** → **Generate Reports** →
> **Finalize**

**Once a shipment is finalized, you cannot re-evaluate it.** The
Re-Evaluate, Generate Reports and Finalize buttons all disappear
— only **View** remains. Be sure the reports look right
*before* you finalize, because finalize locks the shipment and
sends reports to participants.

### First time — at least one response in, never evaluated

- **View** *(green eye)* — open the per-participant breakdown
  (see "View / Re-Evaluate Shipment").
- **Evaluate** *(green pencil)* — queues an evaluation job. The
  shipment is scored shortly. You will see a message that the
  job is queued and the page reloads.
- **Mail N Not Responded Participants** *(orange envelope)* —
  shows when some participants have not yet responded. Sends a
  reminder email to just those labs. The label shows the count
  (for example, *Mail 24 Not Responded Participants*).

If no participant has responded yet, you will see only a disabled
**View** button.

### After Evaluate

- **View** — now shows the latest evaluation.
- **Re-Evaluate** — re-scores the shipment. Use this after
  fixing rules, after late responses, or any time you want a
  fresh pass.
- **Generate Reports** *(blue ✓)* — produces the per-participant
  PDF reports. Disabled if the shipment date is still in the
  future.
- **VL Range** — only for **VL** shipments. Opens the manual
  reference-range editor.
- The mail-non-responders button stays while some labs have not
  responded.

### After Generate Reports

- **View Reports** *(light blue eye)* — open the draft reports
  to review.
- **Regenerate Reports** *(orange refresh)* — re-builds the
  reports with the latest evaluation. Use after any change.
- **Finalize** *(blue ✓)* — lock the reports and show them to
  participants. Disabled until the shipment date has been
  reached.
- Re-Evaluate, View, VL Range and the mail button stay
  available.

### After Finalize

- **View** — only View remains. The shipment is locked: no more
  evaluating, regenerating, or finalizing. **Participants can
  now see the reports** on their dashboard.

## While a job is running

While evaluation or report generation is running, the shipment
shows a temporary status (*draft*, *ready*, *queued*,
*processing*) and **all action buttons are disabled**. This stops
you from starting the same job twice. Reload the page to pick up
the new status.

If a *queued* shipment looks stuck, the **Evaluate** button is
re-enabled after 15 minutes so you can try again.

## Tips

- **Re-evaluate freely.** Each run replaces the previous scores.
  Participants only ever see what you finalize.
- **Generate Reports before Finalize** so you can review the
  PDFs. Finalize cannot be undone.
- For VL shipments, set the **VL Range** before you generate
  reports — the ranges affect pass / fail.
- Use **Mail Not Responded Participants** carefully — each click
  sends one email.
