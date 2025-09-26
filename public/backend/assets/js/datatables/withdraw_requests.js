(function ($) {
  "use strict";

  var withdraw_requests_table = $("#withdraw_requests_table").DataTable({
    processing: true,
    serverSide: true,
    ajax: {
      url: _tenant_url + "/withdraw_requests/get_table_data",
      method: "POST",
      data: function (d) {
        d._token = $('meta[name="csrf-token"]').attr("content");

        if ($("select[name=status]").val() != "") {
          d.status = $("select[name=status]").val();
        }
        if ($("input[name=from_date]").val() != "") {
          d.from_date = $("input[name=from_date]").val();
        }
        if ($("input[name=to_date]").val() != "") {
          d.to_date = $("input[name=to_date]").val();
        }
        if ($("input[name=min_amount]").val() != "") {
          d.min_amount = $("input[name=min_amount]").val();
        }
        if ($("input[name=max_amount]").val() != "") {
          d.max_amount = $("input[name=max_amount]").val();
        }
        if ($("input[name=member_search]").val() != "") {
          d.member_search = $("input[name=member_search]").val();
        }
      },
      error: function (request, status, error) {
        console.log(request.responseText);
      },
    },
    columns: [
      { data: "member_first_name", name: "member_first_name", "defaultContent": "" },
      { data: "account_number", name: "account_number", "defaultContent": "" },
      { data: "currency_name", name: "currency_name", "defaultContent": "" },
      { data: "amount", name: "amount" },
      { data: "method_name", name: "method_name" },
      { data: "status", name: "status" },
      { data: "action", name: "action" },
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
    withdraw_requests_table.draw();
  });

  $(".select-filter").on("keyup", function (e) {
    if (e.keyCode === 13) { // Enter key
      withdraw_requests_table.draw();
    }
  });

  $(".select-filter").on("blur", function (e) {
    withdraw_requests_table.draw();
  });

  // Clear filters functionality
  $("#clear-filters").on("click", function () {
    $(".select-filter").val("");
    withdraw_requests_table.draw();
  });

  $(document).on("ajax-submit", function () {
    withdraw_requests_table.draw();
  });
})(jQuery);
