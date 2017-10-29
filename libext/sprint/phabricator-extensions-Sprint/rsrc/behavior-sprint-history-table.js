/**
 * @provides javelin-behavior-sprint-history-table
 */

JX.behavior('sprint-history-table', function (config) {
    jQuery( document ).ready(function ($) {
        $('#sprint-history').DataTable({
            "order": [[ 4, "asc" ]],
            "iDisplayLength": 100,
            "aoColumnDefs": [
                { "aTargets": [ 0 ], "sWidth": "8%"},
                { "aTargets": [ 0 ], "className": "dt-body-center"},
                { "aTargets": [ 1 ], "sWidth": "8%" },
                { "aTargets": [ 1 ], "className": "dt-body-center"},
                { "bVisible": false, "aTargets": [ 4 ] },
                { "iDataSort": 4, "aTargets": [ 5 ] }
            ],
            "dom": 'T<"clear">lfrtip',
        });
    });
});
