{{--
Compatibility wrapper. The canonical base layout lives in `components/layouts/auth.blade.php`.
Use the component path for new customisation; this alias remains for legacy includes.
--}}
@include('authentication::components.layouts.auth', get_defined_vars())
