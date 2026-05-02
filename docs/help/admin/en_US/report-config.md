---
title: PDF Report Settings
summary: Header text, logo, layout, and PDF template used on generated reports
tags: [config, reports, pdf, logo, template]
---

# PDF Report Settings

This page controls the **look** of every PDF report the system
produces — per-participant reports, summary reports, certificates,
and so on. The form has three sections.

## Report Header Configuration

- **Report Header Text** *(required)* — free-text that appears at
  the top of every PDF page. Keep it concise (1-2 lines is the
  sweet spot); long header text crowds the rest of the page.
- **Institute Address Position** — choose where the institute
  address (set in *Global Settings*) is shown:
  - **Header** — print it under the report title on every page.
  - **Footer** — print it in the page footer instead.
  - **Don't Show** — omit it entirely.

## Report Logo

- **Select image / Change / Remove** — upload a logo to print on
  every report. The widget shows the current logo if one is
  already saved.
- Recommended format: **PNG or JPG, around 200×150 px**. Bigger
  images will be scaled down; transparent PNGs work well over the
  PDF background.
- Click **Remove** to clear the saved logo. The change only
  persists when you click **Update**.

## Layout & Template Settings

This section has two parts: choosing a layout and (optionally)
uploading a background PDF template.

- **Report Layout** — predefined layout templates shipped with
  the application. Pick the one that matches your reporting
  style. (This dropdown only appears when at least one layout is
  available on disk.)
- **Template Top Margin** — top padding (in pixels) before report
  content starts. Increase if your uploaded template has a tall
  header / logo strip; decrease for a tighter top margin.
  Typical value: `40`.
- **Upload Report Template** — optional PDF that becomes the
  **background** for every generated report (think:
  pre-printed letterhead). Useful when your organisation has a
  branded template with letterhead, watermark, footer lines, etc.
  - When a template is already uploaded, you'll see an inline
    preview with three actions:
    - **View Full Size** — open the PDF in a modal.
    - **Replace** — swap in a new file.
    - **Delete** — remove the template (a confirmation prompts;
      action is irreversible).
  - When no template is uploaded, you get a drop area to choose a
    PDF.

Only `.pdf` files are accepted for the template.

## Saving

The **Update** button at the bottom (or the floating
*Update / Cancel* card in the bottom-right) saves the whole form,
including any uploaded logo or template. Required fields are
marked with `*`.

## Tips

- Test changes by **regenerating** a single shipment's reports
  from *Analyze → Generate Reports* — the new layout / margin /
  template kicks in on the next generation.
- If you replace the logo or template, **regenerate** any
  important draft reports so the new visual is reflected.
  Finalized reports keep the visuals from the time they were
  finalized.
- Top margin too small? Content overlaps the template's header.
  Top margin too large? You waste space on the first page. Iterate
  with regenerate-and-preview until it looks right.
- Keep the **Report Header Text** short — long headers wrap and
  push report content down on every page.
