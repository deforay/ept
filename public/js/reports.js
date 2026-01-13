// To generate participant individual reports
function generateReports(sId, checkReportDate, surveyDate, _type) {
    if (checkReportDate == 1 || checkReportDate == true) {
        $.blockUI();
        var individual = null;
        $.when(
            $.post("/reports/distribution/queue-reports-generation", {
                sid: sId,
                type: _type
            },
                function (data) {
                    individual = data;
                })
        ).then(function () {
            if (individual) {
                // Initialize progress tracker instead of immediate reload
                if (typeof JobProgressTracker !== 'undefined') {
                    $.unblockUI();
                    JobProgressTracker.init(sId);
                } else {
                    document.location.reload(true);
                    $.unblockUI();
                }
            } else {
                $.unblockUI();
            }
        });
    } else {
        $.unblockUI();
        alert("You cannot generate reports on or before PT Survey Date (" + surveyDate + ").\n\n\nYou can change the PT Survey Date and retry.");
    }
}

/**
 * Job Progress Tracker Module
 * Polls for report generation progress and updates the UI
 */
var JobProgressTracker = (function() {
    var pollInterval = null;
    var POLL_DELAY = 2000; // 2 seconds
    var currentShipmentId = null;

    function init(shipmentId) {
        currentShipmentId = shipmentId;
        checkProgress(shipmentId);
    }

    function checkProgress(shipmentId) {
        $.ajax({
            url: '/reports/distribution/get-job-progress',
            type: 'POST',
            data: { sid: shipmentId },
            dataType: 'json',
            success: function(data) {
                updateProgressUI(data);

                if (data.in_progress) {
                    showProgressTracker();
                    startPolling(shipmentId);
                } else {
                    stopPolling();
                    if (data.status === 'evaluated' || data.status === 'finalized') {
                        showCompletionMessage(data);
                    }
                }
            },
            error: function() {
                console.error('Failed to fetch job progress');
                // Retry after delay on error
                setTimeout(function() {
                    if (currentShipmentId) {
                        checkProgress(currentShipmentId);
                    }
                }, POLL_DELAY * 2);
            }
        });
    }

    function startPolling(shipmentId) {
        if (pollInterval) return; // Already polling

        pollInterval = setInterval(function() {
            checkProgress(shipmentId);
        }, POLL_DELAY);
    }

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    function updateProgressUI(data) {
        var $tracker = $('#job-progress-tracker');

        // Update status badge
        var statusText = formatStatus(data.status);
        var statusClass = getStatusClass(data.status);
        $tracker.find('.status-badge')
            .text(statusText)
            .css('background', statusClass === 'processing' ? 'rgba(255,255,255,0.3)' :
                             statusClass === 'completed' ? '#5cb85c' : 'rgba(255,255,255,0.2)');

        // Update progress bar
        var percent = data.participant_reports ? data.participant_reports.percentage : 0;
        var completed = data.participant_reports ? data.participant_reports.completed : 0;
        var total = data.participant_reports ? data.participant_reports.total : 0;

        $tracker.find('.progress-bar')
            .css('width', percent + '%')
            .attr('aria-valuenow', percent)
            .text(Math.round(percent) + '%');

        // Update progress text
        $tracker.find('.progress-text').text(
            completed + ' of ' + total + ' participant reports generated'
        );

        // Update summary status
        var summaryStatus = 'Pending';
        if (data.summary_report) {
            if (data.summary_report.status === 'completed') {
                summaryStatus = '<span style="color: #5cb85c;"><i class="icon-ok"></i> Completed</span>';
            } else if (data.summary_report.status === 'not_started') {
                summaryStatus = 'Not Started';
            } else {
                summaryStatus = '<i class="icon-spinner icon-spin"></i> Generating...';
            }
        }
        $tracker.find('.summary-status').html(summaryStatus);

        // Update elapsed time
        if (data.started_at) {
            var elapsed = calculateElapsed(data.started_at);
            $tracker.find('.elapsed-time').text('Started: ' + elapsed + ' ago');
        }

        // Update progress bar color on completion
        if (!data.in_progress && (data.status === 'evaluated' || data.status === 'finalized')) {
            $tracker.find('.progress-bar')
                .removeClass('progress-bar-info progress-bar-striped active')
                .addClass('progress-bar-success');
        }
    }

    function showProgressTracker() {
        $('#job-progress-tracker').slideDown(300);
    }

    function hideProgressTracker() {
        $('#job-progress-tracker').slideUp(300);
    }

    function showCompletionMessage(data) {
        var $tracker = $('#job-progress-tracker');

        // Update heading to show completion
        $tracker.find('.panel-heading strong').html('<i class="icon-ok"></i> Report Generation Complete');
        $tracker.find('.status-badge').text('Completed').css('background', '#5cb85c');

        // Reload page after short delay to show updated reports
        setTimeout(function() {
            location.reload();
        }, 1500);
    }

    function getStatusClass(status) {
        var statusMap = {
            'pending': 'pending',
            'not-evaluated': 'processing',
            'not-finalized': 'processing',
            'evaluated': 'completed',
            'finalized': 'completed',
            'processing': 'processing',
            'completed': 'completed'
        };
        return statusMap[status] || 'pending';
    }

    function formatStatus(status) {
        var statusLabels = {
            'pending': 'Queued',
            'not-evaluated': 'Generating...',
            'not-finalized': 'Finalizing...',
            'evaluated': 'Completed',
            'finalized': 'Finalized',
            'processing': 'Processing...',
            'completed': 'Completed'
        };
        return statusLabels[status] || status;
    }

    function calculateElapsed(startTime) {
        var start = new Date(startTime.replace(' ', 'T')); // Handle MySQL datetime format
        var now = new Date();
        var diff = Math.floor((now - start) / 1000);

        if (diff < 0) diff = 0;

        var hours = Math.floor(diff / 3600);
        var minutes = Math.floor((diff % 3600) / 60);
        var seconds = diff % 60;

        if (hours > 0) {
            return hours + 'h ' + minutes + 'm';
        } else if (minutes > 0) {
            return minutes + 'm ' + seconds + 's';
        } else {
            return seconds + 's';
        }
    }

    // Public API
    return {
        init: init,
        stop: stopPolling
    };
})();

/**
 * Cancel a report generation job
 * @param {string} shipmentId - Base64 encoded shipment ID
 */
function cancelReportJob(shipmentId) {
    if (!confirm('Are you sure you want to cancel this report generation job?')) {
        return;
    }

    $.ajax({
        url: '/reports/distribution/cancel-job',
        type: 'POST',
        data: { sid: shipmentId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Job cancelled successfully');
                // Stop polling and reload page
                if (typeof JobProgressTracker !== 'undefined') {
                    JobProgressTracker.stop();
                }
                location.reload();
            } else {
                alert('Failed to cancel job: ' + response.message);
            }
        },
        error: function() {
            alert('Error cancelling job. Please try again.');
        }
    });
}
