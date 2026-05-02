---
title: TB Settings
summary: Scoring, report metadata, and report content for TB PT
tags: [config, tb, tuberculosis, scoring]
---

# TB Settings

Settings specific to the **TB** scheme — passing threshold,
report version metadata, and the contact information block printed
on every TB report.

## Fields

- **Passing Score** *(required, 0-100)* — the minimum score a
  participant must achieve to pass a TB shipment. Common value:
  `95`.
- **Report Version** — version string printed in the **PDF
  report footer** (e.g. `v1.4`).
- **Report Effective Date** — date the current report version
  came into effect; printed in the report footer alongside the
  version.
- **Contact Information Content** *(required, rich text)* —
  HTML-formatted block printed on every individual TB report.
  Typical use: PT coordinator name, address, phone, email,
  reference links. Edited via the built-in rich-text editor
  (Summernote), so bold, lists, and links work without
  hand-writing HTML.

## Saving

- **Update** — saves the form. Required fields are marked with
  `*`.
- **Back** — returns to the admin home page without saving.

## Tips

- **Re-evaluate TB shipments after changing the passing score**
  — the new threshold only feeds into pass/fail counts on the
  next evaluation pass.
- Bumping **Report Version** and **Effective Date** is good
  practice whenever you change scoring or content; they let
  auditors trace which version of the rules generated a given
  report.
- The **Contact Information** rich-text block is reused on
  *every* TB report — keep it short and information-dense (a
  footer-style callout works best).
