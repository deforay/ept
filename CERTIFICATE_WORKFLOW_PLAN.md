# Certificate Generation Workflow - Implementation Plan

## Overview

Improve the certificate generation workflow with:
1. PDF form template validation on upload
2. Progress tracking during generation
3. Approval workflow before distribution
4. Auto-distribution to participant folders

---

## Task Groups (Can Run in Parallel)

### GROUP A: Database & Models (No dependencies)

**Task A1: Create Database Migration**
- File: `database/migrations/7.3.5.sql`
- Create `certificate_batches` table:
```sql
CREATE TABLE certificate_batches (
    batch_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_name VARCHAR(100) NOT NULL,
    shipment_ids TEXT NOT NULL,
    status ENUM('pending','generating','generated','approved','distributed','failed') DEFAULT 'pending',
    download_url VARCHAR(500) NULL,
    folder_path VARCHAR(500) NULL,
    excellence_count INT DEFAULT 0,
    participation_count INT DEFAULT 0,
    skipped_count INT DEFAULT 0,
    error_message TEXT NULL,
    created_by INT NOT NULL,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_by INT NULL,
    approved_on DATETIME NULL,
    distributed_on DATETIME NULL,
    INDEX idx_status (status)
);
```

**Task A2: Create CertificateBatches Model**
- File: `application/models/DbTable/CertificateBatches.php`
- Methods needed:
  - `createBatch($data)` - Insert new batch, return batch_id
  - `updateStatus($batchId, $status, $data = [])` - Update status and optional fields
  - `getBatch($batchId)` - Get batch by ID
  - `getLatestBatchForShipments($shipmentIds)` - Get most recent batch

---

### GROUP B: Certificate Templates Page (No dependencies)

**Task B1: Add PDF Validation to Service**
- File: `application/services/CertificateTemplates.php`
- Add method `validatePdfTemplate($filePath)`:
  - Use `pdftk {file} dump_data_fields` to extract field names
  - Check for required field (participant_name or labname)
  - Return: `['valid' => bool, 'fields' => [], 'error' => '']`

**Task B2: Update Controller with New Actions**
- File: `application/modules/admin/controllers/CertificateTemplatesController.php`
- Add actions:
  - `downloadAction()` - Serve template PDF for preview (param: scheme, type)
  - `validateAction()` - AJAX: validate uploaded PDF, return JSON
  - `uploadAction()` - AJAX: save validated template
  - `removeAction()` - AJAX: delete template file and DB record

**Task B3: Update Template Upload Model**
- File: `application/models/DbTable/CertificateTemplates.php`
- Modify `saveCertificateTemplateDetails()`:
  - Call validation before saving
  - Store detected fields in new column `detected_fields` (JSON)
  - Return validation result to controller

**Task B4: Redesign Templates View**
- File: `application/modules/admin/views/scripts/certificate-templates/index.phtml`
- New UI showing:
  - Current template filename + detected fields
  - [View] button to preview PDF
  - [Replace] button to upload new template
  - [Upload] button if no template exists
  - Validation feedback (success/error messages)
  - Use AJAX for upload with progress indicator

---

### GROUP C: Generation Script Updates (Depends on A1, A2)

**Task C1: Update generate-certificates.php**
- File: `scheduled-jobs/generate-certificates.php`
- Changes:
  - Add `-b <batch_id>` parameter
  - At start: Update batch status to 'generating'
  - During: Periodically update progress (optional)
  - At end: Update batch with stats (counts), status='generated', download_url
  - Always create ZIP file with download URL
  - On error: Update status='failed', store error_message

**Task C2: Create distribute-certificates.php**
- File: `scheduled-jobs/distribute-certificates.php`
- New script that:
  - Takes `-b <batch_id>` parameter
  - Verifies batch status is 'approved'
  - Scans excellence/ and participation/ folders
  - For each PDF: extract participant UID from filename, copy to `DOWNLOADS_FOLDER/{uid}/`
  - Update batch status to 'distributed'

**Task C3: Update Job Queue Whitelist**
- File: `scheduled-jobs/execute-job-queue.php`
- Add `distribute-certificates.php` to `ALLOWED_JOB_SCRIPTS` array

---

### GROUP D: Annual Report Page (Depends on A1, A2, C1, C2)

**Task D1: Update ScheduledJobs Model**
- File: `application/models/DbTable/ScheduledJobs.php`
- Modify `scheduleCertificationGeneration()`:
  - Create batch record first using CertificateBatches model
  - Include `-b {batch_id}` in job command
  - Return batch_id to caller

**Task D2: Add Controller Actions**
- File: `application/modules/reports/controllers/AnnualController.php`
- Add actions:
  - `getCertificateStatusAction()` - AJAX: Return batch status, stats, download_url
  - `approveCertificatesAction()` - AJAX: Set status='approved', schedule distribute job

**Task D3: Update Annual Report View**
- File: `application/modules/reports/views/scripts/annual/index.phtml`
- After "Generate Certificates" clicked:
  - Show progress indicator
  - Poll `getCertificateStatus` every 3 seconds
  - On complete: Show stats table + [Download ZIP] + [Approve & Distribute]
  - On approve: Call approve endpoint, show success message

---

## Execution Order

```
Phase 1 (Parallel):
├── GROUP A (Database & Models)
├── GROUP B (Certificate Templates Page)
└── Wait for both to complete

Phase 2 (Sequential after Phase 1):
├── GROUP C (Generation Script) - needs A1, A2
└── Wait to complete

Phase 3 (Sequential after Phase 2):
└── GROUP D (Annual Report Page) - needs everything
```

---

## Files Summary

**Create (4 files):**
1. `database/migrations/7.3.5.sql`
2. `application/models/DbTable/CertificateBatches.php`
3. `scheduled-jobs/distribute-certificates.php`
4. `CERTIFICATE_WORKFLOW_PLAN.md` (this file)

**Modify (8 files):**
1. `application/services/CertificateTemplates.php`
2. `application/models/DbTable/CertificateTemplates.php`
3. `application/modules/admin/controllers/CertificateTemplatesController.php`
4. `application/modules/admin/views/scripts/certificate-templates/index.phtml`
5. `scheduled-jobs/generate-certificates.php`
6. `scheduled-jobs/execute-job-queue.php`
7. `application/models/DbTable/ScheduledJobs.php`
8. `application/modules/reports/controllers/AnnualController.php`
9. `application/modules/reports/views/scripts/annual/index.phtml`

---

## Verification Checklist

### V1: Certificate Templates Page
- [ ] Upload valid PDF form → Accepted, shows detected fields
- [ ] Upload PDF without form fields → Rejected with clear error
- [ ] Upload PDF missing participant_name → Rejected with error
- [ ] Click View → Opens PDF in new tab
- [ ] Click Replace → Can upload new template
- [ ] Click Remove → Template deleted

### V2: Certificate Generation
- [ ] Go to /reports/annual
- [ ] Select shipments, enter name, click Generate
- [ ] Progress indicator shows during generation
- [ ] On complete: Shows excellence/participation/skipped counts
- [ ] Download ZIP works, contains certificates with data filled

### V3: Approval & Distribution
- [ ] Click "Approve & Distribute" button
- [ ] Success message shown
- [ ] Check `DOWNLOADS_FOLDER/{participant_uid}/` - certificates present
- [ ] Login as participant → File Downloads shows certificate

### V4: Edge Cases
- [ ] Generate with no valid participants → Shows "0 certificates generated"
- [ ] Missing template for scheme → Clear error message
- [ ] pdftk not installed → Clear error on template upload

---

## Agent Prompts

### Agent 1: Database & Models (GROUP A)
```
Implement Tasks A1 and A2 from CERTIFICATE_WORKFLOW_PLAN.md:
- Create database migration for certificate_batches table
- Create CertificateBatches model with CRUD methods
Reference existing models in application/models/DbTable/ for patterns.
```

### Agent 2: Certificate Templates Page (GROUP B)
```
Implement Tasks B1-B4 from CERTIFICATE_WORKFLOW_PLAN.md:
- Add PDF validation using pdftk to CertificateTemplates service
- Add controller actions for download, validate, upload, remove
- Update the view with new UI showing fields and validation feedback
Reference existing code in the certificate-templates controller and view.
```

### Agent 3: Generation Scripts (GROUP C) - Run after Agent 1
```
Implement Tasks C1-C3 from CERTIFICATE_WORKFLOW_PLAN.md:
- Update generate-certificates.php to accept -b batch_id and update batch status
- Create distribute-certificates.php to copy approved PDFs to participant folders
- Add distribute-certificates.php to job queue whitelist
Reference existing generate-certificates.php and copy-certificates.php for patterns.
```

### Agent 4: Annual Report Page (GROUP D) - Run after Agents 1, 2, 3
```
Implement Tasks D1-D3 from CERTIFICATE_WORKFLOW_PLAN.md:
- Update ScheduledJobs to create batch record when scheduling
- Add status polling and approve actions to AnnualController
- Update view with progress indicator, stats display, and approve button
Reference existing job tracking UI in admin/job-tracking for polling patterns.
```

---

---

## Help Modal: How to Create PDF Form Templates

Add a help button to the certificate templates page that opens a modal with instructions.

### Button Location
Add next to page title:
```html
<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#helpModal" style="float: right;">
    <i class="icon-question-sign"></i> How to Create Templates
</button>
```

### Modal Content

```html
<div class="modal fade" id="helpModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">How to Create PDF Form Templates</h4>
      </div>
      <div class="modal-body">
        <p>Certificate templates must be PDF files with <strong>fillable form fields</strong>.
           The system will automatically populate these fields with participant data.</p>

        <h5>Required Field</h5>
        <p>Your template must have at least one of these fields for the participant name:</p>
        <ul>
          <li><code>participant_name</code></li>
          <li><code>labname</code></li>
          <li><code>participantname</code></li>
          <li><code>participant</code></li>
        </ul>

        <h5>Optional Fields</h5>
        <table class="table table-bordered table-condensed">
          <tr><th>Field Name</th><th>Data</th></tr>
          <tr><td><code>city</code></td><td>Participant's city</td></tr>
          <tr><td><code>country</code></td><td>Participant's country</td></tr>
          <tr><td><code>shipment_year</code> or <code>shipmentyear</code></td><td>Year of the shipment</td></tr>
          <tr><td><code>assay</code> or <code>assayname</code></td><td>Assay/Platform (VL/EID only)</td></tr>
        </table>

        <hr>

        <h4>Option A: Using LibreOffice (Free)</h4>
        <ol>
          <li>Download and install <a href="https://www.libreoffice.org" target="_blank">LibreOffice</a> (free)</li>
          <li>Open LibreOffice Writer and design your certificate (add logo, text, borders)</li>
          <li>Go to <strong>View → Toolbars → Form Controls</strong></li>
          <li>Click the <strong>Text Box</strong> icon in the Form Controls toolbar</li>
          <li>Draw a text box where you want each field</li>
          <li>Right-click the text box → <strong>Control Properties</strong></li>
          <li>Set the <strong>Name</strong> field to match the field names above (e.g., <code>participant_name</code>)</li>
          <li>Repeat for each field you want</li>
          <li>Go to <strong>File → Export as PDF</strong></li>
          <li>Check <strong>"Create PDF form"</strong> and select <strong>FDF</strong> format</li>
          <li>Click Export and save the PDF</li>
          <li>Upload the PDF here</li>
        </ol>

        <hr>

        <h4>Option B: Using Microsoft Word + PDFescape (Free)</h4>
        <ol>
          <li>Design your certificate in Microsoft Word</li>
          <li>Save as PDF: <strong>File → Save As → PDF</strong></li>
          <li>Go to <a href="https://www.pdfescape.com" target="_blank">PDFescape.com</a> (free account)</li>
          <li>Upload your PDF</li>
          <li>Click <strong>Form Field → Text</strong></li>
          <li>Draw a text field where you want each data field</li>
          <li>Click on the field and set its <strong>Name</strong> in the properties panel</li>
          <li>Repeat for each field</li>
          <li>Download the form-enabled PDF</li>
          <li>Upload the PDF here</li>
        </ol>

        <hr>

        <div class="alert alert-warning">
          <strong>Important:</strong> The PDF must have AcroForm fields, not XFA forms.
          LibreOffice and PDFescape create compatible AcroForm PDFs.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
```

---

## Notes

- DOCX flow remains available via CLI: `php generate-certificates.php -s <ids> -c <name> -t docx`
- PDF form templates can be created with LibreOffice (free) or Word + PDFescape
- Required field: `participant_name` (or aliases: labname, participantname, participant)
- Optional fields: city, country, shipment_year, assay
