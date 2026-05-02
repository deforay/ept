---
title: Bulk Enroll Participants
summary: Upload an Excel file to enroll many participants in a scheme at once
tags: [enrollment, bulk-import, excel, scheme]
---

# Bulk Enroll Participants

Enrolls many participants in a single scheme by uploading an
Excel spreadsheet. Faster than the dual-list selector when
you're onboarding more than ~50 participants, and a good fit
for new-country rollouts or large cohort expansions.

## Fields

- **Scheme Types** *(required)* — the scheme to enroll all rows
  in the file into (DTS, VL, TB, etc.). The file applies to one
  scheme per upload.
- **Enrollment List Name** *(optional)* — free-text label for
  this batch. Helps audit / reporting (e.g. *"Q3 2026 expansion
  — Western Province"*).
- **Select file to upload** *(required, .xlsx)* — the Excel file
  containing the rows to enroll.

## File Format

Click **Click here to download the Excel format for importing
the participants** to grab the template. Important constraints:

- **Columns marked in red are mandatory.**
- The **Unique Identifier** field must contain a unique value
  per participant.
- The **Unique Identifier must match an existing Participant
  Unique Identifier** — bulk *enrollment* does not create new
  participants; it only enrolls people who are already in the
  system. Add the participants first (via *Manage → Participants
  → Bulk Import* or one-by-one).

## Saving

- **Import Excel** — uploads and processes the file. After
  processing the page reloads to the same form so you can
  upload another file.
- **Cancel** — returns to the Enrollments index without
  uploading.

## Tips

- **Verify participants exist first.** The most common bulk-
  enrollment failure is a Unique Identifier in the file that
  doesn't match any participant. Run *Manage → Participants*
  with a Unique-ID search, or do a small participant bulk-import
  first, *then* run this enrollment import.
- After import, go back to **Enrollments** and check the
  per-scheme **count line** at the top — it should jump by the
  number of new rows. If it didn't, some rows likely failed
  matching.
- **One scheme per file.** If you need to enroll the same
  participants in multiple schemes, run the import once per
  scheme — there's no multi-scheme column in the format.
- The **Enrollment List Name** is only metadata — it doesn't
  filter anything in the UI today, but it shows up in audit logs
  and is useful for tracing where a row came from later.
