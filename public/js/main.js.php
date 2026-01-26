<script type="text/javascript">
    <?php
    $csrfNamespace = new Zend_Session_Namespace('csrf');
    ?>
    window.csrf_token = "<?= $csrfNamespace->token; ?>";

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
</script>