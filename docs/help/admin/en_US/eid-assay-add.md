---
title: Add EID Assay / Platform
summary: Create a new Early Infant Diagnosis Extraction or Detection assay
tags: [config, eid, early-infant-diagnosis, assay, add, extraction, detection]
---

# Add EID Assay / Platform

Creates a new EID assay / platform under one of the two
categories — **Extraction** or **Detection**. The two categories
have separate lists; pick the right one before saving.

## Fields

- **Choose Category** *(required)* — *Extraction* or
  *Detection*. Pre-picked based on which tab you came from on
  the EID Assays list. Decides which list the new entry lands
  in (and which list is checked for duplicates).
- **Name** *(required)* — assay / platform name. The form
  checks for duplicates within the chosen category.

## Saving

- **Add** — saves and returns to the EID Assays list, pre-filtered
  to the category you just added.
- **Cancel** — discards changes and returns to the EID list.

## Tips

- **Pick the right category first.** Switching *Choose Category*
  after typing a name clears the field, so set the category first
  to avoid retyping.
- A single platform that performs both extraction and detection
  needs to be added **twice** — once per category.
- New assays are **Active** by default — they appear in the
  participant dropdown immediately. Use the status toggle on the
  EID Assays list page to deactivate later if needed.
- There is **no Edit page** for EID assays — once saved, the
  *Name* is fixed. Plan the spelling and casing before clicking
  *Add*.
