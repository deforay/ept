---
title: PT Surveys
summary: View, add, edit, and ship PT Surveys (distributions) and their shipments
tags: [distributions, pt-survey, shipments, ship]
---

# PT Surveys

A **PT Survey** (also called a *distribution*) is a single round of
proficiency testing on a specific date. Each survey contains one or
more **shipments** — the actual panels participants test and respond
to. This page lists every survey in the system and lets you create,
edit, and ship them.

## What's on the page

- **Add New PT Survey** (top right) — creates a new survey for a
  date that doesn't already have one.
- The table lists every survey, sortable and searchable, with one
  row per survey.

### Columns

- **See Shipments** — magnifying-glass button. Click to open a
  modal showing every shipment configured under this survey
  without leaving the page.
- **PT Shipment/Panel Type** — the kind of panels (e.g., DBS, VL,
  EID, DTS) tied to this survey. Shows *No Shipment/Panel Added*
  in grey when the survey has no shipments yet.
- **PT Survey Date** — the date the round is scheduled for.
- **PT Survey Code** — the unique code identifying this survey.
  Click it to jump to the shipments page filtered to this code.
- **Shipment Code(s)** — the codes of all shipments configured
  under this survey, comma-separated.
- **Status** — current state of the survey (see below).
- **Action** — context-aware buttons based on status (see below).

## Survey statuses

A survey moves through three states:

- **Created** *(Not yet fully configured)* — the survey exists but
  has no shipments under it yet, or its shipments don't have
  participants mapped. Participants can't see anything until the
  survey is shipped.
- **Configured** *(Ready to ship)* — shipments are set up and
  participants are mapped. The **Ship Now** button becomes
  available in the Action column.
- **Shipped** *(Already Shipped)* — you've clicked **Ship Now**.
  The survey and its shipments are now visible to participants and
  they can begin responding.

## Action column buttons

What you see in the Action column depends on the survey's status
and how far along configuration is:

- **Edit** — always present. Opens the survey for editing.
- **Add Shipment** — shown when the survey has *no shipments* yet.
  Jumps to the shipments page so you can add one.
- **Add Participants** — shown when shipments are configured but
  participants aren't fully mapped to them yet. Jumps to the
  shipments page so you can finish mapping participants.
- **Ship Now** — shown when shipments and participants are both
  ready (status *Configured* and the per-shipment readiness check
  passes). See "Shipping a survey" below.
- **Shipped** *(disabled)* — shown after shipping, as a
  non-clickable indicator that the survey is live.
- **Send Email to Participants** — yellow envelope button shown
  only on **shipped** surveys. Opens the email composer pre-scoped
  to the participants mapped to this survey, so you can send
  reminders, follow-ups, or instructions about this PT round
  specifically.

## Shipping a survey

Until you click **Ship Now**, a survey and the shipments under it
are **not visible to participants** — they are still in your draft
area. Clicking **Ship Now**:

1. Asks for confirmation (this action can't be undone).
2. Marks the survey and its shipments as live.
3. Triggers participant notifications so labs know they have new
   panels to respond to.

A typical workflow is therefore: **Add New PT Survey** → add
shipments under it on the next page → map participants → review
via **See Shipments** → **Ship Now** when everything looks
correct → optionally use **Send Email to Participants** later for
reminders.

## Tips

- The list is sorted by **PT Survey Date** with the newest surveys
  on top, so the next round you're working on is always at the top.
- If you only want to see surveys waiting to be shipped, use the
  **Status** column search and type *configured* — this shows only
  the *Ready to ship* rows so you can clear them off your queue.
- Editing a survey *after* shipping is restricted: the date is
  locked once shipments have gone out (see **Edit PT Survey** help
  for details).
