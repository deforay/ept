---
title: Add PT Survey
summary: Create a new PT Survey on a specific date with a unique code
tags: [distributions, pt-survey, add, create]
---

# Add a PT Survey

A **PT Survey** is a single PT round on a chosen date. Adding a
survey is step one — afterwards you add the **shipments** that
make it up. The survey only becomes visible to participants
after you click **Ship Now** on the surveys list.

## Mandatory fields

### PT Survey Date

Pick the date of this PT round from the date picker.

- A **red circle** marks a date that already has a PT Survey.
  You can still pick it. The page then warns you about the
  other survey.
- If the existing survey is the same round, add your shipment
  to it instead of creating a second survey.

### PT Survey Code

A short, unique identifier for this survey (e.g. `DTS-2026-Q1`,
`VL-MAY-2026`).

- Must be **unique across all surveys**. The form checks for
  duplicates as soon as you leave the field, and you will see
  an error if the code is already in use.
- Allowed characters: letters, digits and hyphens. Other
  characters are removed as you type.
- If the system is set up to fill in codes for you (the
  *Auto-generate PT survey code* setting), the code is filled
  in as soon as you pick a date. You can still edit it before
  saving.

## Saving — two options

The form has two save buttons depending on what you want to do
next:

- **Add and continue to Shipment** *(primary)* — saves the
  survey and goes straight to the shipments page so you can
  set up the panels right away. This is the usual flow.
- **Save and add shipment later** — saves the survey and
  returns you to the surveys list. The new survey will sit in
  the **Created** state until you go back and add
  shipments under it.

Use **Cancel** to drop the changes and return to the surveys
list.

## What happens after saving

- The survey is created with the date and code you chose.
- It does **not** become visible to participants yet. Surveys
  are hidden until you click **Ship Now** on the surveys list,
  after the shipments under them are fully set up.
- You can edit the date and code later from the surveys list.
  The code locks once one of the survey's shipments is
  finalized.

## Tips

- Pick the date *first*. If the system fills in codes for you,
  the code is built from the date. Changing the date after
  typing a code may replace what you typed.
- Keep codes short and meaningful — they appear in participant
  emails, dashboards, and on every shipment-level report.
