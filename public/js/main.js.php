<script type="text/javascript">
    <?php
    $csrfNamespace = new Zend_Session_Namespace('csrf');
    $heartbeatDmNs = new Zend_Session_Namespace('datamanagers');
    $heartbeatAdmNs = new Zend_Session_Namespace('administrators');
    $heartbeatSessionActive = !empty($heartbeatDmNs->dm_id) || !empty($heartbeatAdmNs->admin_id);
    ?>
    window.csrf_token = "<?= $csrfNamespace->token; ?>";
    window.eptSessionActive = <?= $heartbeatSessionActive ? 'true' : 'false'; ?>;

    function addCsrfTokenToForm(form) {
        if (form.find('input[name="csrf_token"]').length === 0) {
            $('<input>').attr({
                type: 'hidden',
                name: 'csrf_token',
                value: window.csrf_token
            }).appendTo(form);
        }
    }
    $.ajaxSetup({
        beforeSend: function (xhr, settings) {
            if (settings.type === 'POST' || settings.type === 'PUT' || settings.type === 'DELETE') {
                xhr.setRequestHeader('X-CSRF-Token', window.csrf_token);
            }
        }
    });

    // Idle-timeout coordination with PreSetter.php (server enforces 30 min).
    // Heartbeat fires only when the user has actually interacted since the last
    // ping, so a walked-away tab still expires server-side.
    (function () {
        if (!window.eptSessionActive) {
            return;
        }
        var heartbeatMs = 5 * 60 * 1000;
        var sawActivity = false;
        var activityEvents = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'];
        activityEvents.forEach(function (evt) {
            document.addEventListener(evt, function () { sawActivity = true; }, { passive: true });
        });
        setInterval(function () {
            if (!sawActivity) return;
            sawActivity = false;
            $.ajax({ url: '/common/heartbeat', type: 'POST', dataType: 'json' });
        }, heartbeatMs);
    })();

    // Catch session-expired responses from any XHR (heartbeat, form submits,
    // datatables, etc.) so the user lands on login instead of staring at a
    // silently-failed call.
    $(document).ajaxComplete(function (event, xhr) {
        if (xhr.status !== 401) return;
        var loginUrl = '/auth/login';
        try {
            var data = JSON.parse(xhr.responseText);
            if (data && data.status === 'session_expired') {
                if (data.loginUrl) loginUrl = data.loginUrl;
            } else {
                return;
            }
        } catch (e) {
            return;
        }
        window.location.href = loginUrl;
    });

    $(document).ready(function () {

        // Add CSRF token to all existing forms
        $('form').each(function () {
            addCsrfTokenToForm($(this));
        });

        // Add CSRF token to forms added in the future
        // If your application adds forms dynamically via AJAX or other means
        $(document).on('submit', 'form', function (e) {
            addCsrfTokenToForm($(this));
        });

    });

    // Shared per-column DataTables search binder.
    // Debounces typing (1s), fires immediately on Enter, flushes on blur/change,
    // and skips redundant requests when the value hasn't changed.
    window.bindColSearch = function (tableSelector, onSearch, opts) {
        opts = opts || {};
        var delay = typeof opts.delay === 'number' ? opts.delay : 1000;
        var inputSelector = opts.inputSelector || '.col-search';
        var $head = $(tableSelector + ' thead');
        if (!$head.length) return;
        var timer = null;
        var last = {};
        function apply($input) {
            var col = parseInt($input.data('col'), 10);
            var val = $input.val();
            if (last[col] === val) return;
            last[col] = val;
            onSearch(val, col);
        }
        var ns = '.colsearch_' + tableSelector.replace(/[^a-z0-9]/gi, '_');
        $head.off('keyup' + ns + ' change' + ns + ' blur' + ns)
            .on('keyup' + ns, inputSelector, function (e) {
                var $input = $(this);
                clearTimeout(timer);
                if (e.keyCode === 13) { apply($input); return; }
                timer = setTimeout(function () { apply($input); }, delay);
            })
            .on('change' + ns + ' blur' + ns, inputSelector, function () {
                clearTimeout(timer);
                apply($(this));
            });
        $head.find(inputSelector).off('click' + ns + ' mousedown' + ns + ' focus' + ns)
            .on('click' + ns + ' mousedown' + ns + ' focus' + ns, function (e) {
                e.stopPropagation();
            });
    };

    // Response forms have a lot of expiry-date / receipt-date / test-date
    // datepickers that often need to jump several years (reagent expiry can be
    // 2-3 years out, backfilled receipt dates can be a few years old). Default
    // jQuery UI shows only the prev/next month arrows, so reaching e.g. Jan
    // 2028 from May 2026 means 32 clicks. Enable the month + year dropdowns
    // and widen the year range so a single dropdown selection gets you there.
    // Per-call dateFormat / minDate / maxDate options still override these.
    if ($.datepicker) {
        $.datepicker.setDefaults({
            changeMonth: true,
            changeYear: true,
            yearRange: 'c-20:c+20'
        });
    }

    if ($.fn.dataTable) {
        $.extend(true, $.fn.dataTable.defaults, {
            "language": {
                "lengthMenu": "_MENU_ <?= $this->jsTranslate("records per page"); ?>",
                "zeroRecords": "<?= $this->jsTranslate("No records found"); ?>",
                "sEmptyTable": "<?= $this->jsTranslate("No data to show"); ?>",
                "info": "<?= $this->jsTranslate("Showing _START_ to _END_ of _TOTAL_ entries"); ?>",
                "infoEmpty": "<?= $this->jsTranslate("Showing 0 to 0 of 0 entries"); ?>",
                "infoFiltered": "(<?= $this->jsTranslate("filtered from _MAX_ total entries"); ?>)",
                "search": "<?= $this->jsTranslate("Search"); ?>:",
                "paginate": {
                    "first": "<?= $this->jsTranslate("First"); ?>",
                    "last": "<?= $this->jsTranslate("Last"); ?>",
                    "next": "<?= $this->jsTranslate("Next"); ?>",
                    "previous": "<?= $this->jsTranslate("Previous"); ?>"
                },
                "sProcessing": "<?= $this->jsTranslate("Loading Table Data..."); ?>",
                "loadingRecords": "<?= $this->jsTranslate("Loading..."); ?>"
            },
            "lengthMenu": [
                [10, 25, 50, 100, 200, 250, 500],
                [10, 25, 50, 100, 200, 250, 500]
            ],
            "pageLength": 10
        });
    }
</script>