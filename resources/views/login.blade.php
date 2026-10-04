{{--
Compatibility wrapper: implementation moved to `authentication::pages.auth.login`.

The flat alias remains available for compatibility with direct package references.
New installs and published customizations should override the `pages/...` path instead.
--}}
@include('authentication::pages.auth.login', get_defined_vars())
