# Exercise: DTS HIV Serology

This exercise walks through a complete PT cycle for the **Dried Tube Specimen (DTS) - HIV Serology** scheme. By the end, you will have created a PT Survey, enrolled participants, collected results, evaluated responses, and generated final reports.

**Scheme:** Dried Tube Specimen (DTS) - HIV Serology

**Time required:** 20–30 minutes

**Prerequisites:**
- Access to a training/test instance of ePT
- Admin login credentials (PT Administrator or PT Manager)
- The participant bulk import Excel template (downloaded in Step 1)

> For questions, reach out to amit@deforay.com

---

## Exercise Overview

| Step | Action | Role |
|------|--------|------|
| 1 | Add 8 new participants (bulk import) | Admin |
| 2 | Enroll participants in the HIV Serology scheme | Admin |
| 3 | Create a new PT Survey | Admin |
| 4 | Add a DTS HIV Serology shipment with samples | Admin |
| 5 | Enroll participants in the shipment | Admin |
| 6 | Ship the PT Survey | Admin |
| 7 | Submit results as participants | Participant |
| 8 | Evaluate, generate reports, and finalize | Admin |
| 9 | Review performance reports | Admin |
| 10 | Download reports as a participant | Participant |

---

## Admin Section

### Step 1: Add New Participants (Bulk Import)

1. Log in as **PT Administrator / PT Manager**
2. Go to **Configure → PT Participants**
3. Click **Bulk Import Participants**
4. Download the Excel template by clicking the link on the right side of the page
5. Open the file and fill in details for **8 new participants**:
   - Fields with **red headings** are required (Participant ID, Name, etc.)
   - Use a naming convention you can recognize later, e.g. `TRAIN-001` through `TRAIN-008`
   - You may leave the password field blank (the system will auto-generate) or set a password you will remember for Step 7
6. Save the Excel file and upload it on the same page
7. Verify the participants appear in the **PT Participants** list

> **Tip:** If you set a custom password, use the same one for all 8 participants — this makes Step 7 much faster. Note down the Participant IDs and passwords for later use.

### Step 2: Enroll Participants in the Scheme

Scheme enrollment is a one-time setup. Participants must be enrolled in the HIV Serology scheme before they can be enrolled in any DTS shipment.

1. Go to **Configure → Scheme Enrollments**
2. Select **HIV Serology** from the scheme type dropdown
3. Click on **Enroll the Participants for Dried Tube Specimen - HIV Serology**
4. Select your 8 new participants from the **Available** list (left) and move them to the **Enrolled** list (right)
5. Click **Enroll Selected**

> **Don't see your participants?** Make sure you uploaded the file in Step 1 and that the participants are in **Active** status. Check **Configure → PT Participants** to verify.

### Step 3: Create a New PT Survey

1. Go to **Manage → PT Survey**
2. Click **Add New PT Survey**
3. Enter a **PT Survey Code** (e.g., `TRAIN-2026-01`) — letters, numbers, and hyphens only
4. Select today's date as the **PT Survey Date**
5. Click **Add**

### Step 4: Add a DTS HIV Serology Shipment

1. Your new PT Survey should appear in the list — click **Add Scheme**
2. Select **Dried Tube Specimen - HIV Serology** from the dropdown
3. Scroll down and configure:
   - **Shipment Code** — keep the default or customize it
   - **Result Due Date** — set a date at least a few days from now
   - **Samples** — add **5 samples** using the **+Sample/Control** button
4. For each sample, set the **expected reference result**. Use a mix so participants can be properly evaluated:

   | Sample | Suggested Expected Result |
   |--------|--------------------------|
   | Sample 1 | HIV Positive |
   | Sample 2 | HIV Negative |
   | Sample 3 | HIV Positive |
   | Sample 4 | HIV Negative |
   | Sample 5 | HIV Positive |

5. Click **Add Shipment**

> **About DTS samples:** In HIV Serology, participants test each sample using the national testing algorithm (typically Test-1 screening, then confirmatory Test-2 and Test-3 as needed). The expected result is the final interpretation for each sample.

### Step 5: Enroll Participants in the Shipment

1. Your new shipment should appear in the list — click the red **Enroll** button
2. Select all 8 participants from the **Available** list and move them to the **Enrolled** list
3. Click **Enroll**

> **Don't see your participants?** They must already be enrolled in the HIV Serology scheme (Step 2). Go back and check if needed.

### Step 6: Ship the PT Survey

1. Go to **Manage → PT Survey**
2. Find your PT Survey in the table (it should be near the top)
3. Click **Ship Now**
4. Confirm by clicking **Yes**

Your PT Survey is now shipped. Participants can begin submitting results.

> **What just happened?** The shipment is now visible in the Participant Portal. In a real scenario, this is when physical DTS samples would be distributed to testing sites alongside the ePT notification.

---

## Participant Section

### Step 7: Submit Results as Participants

Repeat this for **at least 3 of the 8 participants** you created. To make evaluation meaningful, have some participants submit correct results and others submit intentionally incorrect results.

**For each participant:**

1. Go to the **Participant Login** page
2. Log in using the participant's credentials (from Step 1)
3. Click **PT Result Submission** on the left menu
4. Find the shipment you created in Step 4
5. Click **Enter Result**
6. Fill in the result form for each sample:
   - **Test Kit** — select any test kit from the dropdown for Test-1 (and Test-2, Test-3 if applicable)
   - **Individual test results** — select Reactive or Non-Reactive for each test
   - **Final Interpretation** — select HIV Positive or HIV Negative
   - **Documentation fields** — fill in lot number, expiry date, etc. (these affect the documentation score)
7. Click **Submit**
8. Log out and repeat for the next participant

**Suggested result pattern for the exercise:**

| Participant | Submit correct results? | Purpose |
|-------------|------------------------|---------|
| Participant 1 | Yes — match the expected results | Should pass evaluation |
| Participant 2 | Yes — match the expected results | Should pass evaluation |
| Participant 3 | No — submit some incorrect final interpretations | Should fail, to demonstrate evaluation scoring |

> **Tip:** Fill in the documentation fields (lot numbers, expiry dates) for at least one participant and leave them blank for another. This demonstrates how ePT scores documentation separately from test accuracy.

> **Video guide:** https://youtu.be/RE_rS5GlcJs

---

## Admin Section (continued)

### Step 8: Evaluate, Generate Reports, and Finalize

**8a. Evaluate:**

1. Return to the **Admin** section
2. Go to **Analyze → Evaluate Responses**
3. Find your shipment (use the search box) and click **View**
4. Scroll to the bottom and click the green **Evaluate** button
5. Review the results table — you should see:
   - Participants who submitted correct results marked as **Passed**
   - Participants who submitted incorrect results marked as **Failed**
   - A **Response Score** (test accuracy) and **Documentation Score** for each participant

> **Note:** Evaluation can be run multiple times and is invisible to participants. Use this to verify results are scored correctly before generating reports.

**8b. Generate Reports:**

1. Go to **Analyze → Generate Reports**
2. Find your shipment and click **View**
3. Scroll down and click the green **Report** button
4. Click **Generate Participant Reports**
5. Wait a moment and refresh the page — reports generate in the background

**8c. Finalize:**

1. Go to **Analyze → Finalize Reports**
2. Find your shipment and click **View**
3. Scroll down and click the green **Finalize** button
4. Click **Generate Reports and Finalize Shipment**
5. Wait a moment and refresh the page

> **Important:** Finalization is permanent. Once finalized, participants can download their individual reports. In a real scenario, ensure evaluation and reports are correct before this step.

### Step 9: Review Performance Reports

1. Go to **Reports → Shipment Reports**
2. Find your shipment and click the **green shipment code** link
3. An Excel file will download — open it and explore the sheets:
   - **Summary** — overall shipment statistics
   - **Participant results** — per-participant scores and pass/fail status
   - **Sample-level detail** — how each participant responded to each sample

---

## Participant Section (continued)

### Step 10: Download Reports as a Participant

1. Go to the **Participant Login** page and log in as one of the participants who submitted results
2. Click **View PT Results → Individual Reports** on the left menu
3. Search for your shipment and download the report
4. Open the PDF — it includes the participant's results, scores, and any corrective actions

> **Video guide:** https://youtu.be/oUsc9-CgvxI

---

## Exercise Checklist

Use this to verify you've completed each step:

- [ ] 8 participants added via bulk import
- [ ] 8 participants enrolled in the HIV Serology scheme
- [ ] PT Survey created with a recognizable code
- [ ] DTS HIV Serology shipment added with 5 samples and expected results
- [ ] 8 participants enrolled in the shipment
- [ ] PT Survey shipped
- [ ] Results submitted for at least 3 participants (mix of correct and incorrect)
- [ ] Shipment evaluated — pass/fail results visible
- [ ] Participant reports generated
- [ ] Shipment finalized
- [ ] Performance report downloaded and reviewed (admin)
- [ ] Individual report downloaded as a participant

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Participants not showing in scheme enrollment | Verify they were imported successfully in **Configure → PT Participants** and are in Active status |
| Participants not showing in shipment enrollment | They must be enrolled in the scheme first (Step 2) |
| Participant can't see the shipment after login | The PT Survey must be shipped first (Step 6). Also verify the participant is enrolled in the shipment (Step 5) |
| Evaluation shows no results | Make sure at least one participant has submitted results (Step 7) |
| Reports not generating | Wait a moment and refresh the page. Report generation runs in the background |
| Participant can't download reports | The shipment must be finalized first (Step 8c) |
