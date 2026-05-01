# In-app help content

Markdown sources for the in-app help drawer (Cmd/Ctrl+/) and the standalone
help pages at `/help` (participant) and `/admin/help` (admin).

## Layout

```
docs/help/
  admin/
    en_US/
      <slug>.md
    fr_FR/
      <slug>.md      (optional — falls back to en_US per file)
  participant/
    en_US/
      <slug>.md
    fr_FR/...
```

## File format

Each `.md` starts with frontmatter, then the body:

```markdown
---
title: Page Title
summary: One-line description shown in the topic index
tags: [tag1, tag2]
---

# Heading

Body markdown here…
```

## Adding a topic

1. Drop a new `.md` into the appropriate `{audience}/en_US/` directory.
2. Map the page's slug in the layout's `$helpSlugMap`:
   - Admin: `application/layouts/scripts/admin.phtml` (keyed by `controllerName/actionName`).
   - Participant: `application/layouts/scripts/layout.phtml` (keyed by `$activeMenu`).
3. The catalog picks it up on the next request — no migration or cache step.

## Translating

Drop a translated copy into `{audience}/{locale}/{slug}.md`. Missing
translations gracefully fall back to `en_US`. Frontmatter (title, summary,
tags) is also translated by being authored in each locale's file.
