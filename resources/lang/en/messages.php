<?php

declare(strict_types=1);

/*
|=============================================================================
| LANGUAGE FILE: ENGLISH
| Package: mixudev/laravel-authentication
| Description: All authentication system messages in English.
|=============================================================================
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Error Messages
    |--------------------------------------------------------------------------
    */
    'auth_failed'             => 'These credentials do not match our records.',
    'invalid_credentials'     => 'These credentials do not match our records.',
    'invalid_password'        => 'The provided password was incorrect.',
    'unauthenticated'         => 'Your session is unauthenticated.',
    'throttled'               => 'Too many login attempts. Please try again in :seconds seconds.',
    'throttle_error'          => 'Too many attempts. Please try again in :seconds seconds.',
    'account_locked'          => 'Your account is temporarily locked for security reasons.',
    'logged_out'              => 'You have been logged out successfully.',
    'password_history'        => 'You cannot reuse any of your recent passwords.',
    'captcha_failed'          => 'CAPTCHA verification failed or expired. Please try again.',

    /*
    |--------------------------------------------------------------------------
    | 2FA & Session Messages
    |--------------------------------------------------------------------------
    */
    'invalid_two_factor_code' => 'The two-factor authentication or recovery code is invalid.',
    'two_factor_enabled'      => 'Two-factor authentication (2FA) has been enabled.',
    'two_factor_disabled'     => 'Two-factor authentication (2FA) has been disabled.',
    'session_revoked'         => 'The session was successfully revoked.',
    'other_sessions_revoked'  => 'All other active sessions have been logged out.',

    /*
    |--------------------------------------------------------------------------
    | OTP Messages
    |--------------------------------------------------------------------------
    */
    'otp_sent'                => 'A verification code has been sent to your email.',
    'otp_invalid'             => 'The verification code is invalid or has expired.',
    'otp_expired'             => 'The OTP code has expired. Please request a new one.',
    'otp_resent'              => 'A new verification code has been resent.',

    /*
    |--------------------------------------------------------------------------
    | Password Reset Messages
    |--------------------------------------------------------------------------
    */
    'password_reset_sent'     => 'A password reset link has been sent to your email.',
    'password_reset_done'     => 'Your password has been updated successfully. Please log in again.',
    'password_reset_invalid'  => 'This password reset link is invalid or has expired.',

    /*
    |--------------------------------------------------------------------------
    | Registration Messages
    |--------------------------------------------------------------------------
    */
    'registered'              => 'Account created successfully. Please log in.',
    'email_taken'             => 'This email address is already in use.',
    'username_taken'          => 'This username is already taken.',

    /*
    |--------------------------------------------------------------------------
    | UI Labels
    |--------------------------------------------------------------------------
    */
    'sign_in'                 => 'Sign In to Your Account',
    'sign_in_subtitle'        => 'Please enter your credentials to continue.',
    'sign_in_btn'             => 'Sign In',
    'sign_in_otp'             => 'Sign in without password using OTP Code',
    'no_account'              => 'Don\'t have an account?',
    'register_now'            => 'Register now',
    'forgot_password'         => 'Forgot password?',
    'remember_me'             => 'Remember me',
    'identifier_label'        => 'Email or Username',
    'identifier_placeholder'  => 'name@domain.com or username',
    'password_label'          => 'Password',
    'password_placeholder'    => 'Enter your password',

    'register_title'          => 'Create New Account',
    'register_subtitle'       => 'Fill in the information below to register your account.',
    'register_btn'            => 'Create Account',
    'already_account'         => 'Already have an account?',
    'login_here'              => 'Sign in here',
    'terms_agree'             => 'I agree to the',
    'terms_label'             => 'Terms & Conditions',

    'otp_request_title'       => 'Passwordless Sign In',
    'otp_request_subtitle'    => 'Enter your email to receive a one-time verification code.',
    'otp_request_btn'         => 'Send Verification Code',
    'back_to_login'           => 'Sign in with password instead',
    'back_to_login_arrow'     => 'Back to login',

    'otp_verify_title'        => 'Verify Your Code',
    'otp_verify_subtitle'     => 'Enter the 6-digit security code sent to your email.',
    'otp_verify_btn'          => 'Verify & Sign In',
    'otp_resend_hint'         => 'Didn\'t receive a code?',
    'otp_resend_btn'          => 'Resend',
    'remember_device'         => 'Remember me on this device',

    'two_factor_title'        => 'Two-Factor Authentication',
    'two_factor_subtitle'     => 'Open your Authenticator app (Google Auth/Authy) and enter the 6-digit code.',
    'two_factor_btn'          => 'Verify & Proceed',
    'two_factor_use_recovery' => 'Use a Recovery Backup Code',
    'two_factor_use_totp'     => 'Use Authenticator App Code',
    'trust_device_label'      => 'Trust this device for 30 days',

    'confirm_password_title'    => 'Confirm Password',
    'confirm_password_subtitle' => 'This is a secure area. Please confirm your password before continuing.',
    'confirm_password_btn'      => 'Confirm Password',

    'sessions_title'          => 'Active Sessions & Devices',
    'sessions_subtitle'       => 'Manage and revoke active login sessions on your devices.',
    'current_session'         => 'Current Device',
    'last_active'             => 'Last Active',
    'revoke_btn'              => 'Revoke Access',
    'revoke_others_btn'       => 'Log Out All Other Devices',

    'forgot_title'            => 'Password Recovery',
    'forgot_subtitle'         => 'Enter your registered email and we\'ll send you a reset link.',
    'forgot_btn'              => 'Send Reset Link',

    'reset_title'             => 'Reset Your Password',
    'reset_subtitle'          => 'Please enter a new password for your account.',
    'reset_btn'               => 'Save New Password',
    'full_name'               => 'Full Name',
    'full_name_placeholder'   => 'Your full name',
    'email_label'             => 'Email Address',
    'email_placeholder'       => 'name@domain.com',
    'confirm_password_label'  => 'Confirm Password',
    'confirm_password_placeholder' => 'Repeat password',

    'passkey_sign_in'         => 'Sign In with Passkey',
    'passkey_btn'             => 'Sign in with Passkey / Face ID',
    'passkey_register_btn'    => 'Register New Passkey',
    'passkey_title'           => 'Passkeys (FIDO2 / WebAuthn)',
    'passkey_subtitle'        => 'Log in securely without passwords using Touch ID, Face ID, or Security Keys.',
    'passkey_name'            => 'Passkey Device Name',
    'passkey_registered'      => 'Passkey registered successfully.',
    'passkey_deleted'         => 'Passkey deleted successfully.',
    'passkey_failed'          => 'Passkey authentication failed or cancelled.',
    'passkey_not_supported'   => 'Passkeys are not supported on this browser or device.',
    'passkey_none_registered' => 'No passkeys found for this account. Please log in with your password.',

    /*
    |--------------------------------------------------------------------------
    | Form Input Validation Messages
    |--------------------------------------------------------------------------
    */
    'validation_required'     => 'The :attribute field is required.',
    'validation_email'        => 'The :attribute must be a valid email address.',
    'validation_min'          => 'The :attribute must be at least :min characters.',
    'validation_max'          => 'The :attribute may not be greater than :max characters.',
    'validation_confirmed'    => 'The :attribute confirmation does not match.',
    'validation_string'       => 'The :attribute must be a string.',
    'validation_unique'       => 'The :attribute has already been taken.',
    'validation_numeric'      => 'The :attribute must be a number.',
    'validation_digits'       => 'The :attribute must be :digits digits.',

    'divider'                 => 'OR',
];
