---
title: Bulk Import Participants
summary: Upload an Excel file to create or update many participants at once
tags: [participants, bulk-import, excel, upload]
---

# Bulk Import Participants

Bulk import is the **recommended way** to add or update participants
when you have more than a few records. You upload an Excel (`.xls` or
`.xlsx`) file based on a fixed template and the system creates or
updates rows in a single pass, then shows you exactly which rows
succeeded and which were skipped — and why.

## Step 1 — Download the template

Use the **Download Excel template** button on the right side of the
page. The template has the exact column headers the importer expects,
with mandatory columns highlighted in red.

Always start from the latest template. If your file's headers don't
match, the import is rejected and you'll see a list of which columns
are missing or misnamed.

## Step 2 — Fill the file

One row per participant. The required columns are:

- `unique_identifier` — your internal Participant ID. Must be unique
  across all participants.
- `first_name` — for a Lab or Clinic, put the **lab/clinic name**
  here. (The importer uses one column for both kinds of participants.)
- `primary_email`
- `country`

Everything else is optional but recommended (address, phone,
affiliation, network tier, etc.).

## Step 3 — Choose the upload options

Two dropdowns control how the importer behaves; pick what fits the
job before uploading.

### If a Unique ID already exists

Controls what happens when a row's `unique_identifier` matches an
existing participant.

- **Skip the row (don't update)** — leave the existing record alone.
  Safe default if you're only adding new participants.
- **Update all data for that participant** — overwrite every field
  on the existing record from the row in your file. Use this when
  the spreadsheet is your source of truth.
- **Update email only (if changed)** — narrowest possible update;
  only touches the primary email when it differs.

When you choose **Update all data for that participant**, an extra
**Reset password on update** dropdown appears. Set it to *Yes* to
issue a new password to the participant's login on update; *No* keeps
the existing password.

### Same email across participants

Controls whether one email address may belong to multiple
participants or Data Managers.

- **Allow — reuse existing DM / PT login email** — accept the row
  even when its email is already in use elsewhere.
- **Don't allow — email must be unique** — reject any row whose
  email is already used by another participant or DM.

## Step 4 — Upload

Drag the file onto the drop area or click to browse. The card below
the drop area shows the selected file's name and size; you can
**Replace** or remove it before submitting.

Click **Import Excel** to start the import.

## What happens after import

You'll be taken to a results page that summarises:

- How many rows were saved as new participants.
- How many existing rows were updated (and how — full update or
  email-only).
- How many rows were skipped, with the reason for each skip
  (duplicate ID under skip mode, missing required field, invalid
  email, country not recognised, etc.).

If your file's column headers don't match the template, the importer
**doesn't import anything** — instead it shows a red error block at
the top of the bulk import page listing every header issue
(*Expected* vs. *Found in your file*). Fix the headers, save, and
upload again.

## Troubleshooting

- **"File format not supported"** — the importer accepts `.xls` and
  `.xlsx` only. Re-save your file as Excel.
- **"The uploaded file does not match the template"** — your column
  headers are off. Compare each *Expected* / *Found* pair in the
  error table and fix the spreadsheet, or re-download the template
  and copy your data into it.
- **A row was skipped as a duplicate** — that `unique_identifier`
  already exists. Either change the option to *Update all data* /
  *Update email only*, or change the ID in your file.
- **A row was skipped for an existing email** — switch the second
  dropdown to *Allow* if that's intentional, or correct the email.
