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
    'auth_failed'           => 'These credentials do not match our records.',
    'throttled'             => 'Too many login attempts. Please try again in :seconds seconds.',
    'account_locked'        => 'Your account is temporarily locked for security reasons.',
    'logged_out'            => 'You have been logged out successfully.',
    'password_history'      => 'You cannot reuse any of your recent passwords.',

    /*
    |--------------------------------------------------------------------------
    | OTP Messages
    |--------------------------------------------------------------------------
    */
    'otp_sent'              => 'A verification code has been sent to your email.',
    'otp_invalid'           => 'The verification code is invalid or has expired.',
    'otp_expired'           => 'The OTP code has expired. Please request a new one.',
    'otp_resent'            => 'A new verification code has been resent.',

    /*
    |--------------------------------------------------------------------------
    | Password Reset Messages
    |--------------------------------------------------------------------------
    */
    'password_reset_sent'   => 'A password reset link has been sent to your email.',
    'password_reset_done'   => 'Your password has been updated successfully. Please log in again.',
    'password_reset_invalid'=> 'This password reset link is invalid or has expired.',

    /*
    |--------------------------------------------------------------------------
    | Registration Messages
    |--------------------------------------------------------------------------
    */
    'registered'            => 'Account created successfully. Please log in.',
    'email_taken'           => 'This email address is already in use.',
    'username_taken'        => 'This username is already taken.',

    /*
    |--------------------------------------------------------------------------
    | UI Labels
    |--------------------------------------------------------------------------
    */
    'sign_in'               => 'Sign In to Your Account',
    'sign_in_subtitle'      => 'Please enter your credentials to continue.',
    'sign_in_btn'           => 'Sign In',
    'sign_in_otp'           => 'Sign in without password using OTP Code',
    'no_account'            => 'Don\'t have an account?',
    'register_now'          => 'Register now',
    'forgot_password'       => 'Forgot password?',
    'remember_me'           => 'Remember me',
    'identifier_label'      => 'Email or Username',
    'identifier_placeholder'=> 'name@domain.com or username',
    'password_label'        => 'Password',
    'password_placeholder'  => 'Enter your password',

    'register_title'        => 'Create New Account',
    'register_subtitle'     => 'Fill in the information below to register your account.',
    'register_btn'          => 'Create Account',
    'already_account'       => 'Already have an account?',
    'login_here'            => 'Sign in here',
    'terms_agree'           => 'I agree to the',
    'terms_label'           => 'Terms & Conditions',

    'otp_request_title'     => 'Passwordless Sign In',
    'otp_request_subtitle'  => 'Enter your email to receive a one-time verification code.',
    'otp_request_btn'       => 'Send Verification Code',
    'back_to_login'         => 'Sign in with password instead',
    'back_to_login_arrow'   => 'Back to login',

    'otp_verify_title'      => 'Verify Your Code',
    'otp_verify_subtitle'   => 'Enter the 6-digit security code sent to your email.',
    'otp_verify_btn'        => 'Verify & Sign In',
    'otp_resend_hint'       => 'Didn\'t receive a code?',
    'otp_resend_btn'        => 'Resend',
    'remember_device'       => 'Remember me on this device',

    'forgot_title'          => 'Password Recovery',
    'forgot_subtitle'       => 'Enter your registered email and we\'ll send you a reset link.',
    'forgot_btn'            => 'Send Reset Link',

    'reset_title'           => 'Reset Your Password',
    'reset_subtitle'        => 'Please enter a new password for your account.',
    'reset_btn'             => 'Save New Password',
    'new_password_label'    => 'New Password',
    'new_password_ph'       => 'Minimum 8 characters',
    'confirm_password_label'=> 'Confirm New Password',
    'confirm_password_ph'   => 'Repeat new password',

    'divider'               => 'OR',
];
