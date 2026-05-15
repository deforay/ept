---
title: DTS (HIV Serology) Settings
summary: Scoring, sample and testing options, and national algorithm test-kit rules for HIV Serology PT
tags: [config, dts, hiv-serology, scoring, algorithm, testkit]
---

# DTS (HIV Serology) Settings

These settings control scoring and reporting for the **HIV
Serology (DTS)** scheme. The page has three sections.

## Scoring Configuration

How DTS responses are scored.

- **Minimum Passing Score** *(required, %)* — the score a
  participant needs to **pass** a shipment. Range 0–100. A
  common value is `95`.
- **Panel/Shipment Score** *(required, pts)* — points given for
  the panel result (the actual rapid-test results).
- **Documentation Score** *(required, pts)* — points given for
  the documentation questions.
- **Algorithm Score** *(required, %)* — bonus or penalty for
  using the correct national diagnostic algorithm.

> **Panel + Documentation must equal 100.** As you type in one
> field, the other adjusts so the total stays at 100.

## Sample & Testing Options

Day-to-day rules and report details.

- **Days for Sample Rehydration** *(required)* — how soon a
  participant may test the rehydrated sample:
  - **0 — Allow same-day testing**
  - **1 — Testing after 1 day only**
  - **2 — Testing after 2 days only**
- **Collect Additional Testkits** *(Yes/No)* — let participants
  list any extra or backup kits they used.
- **Report Version** — version text printed in the PDF report
  footer (for example, `v3.2`).
- **Report Effective Date** — date printed in the report footer
  to show when the current methodology took effect.
- **DTS Scheme Type** *(required)* — pick the scheme:
  - **Standard Types**: *Standard*, *Updated 3-Tests*
  - **Country Specific**: Ghana, Malawi, Myanmar, Sierra Leone,
    Côte d'Ivoire, DRC, Vietnam
  - Your choice changes which algorithms are available below
    and which extra fields appear.
- **Allowed Algorithms** *(required, multi-select)* — which
  algorithm options participants can pick on their response
  form. Includes *Serial*, *Parallel*, and the national
  algorithms for each supported country.
- **Disable Other Test Kit Option** *(required, Yes/No)* — when
  *Yes*, the *Other* option is removed from the participant's
  kit dropdown, so they must pick one of the kits you have set
  up.

### Malawi / Updated 3-Tests only

When **DTS Scheme Type** is *Malawi* or *Updated 3-Tests*, two
extra toggles appear:

- **Display Sample Condition Fields** *(Yes/No)* — show sample
  condition inputs on the participant result form.
- **Display Repeat Test Fields** *(Yes/No)* — let participants
  record results for samples they re-tested.

## National HIV Rapid Test Algorithm

This section is **optional**. Use it only if you want the system
to require a specific test-kit at each test position.

> **How it works.** A participant's response only counts as a
> pass for a sample if the kit they pick at each test position
> matches one of the kits you have selected here. Leave a
> position blank to skip this check for that position.
>
> **Set up the test kits first** under *Manage → Test Kits →
> Standard Kit*. Otherwise the kit dropdowns here will be empty.

The section is split into three panels (each one shows up
depending on your scheme):

### Standard DTS Panel

- **Test 1 Kit Selections** — kits allowed at test position 1.
- **Test 2 Kit Selections** — kits allowed at test position 2.
- **Test 3 Kit Selections** — kits allowed at test position 3.

Each is a multi-select — you can allow more than one kit per
position.

### DTS + Syphilis Combined Panel

Same Test 1 / 2 / 3 multi-selects for the combined HIV +
Syphilis panel. Only used when your scheme includes syphilis.

### DTS + RTRI Combined Panel

Same structure for the combined HIV + RTRI (Recent Infection)
panel. Only used when your scheme includes RTRI.

## Saving

Click **Update** to save the whole form. Required fields are
marked with `*`.

## Tips

- **Re-evaluate one shipment** after changing scoring or
  algorithm settings. The new rules only take effect on the
  next re-evaluation.
- **Don't change the passing score after a survey is shipped**
  unless you plan to re-evaluate every shipment in it.
- Set up **test kits first** before filling in the National HIV
  Rapid Test Algorithm section, or the kit dropdowns will be
  empty.
- After you switch **DTS Scheme Type**, re-check the **Allowed
  Algorithms** list — the available options may have changed.
