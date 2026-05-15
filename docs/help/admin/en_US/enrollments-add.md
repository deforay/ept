---
title: Enroll Participants
summary: Enroll or unenroll many participants in a scheme using a two-list selector with filters
tags: [enrollment, scheme, participants, dual-list, filters]
---

# Enroll Participants

Enrolls (or unenrolls) participants in a single scheme. You
chose the scheme on the previous page (*Enrollments → Select
Scheme Type → Enroll Participants*). This page works on that
scheme only.

## Step 1 — Optional advanced filters

- **Show advanced filters** *(button)* — opens filter
  dropdowns. Filters narrow the **Unenrolled Participants** pane
  on the left so you don't have to scroll through everyone.

Available filters (each is multi-select with type-ahead search):

- **Institute**
- **Country**
- **Region**
- **State**
- **District**
- **City**
- **Network**
- **Affiliation**
- **Site Type**
- **Enrolled Programs**

After picking filter values, click:

- **Filter** — applies the filters and reloads the dual list.
- **Reset** — clears filters and reloads the full list.

The right pane (*Selected Participants*) is **not** affected by
filters. Already-enrolled participants stay visible regardless of
the filter, so you don't accidentally unenroll someone just
because they fall outside the current filter.

## Step 2 — Optional enrollment list name

- **Enrollment List Name** — a free-text label for this batch.
  Useful for audit and reporting (for example, *"Q3 2026
  expansion — Western Province"*). Optional.

## Step 3 — Move participants between the two panes

- **Left pane — Unenrolled Participants** — participants who
  match the current filters and are *not yet enrolled* in this
  scheme.
- **Right pane — Selected Participants** — participants
  *currently enrolled* in this scheme, plus any new ones you
  move over.
- **Click a participant name** to move it between panes. Single
  click — no drag-and-drop.
- **Type in the search box** at the top of either pane to filter
  that pane.
- **Select All** *(left → right)* — moves every participant in
  the left pane to the right (enrolls them).
- **Deselect All** *(right → left)* — moves everyone in the
  right pane back to the left (unenrolls them).

The list stays fast even with very long lists.

## Step 4 — Save

- **Enroll Selected** — saves the right pane as the complete
  enrollment list for this scheme.
- **Cancel** — returns to the Enrollments list without saving.

> **Saving replaces the existing enrollment list for that
> scheme.** Whatever is in the right pane *replaces* the old
> list. Anyone *not* in the right pane is unenrolled. If you
> only want to add a few participants, check that existing
> enrollees are still in the right pane before saving.

## Tips

- **Apply filters narrowly first, then expand.** Filtering by
  Country + Institute, for example, makes the left pane
  manageable when you are enrolling a specific group. Filters
  work together — every active filter must match.
- **The right pane is your source of truth.** Anything not in
  the right pane at save time is unenrolled — including people
  you can't see because they were filtered out. (They stay in
  the right pane regardless of filters, just so this is safe.)
- For a one-off enrollment of a single participant, this page is
  overkill — you can enroll from the participant's edit page
  instead.
- For onboarding many participants at once, **Bulk Import
  Enrollment** (Excel upload) is faster than this two-list
  picker.
