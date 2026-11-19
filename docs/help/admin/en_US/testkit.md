---
title: Test Kits
summary: List, filter, approve, and manage test kits used by participants in their response forms
tags: [config, testkit, dts, hiv-serology, approval]
---

# Test Kits

The **Test Kits** page lists every diagnostic kit set up in the
system. Participants pick from this list when filling in their
response form. What you approve here is what they see.

## Page Header Buttons

- **Add New Test Kit** — opens the *Add* form to create a new kit.
- **Map Test kits to Tests** *(only when DTS / HIV Serology is
  an active scheme)* — opens the page that links kits to test
  positions (Test 1 / Test 2 / Test 3, or generic-scheme
  tests).

## Status Filter

The **status dropdown** above the table filters which kits are
shown:

- **Show All** — every kit, regardless of approval status.
- **Show Approved** — kits marked as approved.
- **Show UnApproved** — kits explicitly disapproved.
- **Show Pending** — kits awaiting an approval decision. Picking
  this option also reveals a **Change Status** dropdown with
  **Approve All**. It approves every pending kit in the system,
  and they then show under **Show Approved**.

**Approve All** ignores the other filters and the visible rows.
It changes every pending kit, so check the pending list first.
To change one kit, click **Edit** on its row.

## Columns

- **Test Kit Name** — full name of the kit.
- **Scheme Name** — which scheme(s) it belongs to.
- **Test Kit Manufacturer** — manufacturer string, free text.
- **Is MoH Approved?** — whether the Ministry of Health approved
  the kit.
- **Is PT Provider Validated?** — whether your PT programme
  validated the kit.
- **Approved** — *Yes* / *No*. Set by **Active** on the edit form,
  or by **Approve All** for pending kits.
- **Created On** — when the kit was first added.
- **Action** — pencil **Edit** button.

## Tips

- A new kit defaults to **Approved = Yes** when created.
- Participants can pick a kit when it is mapped to the scheme and
  test number. If **Only Approved Test Kits for Participants** is
  *Yes* in **ePT Global Settings**, the kit must also be approved.
  A kit a participant already saved on a response stays on that
  response.
- **Approve All** appears only after you choose *Show Pending*.
- For DTS, after approving a kit you usually want to also link
  it to a test position (Test 1 / 2 / 3) via **Map Test kits
  to Tests** — otherwise it will not show up in the response
  form for that position.
