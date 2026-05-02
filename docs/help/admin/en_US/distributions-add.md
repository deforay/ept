---
title: Add PT Survey
summary: Create a new PT Survey on a specific date with a unique code
tags: [distributions, pt-survey, add, create]
---

# Add a PT Survey

A **PT Survey** is a single PT round on a specific date. Adding a
survey is step one — afterwards you'll add the **shipments** that
make it up. The survey only becomes visible to participants after
you click **Ship Now** on the surveys list.

## Mandatory fields

### PT Survey Date

Pick the date of this PT round from the date picker.

- The system allows **only one PT Survey per date** — dates that
  already have a survey are **disabled in the picker and marked
  with a red circle**, so you can't accidentally collide.
- If you need a survey on a date that's already taken, edit the
  existing survey instead, or pick a different date.

### PT Survey Code

A short, unique identifier for this survey (e.g. `DBS-2026-Q1`,
`VL-MAY-2026`).

- Must be **unique across all surveys** — the form checks for
  duplicates as soon as you leave the field, and you'll see an
  error if the code is already in use.
- Allowed characters: letters, digits, and hyphens. Other
  characters are stripped automatically as you type.
- If the system is configured to auto-generate codes (the
  *Auto-generate PT survey code* setting), the code is filled in
  for you as soon as you pick a date — you can still edit it
  before saving.

## Saving — two options

The form has two save buttons depending on what you want to do
next:

- **Add and continue to Shipment** *(primary)* — saves the survey
  and takes you straight to the shipments page so you can configure
  the panels right away. This is the usual flow.
- **Save and add shipment later** — saves the survey and returns
  you to the surveys list. The new survey will sit in the
  *Not yet fully configured* state until you go back and add
  shipments under it.

Use **Cancel** to discard and return to the surveys list.

## What happens after saving

- The survey is created with the date and code you chose.
- It does **not** become visible to participants yet — surveys are
  invisible until you click **Ship Now** on the surveys list, after
  the shipments under them are fully configured.
- You can edit the code or other details later from the surveys
  list, until shipments have actually gone out.

## Tips

- Pick the date *first*. If auto-generation is on, the code is
  derived from the date, so changing the date after typing a code
  may overwrite it.
- Keep codes short and meaningful — they appear in participant
  emails, dashboards, and on every shipment-level report.
