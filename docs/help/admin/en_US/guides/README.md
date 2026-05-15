---
title: Guides (internal — schema notes)
summary: How workflow guides are structured. NOT shown in the help drawer.
internal: true
---

# Guide file schema

Each guide is one `.md` file in this folder. The frontmatter is the
index of steps; the body has one H2 per step in the same order.

```yaml
---
title: How to add a shipment            # shown in chip + topic list
summary: One-line description for the guides list
audience: admin                          # admin | participant
estimated_minutes: 5                     # rough total time
tags: [guide, shipment, workflow]
steps:
  - id: 1
    title: Create or open the PT Survey
    # target_pages: controller/action slugs where this step is "active".
    # The drawer matches these against the current page to show
    # "✓ You're here" vs the "Open this screen" button.
    target_pages: [distributions/index, distributions/add]
  - id: 2
    title: Click "Add Scheme"
    target_pages: [distributions/index]
  ...
---
```

Body section per step (use the same titles as the frontmatter):

```markdown
## Step 1: Create or open the PT Survey

Plain-language explanation of what the user does on this screen.

Bullets, **bold UI labels**, short paragraphs.

## Step 2: Click "Add Scheme"

...
```

## Conventions

- `target_pages` slugs are `controller/action` (same shape as the
  slug map in `application/layouts/scripts/admin.phtml`). Use
  `controller/index` to match any action under that controller.
- Keep each step under ~150 words. Users read one step at a time.
- The first step often has no `target_pages` if it's "Make sure
  you have X before starting" — that's fine; the drawer will show
  no "✓ You're here" marker.
- Use simple language. See [[feedback_no_dev_jargon]] in project
  memory. No "modal", "configured", "mapped" — use "pop-up",
  "set up", "linked". Same rules as per-page help.
- Link to per-page help docs with `[See: Add Shipment](../shipment-add.md)`
  when a step is best explained on its own page.

## What the drawer does with this

1. Reads frontmatter — knows the step list and current step.
2. Splits body on H2 headings — renders only the current step.
3. Reads `target_pages` for current step — if the user's current
   page slug is in that list, shows "✓ You're here". Otherwise
   shows an "Open this screen" link.
4. Tracks `sessionStorage.ept.help.activeGuide` +
   `ept.help.guideStep` so the guide stays pinned across page
   navigation.

## File naming

Action-first verbs, kebab-case:
- `add-a-shipment.md`
- `enroll-participants.md`
- `evaluate-and-finalize.md`
- `set-up-a-new-pt-programme.md`
