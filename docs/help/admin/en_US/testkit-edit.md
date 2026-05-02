---
title: Edit Test Kit
summary: Update a test kit's details, approval status, scheme/test mapping, and Additional Information settings
tags: [config, testkit, edit, approval, additional-info]
---

# Edit Test Kit

Edits an existing test kit. Same fields as the **Add** form, plus
an **Approved** dropdown so you can flip a kit's visibility to
participants without deleting it.

## Fields

- **Test Kit Name** *(required)* — full kit name. Duplicate-name
  check excludes the current kit.
- **Short Test Kit Name** — optional short label.
- **Approval Agency** *(required)* — agency / authority that
  approved the kit.
- **Source Reference** — optional citation / URL / document.
- **Test Kit Manufacturer** — manufacturer name.
- **Approved** *(required, Yes/No)* — controls whether
  participants see this kit in their response form.
  - **Yes** — visible to participants.
  - **No** — hidden, but not deleted; you can re-enable later.
- **Scheme** *(required, multi-select)* — scheme(s) the kit
  belongs to. When **DTS** is one of them, the **Choose a Test
  Number** field appears.
- **Choose a Test Number to map test kit** — DTS-only test
  position(s): Test 1, Test 2, Test 3. Multi-select.
- **Country Approved/Validated** *(required, Yes/No)*.
- **Allow Additional Information** *(Yes/No)* — toggles the
  extra-data column on the participant response form. When
  **Yes**:
  - **Additional Information Label** — column label on the
    response form (e.g. *OD Value*, *Ct Value*).
  - **Is Additional Information Mandatory?** *(Yes/No)*.
- **Comments** — internal notes; not shown to participants.

## Saving

- **Update** — saves changes and returns to the list.
- **Cancel** — discards changes and returns to the list.

## Tips

- **Flip Approved → No instead of deleting.** Past shipments may
  reference this kit; switching to *No* removes it from the
  participant dropdown without breaking historical data.
- Changing **Allow Additional Information** affects the
  *participant response form going forward* — historical
  responses keep whatever was captured at the time.
- If you switch **Allow Additional Information** from *Yes* to
  *No*, the extra column disappears on new responses, but the
  *Additional Information Label* and any captured values are
  preserved on existing records.
- For DTS, changing **Choose a Test Number** moves the kit
  between test positions immediately — re-check the **Map Test
  kits to Tests** page afterward to confirm the kit is still
  selected (or deselected) at the right positions.
