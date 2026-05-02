---
title: Edit Participant
summary: Update an existing participant's details, mapped Data Managers, and enrolled schemes
tags: [participants, edit, update, data-managers, schemes]
---

# Edit a Participant

This form is used to update an existing participant. The fields are
the same as the **Add Participant** form — see that page for what
each field means and which are mandatory — but a few things behave
slightly differently when you're editing an existing record.

## What's different from Add

- **Participant ID** is editable, but the duplicate check excludes
  the *current* participant. You can fix a typo without the form
  rejecting it as a self-collision.
- The **Individual Participant** checkbox is pre-set to match how the
  participant was originally created. Toggling it switches between
  **First Name / Last Name** (individual) and **Lab Name / Contact
  Person Name** (lab/clinic). Be careful — if you toggle this, make
  sure the name fields still hold the right values for the new shape.
- The legend at the top shows the current **Status** (Active /
  Inactive / Pending) so you know the state at a glance.

## Mapping to Data Managers

The dual-list picker is pre-populated with the participant's current
mappings on the right. From here you can:

- Add a new DM by clicking it on the left.
- Remove an existing mapping by clicking it on the right.
- Create a brand-new DM via **Create New Data Manager** if the one
  you need doesn't exist yet.

Removing every DM from the right pane will leave the participant
unmapped, which is flagged on the participants list with a yellow
highlight (active participants only). Avoid this unless the
participant is being deactivated.

## Schemes

The edit form additionally shows the **schemes** this participant is
enrolled in. Use this section to enrol or unenrol the participant
from PT schemes — for example, adding them to a new viral load panel
or removing them from a discontinued one.

## Password reset

When direct participant login is enabled, you can set a new password
for this participant's login from this form. Leaving the password
fields blank keeps the existing password unchanged.

## Saving

Click the submit button at the bottom to save. You'll be returned to
the participants list. If you change the Participant ID to one that
already belongs to another participant, the duplicate check will
flag it and prevent the save.
