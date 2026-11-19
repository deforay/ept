---
title: Data Manager Form
summary: Add or edit a Data Manager (PT Login), set their password and access, and choose the participants they see
tags: [data-manager, pt-login, participant-login, password, mapping, form]
---

# Data Manager Form

Use this form to add or edit a Data Manager. A Data Manager is a
PT Login that participants use to log in and enter results. Each
Data Manager sees only the participants you map to them.

## Add a Data Manager

1. Open **Configure → Data Manager (Participant Login)**.
2. Click **Add New Data Manager (Participant Login)**.
3. Enter **Login (Primary e-mail)**. This address is the user's
   login name.
4. Enter **Password** and **Confirm Password**.
5. Enter **User First Name**.
6. Under **Choose Participant(s)**, click each participant this
   user enters results for. The participant moves to **Selected
   Participants**.
7. Click **Add**.

If you select more than one participant, ePT asks you to confirm.
The Data Manager can see the data of every selected participant.

ePT asks the user to change the password the first time they log
in.

## Edit a Data Manager

1. On the Data Manager list, click **Edit** on the row.
2. Change the fields.
3. Click **Save**.

To keep the current password, leave **Password** and **Confirm
Password** empty. If you enter a new password, ePT asks the user
to change it at their next login.

## Fields

| Field | Required | What it does |
| --- | --- | --- |
| **Login (Primary e-mail)** | Yes | The user's login name and main email address. No other login can use it |
| **Password** | Yes, when adding | The user's password. See the rules below |
| **Confirm Password** | Yes, when adding | The same password again |
| **Institute** | No | The user's institute |
| **User First Name** | Yes | The user's first name |
| **User Last Name** | No | The user's last name |
| **Secondary e-mail Address** | No | A second address. It must differ from the primary email |
| **Cell Phone Number** | No | The user's mobile number |
| **Phone Number** | No | Another phone number |
| **QC Access** | No | **Yes** lets the user enter QC details on the response form, when QC is turned on for ePT. The default is **No** |
| **Enable Adding Response Date** | No | **Yes** lets the user enter the response date. The default is **No** |
| **Enable Choosing Mode of Receipt** | No | **Yes** lets the user choose how the samples arrived. The default is **No** |
| **Enable View-Only Access** | No | **Yes** lets the user see results but not change them. The default is **No** |
| **Status** | Yes | **Active** users can log in. **Inactive** users cannot |

A password must:

- be at least as long as **Password Length** in **ePT Global
  Settings**
- contain at least one letter and one number
- not contain the user's name or the part of the email address
  before the @

## Choose the participants

The **Choose Participant(s)** box has two lists.
**Unselected Participants**, on the left, holds the participants
that are not mapped. **Selected Participants**, on the right,
holds the participants this user sees.

- Click a participant's name to move it to the other list.
- Type in the box above a list to filter it.
- Click **Select All** or **Deselect All** to move every
  participant in the list.
- Click **Show advanced filters** to filter by **Country**,
  **Province**, **District**, **Institute**, **Network Tier** or
  **Affiliation**.

Colours show what changes when you save:

| Colour | Meaning |
| --- | --- |
| Grey | **Saved (already mapped)** |
| Green | **Pending add (save to apply)** |
| Orange | **Pending remove (save to apply)** |

The lists show active participants only. Saving keeps any mapping
to an inactive participant.

You must select at least one participant.

## Common problems

| Message | What to do |
| --- | --- |
| *This primary e-mail ID already exists for another PT login* | Use another address, or edit the existing Data Manager |
| *Please select at least one Participant to map* | Move at least one participant to **Selected Participants** |
| *Please check your primary email and secondary email* | Enter a secondary address that differs from the primary one |

To add a coordinator who covers a region, use the PTCC form
instead. See [PTCC form](ptcc-form.md).
