---
title: Participant Performance Report
summary: Each participant's results across shipments, with pass rate, failures and average score
tags: [reports, participants, performance, pass rate, scores, failures]
---

# Participant Performance Report

**Reports → Participant Performance Report** shows how each participant performed across shipments. Each row is one participant, with a strip of results, one per shipment.

The report shows as soon as the page opens. The filters apply as soon as you change them. There is no Get Report button.

## Filters

Click **Expand Filters** to show the filters.

| Filter | What it does | Default |
|---|---|---|
| Shipment date | Includes shipments whose shipment date falls in this range. Click **Clear** in the date picker to include all dates. | Last 3 years |
| Scheme | Includes shipments of one scheme. | All schemes. If only one scheme is active, that scheme. |
| Shipment | Includes only the shipments you pick. You can pick more than one. The list holds every shipment in the date range and scheme that is not cancelled. | All shipments in the date range |
| Country, Region, Province, District | Includes only participants in that place. Each filter shows only when participants have that field filled in. | All |

Changing the date range or the scheme reloads the **Shipment** list. A picked shipment stays picked while it is still in the list.

Each active filter shows as a chip next to the **Expand Filters** button.

- Click a chip to open the filter panel on that filter.
- Click **×** on a chip to remove that filter.

Cancelled shipments never show in this report.

## Performance buttons

The buttons above the table narrow it to a group of participants. Each button shows how many participants it lists.

| Button | Lists the participants who |
|---|---|
| All | Are enrolled in at least one shipment. |
| Failed at least once | Failed one or more shipments. |
| Missed a response | Did not respond to one or more shipments. |
| Never failed | Passed one or more shipments and failed none. |

## Table

Each row is one participant. The counts include only the shipments that match the filters.

| Column | What it shows |
|---|---|
| Participant | The participant name. Below it, the participant ID and the institute name. |
| Location | The district, province and country. |
| Shipments | The number of shipments the participant is enrolled in. Below it, the number they responded to. |
| Pass rate | The percentage of evaluated responses that passed, with a bar. Below it, the number passed out of the number evaluated. Shows **Not evaluated yet** before evaluation. |
| Failed | The number of shipments the participant failed. |
| Average score | The average score of the evaluated responses. Shows **No score** before evaluation. |
| Results, oldest first | One square per shipment, from the oldest shipment to the newest. |

The table lists the participants with the most failures first. Click a column heading to sort by that column.

The **Search** box above the table matches the participant name, the participant ID, the institute name and the place.

## Results strip

Each square is one shipment. The color shows the result. A pass or a fail shows the score inside the square.

| Square | Meaning |
|---|---|
| Light green | Pass |
| Red | Fail |
| Dashed outline | Not responded |
| Yellow | Could not test |
| Grey | Excluded |
| Light grey outline | Responded, not evaluated yet |

Point at a square to see the shipment code, the shipment date, the result and the score.

The strip shows the 12 newest shipments. A **+** number before the squares counts the older shipments that do not fit.

## Shipments of a participant

Click a participant to show their shipments under the row, newest first. Click the participant again to hide them.

| Column | What it shows |
|---|---|
| Shipment | The shipment code and the shipment date. |
| Scheme | The scheme of the shipment. |
| Responded On | The date the response was submitted. **Late** marks a response submitted after the due date. |
| Score | The score. Empty unless the response passed or failed. |
| Result | **Pass**, **Fail**, **Not responded**, **Could not test**, **Excluded** or **Not evaluated**. |

## Download Excel

**Download Excel** saves the report as an Excel file with two sheets.

| Sheet | Contents |
|---|---|
| Participants | One row per participant, with every count from the table. |
| By shipment | One row per participant and shipment, with the score and the result. |

The file uses the filters, the performance button and the search of the report on screen. It includes every participant, not only the current page.
