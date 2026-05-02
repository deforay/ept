---
title: Enroll Participants
summary: Enroll or unenroll many participants in a scheme using a filtered dual-list selector
tags: [enrollment, scheme, participants, dual-list, filters]
---

# Enroll Participants

Enrolls (or unenrolls) participants in a single scheme. The
scheme is picked on the previous page (*Enrollments → Select
Scheme Type → Enroll Participants*) — this page operates on
that scheme only.

## Step 1 — Optional Advanced Filters

- **Show advanced filters** *(button)* — reveals filter
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
filters — already-enrolled participants stay visible regardless
of the filter set, so you don't accidentally unenroll someone
just because they're outside the current filter scope.

## Step 2 — Optional Enrollment List Name

- **Enrollment List Name** — free-text label for this enrollment
  batch. Useful for audit / reporting (e.g. *"Q3 2026 expansion
  — Western Province"*). Optional.

## Step 3 — Move Participants Between the Two Panes

- **Left pane — Unenrolled Participants** — participants who
  match the current filters and are *not yet enrolled* in this
  scheme.
- **Right pane — Selected Participants** — participants
  *currently enrolled* in this scheme (plus any new ones you
  move over).
- **Click a participant name** to move it between panes. Single
  click — no drag-and-drop.
- **Type in the search box** at the top of either pane to filter
  that pane in place.
- **Select All** *(left → right)* — moves every participant in
  the left pane to the right (enrolls them).
- **Deselect All** *(right → left)* — moves everyone in the
  right pane back to the left (unenrolls them).

The list uses a virtualized renderer — large lists stay
responsive.

## Step 4 — Save

- **Enroll Selected** — replaces this scheme's enrollment list
  with whatever's in the right pane.
- **Cancel** — returns to the Enrollments index without saving.

> **The save is destructive for that scheme.** Whatever is in
> the right pane *replaces* the existing enrollment list for the
> scheme. Anyone *not* in the right pane is unenrolled. If you
> only want to add a few participants, double-check that
> existing enrollees are still in the right pane before saving.

## Tips

- **Apply filters narrowly first, then expand.** Filtering by
  Country + Institute, for example, makes the left pane
  manageable when you're enrolling a specific cohort. Combining
  filters is *AND* — every active filter must match.
- **The right pane is your source of truth.** Anything not in
  the right pane at save time is unenrolled — including people
  you can't see because they're filtered out. They stay in the
  right pane regardless of filters precisely so this works
  safely.
- For a one-off enrollment of a single participant, this page is
  overkill — you can also enroll from the participant's edit
  page directly.
- For onboarding many participants at once, **Bulk Import
  Enrollment** (Excel upload) is faster than the dual list.
