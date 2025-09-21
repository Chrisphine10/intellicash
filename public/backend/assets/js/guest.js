(function ($) {
    "use strict";

	$(document).on('click', '.print', function (event) {
		event.preventDefault();
		$("#preloader").css("display", "block");
		var div = "#" + $(this).data("print");
		$(div).print({
			timeout: 1000,
		});
	});

	$(document).on('click', '#close_alert', function () {
		$("#main_alert").fadeOut();
	});

	$(document).on('change', '.plan_type', function() {
		var selectedPlan = $(this).val();
		
		var planMap = {
			'monthly': '.monthly-plan',
			'yearly': '.yearly-plan',
			'lifetime': '.lifetime-plan'
		};

		$('.monthly-plan, .yearly-plan, .lifetime-plan').css('display', 'none');
		$(planMap[selectedPlan]).css('display', 'block');
	});

	//File Upload Field
	$(".file-uploader").after("<input type='text' class='form-control filename' readOnly>"
		+ "<button type='button' class='btn btn-primary file-uploader-btn'>Browse</button>");

	$(".file-uploader").each(function () {
		if ($(this).data("placeholder")) {
			$(this).parent().find(".filename").prop('placeholder', $(this).data("placeholder"));
		}
		if ($(this).data("value")) {
			$(this).parent().find(".filename").val($(this).data("value"));
		}
		if ($(this).attr("required")) {
			$(this).parent().find(".filename").prop("required", true);
		}
	});

	$(document).on("click", ".file-uploader-btn", function () {
		$(this).parent().find("input[type=file]").click();
	});

	$(document).on('change', '.file-uploader', function () {
		readFileURL(this);
	});

	if (
		$("input:required, select:required, textarea:required")
		.closest(".form-group")
		.find(".required").length == 0
	) {
		// INITIALIZATION REQUIRED FIELDS SIGN
		$("input:required, select:required, textarea:required, file:required")
		.closest(".form-group, .row")
		.find("label.form-label, label.col-form-label, label.control-label")
		.append("<span class='required'> *</span>");
	}

})(jQuery);


function readFileURL(input) {
	if(input.files){
		for (let i = 0; i < input.files.length; i++) {
			var reader = new FileReader();
			reader.onload = function (e) { };

			$(input).parent().find(".filename").val(input.files[i].name);
			reader.readAsDataURL(input.files[i]);
		}

		if(input.files.length > 1){
			$(input).parent().find(".filename").val(input.files.length + ' files selected');
		}else{
			$(input).parent().find(".filename").val(input.files[0].name);
		}
	}
}