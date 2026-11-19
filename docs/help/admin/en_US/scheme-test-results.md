---
title: Scheme Test Results
summary: Set the results that participants and admins can choose for each qualitative scheme
tags: [config, schemes, results, possible results, result codes]
---

# Scheme Test Results

Each qualitative scheme has a list of possible results. The response
form and the shipment form offer these results in their drop-downs.
Quantitative schemes, such as Viral Load, have no list here.

Open **Configure → Scheme Test Results**. The page lists every active
qualitative scheme. Click **Test Results** on a scheme's row to open
its result list.

You can also open a scheme's list from **Configure → Scheme Config**.
In the sidebar, open the scheme and click **Test Results**.

## Add a result

1. Use the bar at the top of the result list.
2. Under **Result Type**, choose **Test Result** or **Final
   Interpretation**.
3. Enter the **Response**: the result as people see it, for example
   *Reactive*.
4. Enter the **Result Code**: a short code stored with each answer.
5. Under **Displayed To**, choose who sees the result.
6. Optional: enter a **Sort Order**.
7. Click **Add**. The row drops into the list in its sorted place.
8. Click **Update** to save.

Nothing is saved until you click **Update**. While changes are
unsaved, the page shows a warning banner.

Within one result type, two rows cannot share a response or a code.
If you click **Update** with text still in the add bar, the page asks
you to add it or clear it first.

For a custom test, ePT puts the scheme code in front of the result
code that you type. Test results get *CODE-T-* and final
interpretations get *CODE-F-*.

## Result types

- **Test Result**: the result of one test on a sample.
- **Final Interpretation**: the overall conclusion for a sample.

The list shows test results first, then final interpretations. Within
each type, rows follow **Sort Order**.

For a custom test, add at least one **Final Interpretation** row.
The shipment form takes the expected result from the final
interpretations. Without one, that drop-down offers every code,
including the test results.

## Who sees a result

| **Displayed To** | Effect |
| --- | --- |
| **All** | Offered on participant and admin forms |
| **Participant** | Offered on the participant response form only |
| **Admin** | Offered on the admin shipment form only |
| **None** | Offered on no form |

## Locked results

A lock icon in the **Action** column marks a locked row. All its
fields are greyed out, and it has no remove button. Hover over the
lock icon to see why the row is locked.

A row is locked for one of two reasons:

- **In use by a shipment**: a shipment uses the result. Shipment
  results keep this code, so it stays locked after the shipment is
  finalized.
- **Pre-defined sub-test**: the result belongs to a built-in
  sub-test, such as syphilis within HIV Serology. The row shows the
  sub-test group, for example *DTS_SYP_TEST*.

## Remove a result

1. Click the minus button in the row's **Action** column. Its tooltip
   reads **Remove row**.
2. Click **Update**.

Only rows that are not locked have the minus button.
