(function ($) {
  "use strict";

  console.log("Starting DataTables initialization...");
  console.log("jQuery version:", $.fn.jquery);
  console.log("DataTables available:", typeof $.fn.DataTable !== 'undefined');
  console.log("_tenant_url:", typeof _tenant_url !== 'undefined' ? _tenant_url : 'undefined');

  var deposit_request_table = $("#deposit_request_table").DataTable({
    processing: true,
    serverSide: true,
    ajax: {
      url: _tenant_url + "/deposit_requests/get_table_data",
      method: "POST",
      data: function (d) {
        d._token = $('meta[name="csrf-token"]').attr("content");

        if ($("select[name=status]").val() != "") {
          d.status = $("select[name=status]").val();
        }
      },
      error: function (request, status, error) {
        console.log("DataTables Error:", request.responseText);
      },
    },
    columns: [
      { data: "member_first_name", name: "member_first_name", "defaultContent": "" },
      { data: "account_number", name: "account_number", "defaultContent": "" },
      { data: "currency_name", name: "currency_name", "defaultContent": "" },
      { data: "amount", name: "amount", "defaultContent": "0.00" },
      { data: "method_name", name: "method_name", "defaultContent": "N/A" },
      { data: "status", name: "status", "defaultContent": "" },
      { data: "action", name: "action", "defaultContent": "" },
    ],
    responsive: true,
    bStateSave: true,
    bAutoWidth: false,
    ordering: false,
    language: {
      decimal: "",
      emptyTable: $lang_no_data_found,
      info:
        $lang_showing +
        " _START_ " +
        $lang_to +
        " _END_ " +
        $lang_of +
        " _TOTAL_ " +
        $lang_entries,
      infoEmpty: $lang_showing_0_to_0_of_0_entries,
      infoFiltered: "(filtered from _MAX_ total entries)",
      infoPostFix: "",
      thousands: ",",
      lengthMenu: $lang_show + " _MENU_ " + $lang_entries,
      loadingRecords: $lang_loading,
      processing: $lang_processing,
      search: $lang_search,
      zeroRecords: $lang_no_matching_records_found,
      paginate: {
        first: $lang_first,
        last: $lang_last,
        previous: "<i class='fas fa-angle-left'></i>",
        next: "<i class='fas fa-angle-right'></i>",
      },
    },
    drawCallback: function () {
      $(".dataTables_paginate > .pagination").addClass("pagination-bordered");
    },
  });

  $(".select-filter").on("change", function (e) {
    deposit_request_table.draw();
  });

  $(document).on("ajax-submit", function () {
    deposit_request_table.draw();
  });
})(jQuery);
