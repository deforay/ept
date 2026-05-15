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

- **Add New PT Survey** (top right) — creates a new survey for a
  date that does not already have one.
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

A survey moves through three states:

- **Created** *(Not yet fully set up)* — the survey exists
  but has no shipments yet, or its shipments do not have
  participants linked. Participants cannot see anything until the
  survey is shipped.
- **Configured** *(Ready to ship)* — shipments are set up and
  participants are linked. The **Ship Now** button appears in
  the Action column.
- **Shipped** *(Already Shipped)* — you have clicked **Ship
  Now**. The survey and its shipments are now visible to
  participants and they can start responding.

## Action column buttons

What you see depends on the survey's status and how far along
setup is:

- **Edit** — always present. Opens the survey for editing.
- **Add Shipment** — shown when the survey has *no shipments*
  yet. Jumps to the shipments page so you can add one.
- **Add Participants** — shown when shipments are set up but
  participants are not fully linked yet. Jumps to the shipments
  page so you can finish linking them.
- **Ship Now** — shown when shipments and participants are both
  ready. See "Shipping a survey" below.
- **Shipped** *(disabled)* — shown after shipping, just to
  confirm the survey is live.
- **Send Email to Participants** — yellow envelope button shown
  only on **shipped** surveys. Opens the email composer with the
  participants on this survey already picked, so you can send
  reminders or follow-ups about this round.

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
  in the **Status** column search. This shows only the *Ready to
  ship* rows so you can clear them off your queue.
- Editing a survey *after* shipping is limited: the date is
  locked once shipments have gone out (see the **Edit PT Survey**
  help for more).
