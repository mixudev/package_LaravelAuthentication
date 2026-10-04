{{--
Compatibility wrapper. The layout now lives in `components/layouts/split.blade.php`.

`<x-authentication::layouts.split />` resolves to the component path directly; this file only
serves `@include('authentication::layouts.split', get_defined_vars())` for host code that used the view path.
New customisation belongs in the component path.
--}}
@include('authentication::components.layouts.split', get_defined_vars())
