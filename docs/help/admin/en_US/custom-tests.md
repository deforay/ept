---
title: Custom Tests
summary: Set up a test type that ePT does not have built in, with its own results and scoring
tags: [custom tests, schemes, results, import, export]
---

# Custom Tests

A custom test is a test type you define yourself, such as a
syphilis or malaria rapid test. Once it is active, you can add
shipments for it like any built-in scheme.

## Add a custom test

1. Click **Add New Custom Test**.
2. Enter the **Custom Test Name** and a short **Custom Test
   Code**. The page tells you if the code is already in use.
3. Set **Number of Tests**: how many tests a participant runs on
   each sample.
4. Set **Passing Score** and **Status**.
5. Under **Select Test Format**, choose **Qualitative**.
   **Quantitative** is still in development and may not work
   fully.
6. Fill in the results table. Add one row per result a
   participant can report. See "The results table" below.
7. Click **Add**.

## The results table

Each row is one result participants can choose.

| Column | What to enter |
| --- | --- |
| **Expected Result** | The result as participants see it, for example *Reactive* |
| **Result Code** | A short code stored in the database. Must be unique |
| **Result Type** | **Test Result** for a single test, or **Final Interpretation** for the overall conclusion |
| **Displayed To** | **Participant**, **Admin** or **All** |
| **Sort Order** | The position in the drop-down |

Add at least one **Final Interpretation** row. Without one, the
final result drop-down offers every code, including the test
results. Participants who pick a test-result code then score
zero, even when their answer is correct.

## Optional settings

- **Disable Other Test Kit** removes the *Other* choice, so
  participants cannot type in a kit that is not on the list.
- **Enforce these Test kits** sets the recommended test kits for
  this test.
- **Capture Additional Detail** adds an extra field to the
  response form, with the label you enter.
- **Report Version** and **Report Effective Date** print in the
  report footer.

## Change a custom test

Click **Edit** on its row. When a shipment already uses a result,
its code and label are locked. You can still change its sort
order and who sees it.

## Copy a custom test

- **Clone** starts a new custom test with the same settings and
  results on this instance.
- **Export** saves the test as a `.json` file. On another ePT
  instance, click **Import** and upload it. Tick **Overwrite a
  custom test if one with the same code already exists** to
  replace a test with the same code. Test kits are matched by
  name, and kits missing on that instance are skipped with a
  warning.
