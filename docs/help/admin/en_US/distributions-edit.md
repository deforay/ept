---
title: Edit PT Survey
summary: Update an existing PT Survey's code or date before it has been shipped
tags: [distributions, pt-survey, edit, update]
---

# Edit a PT Survey

Use this form to change a survey's **PT Survey Code** or **PT
Survey Date** after it has been created. The fields are the same
as in *Add PT Survey* — see that page for what each field
means.

## What's different from Add

- **PT Survey Code** is editable. The duplicate check ignores
  the *current* survey, so you can fix a typo without the form
  rejecting it.
- **PT Survey Date** is editable too, but only to dates that
  don't already have another survey. The current survey's own
  date is *not* in the disabled list, so you can keep it as it
  is or move it freely to any free date.
- The title at the top reads **Edit PT Survey** so it is clear
  you are working on an existing record.

## Saving — two options

- **Update** *(primary)* — saves your changes and returns to
  the surveys list.
- **Add and continue to Shipment** — saves your changes and
  jumps to the shipments page. Useful when the survey is still
  in the *Not yet fully set up* state and needs shipments added
  or adjusted.

Use **Cancel** to drop the changes and return to the surveys
list.

## Editing after shipping

Once a survey has been shipped (status **Already shipped**), it
is already visible to participants. The system protects shipped
surveys from changes that would confuse people:

- The date picker is locked to the current survey date.
- The PT Survey Code is still editable, but think twice before
  changing it — participants will have already seen the old
  code in their notifications and dashboards.

If you need to make bigger changes to a shipped survey, adjust
the individual *shipments* under it from the shipments page
rather than editing the survey itself.

## Tips

- Editing the date quietly re-orders the surveys list (it is
  sorted by date). A survey you renamed may appear in a
  different position when you return.
- The duplicate-code check runs as soon as you leave the field,
  so you will see the error before you submit.
