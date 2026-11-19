---
title: PT Surveys
summary: View, add, edit, and ship PT Surveys (distributions) and their shipments
tags: [distributions, pt-survey, shipments, ship]
---

# PT Surveys

A **PT Survey** (also called a *distribution*) is a single round
of proficiency testing on a chosen date. Each survey holds one or
more **shipments** — the actual panels participants test and
respond to. This page lists every survey in the system and lets
you create, edit, and ship them.

## What's on the page

- **Add New PT Survey** (top right) — creates a new survey.
- The table lists every survey, sortable and searchable, one row
  per survey.

### Columns

- **See Shipments** — magnifying-glass button. Click to open a
  pop-up that shows every shipment under this survey without
  leaving the page.
- **PT Shipment/Panel Type** — the kinds of panels (for example
  DBS, VL, EID, DTS) on this survey. Shows *No Shipment/Panel
  Added* in grey when the survey has no shipments yet.
- **PT Survey Date** — the date of the round.
- **PT Survey Code** — the unique code for this survey. Click
  it to jump to the shipments page filtered to this code.
- **Shipment Code(s)** — the codes of all shipments under this
  survey, separated by commas.
- **Status** — current state (see below).
- **Action** — buttons that change with the status (see below).

## Survey statuses

- **Created** — the survey exists, but it has no shipments yet,
  or its shipments have no participants. Participants see
  nothing.
- **Configured** — shipments and participants are set up. The
  **Ship Now** button appears.
- **Shipped** — you clicked **Ship Now**. Participants can see
  the shipments and respond.
- **Partially Finalized** — some of the survey's shipments are
  finalized, and others are not yet.
- **Finalized** — every active shipment is finalized.
- **Cancelled** — every shipment in the survey was cancelled.

## Action column buttons

What you see depends on the survey's status:

- **Edit** — opens the survey for editing.
- **Add Shipment** — shown when the survey has no shipments.
  Opens the survey's shipment list so you can add one.
- **Add Participants** — shown when shipments exist but are not
  ready to ship. Opens the shipment list, where you click
  **Enroll** on a shipment.
- **Ship Now** — shown when the shipments and participants are
  ready. See "Shipping a survey" below.
- **Shipped** *(disabled)* — confirms the survey is live.
- **Email** — opens the email composer for the participants in
  this survey's shipments.
- **Cancel** — cancels every shipment in the survey that is not
  finalized. Shown only while no shipment is finalized. You are
  asked to confirm. Cancelling cannot be undone.
- **Delete** — removes an empty survey. Shown only when the
  survey has no shipments and is not shipped.

## Shipping a survey

Until you click **Ship Now**, a survey and its shipments are
**not visible to participants** — they are still in your draft
area. Clicking **Ship Now**:

1. Asks you to confirm (this cannot be undone).
2. Marks the survey and its shipments as live.
3. Sends a notification so labs know they have new panels to
   respond to.

A typical flow is: **Add New PT Survey** → add shipments on the
next page → link participants → review via **See Shipments** →
**Ship Now** when everything looks right → later, use **Send
Email to Participants** for reminders.

## Tips

- The list is sorted by **PT Survey Date** with the newest
  surveys on top, so the round you are working on is always at
  the top.
- To see only surveys waiting to be shipped, type *configured*
  in the **Status** column search. This shows only the
  **Configured** rows so you can clear them off your queue.
- After shipping you can still edit the date. The code locks
  once one of the survey's shipments is finalized. See the
  **Edit PT Survey** help.
