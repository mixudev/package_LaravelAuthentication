{{--
Compatibility wrapper: implementation moved to `authentication::pages.otp.verify`.

The flat alias remains available for compatibility with direct package references.
New installs and published customizations should override the `pages/...` path instead.
--}}
@include('authentication::pages.otp.verify', get_defined_vars())
