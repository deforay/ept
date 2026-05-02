---
title: Edit VL Assay / Platform
summary: Rename a Viral Load assay / platform
tags: [config, vl, viral-load, assay, edit]
---

# Edit VL Assay / Platform

Edits an existing HIV Viral Load assay / platform.

## Fields

- **Name** *(required)* — full assay name. Duplicate check
  excludes the current assay.
- **Short Name** *(required)* — shorter label.

## Saving

- **Update** — saves and returns to the VL Assay list.
- **Cancel** — discards changes and returns to the list.

## Tips

- Renames take effect **immediately** — the new name shows up in
  participant dropdowns on the next page load. Existing responses
  keep the foreign-key reference, so historical data still
  resolves to the (renamed) assay.
- There is no soft-delete here. If an assay is being retired,
  prefix the name with a marker like *"[retired] …"* so
  participants know not to pick it, while existing responses stay
  intact.
