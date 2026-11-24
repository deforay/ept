/*
 * Live progress for shipments that are being processed.
 *
 * The Evaluate, Distribution and Finalize listings render the Shipment Status cell
 * of a queued or processing shipment as
 *   <span class="shipment-run-status" data-run-sid="<base64 id>" data-label-...>
 * (Application_Service_Shipments::statusCellHtml). This script polls the report job
 * for each such cell and shows "Generating · 37% (480/1,309)". When a run it has seen
 * in progress ends, it refreshes the rows so the buttons match the new status.
 *
 * A queued or processing shipment can also be in evaluation, which has no report
 * job. Those cells keep their label while the shipment stays queued or processing,
 * and the rows refresh once it leaves that state, so they never refresh in a loop.
 */
(function ($) {
    'use strict';

    var POLL_MS = 4000;
    var seenRunning = {};
    var polling = false;

    // Mirrors the statuses statusCellHtml() hooks.
    function isBusy(shipmentStatus) {
        return shipmentStatus === 'queued' || shipmentStatus === 'processing';
    }

    function formatNumber(n) {
        return (Number(n) || 0).toLocaleString();
    }

    function labelFor($cell, status) {
        if (status === 'pending') {
            return $cell.data('labelQueued');
        }
        if (status === 'finalizing') {
            return $cell.data('labelFinalizing');
        }
        return $cell.data('labelGenerating');
    }

    function render($cell, data) {
        var reports = data.participant_reports || {};
        var text = labelFor($cell, data.status);
        if (data.status !== 'pending' && reports.total > 0) {
            text += ' · ' + Math.floor(reports.percentage || 0) + '% ('
                + formatNumber(reports.completed) + '/' + formatNumber(reports.total) + ')';
        }
        $cell.text(text);
    }

    // The same row refresh the Generate button uses (see generateReports in reports.js).
    function refreshRows() {
        if (typeof currentHighlighted !== 'undefined' && currentHighlighted) {
            if (typeof getShipments === 'function') {
                getShipments(currentHighlighted, null, true);
                return;
            }
            if (typeof getShipmentInReports === 'function') {
                getShipmentInReports(currentHighlighted, true);
                return;
            }
        }
        location.reload();
    }

    function poll() {
        var $cells = $('[data-run-sid]');
        if ($cells.length === 0 || polling) {
            return;
        }
        polling = true;

        var requests = [];
        var finishedCells = [];
        $cells.each(function () {
            var $cell = $(this);
            var sid = String($cell.data('runSid'));
            requests.push(
                $.getJSON('/reports/distribution/get-job-progress', { sid: sid })
                    .done(function (data) {
                        // The panel may have been collapsed or redrawn while this request
                        // was in flight. Act only on a cell that is still on the page, or a
                        // late reply would reopen a panel the admin just closed.
                        if (!data || data.error || !$.contains(document.documentElement, $cell[0])) {
                            return;
                        }
                        if (data.in_progress) {
                            seenRunning[sid] = true;
                            render($cell, data);
                        } else if (seenRunning[sid] || !isBusy(data.shipment_status)) {
                            // Finished: either a run we watched, or the shipment has left
                            // queued/processing since the row was drawn (a short run, or an
                            // evaluation, that ended between two polls). The refreshed row
                            // no longer carries data-run-sid, so this happens once.
                            delete seenRunning[sid];
                            finishedCells.push($cell[0]);
                        }
                    })
            );
        });

        $.when.apply($, requests).always(function () {
            polling = false;
            // Decide at the end of the batch, after every reply: the panel may have
            // been collapsed between the reply that finished and now.
            var stillShown = finishedCells.some(function (el) {
                return $.contains(document.documentElement, el);
            });
            if (stillShown) {
                refreshRows();
            }
        });
    }

    $(function () {
        setInterval(poll, POLL_MS);
        poll();
    });
})(jQuery);
