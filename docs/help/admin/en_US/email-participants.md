---
title: Email Participants
summary: Send a bulk email to participants, data managers, or PTCCs for a chosen shipment date range
tags: [email, communication, participants, data-managers, ptcc, templates]
---

# Email Participants

Sends a **bulk email** to participants, their Data Managers (PT
Logins), or PT Country Coordinators, based on which shipments
they took part in. Useful for reminders ("you haven't submitted
yet"), announcements, or follow-ups after a survey closes.

## Fields

### Template (optional)

- **Template** — pick a saved email template (for example,
  *Not Participated*, *Reminder*, *Final Reminder*). Picking a
  template fills the **Subject** and **Mail Content** with its
  pre-written text. You can edit either before sending.

> Templates are managed under *Manage → Email Templates*. If a
> template you need isn't here, add it there first.

### Date Range and Shipments

- **Date Range** *(required)* — pick a date window. Defaults to
  the last 180 days. This filters the **Shipment Code** dropdown
  to shipments inside the window.
- **Shipment Code** *(required, multi-select)* — one or more
  shipments to limit the email to. If a recipient was on any of
  them, they get the email.

### Skip internal emails

- **Skip emails with @{your-domain}** *(checkbox, on by
  default)* — leaves out every recipient whose email is on your
  own ePT domain. Stops you from spamming testers, demo
  accounts, or internal staff who happen to be in the
  participant list.

### Recipients (To)

- **To** *(required, multi-select)* — pick one or more groups:
  - **Participants** — every participant on the chosen
    shipments.
  - **Data Managers** — every PT Login linked to those
    participants.
  - **PTCC Managers** *(only when TB is an active scheme)* — the
    PT Country Coordinators who cover those participants.

### Subject and content

- **Subject** *(required)* — the email subject line. As you
  type, an autocomplete suggests previously-used subjects. If
  you pick one, you will be asked whether to load that
  subject's saved content into the editor.
- **Mail Content** *(required, rich text)* — Summernote
  rich-text editor. Picking a Template or auto-suggested
  Subject pre-fills this; you can still edit freely.

### Personalization keys

The placeholders below, listed in the **Key** section, are
replaced for each recipient:

- `##NAME##` — Lab Name / Participant Name
- `##SHIPCODE##` — Shipment Code
- `##SHIPTYPE##` — Scheme Type
- `##SURVEYCODE##` — PT Survey Code
- `##SURVEYDATE##` — PT Survey Date

Drop them anywhere in the Subject or Mail Content. Each
recipient gets their own values in.

## Sending

- **Send** — queues the email batch. The form will not let you
  send if Shipments, To, Subject, or Mail Content are empty.
- **Back** — returns to the admin home without sending.

> **Sends cannot be undone.** Once you click *Send*, the queue
> picks up the batch and starts. Double-check the recipient
> list, especially if testers or internal accounts are mixed in
> (and check *Skip emails with @{your-domain}* is ticked).

## Tips

- **Start from a Template, then edit.** Templates exist so you
  don't rebuild a "you haven't submitted yet" email every
  cycle. Pick the template, scan the content, change the
  specifics (date, cycle, next steps), then send.
- **Date Range first, then Shipment Code.** The Shipment
  dropdown reloads when the date range changes — set the date
  window before picking shipments.
- For a *"haven't submitted yet"* reminder, send to
  **Participants + Data Managers** — Data Managers usually do
  the data entry, while Participants are the lab-level contact.
- **Use personalization keys generously** — emails with the
  lab name and shipment code feel like real correspondence and
  get better response rates.
- Opening this page from a specific shipment (via the envelope
  button on *PT Surveys → Action*) pre-picks that shipment and
  the *Not Participated* template — a one-click reminder flow.
