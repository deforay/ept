---
title: DTS (HIV Serology) Settings
summary: Scoring, sample/testing options, and national algorithm test-kit enforcement for HIV Serology PT
tags: [config, dts, hiv-serology, scoring, algorithm, testkit]
---

# DTS (HIV Serology) Settings

These settings control evaluation and reporting for the **HIV
Serology (DTS)** scheme — passing thresholds, sample / testing
options, and (optionally) national algorithm test-kit enforcement.
Three sections.

## Scoring Configuration

How DTS responses are scored.

- **Minimum Passing Score** *(required, %)* — the score
  participants must achieve to **pass** a shipment. Range 0-100.
  Common value: `95`.
- **Panel/Shipment Score** *(required, pts)* — points allocated
  to the panel result itself (the actual rapid-test results).
- **Documentation Score** *(required, pts)* — points allocated
  to documentation / metadata fields.
- **Algorithm Score** *(required, %)* — bonus / penalty for
  using the correct national diagnostic algorithm.

> **Panel + Documentation must equal 100.** The form auto-updates
> the other field as you type into one of them so they stay in
> sync.

## Sample & Testing Options

Operational rules and report metadata.

- **Days for Sample Rehydration** *(required)* — how soon a
  participant may test the rehydrated sample:
  - **0 — Allow same-day testing**
  - **1 — Testing after 1 day only**
  - **2 — Testing after 2 days only**
- **Collect Additional Testkits** *(Yes/No)* — let participants
  declare any extra / backup kits they used.
- **Report Version** — version string printed in the PDF report
  footer (e.g. `v3.2`).
- **Report Effective Date** — date printed in the report footer
  to indicate when the current guidelines / methodology took
  effect.
- **DTS Scheme Type** *(required)* — pick the scheme variant:
  - **Standard Types**: *Standard*, *Updated 3-Tests*
  - **Country Specific**: Ghana, Malawi, Myanmar, Sierra Leone,
    Côte d'Ivoire, DRC, Vietnam
  - The choice affects which algorithms are available below and
    which optional fields appear (e.g. Malawi-specific toggles).
- **Allowed Algorithms** *(required, multi-select)* — which
  algorithm options participants may pick from on their response
  form. Includes *Serial*, *Parallel*, plus the national
  algorithms for each supported country.
- **Disable Other Test Kit Option** *(required, Yes/No)* — *Yes*
  removes the *Other* fallback kit from the participant's drop
  down so they must choose one of the configured kits.

### Malawi / Updated 3-Tests only

When **DTS Scheme Type** is *Malawi* or *Updated 3-Tests*, two
extra toggles appear:

- **Display Sample Condition Fields** *(required, Yes/No)* —
  show the sample-condition (quality, integrity) inputs on the
  participant result form.
- **Display Repeat Test Fields** *(required, Yes/No)* — let
  participants record repeat-test results for samples they
  re-tested.

## National HIV Rapid Test Algorithm

This section is **optional**. Configure it only if you want the
system to enforce a specific test-kit per test-position pairing.

> **How enforcement works.** When the standard panel is
> configured, a participant's response only counts as *passing*
> for a sample if their declared kit at each test position
> matches one of the kits you've selected for that position
> here. Leave a position blank (or all positions blank) to
> disable enforcement for that panel.
>
> **Test kits must already exist** in *Manage → Test Kits →
> Standard Kit* — that's where you create the kits before they
> show up as options in this section.

The section is split into three panels (each enabled depending on
your scheme):

### Standard DTS Panel

- **Test 1 Kit Selections** — kits permitted at test position 1.
- **Test 2 Kit Selections** — kits permitted at test position 2.
- **Test 3 Kit Selections** — kits permitted at test position 3.

Each is a multi-select; you can allow multiple kits per
position.

### DTS + Syphilis Combined Panel

Same Test 1 / Test 2 / Test 3 multi-selects, but for the
combined HIV + Syphilis panel. Only relevant when your scheme
includes syphilis.

### DTS + RTRI Combined Panel

Same structure for the combined HIV + RTRI (Recent Infection)
panel. Only relevant when your scheme includes RTRI.

## Saving

The **Update** button saves the whole form. Required fields are
marked with `*`.

## Tips

- **Run a single shipment through evaluation** after changing
  scoring or algorithm settings — the new rules only kick in on
  the next *Re-Evaluate*.
- **Don't change passing score after a survey is shipped**
  unless you intend to re-evaluate every shipment in it. Score
  rule changes only re-flow into pass/fail counts on the next
  evaluation pass.
- Set up **test kits first** at *Manage → Test Kits → Standard
  Kit* before configuring the National HIV Rapid Test Algorithm
  section — otherwise the kit dropdowns will be empty.
- Switching **DTS Scheme Type** swaps the visible options
  (Malawi-specific toggles, country algorithms). Re-check the
  Allowed Algorithms list after switching to make sure the
  selection still makes sense.
