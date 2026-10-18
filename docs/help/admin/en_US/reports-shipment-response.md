---
title: Shipment Response Report
summary: Who responded to each shipment, who did not, and how they performed
tags: [reports, shipments, response rate, non-responders, pass rate, participants]
---

# Shipment Response Report

**Reports → Shipment Response Report** shows how many participants responded to each shipment, and lists every participant with their response and result.

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

## By shipment

Each row is one shipment. The counts include only the participants that match the filters.

| Column | What it shows |
|---|---|
| Shipment | The shipment code and the shipment date. |
| Scheme | The scheme of the shipment. Hidden when a scheme is picked. |
| Enrolled | The number of participants enrolled. |
| Responded | The percentage of enrolled participants who responded, with a bar. Below it, the number who responded out of the number enrolled. |
| Not responded | The number of participants who did not respond. |
| Pass rate | The percentage of evaluated responses that passed, with a bar. Below it, the number passed out of the number evaluated. Shows **Not evaluated yet** before evaluation. |
| Failed | The number of participants who failed. |

| Click | Result |
|---|---|
| A shipment code | The **Participants** table lists only that shipment. Click the code again to list every shipment. |
| A **Not responded** count | The **Participants** table lists the participants of that shipment who did not respond. |
| A **Failed** count | The **Participants** table lists the participants of that shipment who failed. |
| Anywhere else on the row | Opens the participation breakdown of the shipment under the row. Click again to close it. |

The breakdown counts every participant of the shipment. It does not apply the place filters.

## Participants

Each row is one participant in one shipment. A participant enrolled in three shipments has three rows.

| Column | What it shows |
|---|---|
| Participant | The participant name. Below it, the participant ID and the institute name. |
| Location | The district, province and country. |
| Shipment | The shipment code and the shipment date. |
| Response | **Responded**, **Late**, **Could not test** or **Not responded**. |
| Responded On | The date the response was submitted. |
| Result | **Pass**, **Fail**, **Excluded** or **Not evaluated**. Empty before evaluation. |

The buttons above the table narrow it by response.

| Button | Lists the participants who |
|---|---|
| All | Are enrolled. |
| Responded | Submitted results, including late results, or reported that they could not test. |
| Late | Submitted results after the due date. |
| Could not test | Reported that they could not test the panel. |
| Not responded | Did not respond. |
| Failed | Responded and failed. |

The **Search** box above the table matches the participant name, the participant ID, the institute name, the place and the shipment code.

## Download Excel

**Download Excel** saves the **Participants** table as an Excel file. The file uses the filters, the response button and the search of the table on screen. It includes every page of the table.
