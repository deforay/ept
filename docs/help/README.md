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

**Body content** — Drop a translated copy into `{audience}/{locale}/{slug}.md`.
Missing translations gracefully fall back to `en_US`. Long-form prose
translates better as whole documents than as gettext strings.

**Frontmatter (title, summary, tags)** — Translated through the normal
PO/MO gettext flow. After authoring or editing a topic, run:

```bash
php bin/generate-help-translation-strings.php
```

This regenerates `application/languages/help-translation-strings.php`,
a stub file that exists only so xgettext can discover the frontmatter
strings. Translators then translate them in their PO file like any other
UI string. The catalog runs every frontmatter value through the
translator at read time, so the topic index, drawer header, and search
are localised even when a body translation hasn't landed yet.
