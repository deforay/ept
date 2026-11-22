<?php

// Strings the audit log stores in English and translates when the Audit Log
// page shows them: field labels, facts, list titles, reasons and item types.
// They live in model and controller arrays, where xgettext cannot see them.
// Listing them here inside _() lets bin/refresh-translations.php extract them.
//
// This file is never executed. Add a string here when new audit code stores
// one that the app does not already show inside a translate call.

_('Accounts');
_('Added');
_('Affects scores or results');
_('An admin without the View as Participant privilege tried to use it');
_('Cancelling locks the shipment for good and deletes its reports');
_('Cancelling locks the shipments for good and deletes their reports');
_('Changes which accounts can log in');
_('Changes which schemes run or which instance rules apply');
_('Changes who can submit results for the mapped labs');
_('Changes who can submit results for the updated labs');
_('Changes who can submit results for this participant');
_('Copied from');
_('Credentials emailed');
_('Custom tests in the file');
_('Data manager');
_('Deactivated');
_('Decides whether a late response is scored');
_('Deletes submitted results');
_('Domain does not accept mail');
_('E-mail only');
_('Edited an earlier submission');
_('Emails queued');
_('Ended by');
_('Evaluated with different settings than the previous evaluation of this shipment');
_('Evaluation queued');
_('Finalizing publishes results to participants');
_('Had submitted results');
_('Idle time limit (minutes)');
_('Individual reports');
_('Inserted');
_('Invalid addresses skipped');
_('Job status');
_('Job');
_('Labs emailed');
_('Labs without an email address');
_('Login-only address (made up at import)');
_('Mail template');
_('Mappings');
_('Merged list');
_('Message changed');
_('Messages');
_('Moves a finalized shipment back to an earlier status');
_('Must change password at next login');
_('New data managers');
_('No email');
_('Not a valid email address');
_('Number of controls');
_('Number of samples');
_('Overwrite');
_('Participants added');
_('Participants removed');
_('Passwords reset');
_('Platforms removed');
_('Previous name');
_('PTCC logins reset');
_('Removed labs are no longer offered for shipments from this list');
_('Removed participants had submitted results, which were deleted');
_('Removed through');
_('Removed');
_('Removes platforms from result entry');
_('Removes test kits that labs could report');
_('Removes the platform from result entry');
_('Replaced the previous template');
_('Replaces the scoring settings of existing custom tests.');
_('Replaces the summary report participants download');
_('Response switch');
_('Responses received');
_('Results approved by');
_('Results approved on');
_('Retires results that shipments already use');
_('Rows');
_('Saved as a list');
_('Shipment Evaluated');
_('Shipments cancelled');
_('Shipments not cancelled');
_('Shipments scored with the earlier settings');
_('Some shipments could not be cancelled');
_('Status now');
_('Templates uploaded');
_('Test kits added (+) and removed (−)');
_('Test kits approved');
_('The assay is no longer offered in result entry.');
_('This PT Survey cannot be cancelled because these shipments are finalized');
_('Updated');
