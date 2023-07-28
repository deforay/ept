// To generate participant individual reports
function generateReports(sId, checkReportDate, surveyDate, _type) {
    if (checkReportDate) {
        $.blockUI();
        var individual = null;
        $.when(
            $.post("/reports/distribution/queue-reports-generation", {
                    sid: sId,
                    type: _type
                },
                function(data) {
                    individual = data;
                })
        ).then(function() {
            if (individual) {
                document.location.reload(true);
            }
            $.unblockUI();
        });
    } else {
        $.unblockUI();
        alert("You cannot generate reports on or before PT Survey Date ("+surveyDate+").\n\n\nYou can change the PT Survey Date and retry.");
    }
}