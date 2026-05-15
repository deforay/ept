---
title: Map Participants to Data Managers
summary: Assign participants to a Data Manager so the right person sees the right participants when filling out responses
tags: [participants, data-manager, mapping, pt-login, bulk-import]
---

# Map Participants to Data Managers

This page links **participants** to a **Data Manager (Participant
Login)**. A Data Manager logs into the participant side of the app
and only sees the participants they are linked to. So this page
decides who fills out responses for which participants.

## Buttons at the top

*(Hidden when this page opens in a pop-up — for example, from the
participant edit page — because the Data Manager is already
chosen.)*

- **Bulk Import Participants Mapping** — opens the file-upload
  area where you can load many links from a CSV (see *Bulk
  Import* below).
- **Export Participant Not Mapped to Data Managers** — downloads
  a file listing every participant who is **not yet linked to any
  Data Manager**. Handy as a check-list before a shipment.

## Step 1: Choose a Data Manager

- The **Choose a Data Manager (Participant Login)** dropdown
  searches all PT Logins as you type (at least 1 character). It
  shows the name, institute and email.
- When the page opens from a participant's record (as a pop-up),
  the Data Manager is already picked and shown in a banner at
  the top. You go straight to picking participants.
- **Show advanced filters** *(button)* — opens optional filters
  to narrow the list of participants:
  - **Country**
  - **Province**
  - **District**
  - **Institute**
  - **Network Tier**
  - **Affiliation**

  Filters work together — every active filter must match. They
  only affect the **left pane** (the *Unselected* list).
  Already-linked participants stay in the right pane no matter
  what.

## Step 2: Move participants between the two panes

Once you pick a Data Manager, the two-list selector loads:

- **Left pane — Unselected PT Logins** — participants *not yet
  linked* to this Data Manager.
- **Right pane — Selected PT Logins** — participants *currently
  linked*.
- **Click a name** to move it between panes (single click — no
  drag-and-drop).
- **Type in the search box** at the top of either pane to filter
  that pane.
- **Select All** *(left → right)* — links every unlinked
  participant.
- **Deselect All** *(right → left)* — unlinks everyone.

The footers show live counts.

> The list stays fast even with tens of thousands of
> participants. Scroll and filter freely.

## Step 3: Save

- **Save Selected** — saves the right pane as the complete list
  for the chosen Data Manager. Anyone *not* in the right pane is
  removed from the link.
- **Cancel** — returns to a fresh page (or closes the pop-up).

> **Saving replaces the existing list for that Data Manager.**
> Whatever is in the right pane *replaces* the old link. If you
> only want to add a few names, make sure the existing linked
> participants are still in the right pane before saving.

## Bulk Import

Click **Bulk Import Participants Mapping** to switch to the
upload form:

- **Upload Bulk Importing Map File** — pick a CSV / spreadsheet
  with participant ↔ Data Manager pairs. The file format is
  fixed. Download the *not-mapped* export first to see the
  columns.
- **Upload** — processes the file and applies all links in one
  go.

Bulk import is the right tool when you are onboarding a new
country or loading a fresh batch of participants. Much faster
than clicking through hundreds of names.

## Tips

- **Always pick the Data Manager first.** The participant list
  does not load until you do.
- **Use the export to find gaps.** *Export Participant Not
  Mapped to Data Managers* is the quickest way to find
  participants nobody can see.
- **Use advanced filters for a country / institute job.**
  Filtering by Country + Institute makes the left pane
  manageable for clean-up work.
- Saving with an **empty right pane** removes *all* participants
  from that Data Manager — they will see nothing on login. That
  is correct when you are un-assigning someone, but a surprise
  otherwise.
- A participant can be linked to **more than one Data Manager**
  — just add them to each manager's right pane.
