---
title: Map Participants to Data Managers
summary: Assign participants to a Data Manager (Participant Login) so the right person sees the right participants when filling out responses
tags: [participants, data-manager, mapping, pt-login, bulk-import]
---

# Map Participants to Data Managers

This page maps **participants** to a **Data Manager (Participant
Login)**. A Data Manager logs into the participant side of the
app and only sees the participants they're mapped to — so this
mapping decides who fills out responses for which participants.

## Page Header Buttons

*(Hidden when this page is opened in a modal — e.g. from the
participant edit page — because the Data Manager is already
chosen.)*

- **Bulk Import Participants Mapping** — toggles the file-upload
  block where you can mass-load mappings from a CSV (see *Bulk
  Import* below).
- **Export Participant Not Mapped to Data Managers** — generates
  a downloadable file listing every participant who is **not yet
  mapped to any Data Manager**. Useful as a punch list before a
  shipment.

## Step 1: Choose a Data Manager (PT Login)

- The **Choose a Data Manager (Participant Login)** dropdown
  searches all PT Logins as you type (minimum 1 character). It
  returns the data manager's name, institute, and email.
- When this page is opened from a participant's record (modal
  mode), the Data Manager is already selected and shown in an
  info banner at the top — you skip straight to participant
  selection.
- **Show advanced filters** *(button)* — opens optional filters
  to narrow down which participants appear in the dual list:
  - **Country**
  - **Province**
  - **District**
  - **Institute**
  - **Network Tier**
  - **Affiliation**

  Filters are *AND* — every active filter must match. They drive
  the **left pane** (the *Unselected* list) only — already-mapped
  participants stay in the right pane regardless of filter.

## Step 2: Move Participants Between the Two Panes

Once a Data Manager is chosen, the dual-list selector loads:

- **Left pane — Unselected PT Logins** — participants *not yet
  mapped* to this Data Manager.
- **Right pane — Selected PT Logins** — participants *currently
  mapped*.
- **Click a name** to move it between panes (single click —
  there's no drag-and-drop).
- **Type in the search box** at the top of either pane to filter
  that pane in place.
- **Select All** *(left → right)* — moves every unselected
  participant to the mapped pane.
- **Deselect All** *(right → left)* — unmaps everyone.

The footers show live counts of each pane.

> The list uses a virtualized renderer — it stays fast even with
> tens of thousands of participants. Filter or scroll freely.

## Step 3: Save

- **Save Selected** — persists the right-pane list as the
  complete mapping for the chosen Data Manager. Anyone *not* in
  the right pane is removed from the mapping.
- **Cancel** — returns to a fresh page (or closes the modal).

> **The save is destructive for that Data Manager.** Whatever is
> in the right pane *replaces* the existing mapping. If you only
> want to add a few names, make sure existing mapped participants
> are still in the right pane before saving.

## Bulk Import

Click **Bulk Import Participants Mapping** to switch to the
upload form:

- **Upload Bulk Importing Map File** — pick a CSV / spreadsheet
  with participant ↔ Data Manager pairs. The file format is
  fixed; download the *not-mapped* export first to see the
  expected columns and unique IDs.
- **Upload** — processes the file and applies all mappings in
  one pass.

Bulk import is the right tool for onboarding a new country or
loading a fresh batch of participants — it's much faster than
clicking through hundreds of names per Data Manager.

## Tips

- **Always pick the Data Manager first** — the participant list
  doesn't load until a PT Login is selected.
- **Use the export to find gaps.** *Export Participant Not Mapped
  to Data Managers* is the quickest way to find participants who
  will be invisible to every PT Login (and so won't show up on
  any response form).
- **Use advanced filters to scope a country / institute mapping.**
  Filtering by Country + Institute, for example, reduces the
  left pane to a manageable set when you're doing data-entry
  cleanup.
- Saving with an **empty right pane** removes *all* participants
  from that Data Manager — they'll see nothing on login. That's
  the right action when you're un-assigning a manager, but
  surprising otherwise.
- A participant can be mapped to **multiple Data Managers** —
  just add them to each manager's right pane. They'll then appear
  on all those PT Logins.
