---
title: Data Managers (Participant Logins) and PT Country Coordinators
summary: Manage Data Manager / PT Login accounts and PT Country Coordinators (PTCCs) — add, edit, reset password, change email, and map to participants
tags: [data-manager, pt-login, ptcc, participant-login, password, mapping]
---

# Data Managers (Participant Logins) and PTCCs

This page lists the **PT Login** accounts that participants use
to log into the participant side of the app. The same page
serves two distinct roles, switched by the URL:

- **`/admin/data-managers`** — *Data Managers (Participant
  Logins)*. The standard PT Login. Each one is mapped to one or
  more participants and only sees those participants when filling
  out responses.
- **`/admin/data-managers/index/ptcc/1`** — *PT Country
  Coordinators (PTCCs)*. A TB-specific role. PTCCs oversee a
  geographic scope (country / state / district) instead of a
  participant list. The page columns and action buttons are
  different — see *PTCC mode* below.

## Header Buttons (Data Manager mode)

- **Add New Data Manager (Participant Login)** — opens the *Add*
  form for a regular PT Login.

## Header Buttons (PTCC mode)

- **Add New PTCC** — opens the *Add* form pre-set to PTCC type.
- **Bulk Import PTCC** — opens the bulk-import form for loading
  many PTCCs from a spreadsheet.
- **PTCC** *(green Download)* — exports the full PTCC list to
  Excel.
- **PTCC Mapped Participants** *(green Download)* — exports the
  PTCC ↔ Participant mapping to Excel.

## Filters

- **Status** *(default: Active)* — *Active* / *Inactive* / *All*.
  Defaults to Active so retired logins don't clutter the view.
- **Mapping** — *All* / *Mapped to a Participant* / *Not mapped
  to any Participant*. Useful for finding stranded PT Logins that
  won't see any participants.
- **Per-column search** — small input under each column header
  searches that column independently.

Active rows that are **not mapped to any participant** are
highlighted with a yellow background — they're broken (the user
can log in but won't see anything to do).

## Columns

- **First Name** / **Last Name**
- **Institute** *(Data Manager mode only)*
- **Cell/Mobile**
- **Primary Email**
- **Status** — *Active* / *Inactive*.
- **Country / State / District** *(PTCC mode only)* — the
  geographic scope the PTCC oversees.
- **Mapped Participants** — either *None mapped* (greyed out) or
  a **View (n)** button that expands an in-row list of every
  participant mapped to this PT Login. Click again to collapse.
- **Action** — see below.

## Action Column

Each row has a row of small buttons:

- **Edit** *(yellow)* — opens the edit form. Hidden when the
  page is rendered as part of a participant edit (where the data
  manager is just being shown for reference).
- **Reset Password** *(blue, key icon)* — opens a modal to set a
  new password for this PT Login.
- **Change Email** *(grey, envelope icon)* — opens a modal to
  change the **Primary Email** (which is also the username).
- **Map Participants** *(green, user icon — Data Manager mode
  only)* — opens the Map-Participants dual list in a modal,
  pre-selected to this PT Login. Goes straight to the
  participant-pane selector. PTCCs don't have this button — they
  cover a region rather than a participant list.

## Tips

- **Use the "Not mapped to any Participant" filter regularly.**
  An active PT Login with no mapped participants is dead weight
  — they can log in, but their dashboard is empty. The yellow
  highlight on the index page surfaces them; the filter narrows
  the table to just those rows.
- **Reset Password vs. Change Email are different forms.** If a
  user can't access their old email, change the email *first*
  (so the new email becomes their username), then reset the
  password — the new credentials go to the new email.
- **PTCC mode** is only relevant to the **TB** scheme. If TB
  isn't an active scheme, you'll never need PTCCs and can ignore
  the `/index/ptcc/1` URL.
- The **PTCC Mapped Participants** export is a quick audit of
  which participants fall under each PTCC — useful when reshuffling
  geographic coverage.
- Bulk import (PTCC) is the right tool when onboarding many
  coordinators at once. For one or two, **Add New PTCC** is
  faster.
