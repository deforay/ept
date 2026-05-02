---
title: Add Participant
summary: Create a new participant record and map them to one or more Data Managers
tags: [participants, add, create, data-managers]
---

# Add a Participant

Use this form to add a single new participant — a lab, a clinic, or
an individual. For more than a few participants at a time, prefer
**Bulk Import** instead.

## Mandatory fields

Fields marked with a red `*` must be filled before the form can be
saved. The required fields are:

- **Participant ID** — your internal unique identifier. The form
  checks for duplicates as soon as you leave the field; if the ID is
  already in use by another participant, you'll see an error and
  won't be able to submit.
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
  field is labelled **Lab Name** and there's a separate **Contact
  Person Name** field. The Last Name field is hidden.
- **Checked** — treat as an individual. You get **First Name** and
  **Last Name** fields; the Contact Person field is hidden.

Pick this *before* filling the name fields so the form shows the
right labels.

## Address fields

Fill in the **Physical Address** for the participant. The
**Shipping Address** below it is what's used when dispatching panels;
fill it in only if it differs from the physical address. Tick **Same
as physical address** to copy the physical address fields (street,
city, state, district, zip, country) into the shipping address
automatically — and keep them in sync as you edit.

## Contact info

You can record both a **Cell/Mobile No.** and a **Phone Number**.
The **Additional/Alternate E-mail** field accepts comma-separated
multiple addresses for participants who want notifications copied to
more than one inbox.

## Mapping to Data Managers

Every participant **must be mapped to at least one Data Manager** —
this is who actually logs in and submits PT responses on the
participant's behalf. The mapping section is a side-by-side picker:

- **Available Data Managers** on the left — every DM in the system.
- **Selected Data Managers** on the right — the ones mapped to this
  participant.

Click any name in the left pane to move it to the right; click a
name in the right pane to move it back. Use **Select All** /
**Deselect All** to move every (currently filtered) DM at once.
Each pane has a search box at the top to filter quickly when the list
is long.

If the DM you need doesn't exist yet, click **Create New Data
Manager** above the picker to create one inline without leaving this
form.

When direct participant login is enabled (a system-wide setting),
mapping a DM is optional and the form additionally collects a
password for the participant's own login.

## After saving

Click **Add** to save. You'll be returned to the participants list.
If a duplicate Participant ID was detected after submit, the form
stays open with the duplicate flagged so you can fix it.
