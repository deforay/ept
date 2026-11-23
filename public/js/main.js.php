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

    // Idle-timeout coordination with PreSetter.php (server enforces the idle
    // limit: 2 hours unless session.idleMinutes in application.ini says otherwise).
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
        // Seed with whatever is already in the boxes. Without this, the first blur
        // of an untouched empty box compares '' against undefined, decides the value
        // "changed", and fires a no-op filter — a full server-side redraw just for
        // tabbing through the header. The table re-rendering reads as the grid
        // spontaneously re-sorting itself.
        $head.find(inputSelector).each(function () {
            var seedCol = parseInt($(this).data('col'), 10);
            if (!isNaN(seedCol)) {
                last[seedCol] = $(this).val();
            }
        });
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

    // Response Date is judged against the shipment's response deadline, which is a
    // DATETIME that can close part-way through its own day. A date-only value reads
    // as midnight, so an afternoon submission on the closing day would look early.
    // These inputs therefore carry a time of day, using the same single-date +
    // 24-hour-time daterangepicker the deadline field itself uses on the admin
    // shipment form. Format is fixed at DD-MMM-YYYY HH:mm (month name, so the day
    // and month can never be read the wrong way round).
    //
    // Optional data attributes, both accepting a YYYY-MM-DD date or the word
    // "today": data-mindate, data-maxdate.
    window.eptDateTimeFormat = 'DD-MMM-YYYY HH:mm';
    window.initDateTimePickers = function (selector) {
        if (!$.fn.daterangepicker) {
            return;
        }
        $(selector || '.datetimepicker').each(function () {
            var $input = $(this);
            if ($input.data('daterangepicker')) {
                return;
            }
            var bound = function (attr) {
                var raw = $input.data(attr);
                if (raw === undefined || raw === null || raw === '') return undefined;
                return (String(raw) === 'today') ? moment().endOf('day') : moment(String(raw));
            };
            var current = $input.val() ? moment($input.val(), window.eptDateTimeFormat) : null;
            $input.daterangepicker({
                singleDatePicker: true,
                timePicker: true,
                timePicker24Hour: true,
                autoUpdateInput: false,
                showDropdowns: true,
                minYear: moment().year() - 20,
                maxYear: moment().year() + 20,
                minDate: bound('mindate'),
                maxDate: bound('maxdate'),
                startDate: (current && current.isValid()) ? current : moment(),
                locale: {
                    format: window.eptDateTimeFormat,
                    cancelLabel: '<?= $this->jsTranslate("Clear"); ?>',
                    applyLabel: '<?= $this->jsTranslate("Apply"); ?>'
                }
            });
            $input.on('apply.daterangepicker', function (ev, picker) {
                $(this).val(picker.startDate.format(window.eptDateTimeFormat));
            });
            $input.on('cancel.daterangepicker', function () {
                $(this).val('');
            });
        });
    };
    $(function () {
        window.initDateTimePickers('.datetimepicker');
    });

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

        // Keep every admin grid inside the page.
        // 1. Cells wrap first (admin.css), so most grids fit as they are.
        // 2. If a grid is still wider than the page, the page widens just enough
        //    to fit it, up to the browser window less a 16px margin each side.
        // 3. If it still does not fit, the grid scrolls sideways inside its own
        //    box. A shadow marks the edge that has more columns.
        // Only the admin content shell (.admin-shell in admin.phtml) widens.
        var GRID_GUTTER = 16;

        function gridScrollBox($table) {
            var $box = $table.parent('.grid-scroll-inner');
            if (!$box.length) {
                $table.wrap('<div class="grid-scroll"><div class="grid-scroll-inner"></div></div>');
                $box = $table.parent();
                $box.on('scroll', function () {
                    updateGridShadows(this);
                });
            }
            return $box;
        }

        function updateGridShadows(box) {
            var more = box.scrollWidth - box.clientWidth;
            $(box).parent()
                .toggleClass('has-more-left', more > 1 && box.scrollLeft > 1)
                .toggleClass('has-more-right', more > 1 && box.scrollLeft < more - 1);
        }

        function fitAdminShell() {
            var shell = document.querySelector('.admin-shell');
            if (!shell) {
                return;
            }
            var boxes = $(shell).find('.grid-scroll-inner').filter(function () {
                return $(this).is(':visible') && !$(this).closest('.modal').length;
            });
            // Measure from the shell's own width, so a grid that got narrower
            // (a filter, a resize) lets the page shrink back.
            shell.style.width = '';
            shell.style.maxWidth = '';
            var overflow = 0;
            boxes.each(function () {
                overflow = Math.max(overflow, this.scrollWidth - this.clientWidth);
            });
            if (overflow > 0) {
                var current = shell.getBoundingClientRect().width;
                var limit = document.documentElement.clientWidth - 2 * GRID_GUTTER;
                var target = Math.min(Math.ceil(current + overflow), limit);
                if (target > current) {
                    shell.style.width = target + 'px';
                    shell.style.maxWidth = target + 'px';
                }
            }
            boxes.each(function () {
                updateGridShadows(this);
            });
        }

        var fitTimer = null;
        function scheduleFit() {
            clearTimeout(fitTimer);
            fitTimer = setTimeout(fitAdminShell, 50);
        }

        $(document).on('init.dt', function (e, settings) {
            var $table = $(settings.nTable);
            // Grids with DataTables' own scrolling (scrollX) already have a box.
            if ($table.closest('.dataTables_scroll').length) {
                return;
            }
            if (!$table.closest('.admin-shell, .data-list').length) {
                return;
            }
            gridScrollBox($table);
            scheduleFit();
        });
        $(document).on('draw.dt column-visibility.dt shown.bs.tab', scheduleFit);
        // Child rows (row details) open without a DataTables event.
        $(document).on('click', '.admin-shell table.dataTable tbody', scheduleFit);
        $(window).on('resize', scheduleFit);
    }
</script>