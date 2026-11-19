---
title: Not Tested Reasons
summary: Set the reasons a participant can give for not testing a shipment
tags: [config, not tested, reasons, response]
---

# Not Tested Reasons

When a participant could not test a shipment, they pick a reason on
the response form. This page sets the reasons they can pick, and the
schemes that offer each reason.

Open **Configure → Scheme Config**. In the sidebar, open **Shared**
and click **Not Tested Reasons**.

## Add a reason

1. Click **Add New Not Tested Reason**.
2. Enter the **Not Tested Reason** as participants see it.
3. Enter a short **Reason Code**.
4. Under **Scheme Name**, select every scheme that offers this
   reason.
5. Under **Collect Panel Receipt Date?**, choose **Yes** or **No**.
6. Under **Status**, choose **Active**.
7. Click **Add**.

The page tells you if the reason or the code is already in use, and
clears the field.

## What the fields do

| Field | Effect |
| --- | --- |
| **Scheme Name** | The response forms of these schemes offer the reason. A reason with no scheme appears on no form |
| **Collect Panel Receipt Date?** | **No** sets **Received the PT Panel?** to **No** on the response form when the participant picks this reason |
| **Status** | Only **Active** reasons appear on response forms |

## The reason list

The list shows each reason with its **Reason Code**, its schemes under
**Available Scheme**, its **If It Collect Panel Receipt Date?**
setting and its **Status**.

Use **Scheme name** above the list to show only the reasons of one
scheme.

## Change or retire a reason

1. Click **Edit** on the reason's row.
2. Change the fields.
3. Click **Update** to save.

To stop offering a reason, set its **Status** to **Inactive**. The
page has no delete button.
