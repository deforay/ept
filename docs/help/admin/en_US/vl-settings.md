---
title: VL (HIV Viral Load) Settings
summary: Scoring, report metadata, and report content for HIV Viral Load PT
tags: [config, vl, viral-load, hiv, scoring]
---

# VL (HIV Viral Load) Settings

Settings specific to the **HIV Viral Load (VL)** scheme — passing
thresholds, report version metadata, and the per-participant
report content block.

## Fields

- **Passing Score** *(required, 0-100)* — the minimum score a
  participant must achieve to pass a VL shipment. Common value:
  `95`.
- **Documentation Score** *(required, 0-100)* — the
  documentation portion of the score. (The Panel/result side and
  documentation together drive the final per-participant score.)
- **Report Version** — version string printed in the **PDF
  report footer** (e.g. `v2.1`). Use this to record which set of
  rules / methodology produced the report.
- **Report Effective Date** — date the current report version
  came into effect; printed in the report footer alongside the
  version.
- **Contact Information Content** *(required, rich text)* —
  HTML-formatted block printed on every individual VL report.
  Typical use: PT coordinator name, address, phone, email,
  references / links. Edited with the built-in rich-text editor
  (Summernote), so you can use bold, lists, and links without
  hand-writing HTML.

## Saving

- **Update** — saves the form. Required fields are marked with
  `*`.
- **Back** — returns to the admin home page without saving.

## Tips

- **Re-evaluate VL shipments after changing scoring** — the new
  passing/documentation thresholds only feed into pass/fail on
  the next evaluation pass.
- Setting **Manual VL Reference Ranges** for a specific shipment
  is done from *Analyze → Evaluate Responses →* (open shipment)
  *→* **VL Range** button — not here. This page is for the
  scheme-wide defaults.
- The **Report Version** and **Effective Date** are metadata
  that go into report footers; updating them is good practice
  whenever you adjust the scoring or content. They help auditors
  understand which version of the rules was used.
- The **Contact Information** rich-text block is reused on
  *every* VL report — keep it short (a small footer-style
  callout works best).
