---
title: Bulk Import Enrollment
summary: Upload an Excel file of Participant IDs to fill a scheme's enrollment list, and review the changes before they are saved
tags: [enrollment, bulk-import, excel, scheme, lists]
---

# Bulk Import Enrollment

The import has three steps: **Upload**, **Review** and **Done**.
Nothing is saved until you click **Import** on the Review step.

## Step 1: Upload

1. Choose the **Scheme**.
2. Choose an **Enrollment list**. Pick one from the suggestions or
   type a new name. Leave it blank for the scheme's Default list.
3. Choose what happens to **participants already on the list but
   not in the file**:
   - **Keep them on the list**: the file only adds participants.
   - **Remove them, so the list matches the file**: the list ends
     up holding exactly the participants in the file.
4. Drop the file on the page, or click to browse.
5. Click **Review import**.

### The file

Click **Download Excel template** to get the file layout. Put one
Participant ID (Lab ID) per row in column A. Row 1 is the header.
Other columns are ignored.

To change a list you already have, click **Download** for it on
Scheme Enrollments, edit the file, and upload it here.

If the header in column A does not match the template, the page
shows what it expected and what it found.

## Step 2: Review

The tiles count the rows. Click a tile to show only those rows.

| Action | Meaning |
| --- | --- |
| Add | Will be added to the list |
| Already on list | No change |
| Remove | On the list but not in the file. Shown only when you chose to remove them |
| Skipped | The participant is not active, or the row repeats an earlier row |
| Error | No participant has this Participant ID |

Excel often drops leading zeros, so *06157* becomes *6157*. If
exactly one participant matches once the zeros are added back, the
row is used and the review says so.

Click **Import** to save, or **Upload a different file** to start
again.

## Step 3: Done

The page shows how many participants were added and removed. Click
**Open this list** to see the list.
