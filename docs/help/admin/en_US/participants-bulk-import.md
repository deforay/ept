---
title: Bulk Import Participants
summary: Upload an Excel file to add or update many participants at once
tags: [participants, bulk-import, excel, upload]
---

# Bulk Import Participants

Bulk import is the **recommended way** to add or update
participants when you have more than a few records. You upload an
Excel file (`.xls` or `.xlsx`) based on a fixed template. The
system adds or updates rows in one pass and then shows you which
rows worked and which were skipped — and why.

## Step 1 — Download the template

Click **Download Excel template** on the right side of the page.
The template has the exact column headings the importer expects.
Required columns are highlighted in red.

Always start from the latest template. If your file's headings do
not match, the import is rejected and you will see exactly which
columns are missing or misnamed.

## Step 2 — Fill in the file

One row per participant. The required columns are:

- `unique_identifier` — your internal Participant ID. Must be
  unique across all participants.
- `first_name` — for a Lab or Clinic, put the **lab/clinic name**
  here. (The importer uses one column for both kinds of
  participants.)
- `primary_email`
- `country`

Everything else is optional but useful (address, phone,
affiliation, network tier, and so on).

## Step 3 — Pick the upload options

Two dropdowns decide how the importer behaves. Pick what fits the
job before uploading.

### If a Unique ID already exists

This controls what happens when a row's `unique_identifier`
matches an existing participant.

- **Skip the row (don't update)** — leave the existing record
  alone. Safe when you are only adding new participants.
- **Update all data for that participant** — replace every field
  on the existing record with the row from your file. Use this
  when your spreadsheet is your source of truth.
- **Update email only (if changed)** — the narrowest update;
  only changes the primary email when it is different.

When you pick **Update all data for that participant**, an extra
**Reset password on update** dropdown appears. Set it to *Yes* to
give the participant's login a new password; *No* keeps the old
one.

### Same email across participants

This controls whether one email address can be used by more than
one participant or Data Manager.

- **Allow — reuse existing DM / PT login email** — accept the
  row even when the email is already in use.
- **Don't allow — email must be unique** — reject any row whose
  email is already used.

## Step 4 — Upload

Drag the file onto the drop area or click to browse. The card
below the drop area shows the file's name and size. You can
**Replace** or remove it before submitting.

Click **Import Excel** to start.

## What happens after import

You will see a results page that shows:

- How many rows were saved as new participants.
- How many rows were updated (and how — full update or email
  only).
- How many rows were skipped, with the reason for each one
  (duplicate ID, missing required field, invalid email, country
  not recognised, and so on).

If your file's column headings do not match the template, the
importer **does not import anything**. Instead, a red error block
at the top lists every heading problem (*Expected* vs *Found in
your file*). Fix the headings, save, and upload again.

## Troubleshooting

- **"File format not supported"** — the importer accepts `.xls`
  and `.xlsx` only. Re-save your file as Excel.
- **"The uploaded file does not match the template"** — your
  column headings are off. Compare each *Expected* / *Found* pair
  in the error table and fix the spreadsheet, or download the
  template again and copy your data into it.
- **A row was skipped as a duplicate** — that `unique_identifier`
  already exists. Either switch to *Update all data* / *Update
  email only*, or change the ID in your file.
- **A row was skipped for an existing email** — switch the second
  dropdown to *Allow*, or correct the email.
