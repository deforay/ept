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
- **PT Survey Date** is editable too. A red circle marks dates
  that have another survey. You can still pick one, and the
  page warns you about the clash.
- The title at the top reads **Edit PT Survey** so it is clear
  you are working on an existing record.

## Saving — two options

- **Update** *(primary)* — saves your changes and returns to
  the surveys list.
- **Add and continue to Shipment** — saves your changes and
  jumps to the shipments page. Useful when the survey is still
  in the **Created** state and needs shipments added
  or adjusted.

Use **Cancel** to drop the changes and return to the surveys
list.

## Editing after shipping

Once a survey has been shipped (status **Already shipped**), it
is already visible to participants. The system protects shipped
surveys from changes that would confuse people:

- The date stays editable. Think twice before changing it,
  because participants have already seen it.
- The PT Survey Code stays editable until one of the survey's
  shipments is finalized. After that it is locked, because
  finalized reports print it. Participants have also seen the
  old code in their emails and dashboards.

If you need to make bigger changes to a shipped survey, adjust
the individual *shipments* under it from the shipments page
rather than editing the survey itself.

## Tips

- Editing the date quietly re-orders the surveys list (it is
  sorted by date). A survey you renamed may appear in a
  different position when you return.
- The duplicate-code check runs as soon as you leave the field,
  so you will see the error before you submit.
