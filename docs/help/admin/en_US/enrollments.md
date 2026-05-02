---
title: Enrollments
summary: View and manage which participants are enrolled in each scheme; bulk-import enrollments from a spreadsheet
tags: [enrollment, scheme, participants, bulk-import]
---

# Enrollments

Lists every **participant ↔ scheme enrollment** in the system. A
participant must be enrolled in a scheme before they appear on
shipments for that scheme — so this is where you decide which
schemes (DTS, VL, TB, etc.) each participant takes part in.

## Page Header

- **Bulk Import Enrollment** — opens the bulk-import form for
  loading enrollments from an Excel spreadsheet.

## Top Stats

A one-line table at the top shows the **count of active
participants enrolled in each scheme** (e.g. *DTS = 142, VL =
108, TB = 64*). A quick at-a-glance health check.

## Scheme Filter

- **Select Scheme Type** *(dropdown)* — picks which scheme's
  enrollment list you want to see. Selecting a scheme:
  - Filters the table to participants enrolled in that scheme.
  - Reveals the **Enroll Participants** button (see below).

## Enroll Action

When a scheme is picked, two buttons appear above the table:

- **Enroll Participants for {Scheme}** — opens the dual-list
  selector at *`/admin/enrollments/add/scheme/{schemeId}`*. Use
  it to enroll / unenroll many participants at once for the
  chosen scheme.
- **Reset** — reloads the page without a scheme filter.

## Columns

- **Unique Participant ID** — the participant's unique
  identifier.
- **Lab Name / Participant Name**
- **Country**
- **Scheme** — the scheme this row's enrollment is for. With no
  scheme filter selected, a participant enrolled in three
  schemes appears as three rows.
- **Enrolled On** — date of enrollment. Default sort is most
  recent first.
- **Action** — per-row button(s).

## Tips

- **Use the scheme filter before enrolling.** The *Enroll
  Participants* button is only visible after a scheme is chosen
  — it always operates on the selected scheme. If you don't see
  it, pick a scheme first.
- The **Enrolled On** date is the date the enrollment was added,
  not the date the participant joined the program. Renaming /
  re-enrolling participants creates a new row.
- **Bulk Import Enrollment** is much faster than the dual-list
  selector for >50 participants — it works against existing
  participant Unique IDs, so make sure participants are added
  first (via *Manage → Participants*).
- The **per-scheme stats line** at the top is a fast sanity
  check after a bulk import — make sure the count went up by
  what you expected.
