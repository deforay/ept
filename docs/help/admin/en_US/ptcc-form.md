---
title: PTCC Form
summary: Add or edit a PT Country Coordinator and choose the countries, provinces or districts they cover
tags: [ptcc, coordinator, tb, region, form]
---

# PTCC Form

Use this form to add or edit a PT Country Coordinator (PTCC). A
PTCC covers one or more countries, provinces or districts. ePT
maps the PTCC to every participant in that area.

## Add a PTCC

1. Open **Configure → Manage PTCC**.
2. Click **Add New PTCC**.
3. Enter **Login (Primary e-mail)**. This address is the PTCC's
   login name.
4. Enter **Password** and **Confirm Password**.
5. Enter **User First Name**.
6. Under **Country**, type at least three letters of a country
   name. Pick the country from the list. Repeat for each country.
7. Optional: under **State/Province**, pick the provinces to
   cover.
8. Optional: under **District/County**, pick the districts to
   cover.
9. Click **Add**.

ePT asks the PTCC to change the password the first time they log
in.

## Edit a PTCC

1. On the PTCC list, click **Edit** on the row.
2. Change the fields or the area.
3. Click **Save**.

To keep the current password, leave **Password** and **Confirm
Password** empty. If you enter a new password, ePT asks the PTCC
to change it at their next login.

## How the area works

| You pick | The PTCC covers |
| --- | --- |
| A country only | Every participant in that country |
| A country and some of its provinces | Every participant in those provinces |
| A province and some of its districts | Every participant in those districts |

When you save, ePT maps the PTCC to the participants in the area.
It removes participants outside the area. When you save a
participant, ePT maps it to the PTCCs whose area holds it. A
daily job catches any participant these steps missed.

A participant is in the area when its country, province and
district match. Check these fields on the participant if a PTCC
does not see a participant.

## Fields

| Field | Required | What it does |
| --- | --- | --- |
| **Login (Primary e-mail)** | Yes | The PTCC's login name and main email address. No other login can use it |
| **Password** | Yes, when adding | The PTCC's password. It must be at least as long as **Password Length** in **ePT Global Settings**. It must contain a letter and a number. It must not contain the PTCC's name |
| **Confirm Password** | Yes, when adding | The same password again |
| **Institute** | No | The PTCC's institute |
| **User First Name** | Yes | The PTCC's first name |
| **User Last Name** | No | The PTCC's last name |
| **Secondary e-mail Address** | No | A second address. It must differ from the primary email |
| **Cell Phone Number** | No | The PTCC's mobile number |
| **Phone Number** | No | Another phone number |
| **Enable View-Only Access** | No | **Yes** lets the PTCC see results but not change them. The default is **No** |
| **Status** | Yes | **Active** PTCCs can log in. **Inactive** PTCCs cannot |
| **Country** | Yes | The countries the PTCC covers |
| **State/Province** | No | Limits the area to these provinces |
| **District/County** | No | Limits the area to these districts |

## Turn a Data Manager into a PTCC

When you add a PTCC with the email of an existing Data Manager,
ePT asks whether to add this user as a PTCC.

- Click **OK** to turn the Data Manager into a PTCC. ePT removes
  the user's old participant mappings and maps the participants
  in the new area.
- Click **Cancel** to clear the email and enter another one.

To add a Data Manager for one or more laboratories, see [Data
Manager form](data-managers-form.md).
