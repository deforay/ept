---
title: Global Settings
summary: System-wide configuration applied across the entire ePT application
tags: [config, global, settings, smtp, instance, theme]
---

# Global Settings

This page holds the **system-wide settings** for the ePT instance —
everything that doesn't belong to a specific scheme (DTS, VL, TB)
or to a specific page. Changes here affect the whole application.

The page is long, so two helpers sit at the top:

- **Search settings by keyword** — type to filter visible fields
  by label.
- **Jump to Section** — drop down to *General Settings*,
  *Participant Settings*, or *Email Settings* and the page scrolls
  there.

The rest of the page is grouped into three collapsible sections.

## General Settings

Branding, behaviour, and operational defaults for the whole app.

- **Domain** — base URL the app is served from. Auto-filled, but
  editable for proxy / CDN setups.
- **Language** — UI locale (English, French, Vietnamese).
- **Aggregate Insights URL** — optional analytics endpoint.
- **Administrator Email** — primary admin contact.
- **PT Program Name** / **PT Program Short Name** — full and
  abbreviated names of your program; both appear in headers,
  emails, and reports.
- **Training Instance** — *Yes* turns this instance into a
  training/sandbox environment. Combined with **Report
  Watermark**, every PDF gets a *TRAINING / DRAFT* style watermark
  so test reports can't be confused for real ones.
- **Active Schemes** — at least one must be ticked. Schemes that
  aren't active here don't appear in the menus.
- **Accept Responses After Finalization** — *Yes* lets late
  responses come in even after a shipment is finalized. Default
  *No*.
- **Enable Custom Tests** — allow PT admins to author non-standard
  test types.
- **Re-evaluate Before Finalizing** — when *Yes*, finalize is
  blocked until the latest re-evaluation has run.
- **Enable CAPA** — turns on the Corrective / Preventive Action
  module across the app.
- **Admin Email Notifications** — global toggle for outgoing
  notifications to admin.
- **Institute Name / Address / Additional Details** — appear on
  reports and official documents.
- **Theme Color** / **Instance** — visual appearance and
  environment tier (affects branding/URLs).
- **Job Completion Alerts** + **Job Completion Alert Recipients**
  — notify a comma-separated email list when scheduled jobs
  finish (e.g. evaluation, report generation, finalize).
- **Auto-generate PT Survey Code** — when *Yes*, the PT Survey
  Code field is auto-filled on the *Add PT Survey* form based on
  the chosen date.
- **Footer Text** / **Date Format** — global footer line and the
  date format used everywhere (e.g. `d-M-Y`, `MM/DD/YYYY`).
- **Certificate Email** — when *Yes*, completion certificates are
  emailed to participants.

## Participant Settings

How participants log in and what they can do.

- **Login ID Prefix** — prepended to auto-generated participant
  login IDs (e.g. `PT001`, `PT002`).
- **Password Length** — minimum length for participant passwords.
- **Allow Name Editing** — *Yes* lets participants update their
  name from their profile.
- **Enable Feedback** — *Yes* shows the feedback / corrections
  module to participants after a shipment is finalized.
- **Direct Login** — *Yes* lets participants enter a password to
  log in. *No* means participants get a one-time email link
  instead.
- **Login Attempt Ban** — when *Yes*, three extra fields appear:
  - **Temporary Ban Duration (minutes)** — how long the account
    is locked after the temporary threshold.
  - **Attempts Before Temporary Ban** — failed logins that trip
    the temporary lock (e.g. 3).
  - **Attempts Before Permanent Ban** — failed logins that
    permanently disable the account (e.g. 10).

## Email Settings

SMTP credentials and defaults for outgoing email.

- **SMTP Host / Port** — server hostname and port (587 for TLS,
  465 for SSL).
- **From Name / From Email** — what participants see in their
  inbox.
- **CC / BCC** — default copy recipients for every system email.
- **Encryption** — TLS, SSL, STARTTLS, or None.
- **Username / Password** — SMTP credentials.
- **Authentication Type** — Login, Plain, or CRAM-MD5.

## Saving

The **Update** button saves the whole form. Required fields are
marked with `*`; if any required field is empty the form blocks
submission and highlights the offender.

## Tips

- The **Home Page Settings** are configured at
  *Manage → Home Settings* (the section was moved out of this
  page).
- Toggling **Training Instance** on a real production instance
  will watermark every report — only do this on test / training
  environments.
- After changing **Active Schemes**, reload affected admin pages
  (DTS, VL, TB settings) to see menu items appear or disappear.
- Changing SMTP settings? Send yourself a test email immediately
  via any module that triggers mail (e.g. *Send Email to
  Participants* on a shipped survey) to verify the new
  credentials before participants try to use the system.
