---
title: VL (HIV Viral Load) Settings
summary: Scoring, report metadata, and report content for HIV Viral Load PT
tags: [config, vl, viral-load, hiv, scoring]
---

# VL (HIV Viral Load) Settings

Settings for the **HIV Viral Load (VL)** scheme — passing
score, report version details, and the content block on every
per-participant report.

## Fields

- **Passing Score** *(required, 0–100)* — the minimum score a
  participant needs to pass a VL shipment. A common value is
  `95`.
- **Documentation Score** *(required, 0–100)* — the
  documentation share of the score. The panel result and
  documentation together make up the final score.
- **Report Version** — version text printed in the **PDF report
  footer** (for example, `v2.1`). Use this to record which set
  of rules produced the report.
- **Report Effective Date** — date the current report version
  came into effect. Printed in the report footer next to the
  version.
- **Contact Information Content** *(required, rich text)* — a
  block printed on every per-participant VL report. Common use:
  PT coordinator name, address, phone, email, and reference
  links. Edited with the built-in rich-text editor (Summernote),
  so bold, lists and links work without typing HTML.

## Saving

- **Update** — saves the form. Required fields are marked with
  `*`.
- **Back** — returns to the admin home page without saving.

## Tips

- **Re-evaluate VL shipments after changing scoring** — the new
  passing or documentation score only takes effect on the next
  evaluation pass.
- Setting **Manual VL Reference Ranges** for a specific
  shipment is done from *Analyze → Evaluate Responses →* (open
  shipment) *→* **VL Range** button — not here. This page is
  for the scheme-wide defaults.
- The **Report Version** and **Effective Date** go into the
  report footers. Update them whenever you change the scoring
  or content — it helps auditors see which rules were used.
- The **Contact Information** rich-text block is shown on
  *every* VL report. Keep it short — a small footer-style note
  works best.
