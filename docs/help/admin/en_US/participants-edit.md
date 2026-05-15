---
title: Edit Participant
summary: Update an existing participant's details, mapped Data Managers, and enrolled schemes
tags: [participants, edit, update, data-managers, schemes]
---

# Edit a Participant

Use this form to update an existing participant. The fields are
the same as the **Add Participant** form — see that page for
what each field means and which are required. A few things work
slightly differently when you are editing.

## What's different from Add

- **Participant ID** is editable, but the duplicate check
  ignores the *current* participant. You can fix a typo
  without the form rejecting it.
- The **Individual Participant** checkbox is pre-set to match
  how the participant was first created. Switching it changes
  between **First Name / Last Name** (individual) and **Lab
  Name / Contact Person Name** (lab / clinic). Be careful — if
  you switch this, check the name fields still hold the right
  values.
- The title at the top shows the current **Status** (Active /
  Inactive / Pending) so you can see the state at a glance.

## Linking to Data Managers

The two-list picker is pre-filled with the participant's
current Data Managers on the right. From here you can:

- Add a new DM by clicking it on the left.
- Remove an existing link by clicking it on the right.
- Create a brand-new DM via **Create New Data Manager** if the
  one you need does not exist yet.

Removing every DM from the right pane will leave the participant
unlinked. This is flagged on the participants list with a
yellow highlight (active participants only). Don't do this
unless the participant is being deactivated.

## Schemes

The edit form also shows the **schemes** this participant is
enrolled in. Use this section to enrol or unenrol the
participant from PT schemes — for example, adding them to a new
viral load panel or removing them from a retired one.

## Password reset

When direct participant login is turned on, you can set a new
password for this participant's login from this form. Leaving
the password fields blank keeps the old password unchanged.

## Saving

Click the submit button at the bottom to save. You will go back
to the participants list. If you change the Participant ID to
one that already belongs to another participant, the duplicate
check will flag it and stop the save.
