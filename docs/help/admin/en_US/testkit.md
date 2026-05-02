---
title: Test Kits
summary: List, filter, approve, and manage test kits used by participants in their response forms
tags: [config, testkit, dts, hiv-serology, approval]
---

# Test Kits

The **Test Kits** page lists every diagnostic kit configured in the
system. Participants pick from this list when filling out their
response form, so what you approve here determines what they see.

## Page Header Buttons

- **Add New Test Kit** — opens the *Add* form to create a new kit.
- **Map Test kits to Tests** *(only when DTS / HIV Serology is an
  active scheme)* — opens the kit-to-test-position mapping page
  (Test 1 / Test 2 / Test 3, or generic-scheme tests).

## Status Filter

The **status dropdown** above the table filters which kits are
shown:

- **Show All** — every kit, regardless of approval status.
- **Show Approved** — kits marked as approved (visible to
  participants).
- **Show UnApproved** — kits explicitly disapproved.
- **Show Pending** — kits awaiting an approval decision. Picking
  this option also reveals a second dropdown (**Change Status**)
  with bulk actions:
  - **Approve All** — approves every pending kit.
  - **Pending All** — flips approved kits back to pending (rare —
    use for re-review).

Bulk-status changes apply to **every kit currently matching the
filter**, not just visible rows on the page. Confirm before
clicking.

## Columns

- **Test Kit Name** — full name of the kit.
- **Scheme Name** — which scheme(s) it belongs to (DTS, VL, TB,
  etc.).
- **Test Kit Manufacturer** — manufacturer string, free text.
- **Approval Agency** — agency / authority that approved the kit
  (e.g. WHO, FDA, country MOH).
- **Approved** — *Yes* / *No*. Only **Yes** kits show up in the
  participant response form.
- **Created On** — when the kit was first added.
- **Action** — pencil **Edit** button.

## Tips

- A new kit defaults to **Approved = Yes** on creation. Switch it
  to *No* via Edit if you want to hide it from the participant
  form without deleting it.
- Approve kits *before* shipping a survey that depends on them —
  unapproved kits don't appear in participant dropdowns, so a
  late approval mid-cycle can leave participants stuck.
- The **bulk Approve / Pending** actions are filtered by the
  current status dropdown — switch to *Show Pending* first, then
  bulk-approve to onboard a batch of newly-added kits at once.
- For DTS, after approving a kit you usually want to also map it
  to a test position (Test 1 / 2 / 3) via **Map Test kits to
  Tests** — otherwise it won't surface in the response form for
  that position.
