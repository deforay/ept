---
title: TB Settings
summary: Scoring, report metadata, and report content for TB PT
tags: [config, tb, tuberculosis, scoring]
---

# TB Settings

Settings for the **TB** scheme — passing score, report version
details, and the contact information block printed on every TB
report.

## Fields

- **Passing Score** *(required, 0–100)* — the minimum score a
  participant needs to pass a TB shipment. A common value is
  `95`.
- **Report Version** — version text printed in the **PDF report
  footer** (for example, `v1.4`).
- **Report Effective Date** — date the current report version
  came into effect. Printed in the report footer next to the
  version.
- **Contact Information Content** *(required, rich text)* — a
  block printed on every per-participant TB report. Common use:
  PT coordinator name, address, phone, email, and reference
  links. Edited with the built-in rich-text editor (Summernote),
  so bold, lists and links work without typing HTML.

## Saving

- **Update** — saves the form. Required fields are marked with
  `*`.
- **Back** — returns to the admin home page without saving.

## Tips

- **Re-evaluate TB shipments after changing the passing score**
  — the new score only takes effect on the next evaluation
  pass.
- Updating the **Report Version** and **Effective Date** is
  good practice whenever you change scoring or content. It
  lets auditors see which version of the rules produced a
  given report.
- The **Contact Information** rich-text block is shown on
  *every* TB report. Keep it short — a footer-style note works
  best.
