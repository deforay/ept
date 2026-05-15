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
- **Approval Agency** *(required)* — the agency that approved
  the kit (WHO, FDA, MOH, and so on). Free text.
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

A new kit is **marked as Approved by default** on save — it
appears in participant dropdowns right away. Use the *Edit*
form to switch it to unapproved if you want to hide it.

## Tips

- Pick a **clear Test Kit Name** — it shows up in participant
  dropdowns and on reports. *"Determine HIV-1/2 Ag/Ab Combo
  (Abbott)"* is more useful than *"Determine"*.
- For DTS, **always set the Test Number(s)**. A kit with no
  test position will not appear in the participant form for
  any position. You can also change this later from **Map Test
  kits to Tests**.
- Use **Allow Additional Information** sparingly — every extra
  required column adds work for participants. Use it only when
  the numeric reading is really needed.
- The *Comments* field is a good place for internal notes (for
  example, "checked against panel X", "phasing out 2026").
  None of this is shown to participants.
