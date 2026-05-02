---
title: Participants
summary: Add, view, edit, and manage your PT participants
tags: [participants, labs, data-managers, mapping]
---

# Participants

The Participants page lists every lab, clinic, or individual enrolled in
your PT scheme. From here you can add new participants one at a time,
import many in a single bulk upload, edit existing records, and review
how participants are connected to their Data Managers.

## What's on the page

The page header has three primary actions on the right:

- **Add New Participant** — open the single-participant form. Use this
  when you only need to add one or two records.
- **Bulk Import Participants** — recommended for any sizable list.
  Upload an Excel file and the system creates / updates rows in one go.
- **Download as Excel** — exports the entire participant list (after
  any filters you've applied) as an `.xlsx`.

If there are participants in a *Pending* state, a yellow
**Show only Pending Participants** button appears with a count — click
it to filter the list to just those rows.

## The participants table

Each row shows the participant's ID, name, country, contact details,
affiliation, status, and the Data Managers mapped to them.

- **Search** boxes sit under each column header — type to narrow the
  list by ID, name, country, mobile, phone, affiliation, email, or
  status. Searches stack, so you can combine them.
- The **Action** column has Edit and Delete buttons for each row.

### Data Managers column — viewing the mapped DMs

Click **View** under the Data Managers column to expand a child row
showing every Data Manager mapped to that participant. From the
expanded view you can:

- See each DM's name, institute, primary email, type, and status.
- **Reset password** for a DM directly, without leaving the page.
- **Update primary email** for a DM directly, without leaving the page.

When more than five DMs are mapped, a small filter box appears above
the list so you can search within just that participant's DMs.

Rows where **no Data Manager is mapped** are highlighted in pale
yellow — but only when the participant is *active*, since unmapped
inactive participants aren't actionable.

## Mapping filter

Above the table is a **Mapping** dropdown:

- **All** — show every participant (default).
- **Mapped to a Data Manager** — only participants who have at least
  one DM mapped to them.
- **Not mapped to any Data Manager** — only participants with zero
  mapped DMs. These are the rows that show up highlighted in yellow,
  and the ones you most likely want to clean up: an active participant
  with no DM cannot receive shipments or respond to PT panels.

The filter applies on top of the column searches, so you can, for
example, look at *all unmapped active participants in Kenya* by
combining the country search with **Not mapped to any Data Manager**.

## Tips

- For more than a handful of new participants, prefer **Bulk Import**
  over adding them one by one — it's faster and gives you a single
  audit list of what was saved, skipped, or rejected.
- Use the Mapping filter periodically to catch participants who lost
  their DM (e.g., after a DM was deleted or deactivated).
- The Excel download respects column searches but ignores the Mapping
  filter, so adjust the column searches to scope the export.
