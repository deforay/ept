---
title: Data Managers (Participant Logins)
summary: Manage Data Manager / PT Login accounts — add, edit, reset password, change email, and link to participants
tags: [data-manager, pt-login, participant-login, password, mapping]
---

# Data Managers (Participant Logins)

This page lists the **PT Login** accounts that participants use
to log in and enter results. Each one is linked to one or more
participants and sees only those.

## Buttons at the top

- **Add New Data Manager (Participant Login)**: opens the form to
  add one PT Login.
- **Reset by Pasted List**: opens a pop-up where you paste emails
  or Lab IDs. Each Lab ID finds every PT Login linked to that
  participant. Each email finds one PT Login. You then queue a
  password reset, and you can email each user their new login
  details.
- **Unusable Emails**: downloads an Excel list of accounts whose
  email is missing, has bounced, or cannot receive mail.

## Filters

- **Status** *(default: Active)*: *Active*, *Inactive* or *All*.
  Active is the default, so retired logins stay out of the way.
- **Mapping**: *All*, *Mapped to a Participant* or *Not mapped to
  any Participant*. It appears only when some PT Logins are not
  linked to any participant.
- **Search boxes under each column heading**: each box searches
  that column only.

Active rows that are **not linked to any participant** have a
yellow background. These users can log in but see nothing to do.

## Columns

- **Name**
- **Contact**: email and phone. A small tag marks an email that
  cannot receive mail: *Bounced*, *Undeliverable* or *Login
  only*.
- **Mapped Participants**: how many participants this PT Login
  sees. Click it to list them.
- **Action**: see below.

## Action column

Each row has these buttons:

- **Edit**: opens the edit form.
- **Map**: opens a pop-up to choose which participants this PT
  Login sees.
- **Password**: opens a pop-up to set a new password for this
  user.
- **Email**: opens a pop-up to change the **Primary Email**. The
  Primary Email is also the login name.
- **View as**: opens the participant side as this user, in a new
  tab. It shows only for active users, and only to admins who
  have this permission. Every use is recorded.

## Reset many passwords at once

1. Tick the box at the start of each row you want. The box in
   the heading ticks every row on the current page.
2. Click **Queue Reset & Email**.

The resets run in the background. Each user gets an email with
their new login details. While rows are ticked, **Reset by Pasted
List** is hidden.

## Tips

- **Check the "Not mapped to any Participant" filter now and
  then.** An active PT Login with no participants can log in, but
  their dashboard is empty.
- **Password and Email are two different pop-ups.** If a user
  can't reach their old email, change the email *first*. The new
  email becomes their login name. Then reset the password, so the
  new details go to the new email.
