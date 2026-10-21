---
title: PT Country Coordinators (PTCC)
summary: Manage PT Country Coordinators — add, bulk import, reset passwords, export, and see the participants in each region
tags: [ptcc, coordinator, tb, password, export]
---

# PT Country Coordinators (PTCC)

This page lists the **PT Country Coordinators**. The TB scheme
uses this role. A PTCC covers a region (country, state or
district). They see the participants in that region.

## Buttons at the top

- **Add New PTCC**: opens the form to add one PTCC.
- **Bulk Import PTCC**: loads many PTCCs from a spreadsheet.
- **Reset Password** menu:
  - **Reset by Pasted List**: opens a pop-up where you paste
    emails or Lab IDs. Only PTCC accounts match. You then queue a
    password reset, and you can email each PTCC their new login
    details.
  - **Active PTCC + Passwords**: sets a new password for
    **every active PTCC** and downloads the list with the new
    passwords. Their old passwords stop working at once. You
    must type a confirmation before it runs. This cannot be
    undone.
- **Export** menu:
  - **PTCC**: downloads the full PTCC list to Excel.
  - **PTCC Mapped Participants**: downloads each PTCC with the
    participants in their region.
  - **Unusable Emails**: downloads an Excel list of accounts
    whose email is missing, has bounced, or cannot receive mail.

## Filters

- **Status** *(default: Active)*: *Active*, *Inactive* or *All*.
  Active is the default, so retired coordinators stay out of the
  way.
- **Search boxes under each column heading**: each box searches
  that column only.

## Columns

- **First Name**, **Last Name**, **Cell/Mobile**, **Primary
  Email**, **Status**
- **Country**, **State**, **District**: the region the PTCC
  covers.
- **Mapped Participants**: a **View (n)** button. Click it to
  list the participants in this PTCC's region. Click again to
  close.
- **Action**: see below.

## Action column

Each row has these buttons:

- **Edit**: opens the edit form. Change the region here.
- **Password**: opens a pop-up to set a new password for this
  PTCC.
- **Email**: opens a pop-up to change the **Primary Email**. The
  Primary Email is also the login name.
- **View as**: opens the participant side as this PTCC, in a new
  tab. It shows only for active PTCCs, and only to admins who
  have this permission. Every use is recorded.

## Reset many passwords at once

1. Tick the box at the start of each row you want. The box in
   the heading ticks every row on the current page.
2. Click **Queue Reset & Email**.

The resets run in the background. Each PTCC gets an email with
their new login details. While rows are ticked, the **Reset
Password** menu hides **Reset by Pasted List**.

## Tips

- **Password and Email are two different pop-ups.** If a PTCC
  can't reach their old email, change the email *first*. The new
  email becomes their login name. Then reset the password, so the
  new details go to the new email.
- **PTCC Mapped Participants** is a quick way to check which
  participants fall under each PTCC. It is useful when you move
  regions around.
- For many new coordinators, use **Bulk Import PTCC**. For one or
  two, **Add New PTCC** is faster.
