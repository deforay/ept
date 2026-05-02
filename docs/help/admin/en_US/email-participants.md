---
title: Email Participants
summary: Send a templated bulk email to participants, data managers, or PTCCs for a given shipment date range
tags: [email, communication, participants, data-managers, ptcc, templates]
---

# Email Participants

Sends a **bulk email** to participants, their Data Managers
(PT Logins), or PT Country Coordinators based on which shipments
they participated in over a date range. Useful for reminders
("you haven't submitted yet"), announcements, or follow-ups
after a survey closes.

## Fields

### Template (optional)

- **Template** — picks a saved email template (e.g.
  *Not Participated*, *Reminder*, *Final Reminder*). Selecting a
  template fills the **Subject** and **Mail Content** with the
  template's pre-written text. You can edit either before
  sending.

> Templates are managed under *Manage → Email Templates*. If a
> template you need isn't here, add it there first.

### Date Range and Shipments

- **Date Range** *(required)* — pick a date window. Defaults to
  the last 180 days. The date range filters the **Shipment
  Code** dropdown to shipments inside that window.
- **Shipment Code** *(required, multi-select)* — one or more
  shipments to scope the email by. Each recipient is filtered
  through these shipments — if the recipient was associated with
  any of them, they get the email.

### Skip Internal Emails

- **Skip emails with @{your-domain}** *(checkbox, on by default)*
  — drops every recipient whose email address is on your own
  ePT domain. Stops you from spamming testers, demo accounts, or
  internal staff who happen to be in the participant list.

### Recipients (To)

- **To** *(required, multi-select)* — pick one or more recipient
  groups:
  - **Participants** — every participant on the selected
    shipments.
  - **Data Managers** — every PT Login mapped to those
    participants.
  - **PTCC Managers** *(only when TB is an active scheme)* — the
    PT Country Coordinators with scope over those participants.

### Subject and Content

- **Subject** *(required)* — the email subject line. As you
  type, an autocomplete suggests previously-used subjects; if
  you pick one, you'll be asked whether to load that subject's
  saved content into the editor.
- **Mail Content** *(required, rich text)* — Summernote rich-text
  editor. Selecting a Template or autocompleted Subject pre-fills
  this; you can still edit freely.

### Personalization Keys

The following placeholders, listed under the **Key** section,
are replaced per recipient:

- `##NAME##` — Lab Name / Participant Name
- `##SHIPCODE##` — Shipment Code
- `##SHIPTYPE##` — Scheme Type
- `##SURVEYCODE##` — PT Survey Code
- `##SURVEYDATE##` — PT Survey Date

Drop them anywhere in Subject or Mail Content; each recipient
gets their own values substituted in.

## Sending

- **Send** — queues the email batch for delivery. Validation
  blocks send if Shipments, To, Subject, or Mail Content are
  empty.
- **Back** — returns to the admin home without sending.

> **Sends are not undoable.** Once you click *Send*, the queue
> picks up the batch and dispatches. Double-check the recipient
> set, especially if testers / internal accounts are mixed in
> (and confirm *Skip emails with @{your-domain}* is checked).

## Tips

- **Pre-fill from a Template, then edit.** Templates exist so you
  don't rebuild a "you haven't submitted yet" email from scratch
  every cycle. Pick the template, scan the content, edit the
  specifics (date / cycle / next steps), then send.
- **Date Range first, then Shipment Code.** The Shipment dropdown
  reloads when the date range changes — set the date window
  before picking shipments.
- For a *"haven't submitted yet"* reminder, send to **Participants
  + Data Managers** — Data Managers are usually the ones actually
  doing data entry, while Participants are the lab-level contact.
- **Use personalization keys liberally** — emails with the
  recipient's lab name and shipment code feel like correspondence,
  not bulk mail, and get higher response rates.
- The opening of the page from a specific shipment (via the
  envelope button on *PT Surveys → Action*) pre-selects that
  shipment and the *Not Participated* template — a one-click
  reminder workflow.
