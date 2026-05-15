---
title: Add Participant
summary: Create a new participant record and map them to one or more Data Managers
tags: [participants, add, create, data-managers]
---

# Add a Participant

Use this form to add a single new participant — a lab, a clinic,
or an individual. For more than a few at a time, use **Bulk
Import** instead.

## Required fields

Fields marked with a red `*` must be filled before the form can
save. The required fields are:

- **Participant ID** — your internal unique ID. The form checks
  for duplicates as soon as you leave the field. If the ID is
  already used, you will see an error and won't be able to
  submit.
- **First Name** *(or Lab Name — see below)*
- **Institute Name**
- **Department Name**
- **E-mail**
- **Country**
- **State/Province**
- **District/County**
- **Network Tier**
- **Affiliation**
- **Status** (defaults to *Active*)

## Individual vs. Lab / Clinic

The **Individual Participant** checkbox at the top of the form
changes how the name fields work:

- **Unchecked (default)** — treat as a lab or clinic. The first
  field is labelled **Lab Name** and there is a separate
  **Contact Person Name** field. The Last Name field is hidden.
- **Checked** — treat as an individual. You get **First Name**
  and **Last Name** fields. The Contact Person field is hidden.

Pick this *before* filling the name fields so the form shows the
right labels.

## Address fields

Fill in the **Physical Address** for the participant. The
**Shipping Address** below it is used when sending out panels.
Only fill it in if it differs from the physical address. Tick
**Same as physical address** to copy the physical address into
the shipping address and keep them in sync as you edit.

## Contact info

You can record both a **Cell/Mobile No.** and a **Phone Number**.
The **Additional/Alternate E-mail** field accepts more than one
address, separated by commas, for participants who want
notifications copied to several inboxes.

## Linking to Data Managers

Every participant **must be linked to at least one Data
Manager**. The Data Manager is the person who logs in and submits
PT responses on the participant's behalf. The link section is a
side-by-side picker:

- **Available Data Managers** on the left — every DM in the
  system.
- **Selected Data Managers** on the right — the ones linked to
  this participant.

Click any name in the left pane to move it to the right. Click a
name in the right pane to move it back. Use **Select All** or
**Deselect All** to move every (currently filtered) DM at once.
Each pane has a search box for long lists.

If the DM you need doesn't exist yet, click **Create New Data
Manager** above the picker to create one without leaving this
form.

When direct participant login is turned on (a system-wide
setting), linking a DM is optional and the form also asks for a
password for the participant's own login.

## After saving

Click **Add** to save. You will go back to the participants list.
If a duplicate Participant ID is found after submit, the form
stays open with the duplicate flagged so you can fix it.
