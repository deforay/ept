---
title: Map Test Kits to Tests
summary: Select which test kits appear in the participant response form for each test position (Test 1, Test 2, Test 3, etc.)
tags: [config, testkit, mapping, dts, hiv-serology, generic-scheme]
---

# Map Test Kits to Tests

This page controls **which test kits show up in the
participant's response form for each test position**. For
example, if you link *Determine*, *SD Bioline*, and *Stat-Pak*
to **HIV Serology — Test 1**, those three kits (and only those
three) appear in the *Test 1* dropdown for the participant.

> Without this link, the test-position dropdown on the
> participant form is empty. So this step is required for HIV
> Serology / DTS shipments to work.

## How to use it

1. Pick a **test** from the *Choose a Test to map test kit*
   dropdown:
   - **HIV Serology — Test 1 / Test 2 / Test 3** — the three DTS
     test positions.
   - **Generic-scheme tests** — any custom tests you've defined
     under non-DTS schemes appear below the three HIV Serology
     options.
2. The two-pane selector loads all kits that are *available* for
   that position on the left, and all kits *already mapped* on
   the right.
3. **Click a kit name** to move it between the panes. There's no
   drag-and-drop — single click moves a kit one direction.
4. **Filter** either pane by typing in the search box at the top
   — useful when you have hundreds of kits.
5. **Select All** *(left)* — moves every available kit to the
   selected pane.
6. **Deselect All** *(right)* — moves every selected kit back to
   the available pane.
7. **Save Selected** — saves the choices for that test position.
8. **Cancel** — drops unsaved moves and resets the page.

## Which kits appear on the left?

Only kits **already approved** and **scheme-tagged for the
relevant scheme** show up:

- For **HIV Serology — Test 1 / 2 / 3**: kits that have *DTS* in
  their **Scheme** list and the corresponding **Test Number**
  selected (Test 1, Test 2, or Test 3) on the kit's add/edit
  form.
- For **generic-scheme tests**: kits scheme-tagged to that
  generic scheme.

If a kit isn't appearing on the left, check the kit's edit form
— most often the *Test Number* checkbox for that position isn't
ticked.

## Tips

- **Save each test position on its own.** The form only saves
  the position you are currently on. Switching the dropdown to
  a different test drops any unsaved changes.
- **Changing a link does not affect past responses.** Removing
  a kit from a position only changes what a participant can
  pick *from now on*. Old responses keep whatever they recorded.
- If you remove every kit for a position, the participant form
  for that position will show an empty dropdown. Keep at least
  one kit, or stop using that test position.
- Use the **search filter** in either pane for quick edits.
  Long kit lists are hard to scan visually.
