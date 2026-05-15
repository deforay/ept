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

- Renames take effect **right away** — the new name shows up in
  participant dropdowns on the next page load. Existing responses
  still point to the same assay record, so old data is safe.
- There is no soft-delete here. If an assay is being retired,
  add a marker like *"[retired] …"* to the start of the name so
  participants know not to pick it, while existing responses
  stay intact.

## A bit more on VL assays

Most edits here are simple renames or small corrections. If
you're not sure whether to rename an assay or add a new one, the
safe answer is: **add a new one and retire the old**, so old
reports stay easy to read.

- Use the **manufacturer + product line** when you rename, to
  match what participants see elsewhere.
- Keep the **Short Name** under ~20 characters so it fits in
  tight columns and chart legends.
