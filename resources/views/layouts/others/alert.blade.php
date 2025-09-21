<script>
(function($) {
    "use strict";

    //Show success message
    @if(Session::has('success'))
        $("#main_alert > span.msg").html(" {{ session('success') }} ");
        $("#main_alert").addClass("alert-success").removeClass("alert-danger");
        $("#main_alert").css('display','block');
    @endif
    
    //Show error message
    @if(Session::has('error'))
        $("#main_alert > span.msg").html(" {{ session('error') }} ");
        $("#main_alert").addClass("alert-danger").removeClass("alert-success");
        $("#main_alert").css('display','block');
    @endif


    @foreach ($errors->all() as $error)
        @if ($loop->first)
            $("#main_alert > span.msg").html("<i class='fas fa-exclamation-circle mr-1'></i>{{ $error }} ");
            $("#main_alert").addClass("alert-danger").removeClass("alert-success");
        @else
            $("#main_alert > span.msg").append("<br><i class='fas fa-exclamation-circle mr-1'></i>{{ $error }} ");					
        @endif
        
        @if ($loop->last)
            $("#main_alert").css('display','block');
        @endif

        @if(isset($errors->keys()[$loop->index]))
            var name = "{{ $errors->keys()[$loop->index] }}";

            $("input[name='" + name + "']").addClass('error is-invalid');
            $("select[name='" + name + "'] + span").addClass('error is-invalid');

            if(! $("input[name='"+name+"'], select[name='"+name+"']").prev().hasClass('col-form-label')){
                if(! $("input[name='"+name+"'], select[name='"+name+"']").hasClass('no-msg')){
                    $("input[name='"+name+"'], select[name='"+name+"']").after("<span class='v-error'><i class='fas fa-exclamation-circle mr-1'></i>{{$error}}</span>");
                }
            }else{
                if(! $("input[name='"+name+"'], select[name='"+name+"']").hasClass('no-msg')){
                    $("input[name='"+name+"'], select[name='"+name+"']").parent().parent().append("<span class='v-error'><i class='fas fa-exclamation-circle mr-1'></i>{{$error}}</span>");
                }
            }
        @endif

    @endforeach

})(jQuery);

</script>