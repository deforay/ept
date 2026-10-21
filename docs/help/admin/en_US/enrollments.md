---
title: Scheme Enrollments
summary: See every enrollment list per scheme, create and edit lists, and clone, archive, rename, merge or delete them
tags: [enrollment, scheme, participants, lists, bulk-import]
---

# Scheme Enrollments

An **enrollment list** is a named group of participants for one
scheme. A participant is enrolled in a scheme when they are on at
least one of its lists.

Each scheme has a **Default** list, marked *Main*. The participant
form adds to it when you tick a scheme for a participant. Other
lists are rosters you keep for a round, a region or a shipment,
such as *Round 3 Testing Sites*. Ship It offers your lists as
presets when you choose who gets a shipment.

Each list name can be used in **one scheme only**, because Ship It
offers lists by name. Every scheme has its own Default list, so
Default is the one exception. ePT refuses a new name, a rename, a
clone or an import that would reuse another scheme's list name.
Lists that shared a name before this rule are marked *Name also
used in another scheme*. Rename one of them.

Lists of inactive schemes are not shown on this page or in Ship
It, and their names are free to use again.

## Buttons at the top

- **New list**: choose a scheme and type a list name, then pick
  the participants. Leave the name blank to open the scheme's
  Default list.
- **Bulk Import Enrollment**: fill a list from an Excel file. You
  see what will change before anything is saved.

The **scheme** box next to the tabs shows one scheme on both tabs.

## Lists tab

Lists are grouped under their scheme. Each scheme's header shows
how many active participants are enrolled in it, and a **New list**
link for that scheme.

| Column | Meaning |
| --- | --- |
| List | The list name |
| Participants | Active participants on the list |
| Last changed | When a participant was last added |

Each list has these buttons:

- **Edit**: opens the list to add or remove participants.
- **More**: the other actions for the list.
  - **Import from file**: opens Bulk Import Enrollment for this
    list.
  - **Download**: an Excel file of the list. It uses the import
    layout, so you can edit it and upload it again.
  - **Clone list**: starts a new list with the same participants.
  - **Rename**, **Merge into another list**, **Archive** and
    **Delete**. The Default list does not have these.

### Clone a list

Use **Clone list** to start a new list from an existing one, for
example next round's list from this round's list.

1. Open **More** on the list and click **Clone list**.
2. Choose the scheme for the new list. It can be the same scheme
   or another one.
3. Type a name for the new list and click **Clone**. The name
   cannot be one that another list already uses.

The new list has the active participants of the original list. It
opens so you can add or remove participants. The original list
does not change.

### Archive, rename, merge and delete

- **Archive**: moves the list to the *Archived lists* group at the
  bottom and stops Ship It offering it. Its participants stay
  enrolled. Use **Reactivate** to bring it back.
- **Rename**: if the scheme already has a list with the new name,
  use Merge instead.
- **Merge into another list**: adds everyone on this list to a
  list you choose in the same scheme, then removes this list.
- **Delete**: removes the list. Type the list name to confirm.
  Its participants stay enrolled through any other list they are
  on.

### Older lists without a scheme

Ship It once saved lists without recording their scheme. They are
in a collapsed group at the bottom. Click **Assign scheme** to give
one its scheme. It then becomes a normal list you can edit. If the
scheme already has a list with that name, the two are merged.

## By participant tab

One row per active participant:

- **Schemes**: the schemes they are enrolled in.
- **First enrolled**: the date of their earliest enrollment.
- **Lists**: click **View** to see each scheme and list they are
  on. Click a list name to open it.

Type in the boxes under the headings to search one column.
