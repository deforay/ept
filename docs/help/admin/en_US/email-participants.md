---
title: Email Participants
summary: Send or schedule an email about one or more shipments, such as a reminder to labs that have not responded
tags: [email, communication, reminder, participants, data-managers, ptcc, schedule]
---

# Email Participants

Write one email about one or more shipments. Each recipient gets
their own copy, with their own lab names filled in.

The page has three tabs: **Compose**, **Scheduled** and
**History**.

## Send a reminder to labs that have not responded

1. Under **Who should get it**, select the shipments.
2. Under **Send to**, choose who gets the email:
   **Participants**, **Data managers** or **PTCC managers**.
3. Under **Which labs**, choose **Only labs that have not
   responded**.
4. Under **Start from a template**, choose **Not Responded
   Reminder**. The subject and message fill in.
5. Change the message if you need to.
6. Click **Preview**. Check the list of recipients and the copy
   each one gets.
7. Click **Send now**, or **Schedule…** to send it later.

To open this page with a shipment already selected, click
**Remind** on the shipment row in **Manage → Shipments**. The
**Email** button on a PT Survey row does the same for every
shipment in the survey.

## Choose who gets it

| Setting | Choices |
| --- | --- |
| **Send to** | **Participants** (each lab, at the lab's own address), **Data managers** (the people who enter results for those labs), or **PTCC managers** (country coordinators, TB shipments only) |
| **Also Cc each lab's data managers on the lab's email** | Shown when you send to **Participants**. Copies each lab's data managers |
| **How they get it** | Shown when you send to data managers or PTCC managers. **One email listing all their labs** (one per shipment), or **One email per lab** |
| **Which labs** | **All labs**, or **Only labs that have not responded** |

One email goes to one audience. After you send or schedule it,
the page offers buttons to write a version for another audience,
with the same shipments and labs.

**Only labs that have not responded** includes labs that saved
only a draft. A scheduled email checks again when it goes out,
so labs that respond before then do not get it.

## Write the message

- **Start from a template** fills in the subject and message.
  You can edit both.
- **Merge fields** put each recipient's own values into the
  email, such as the lab name, shipment code or response
  deadline. Click **Insert field**, or click a field in the
  **Merge fields** list, to insert it at the cursor.
- The **Table of labs** field lists the recipient's labs and
  whether each has responded.

Older templates may use the `##NAME##` style of field. ePT still
fills those in.

## Before you send

- **Preview** lists every recipient and shows each copy of the
  message. You can send from the preview.
- ePT skips addresses marked as bounced or invalid.
- The mail job sends queued emails in batches, so delivery takes
  a few minutes.

## Change or stop a scheduled email

Open the **Scheduled** tab. Times are in the time zone shown at
the top. Recipients are worked out when the email goes out.

| Button | What it does |
| --- | --- |
| **Edit** | Change the email before it goes out |
| **Pause** | Holds the email. Messages already sent stay sent |
| **Resume** | Continues a paused email |
| **Send now** | Sends a scheduled or paused email straight away |
| **Cancel** | Stops the email. Messages already sent cannot be recalled |

## Check what was sent

Open the **History** tab. It lists the latest 200 sent and
cancelled emails. Delivery counts are kept for 30 days after
sending.
