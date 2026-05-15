---
title: Global Settings
summary: System-wide settings that apply across the whole ePT app
tags: [config, global, settings, smtp, theme]
---

# Global Settings

This page holds the **system-wide settings** for your ePT
installation — anything that does not belong to a single scheme
(DTS, VL, TB) or a single page. Changes here affect the whole
app.

The page is long, so there are two helpers at the top:

- **Search settings by keyword** — type to filter the visible
  fields by label.
- **Jump to Section** — pick *General Settings*, *Participant
  Settings*, or *Email Settings* and the page scrolls there.

The rest of the page is grouped into three sections you can
collapse.

## General Settings

Branding, behaviour, and defaults for the whole app.

- **Domain** — base URL the app is served from. Filled in for
  you, but you can edit it for proxy / CDN setups.
- **Language** — the UI language (English, French, Vietnamese).
- **Aggregate Insights URL** — optional analytics URL.
- **Administrator Email** — main admin contact.
- **PT Program Name** / **PT Program Short Name** — full and
  short names of your programme. Both appear in headers, emails
  and reports.
- **Training Instance** — set to *Yes* to turn this copy of the
  app into a training / sandbox setup. Combined with **Report
  Watermark**, every PDF gets a *TRAINING / DRAFT* watermark so
  test reports cannot be mistaken for real ones.
- **Active Schemes** — at least one must be ticked. Schemes that
  are not ticked here do not appear in the menus.
- **Accept Responses After Finalization** — set to *Yes* to let
  late responses come in even after a shipment is finalized.
  Default is *No*.
- **Enable Custom Tests** — let PT admins set up non-standard
  test types.
- **Re-evaluate Before Finalizing** — when *Yes*, finalize is
  blocked until a fresh re-evaluation has run.
- **Enable CAPA** — turns on the Corrective / Preventive Action
  module across the app.
- **Admin Email Notifications** — global on/off switch for
  emails sent to admins.
- **Institute Name / Address / Additional Details** — shown on
  reports and official documents.
- **Theme Color** / **Instance** — visual look and environment
  tier.
- **Job Completion Alerts** + **Job Completion Alert
  Recipients** — when scheduled jobs (evaluation, report
  generation, finalize) finish, notify this comma-separated
  email list.
- **Auto-generate PT Survey Code** — when *Yes*, the PT Survey
  Code is filled in for you on the *Add PT Survey* form, based
  on the date.
- **Footer Text** / **Date Format** — global footer line and the
  date format used everywhere (for example, `d-M-Y`,
  `MM/DD/YYYY`).
- **Certificate Email** — when *Yes*, completion certificates
  are emailed to participants.

## Participant Settings

How participants log in and what they can do.

- **Login ID Prefix** — text added to the start of new
  participant login IDs (for example, `PT001`, `PT002`).
- **Password Length** — minimum length for participant
  passwords.
- **Allow Name Editing** — *Yes* lets participants change their
  name from their profile.
- **Enable Feedback** — *Yes* shows the feedback / corrections
  module after a shipment is finalized.
- **Direct Login** — *Yes* lets participants enter a password.
  *No* sends them a one-time email link instead.
- **Login Attempt Ban** — when *Yes*, three extra fields appear:
  - **Temporary Ban Duration (minutes)** — how long the account
    is locked after the first threshold.
  - **Attempts Before Temporary Ban** — failed logins that
    trigger the temporary lock (for example, 3).
  - **Attempts Before Permanent Ban** — failed logins that
    permanently disable the account (for example, 10).

## Email Settings

SMTP credentials and defaults for outgoing email.

- **SMTP Host / Port** — server hostname and port (587 for TLS,
  465 for SSL).
- **From Name / From Email** — what participants see in their
  inbox.
- **CC / BCC** — default copy recipients for every system
  email.
- **Encryption** — TLS, SSL, STARTTLS, or None.
- **Username / Password** — SMTP credentials.
- **Authentication Type** — Login, Plain, or CRAM-MD5.

## Saving

Click **Update** to save the whole form. Required fields are
marked with `*`. If a required field is empty, the form will
not save and the missing field is highlighted.

## Tips

- The **Home Page Settings** are set up under *Manage → Home
  Settings* (they used to live here).
- Turning on **Training Instance** on a real production setup
  will watermark every report — only use it on test or
  training environments.
- After changing **Active Schemes**, reload the affected admin
  pages (DTS, VL, TB settings) to see menu items appear or
  disappear.
- Changed SMTP settings? Send yourself a test email straight
  away (for example, via *Send Email to Participants* on a
  shipped survey) to check the new credentials before
  participants try to use the system.
