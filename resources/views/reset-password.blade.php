{{--
Compatibility wrapper: implementation moved to `authentication::pages.password.reset-password`.

The flat alias remains available for compatibility with direct package references.
New installs and published customizations should override the `pages/...` path instead.
--}}
@include('authentication::pages.password.reset-password', get_defined_vars())
