---
title: VL Sample Analysis
summary: The sample results of one VL shipment, per sample and per assay, with the viral load distribution of each assay
tags: [reports, vl, viral load, samples, assay, z-score, distribution]
---

# VL Sample Analysis

**Reports → VL Sample Analysis** shows how participants scored on each sample of one VL shipment. It also shows the viral loads that each assay reported.

The report shows the newest VL shipment when the page opens. Pick another shipment in the **Shipment** list to change it. The list holds every VL shipment that is not cancelled, newest first.

## Which results count

The report counts the results of submitted responses. Late responses count. Participants who did not respond or could not test never count.

Each result has one score.

| Score | Meaning |
|---|---|
| Acceptable | The z-score is under 2. |
| Warning | The z-score is 2 or more and under 3. Scoring counts a warning as a pass. |
| Unacceptable | The z-score is 3 or more. |
| Excluded | The result was excluded from scoring. |
| Not scored | The shipment is not evaluated yet, or the result has no score. |

## Results per sample

Each row is one sample of the shipment.

| Column | What it shows |
|---|---|
| Sample | The sample label. |
| Results | The number of results for the sample. |
| Pass rate | The percentage of scored results that are Acceptable or Warning, with a bar. Below it, the number passed out of the number scored. Shows **Not evaluated yet** before evaluation. |
| Acceptable, Warning, Unacceptable, Excluded, Not scored | The number of results with that score. |

## Results per assay

Each row is one assay. A participant's results count under the assay in their response. Responses without an assay count under **No assay reported**.

The chart shows one bar per assay, split by score. The table has the same columns as **Results per sample**, plus **Participants**: the number of participants who used the assay.

## Viral load distribution per assay

Each card is one assay, with one chart per sample. Each dot is the log10 viral load of one result. The dot colour shows the z-score: green for acceptable, orange for warning, red for unacceptable.

| Line | Meaning |
|---|---|
| Shaded band, between the dashed lines | The acceptable range of the sample for that assay. |
| Solid line | The median. |

Above each chart are the sample label, the number of results, the median and the standard deviation (SD). The card lists the number of participants and the number of undetectable results.

| Button | X-axis |
|---|---|
| By participant | One position per participant. The y-axis is the log10 viral load. |
| By log10 viral load | The log10 viral load. Dots are spread up and down so that they do not overlap. |

Results marked invalid or error are left out of the charts.

## Download Excel

**Download Excel** saves an Excel file for the shipment on screen. **Samples** has one row per sample. **Assays** has one row per assay. Both sheets include the pass rate.
