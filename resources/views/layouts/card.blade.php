{{--
Compatibility wrapper. The layout now lives in `components/layouts/card.blade.php`.

`<x-authentication::layouts.card />` resolves to the component path directly; this file only
serves `@include('authentication::layouts.card', get_defined_vars())` for host code that used the view path.
New customisation belongs in the component path.
--}}
@include('authentication::components.layouts.card', get_defined_vars())
