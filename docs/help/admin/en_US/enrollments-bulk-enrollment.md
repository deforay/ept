---
title: Bulk Enroll Participants
summary: Upload an Excel file to enroll many participants in a scheme at once
tags: [enrollment, bulk-import, excel, scheme]
---

# Bulk Enroll Participants

Enrolls many participants in a single scheme by uploading an
Excel spreadsheet. Faster than the two-list selector when you
are onboarding more than ~50 participants. A good fit for
new-country rollouts or large group expansions.

## Fields

- **Scheme Types** *(required)* — the scheme to enroll the rows
  in the file into (DTS, VL, TB, and so on). One scheme per
  upload.
- **Enrollment List Name** *(optional)* — a free-text label for
  this batch. Useful for audit and reporting (for example,
  *"Q3 2026 expansion — Western Province"*).
- **Select file to upload** *(required, .xlsx)* — the Excel file
  with the rows to enroll.

## File Format

Click **Click here to download the Excel format for importing
the participants** to grab the template. Important rules:

- **Columns marked in red are required.**
- The **Unique Identifier** field must have a unique value per
  participant.
- The **Unique Identifier must match an existing Participant
  Unique Identifier**. Bulk *enrollment* does not create new
  participants — it only enrolls people who are already in the
  system. Add the participants first (via *Manage → Participants
  → Bulk Import* or one by one).

## Saving

- **Import Excel** — uploads and processes the file. After
  processing, the page reloads to the same form so you can
  upload another file.
- **Cancel** — returns to the Enrollments list without
  uploading.

## Tips

- **Check the participants exist first.** The most common cause
  of a failed row is a Unique Identifier that doesn't match any
  participant. Look in *Manage → Participants* with a Unique-ID
  search, or do a small participant bulk-import first, then run
  this enrollment import.
- After import, go back to **Enrollments** and check the
  per-scheme **count line** at the top. It should jump by the
  number of new rows. If it didn't, some rows likely failed to
  match.
- **One scheme per file.** If you need to enroll the same
  participants in several schemes, run the import once per
  scheme. The file format has no multi-scheme column.
- The **Enrollment List Name** is only a label — it does not
  filter anything in the UI today. But it shows up in audit
  logs and is useful for tracing where a row came from later.
