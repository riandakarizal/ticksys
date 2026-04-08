@if(session('success'))
    <div class="hidden" data-page-toast data-toast-type="success" data-toast-message="{{ session('success') }}"></div>
@endif

@if($errors->any())
    <div class="hidden" data-page-toast data-toast-type="error" data-toast-message="{{ $errors->all()[0] }}"></div>
@endif
