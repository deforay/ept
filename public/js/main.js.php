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
        beforeSend: function(xhr, settings) {
            if (settings.type === 'POST' || settings.type === 'PUT' || settings.type === 'DELETE') {
                xhr.setRequestHeader('X-CSRF-Token', window.csrf_token);
            }
        }
    });

    $(document).ready(function() {

        // Add CSRF token to all existing forms
        $('form').each(function() {
            addCsrfTokenToForm($(this));
        });

        // Add CSRF token to forms added in the future
        // If your application adds forms dynamically via AJAX or other means
        $(document).on('submit', 'form', function(e) {
            addCsrfTokenToForm($(this));
        });

    });
</script>
