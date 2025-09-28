@isset($assets)

@if(is_array($assets) && in_array("datatable", $assets))
<script src="{{ asset('backend/plugins/datatable/datatables.min.js') }}"></script>
@endif

@if(is_array($assets) && in_array("slimscroll", $assets))
<script src="{{ asset('backend/plugins/slimscroll/jquery.slimscroll.min.js') }}"></script>
@endif

@if(is_array($assets) && in_array("tinymce", $assets))
<script src="{{ asset('backend/plugins/tinymce/tinymce.min.js') }}"></script>
@endif

@if(is_array($assets) && in_array("summernote", $assets))
<script src="{{ asset('backend/plugins/summernote/summernote-bs4.min.js') }}"></script>
@endif

@endisset