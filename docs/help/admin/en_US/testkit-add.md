---
title: Add Test Kit
summary: Create a new test kit, optionally enabling Additional Information capture for OD / Ct / custom values
tags: [config, testkit, add, additional-info]
---

# Add Test Kit

Adds a new diagnostic test kit to the system. Once added, it
becomes available in the participant response form for the
selected scheme(s).

## Fields

- **Test Kit Name** *(required)* — full name of the kit. Checked
  for duplicates as you tab out of the field.
- **Short Test Kit Name** — optional shorter label (used where
  space is tight, like report tables).
- **Approval Agency** *(required)* — the agency that approved /
  validated the kit (WHO, FDA, MOH, etc.). Free text.
- **Country Approved/Validated** *(required, Yes/No)* — whether
  the kit has country-level approval.
- **Source Reference** — optional citation / URL / document name.
- **Test Kit Manufacturer** — free text manufacturer name.
- **Scheme** *(required, multi-select)* — one or more schemes
  this kit applies to (DTS, VL, TB, etc.). When **DTS** is
  selected, the **Choose a Test Number** field appears (see
  below).
- **Choose a Test Number to map test kit** *(required when DTS is
  selected)* — pick which DTS test position(s) this kit applies
  to: **Test 1**, **Test 2**, **Test 3**. You can pick multiple.
- **Allow Additional Information** *(Yes/No)* — see the section
  below.
- **Comments** — free-text notes; not shown to participants.

## Allow Additional Information

When set to **Yes**, two extra fields appear, and the participant
response form gets an extra column next to this kit so they can
record a numeric / text reading alongside the result:

- **Additional Information Label** — what to call the column on
  the response form (e.g. *OD Value*, *Ct Value*, *Reading*,
  *Lot Number*).
- **Is Additional Information Mandatory?** *(Yes/No)* — if
  **Yes**, participants cannot submit the form without filling
  this field for the kit.

This is the right place to capture quantitative readings (optical
density for ELISA, Ct values for PCR) that complement the
pass/fail result.

## Saving

- **Add** — saves the form and returns to the test-kit list.
- **Cancel** — discards changes and returns to the list.

A new kit is **automatically marked as Approved** on save — it
appears in participant dropdowns immediately. Use the *Edit* form
to flip it to unapproved if you want to hide it.

## Tips

- Pick a **descriptive Test Kit Name** — it shows up in
  participant dropdowns and on reports. *"Determine HIV-1/2 Ag/Ab
  Combo (Abbott)"* is more useful than *"Determine"*.
- For DTS, **always set the Test Number(s)** — a kit with no test
  position assigned won't surface in the participant form for any
  position. You can also do (or change) this later from **Map
  Test kits to Tests**.
- Use **Allow Additional Information** sparingly — every extra
  mandatory column adds friction to the response form. Reserve
  it for kits where the numeric reading is genuinely needed for
  evaluation or auditing.
- The *Comments* field is a good place to track internal notes
  ("validated against panel X", "phasing out 2026") — none of
  this is shown to participants.
