---
title: VL Assay Distribution
summary: VL results per assay, with the pass rate of each assay
tags: [reports, vl, viral load, assay, platform, pass rate]
---

# VL Assay Distribution

**Reports → VL Assay Distribution** shows which assays participants used in VL shipments, and how the responses of each assay performed.

The report shows as soon as the page opens. The filters apply as soon as you change them. There is no Get Report button.

## Filters

Click **Expand Filters** to show the filters. The report covers VL only, so there is no scheme filter. If VL is not active, the page says so.

| Filter | What it does | Default |
|---|---|---|
| Shipment date | Includes shipments whose shipment date falls in this range. Click **Clear** in the date picker to include all dates. | Last 3 years |
| Shipment | Includes only the shipments you pick. You can pick more than one. The list holds every VL shipment in the date range that is not cancelled. | All shipments in the date range |
| Country, Region, Province, District | Includes only participants in that place. Each filter shows only when participants have that field filled in. | All |

Each active filter shows as a chip next to the **Expand Filters** button.

- Click a chip to open the filter panel on that filter.
- Click **×** on a chip to remove that filter.

Cancelled shipments never show in this report.

## Results

Each enrolment has exactly one result. It counts under the assay in the participant's response.

| Result | Meaning |
|---|---|
| Passed | The response passed. |
| Failed | The response failed. |
| Excluded | The response was excluded from scoring. |
| Could not test | The participant reported that they could not test the panel. |
| Not responded | The participant did not respond. |
| Not evaluated | The participant responded and the shipment is not evaluated yet. |

Enrolments without an assay count under **No assay reported**. Most of them are participants who did not respond or could not test.

## Group by

| Button | Rows |
|---|---|
| Assay | One row per assay. |
| Year and assay | One row per shipment year and assay. The newest year comes first. |

## Chart

Each bar is one assay. The bar is split by result. With **Year and assay**, each bar adds up all the years.

## Table

| Column | What it shows |
|---|---|
| Year | The shipment year. Shows only with **Year and assay**. |
| Assay | The short name of the assay. Below it, the full name. |
| Enrolments | The number of enrolments. |
| Pass rate | The percentage of evaluated responses that passed, with a bar. Below it, the number passed out of the number evaluated. Shows **Not evaluated yet** before evaluation. |
| Failed, Excluded, Could not test, Not responded, Not evaluated | The number of enrolments with that result. |

The assays with the most enrolments come first. **No assay reported** comes last. The **Total** row adds up all the rows. Click a column heading to sort by that column.

## Download Excel

**Download Excel** saves the table as an Excel file, with a total row. The file uses the filters and the grouping of the report on screen.
