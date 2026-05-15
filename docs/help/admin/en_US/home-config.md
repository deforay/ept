---
title: Home Page Settings
summary: Customize the public home page — hero text, banner image, resource cards, logos, and FAQ
tags: [config, home, landing, hero, banner, faq]
---

# Home Page Settings

This page lets you customize the **public home page** that
participants and visitors see at `/` before they log in. Each
section can be collapsed — open the ones you want to edit.

## Hero Content

The hero is the large banner block at the top of the home page.

- **Line 1 (Main Title)** — the largest headline.
- **Line 2 (Subtitle)** — secondary headline below the main
  title.
- **Line 3 (Description)** — a longer description or
  call-to-action sentence.
- **Video URL** — optional YouTube / video link to embed below
  the hero text.
- **Page Title** — what shows in the browser tab.
- **Additional Link URL** + **Additional Link Text** — an extra
  link button next to the hero (for example, *"Download user
  guide"* pointing to a PDF). Both fields work together. Leave
  both blank to hide the button.

## Home Banner Image

Upload the large hero image that sits behind the hero text.

- **Banner Image** — recommended around **1301×531 px**, JPG /
  PNG / GIF.
- Use a wide, low-contrast image so the hero text remains
  readable.

## Resource Sections

Three configurable cards displayed on the home page. Each card
has an icon and a heading.

- **Resource Section 1 / 2 / 3 — Icon** — pick an icon from the
  built-in icon picker (the small *Icon* button opens a
  searchable pop-up). Stored as a class name (e.g. `bx bx-file`,
  `bi bi-book-half`).
- **Resource Section 1 / 2 / 3 — Section Heading** — the title
  shown on the card.

To set the **links** that go under each card heading, use
**Manage → Home Section Links** (a separate admin page) — those
are loaded from elsewhere rather than set in code here.

## Home Page Logos

Two header logos shown side-by-side on the home page.

- **Logo 1 (Left)** / **Logo 2 (Right)** — recommended size is
  around **80×80 px** for both.
- Useful when your program is co-branded with a partner or
  ministry — left logo for the program, right logo for the
  partner.

## Frequently Asked Questions (FAQ)

- A simple **add-row / remove-row** table for question / answer
  pairs that show up in the FAQ section of the home page.
- Click **+** to add another row, **-** to remove one. Each row
  is one question with its answer.
- Rows are saved in the order you place them; reorder by
  removing and re-adding for now.

## Saving

The **Update** button saves the whole form including any
uploaded images. Required fields are marked with `*`.

## Tips

- After changing logos / banner image, **hard-refresh**
  (Ctrl+Shift+R) the home page to see the new asset — browsers
  often cache image URLs aggressively.
- Use the same icon family across the three Resource Sections
  for visual consistency (all `bx-*`, all `bi-*`, etc.).
- Keep the hero **Description** short — long descriptions wrap
  and push the rest of the page down on small screens.
- The FAQ table doesn't have a max — but more than ~8 questions
  typically make the home page feel cluttered.
