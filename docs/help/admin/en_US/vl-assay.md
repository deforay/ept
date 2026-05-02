---
title: VL Assays / Platforms
summary: List, add, and edit Viral Load assays / platforms used by participants
tags: [config, vl, viral-load, assay, platform]
---

# VL Assays / Platforms

Lists every **HIV Viral Load assay / platform** configured in the
system. Participants pick from this list when filling out their VL
response form, so what's here drives what they can choose.

## Page Header

- **Add New VL Assay/Platform** — opens the *Add* form.

## Columns

- **Name** — full assay / platform name (e.g. *Abbott RealTime HIV-1*,
  *Roche COBAS AmpliPrep / TaqMan HIV-1*).
- **Short Name** — shorter label used where space is tight (report
  tables, dropdowns).
- **Action** — pencil **Edit** button.

## Tips

- Use the **Short Name** for anything that might appear in a tight
  column or chart legend — keep it under ~20 characters.
- Both **Name** and **Short Name** are checked for duplicates as you
  type — you cannot add two assays with the same name.
- VL assays cannot be deleted from this page — if an assay is
  retired, edit the name to flag it (e.g. *"[retired] Roche COBAS
  v1"*) so historical responses still resolve correctly.
