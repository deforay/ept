---
title: Bulk Import Participants
summary: Upload an Excel or CSV file, review what will change, then import many participants at once
tags: [participants, bulk-import, excel, upload]
---

# Bulk Import Participants

Bulk import adds or updates many participants from one file. It
takes three steps: **Upload**, **Review** and **Done**. Nothing is
saved until you click **Import** on the review page.

## Step 1: Prepare the file

1. Click **Download Excel template**.
2. Fill in one row per participant. Keep the heading row and the
   column order.
3. Format the **Participant ID** column as Text. Otherwise Excel
   drops leading zeros, and `00123` becomes `123`.

The required columns are:

| Column | What to enter |
| --- | --- |
| **Participant ID** | The lab's ID. Must be unique |
| **Individual Participant (yes/no)** | `yes` for a person, `no` for a lab or clinic |
| **First Name or Lab Name** | For a lab or clinic, the lab name |
| **Country Name** | Pick from the dropdown in the template |
| **Email ID** | The lab's email address |

**Password** is optional. A blank password gives the participant
the shared default password.

## Step 2: Choose the options and upload

**If a Participant ID is already in ePT:**

- **Add new only** leaves existing participants untouched and
  skips their rows.
- **Update all details** replaces every value with the file's
  value. A blank cell clears what is stored. Tick **Also reset
  their passwords** to set new passwords as well.
- **Update email only** changes only the email of existing
  participants.

**Same email on more than one participant:** choose **Allow** to
let one data manager look after several participants.

Drop the file on the page, or click to browse. Then click
**Review import**.

## Step 3: Review

The review page shows what will happen to each row. Nothing has
been saved yet.

- The tabs split the rows into **New participants**, **Will be
  updated**, **Already up to date** and **Errors**.
- **(cleared)** marks a value that a blank cell will erase. Fill
  in the cell and upload again to keep the value.
- Warnings at the top list password resets, inactive
  participants that become active again, and country names that
  ePT will store differently.
- **Download rows that won't import** saves the error rows, so you
  can fix them and upload them again.

Click **Import … row(s)** to save. To start over, click **Upload
a different file**.

## Step 4: Done

The last page shows how many participants were created and
updated. Check a few in **Configure → PT Participants**.

## Troubleshooting

- **The uploaded file does not match the template.** The column
  headings are wrong. The table lists each **Expected header**
  next to what was **Found in your file**. Download the template
  again and copy your data into it.
- **A Participant ID lost its leading zeros.** The column was not
  formatted as Text. Fix the IDs in the file and upload again.
- **A row is in Errors.** The row explains why. Common reasons
  are a missing required value, an invalid email, or an email
  already used when **Allow** is not chosen.
