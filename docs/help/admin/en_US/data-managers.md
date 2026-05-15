---
title: Data Managers (Participant Logins) and PT Country Coordinators
summary: Manage Data Manager / PT Login accounts and PT Country Coordinators — add, edit, reset password, change email, and link to participants
tags: [data-manager, pt-login, ptcc, participant-login, password, mapping]
---

# Data Managers (Participant Logins) and PTCCs

This page lists the **PT Login** accounts that participants use
to log into the participant side of the app. The same page
handles two different roles, depending on the URL:

- **`/admin/data-managers`** — *Data Managers (Participant
  Logins)*. The standard PT Login. Each one is linked to one or
  more participants and only sees those when filling in
  responses.
- **`/admin/data-managers/index/ptcc/1`** — *PT Country
  Coordinators (PTCCs)*. A TB-specific role. A PTCC covers a
  region (country / state / district) rather than a participant
  list. The columns and action buttons are different — see
  *PTCC mode* below.

## Header buttons (Data Manager mode)

- **Add New Data Manager (Participant Login)** — opens the *Add*
  form for a regular PT Login.

## Header buttons (PTCC mode)

- **Add New PTCC** — opens the *Add* form set to PTCC type.
- **Bulk Import PTCC** — opens the bulk-import form for loading
  many PTCCs from a spreadsheet.
- **PTCC** *(green Download)* — exports the full PTCC list to
  Excel.
- **PTCC Mapped Participants** *(green Download)* — exports the
  PTCC ↔ Participant link to Excel.

## Filters

- **Status** *(default: Active)* — *Active* / *Inactive* / *All*.
  Defaults to Active so retired logins don't clutter the view.
- **Mapping** — *All* / *Mapped to a Participant* / *Not mapped
  to any Participant*. Useful for finding PT Logins that won't
  see any participants.
- **Per-column search** — small input under each column heading
  searches that column on its own.

Active rows that are **not linked to any participant** show a
yellow background — they are broken (the user can log in but
won't see anything to do).

## Columns

- **First Name** / **Last Name**
- **Institute** *(Data Manager mode only)*
- **Cell/Mobile**
- **Primary Email**
- **Status** — *Active* / *Inactive*.
- **Country / State / District** *(PTCC mode only)* — the
  region the PTCC covers.
- **Mapped Participants** — either *None mapped* (greyed out)
  or a **View (n)** button that expands a list of every
  participant linked to this PT Login. Click again to close.
- **Action** — see below.

## Action column

Each row has small buttons:

- **Edit** *(yellow)* — opens the edit form. Hidden when this
  page is shown as part of a participant edit (where the data
  manager is just shown for reference).
- **Reset Password** *(blue, key icon)* — opens a pop-up to
  set a new password for this PT Login.
- **Change Email** *(grey, envelope icon)* — opens a pop-up to
  change the **Primary Email** (which is also the login name).
- **Map Participants** *(green, user icon — Data Manager mode
  only)* — opens the Map-Participants two-list selector in a
  pop-up, already set to this PT Login. PTCCs don't have this
  button — they cover a region, not a participant list.

## Tips

- **Use the "Not mapped to any Participant" filter regularly.**
  An active PT Login with no linked participants is dead weight
  — they can log in, but their dashboard is empty. The yellow
  highlight flags them; the filter narrows the table to just
  those rows.
- **Reset Password vs. Change Email are two different forms.**
  If a user can't reach their old email, change the email
  *first* (so the new email becomes their username), then reset
  the password — the new credentials go to the new email.
- **PTCC mode** is only used by the **TB** scheme. If TB is not
  an active scheme, you can ignore the `/index/ptcc/1` URL.
- The **PTCC Mapped Participants** export is a quick audit of
  which participants fall under each PTCC — handy when you are
  reshuffling regions.
- Bulk import (PTCC) is the right tool for onboarding many
  coordinators at once. For one or two, **Add New PTCC** is
  faster.
