---
title: EID Assays / Platforms
summary: List Early Infant Diagnosis Extraction and Detection assays, add new ones, and toggle their active status
tags: [config, eid, early-infant-diagnosis, assay, platform, extraction, detection]
---

# EID Assays / Platforms

Lists the **Early Infant Diagnosis (EID)** assays / platforms
configured in the system. Participants pick from these when
filling out their EID response form.

EID is split into **two categories** (two tabs on this page):

- **EID Extraction Assay** — the nucleic-acid extraction
  platform.
- **EID Detection Assay** — the amplification / detection
  platform.

The two are configured independently because most labs report
them separately on the response form.

## Page Header

- **Add New EID Assay/Platform** — appears on each tab; opens the
  *Add* form pre-set to that tab's category (Extraction or
  Detection).

## Columns

- **Name** — assay / platform name.
- **Status** — *Active* / *Inactive*. Click the status to toggle
  it (a confirm dialog appears first). Only **Active** assays
  appear in the participant dropdown.

## Tips

- **Toggle status instead of deleting.** EID assays don't have a
  delete action — flipping to *Inactive* removes them from the
  participant dropdown without breaking historical responses.
- The **Extraction** and **Detection** lists are completely
  independent — adding a name on one tab doesn't add it to the
  other. If a single platform does both, add it twice (once per
  category).
- Switching tabs reloads from the same page — no data entry is
  lost (there's no editable form on this page itself).
- There is **no Edit form** for EID assays — once added, the name
  is fixed. If you need to rename, mark the old one *Inactive*
  and add a fresh entry with the corrected name.
