(function($) {
    "use strict";

    $(window).on('load', function(){
      $("#preloader").fadeOut();
    });

    $(document).on('click','.fkr-navbar .has-submenu', function(e){
        if($(window).width() <= 992){
            e.preventDefault();
            $(this).next('.submenu').slideToggle();
            $(this).toggleClass('active-submenu');
        }
    });

    $(window).on('resize', function(){
        if($(window).width() > 992){
            $('.fkr-navbar .submenu').css('display', 'block');
            $('.fkr-navbar .has-submenu').removeClass('active-submenu');
        }else{
            $('.fkr-navbar .submenu').css('display', 'none');
        }
    });

    $(document).on('click','.comments .single-comment .reply-btn', function(e){
        e.preventDefault();
        $(this).next(".reply-form").slideToggle();
    });


    $(document).on('change', '.plan_type', function(){
      $('input[name="' + this.name + '"]').not(this).prop('checked', false);
    });

    $(document).on('change', '.plan_type', function(){
      if($(this).val() == 'monthly'){
        $('.monthly-plan').fadeIn();
        $('.yearly-plan').fadeOut();
        $('.lifetime-plan').fadeOut();
      }else if($(this).val() == 'yearly'){
        $('.monthly-plan').fadeOut();
        $('.yearly-plan').fadeIn();
        $('.lifetime-plan').fadeOut();
      }else if($(this).val() == 'lifetime'){
        $('.monthly-plan').fadeOut();
        $('.yearly-plan').fadeOut();
        $('.lifetime-plan').fadeIn();
      }
    });

    //Init Wow
    new WOW({
        'animateClass': 'animate__animated' 
    }).init();

    $('.testimonial-slider').slick({
        dots: true,
        slidesToShow: 3,
        slidesToScroll: 1,
        autoplay: true,
        autoplaySpeed: 5000,
        responsive: [
            {
              breakpoint: 1024,
              settings: {
                slidesToShow: 3,
                slidesToScroll: 3,
                infinite: true,
                dots: true
              }
            },
            {
              breakpoint: 768,
              settings: {
                slidesToShow: 2,
                slidesToScroll: 2
              }
            },
            {
              breakpoint: 480,
              settings: {
                slidesToShow: 1,
                slidesToScroll: 1
              }
            }
        ]
    });

    $(document).on('submit', '#email_subscription', function(e){
        e.preventDefault();
        var elem = $(this);
        var link = $(this).attr("action");
        $(elem).find("button[type=submit]").prop("disabled", true);
    
        $.ajax({
            method: "POST",
            url: link,
            data: new FormData(this),
            mimeType: "multipart/form-data",
            contentType: false,
            cache: false,
            processData: false,
            beforeSend: function () {
              $("#preloader").css("display", "block");
            }, success: function (data) {
              $(elem).find("button[type=submit]").attr("disabled", false);
              $("#preloader").css("display", "none");
              var json = JSON.parse(data);
              
              if (json['result'] == "success") {
                $.toast({
                  text: json['message'],
                  showHideTransition: 'slide',
                  icon: 'success',
                  position: 'top-right'
                });
              }else{
                if (Array.isArray(json['message'])) {
                  jQuery.each(json['message'], function (i, val) {
                    $.toast({
                      text: val,
                      showHideTransition: 'slide',
                      icon: 'error',
                      position: 'top-right'
                    });
                  });
                } else {
                  $.toast({
                    text: json['message'],
                    showHideTransition: 'slide',
                    icon: 'error',
                    position: 'top-right'
                  });
                }
              }
            }
        });
    });

    let timer = null;

    $('input[name="workspace"]').on('keyup', function(){
        clearTimeout(timer);
        var workspace = $(this).val().trim();
        var $inputField = $(this);

        if (workspace.length > 0) {
            timer = setTimeout(function() {
                $("#get-started-btn").prop("disabled", true);
                $.ajax({
                    url: "/tenants/check-tenant-slug",
                    method: "GET",
                    data: { workspace: workspace },
                    success: function(response) {
                        if (response.exists) {
                            $inputField.parent().css("border", "2px solid #dc3545");
                            $inputField.parent().next('.success-msg').remove();

                            $("#icon-box").html(`<i class="bi bi-exclamation-circle-fill text-danger"></i>`);

                            if (!$inputField.parent().next('.error-msg').length) {
                                $inputField.parent().after(`<small class="text-danger fw-bold error-msg"><i class="bi bi-exclamation-circle-fill me-1"></i>${response.message}</small>`);
                            }else{
                                $inputField.parent().next('.error-msg').html(`<i class="bi bi-exclamation-circle-fill me-1"></i>${response.message}`);
                            }
                        } else {
                            $("#get-started-btn").prop("disabled", false);
                            $("#icon-box").html(`<i class="bi bi-check-circle-fill text-success"></i>`);

                            $inputField.parent().css("border", "2px solid #198754");
                            $inputField.parent().next('.error-msg').remove();
                        }
                    },
                    error: function (xhr) {
                        if (xhr.status === 422) {
                            var errors = xhr.responseJSON.errors;
                            var errorMessage = errors ? Object.values(errors).join('<br>') : 'Invalid input!';

                            $inputField.parent().css("border", "1px solid #dc3545");
                            $inputField.parent().next('.success-msg').remove();
                            $inputField.parent().next('.error-msg').remove();
                            
                            $inputField.parent().after(`<small class="text-danger error-msg"><i class="fas fa-exclamation-circle mr-1"></i> ${errorMessage}</small>`);
                        }
                    }
                });
            }, 500);
        } else {
            $inputField.parent().css("border", "");
            $inputField.parent().next('.error-msg').remove();
        }
    });

    // Enable Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    document.addEventListener("DOMContentLoaded", function(){
      window.addEventListener('scroll', function() {
          if (window.scrollY > 50) {
            document.getElementById('main_navbar').classList.add('sticky-navbar');
            // add padding top to show content behind navbar
            var navbar_height = document.querySelector('.navbar').offsetHeight;
            document.body.style.paddingTop = navbar_height + 'px';
          } else {
            document.getElementById('main_navbar').classList.remove('sticky-navbar');
             // remove padding top from body
            document.body.style.paddingTop = '0';
          } 
      });
    });

})(jQuery);