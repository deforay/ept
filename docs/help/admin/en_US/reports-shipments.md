---
title: Shipments Report
summary: Every shipment with its responses, pass rate, status and downloads
tags: [reports, shipments, downloads, overview, non-responders, status]
---

# Shipments Report

**Reports → Shipments** lists every shipment in the selected period. Each row shows how many participants responded, how they performed, where the shipment is in the workflow, and the files you can download.

The newest shipment comes first. Click a column heading to sort by that column. The Downloads column does not sort.

## Filters

Click **Expand Filters** to show the filters. The table updates as soon as you change a filter. There is no Search button.

| Filter | What it does | Default |
|---|---|---|
| Shipment date | Shows shipments whose shipment date falls in this range. Click **Clear** in the date picker to show all dates. | Last 3 years |
| Scheme | Shows shipments of one scheme. | All schemes. If only one scheme is active, that scheme. |
| Status | Shows shipments at the selected stages. You can pick more than one. | All statuses |
| Needs attention | Shows only shipments that are open past their due date, or evaluated but not finalized yet. | Off |
| Include cancelled | Adds cancelled shipments to the list. | Off |

Each active filter shows as a chip next to the **Expand Filters** button.

- Click a chip to open the filter panel on that filter.
- Click **×** on a chip to remove that filter.

The **Search** box above the table matches the shipment code, the PT survey code and the scheme name.

## Columns

| Column | What it shows |
|---|---|
| Shipment | The shipment code. Below it, the PT survey code when it differs from the shipment code, and the shipment date. |
| Scheme | The scheme of the shipment. |
| Due date | The response due date. The time only shows when the deadline is not at the end of the day. The timezone of all due dates shows under the column heading. |
| Responses | The percentage of enrolled participants who responded, with a bar. Below it, the number who responded out of the number enrolled. Click the percentage to open the response chart. |
| Pass rate | The percentage of evaluated responses that passed, with a bar. Below it, the number passed out of the number evaluated. Shows **Not evaluated yet** before evaluation. |
| Status | The stage of the shipment. See **Status** below. |
| Downloads | The files for this shipment. See **Downloads** below. |

A participant counts as **responded** when they submitted results, including late results, or reported that they could not test. Participants who did not respond do not count towards the pass rate.

## Breakdown

The button before the shipment code opens the participation breakdown under the row. You can also click anywhere on the row, except on a link or a button. Click again to close it.

The breakdown shows, for that shipment:

- how many participants were enrolled, replied and tested the panel
- how many passed, failed, are awaiting evaluation, or were excluded from scoring
- how many replied but could not test, and how many did not respond
- the number of late responses and of reports downloaded

## Status

| Status | Meaning |
|---|---|
| Not shipped | The shipment is not shipped to participants yet. |
| Open for responses | Participants can submit results. **Past due date** shows under it when the due date has passed. |
| Evaluated | The responses are evaluated. Reports are not generated yet. |
| Reports generated | Draft reports exist. Participants cannot see them yet. A **Draft reports →** link opens them. |
| Finalized | The reports are finalized and visible to participants. A **Reports →** link opens them. |
| Cancelled | The shipment is cancelled. Cancelled shipments only show when **Include cancelled** is on. |

The **Reports →** and **Draft reports →** pages hold the summary report, the file with every participant's report, and the download counts.

## Downloads

Downloads show on one line. **Overview** is the button. The other files follow as links when they apply.

| Download | What you get | When it shows |
|---|---|---|
| Overview | An Excel workbook with the participant list, the reported results and the scores. | From the moment the shipment is shipped. |
| Non-responders | An Excel list of participants who have not responded yet. | Until the shipment is finalized. |
| Feedback | An Excel file of the participants' answers to the feedback questions. | When at least one participant answered them. |
| TB forms | A ZIP file with the result form for every participant. | TB shipments, after the forms are generated and until the shipment is finalized. |

Cancelled shipments have no downloads.
