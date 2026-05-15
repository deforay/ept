---
title: Participants
summary: Add, view, edit, and manage your PT participants
tags: [participants, labs, data-managers, mapping]
---

# Participants

The Participants page lists every lab, clinic, or individual
enrolled in your PT scheme. From here you can add new
participants one at a time, import many in one upload, edit
existing records, and see how participants are linked to their
Data Managers.

## What's on the page

The page header has three main actions on the right:

- **Add New Participant** — opens the single-participant form.
  Use this when you only need to add one or two records.
- **Bulk Import Participants** — recommended for any sizable
  list. Upload an Excel file and the system adds or updates
  rows in one go.
- **Download as Excel** — exports the whole list (after any
  filters you have applied) as an `.xlsx`.

If there are participants in a *Pending* state, a yellow **Show
only Pending Participants** button appears with a count. Click
it to filter the list to just those rows.

## The participants table

Each row shows the participant's ID, name, country, contact
details, affiliation, status, and the Data Managers linked to
them.

- **Search** boxes sit under each column heading. Type to narrow
  the list by ID, name, country, mobile, phone, affiliation,
  email, or status. Searches stack, so you can combine them.
- The **Action** column has Edit and Delete buttons for each
  row.

### Data Managers column — viewing the linked DMs

Click **View** under the Data Managers column to open a row
showing every Data Manager linked to that participant. From
there you can:

- See each DM's name, institute, primary email, type, and
  status.
- **Reset password** for a DM, without leaving the page.
- **Update primary email** for a DM, without leaving the page.

When more than five DMs are linked, a small filter box appears
above the list so you can search within just that participant's
DMs.

Rows where **no Data Manager is linked** are highlighted in pale
yellow — but only when the participant is *active*. Unlinked
inactive participants are not actionable.

## Mapping filter

Above the table is a **Mapping** dropdown:

- **All** — show every participant (default).
- **Mapped to a Data Manager** — only participants with at least
  one DM linked.
- **Not mapped to any Data Manager** — only participants with no
  DM. These are the yellow-highlighted rows and the ones you
  most likely want to clean up — an active participant with no
  DM cannot receive shipments or respond to PT panels.

The filter combines with the column searches. So, for example,
you can see *all unlinked active participants in Kenya* by
combining the country search with **Not mapped to any Data
Manager**.

## Tips

- For more than a handful of new participants, use **Bulk
  Import** instead of adding them one by one — it is faster and
  gives you a single list of what was saved, skipped, or
  rejected.
- Use the Mapping filter from time to time to catch participants
  who have lost their DM (for example, after a DM was deleted or
  deactivated).
- The Excel download follows the column searches but ignores
  the Mapping filter. Adjust the column searches to limit the
  export.
