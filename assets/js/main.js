var $ = jQuery;

$(document).ready(() => {

    $(".report-table").DataTable({
        "language": {
          "paginate": {
            "previous": "<",
            "next": ">"
          }
        }
      });

});