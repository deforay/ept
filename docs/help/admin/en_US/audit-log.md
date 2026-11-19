---
title: Audit Log
summary: Find who did what in ePT, and when, including logins
tags: [audit, history, activity, login, security]
---

# Audit Log

The Audit Log records what admins, data managers and PTCCs do in
ePT, such as adding a participant or submitting a response. It can also
show every login attempt. The newest entries come first.

## Find out who changed something

1. Open **Configure → Audit Log**.
2. Under **Date Range**, pick the days when the change happened.
3. Type a name, Lab ID, shipment code or word in the search box.
4. Click an entry to see its details.

To see everything one person did in one sitting, click **Filter
by this session** in the details. You can also click the session
tag on any entry.

## Check a user's logins

1. Under **Show**, click **Logins**.
2. Type the user's email address in **Actor email**.
3. Look for **Login failed** and **Login blocked** entries.

Click **All** to see actions and logins together.

## Filters

| Filter | What it does |
| --- | --- |
| **Show** | **Actions** (the default), **Logins** or **All** |
| **Date Range** | Shows entries from these days only |
| **Context** | Shows one area of ePT, such as participants |
| **User** | Shows entries by one admin |
| **Actor email** | Shows entries by anyone whose email contains this text. Use it for data managers and PTCCs |
| **Lab ID** | Shows entries by the data managers of this participant |
| **Session** | Shows entries from one sitting |
| **Reset** | Clears every filter |

The search box searches the action text, the user's name and
email, the context, and participant names and Lab IDs.

## Entry details

Click an entry to open its details:

| Field | What it shows |
| --- | --- |
| **User** | Who did it |
| **Email** | The user's email address |
| **Role** | Admin, Data Manager, PTCC or Participant |
| **Context** | The area of ePT |
| **IP address** | Where the user connected from |
| **Session hash** | A code shared by all entries from one sitting |
| **Browser** and **OS** | The user's browser and operating system |
| **Action** | What the user did |

Use **Per page** at the bottom to show more entries on each page.
