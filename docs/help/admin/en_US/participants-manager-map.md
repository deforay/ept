---
title: Map Participants to Data Managers
summary: Assign participants to a Data Manager so the right person sees the right participants when filling out responses
tags: [participants, data-manager, mapping, pt-login, bulk-import]
---

# Map Participants to Data Managers

This page links **participants** to a **Data Manager (Participant
Login)**. A Data Manager logs into the participant side of the app
and sees only the participants mapped to them. So this page decides
who enters results for which participants.

The same screen opens as a pop-up from the **Map** button on the
Data Managers list. In the pop-up, the Data Manager is already
chosen and shown in the title.

## Buttons at the top

These buttons are not in the pop-up.

- **Bulk Import Mapping**: opens a pop-up to upload a file of
  many mappings at once. See *Bulk import* below.
- **Unmapped Participants**: downloads a list of every participant
  who is **not mapped to any Data Manager**. Nobody can enter
  results for these participants.

## Step 1: Choose a Data Manager

Type a name or email in **Data Manager (Participant Login)**. The
lists load as soon as you pick one.

**Country**, **Province** and **District** narrow the participants
on the left. Click **More filters** for **Institute**, **Network
Tier** and **Affiliation**. The number on the button shows how many
of those are in use.

Filters only narrow the **Not mapped** list. Participants already
mapped stay on the right no matter what. Changing a filter keeps
any changes you have not saved yet.

## Step 2: Move participants

- **Not mapped** (left): active participants this Data Manager
  does not see.
- **Mapped** (right): active participants this Data Manager sees.

Click a participant to move it to the other list. Each row shows
the Lab ID and the name. Type in a list's search box to find a
participant by name or Lab ID.

- **Add all shown**: moves every participant the left search
  shows to the right.
- **Remove all shown**: moves every participant the right search
  shows to the left.

With an empty search box, these move the whole list.

Colours show what saving will do:

| Mark | Meaning |
| --- | --- |
| Grey tick | Mapped and saved |
| Green **+** | Will be added when you save |
| Orange **−** | Will be removed when you save |

Unsaved rows are listed first, so you can always see them. The
counts next to each list title show how many will be added or
removed.

## Step 3: Save

The bar at the bottom shows how many participants will be added
and removed.

- **Save changes**: saves the Mapped list for this Data Manager.
- **Discard**: throws away your unsaved changes and reloads the
  lists.
- **Close** *(pop-up only)*: closes the pop-up.

If you pick another Data Manager, close the pop-up or leave the
page with unsaved changes, the page asks you first.

Saving with an empty **Mapped** list removes every participant
from this Data Manager. The page asks you to confirm.

Mappings to **inactive** participants are not shown in the lists.
Saving keeps them.

## Bulk import

Click **Bulk Import Mapping** and choose an Excel or CSV file.
Row 1 is the header and is skipped. Each other row maps one
participant to one Data Manager:

| Column | Contents |
| --- | --- |
| B | Lab ID (required) |
| C | Data Manager name |
| D | Data Manager primary email (required) |
| E | Secondary email |
| F | Mobile |

If no Data Manager has the email in column D, a new one is created
with the default password. Bulk import only adds mappings. It never
removes one.

## Tips

- **Check Unmapped Participants before a shipment.** It is the
  quickest way to find participants nobody can enter results for.
- **Use filters for country or institute clean-up.** Filter, search,
  then use **Add all shown**.
- A participant can be mapped to **more than one Data Manager**.
  Add them on each Data Manager's list.
