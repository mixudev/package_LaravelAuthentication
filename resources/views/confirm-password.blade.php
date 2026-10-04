{{--
Compatibility wrapper: implementation moved to `authentication::pages.auth.confirm-password`.

The flat alias remains available for compatibility with direct package references.
New installs and published customizations should override the `pages/...` path instead.
--}}
@include('authentication::pages.auth.confirm-password', get_defined_vars())
