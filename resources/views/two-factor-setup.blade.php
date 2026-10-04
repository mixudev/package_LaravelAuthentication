{{--
Compatibility wrapper: implementation moved to `authentication::pages.two-factor.setup`.

The flat alias remains available for compatibility with direct package references.
New installs and published customizations should override the `pages/...` path instead.
--}}
@include('authentication::pages.two-factor.setup', get_defined_vars())
