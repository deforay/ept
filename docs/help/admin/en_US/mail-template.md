---
title: Mail Template
summary: Edit the sender, subject and message of the emails ePT sends about shipments and reports
tags: [config, email, mail template, notifications]
---

# Mail Template

This page holds the standard emails that ePT sends, such as the new
shipment email and the reminder to participants who have not
responded. Each email has its own template.

Open **Configure → Mail Template**.

## Edit a template

1. Click the template's name to open it. The **Mail Template** tab
   lists these templates:
   - **New Shipment Mail Template**
   - **Not Responded Mail Template**
   - **Deadline Extended Mail Template**
   - **Not Participant Report Mail Template**
   - **Send Participant Report Mail Template**
   - **Announcement Mail Template**
2. Under **Written for**, choose who the version is for.
3. Enter the **From Name** and **From Email**.
4. Optional: enter **Cc** and **Bcc** addresses. Separate several
   addresses with commas.
5. Enter the **Subject**.
6. Write the **Message**. Insert keys from the **Key** list to merge
   in details such as the participant name or shipment code.
7. Optional: write a **Footer**.
8. Click **Update**. The page shows *Updated successfully.*

**Update** saves only the template it belongs to.

## Versions for each audience

**Written for** has three choices: **Participants**, **Data
managers** and **PTCC managers**. Each choice is a separate version of
the template.

- **Participants**: ePT uses this version for the emails it sends on
  its own. **Email Participants** also offers it.
- **Data managers** and **PTCC managers**: **Email Participants**
  offers these versions. A version exists once you save it.

## Keys

The **Key** list shows the keys that work in the open template. Copy a
key into the message exactly as shown.

Two key styles exist:

- Most templates use keys such as `##NAME##` and `##SHIPCODE##`.
- **Not Responded Mail Template**, **Deadline Extended Mail
  Template** and the data manager and PTCC manager versions use keys
  such as `{{participant_name}}` and `{{shipment_code}}`.

The data manager and PTCC manager versions also offer
`{{lab_table}}`, a table of the recipient's laboratories.

To send a one-off email, use [Email Participants](email-participants.md).
