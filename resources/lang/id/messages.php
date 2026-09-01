<?php

declare(strict_types=1);

/*
|=============================================================================
| FILE BAHASA: INDONESIA
| Package: mixudev/laravel-authentication
| Deskripsi: Semua pesan sistem autentikasi dalam Bahasa Indonesia.
|=============================================================================
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Pesan Error Autentikasi
    |--------------------------------------------------------------------------
    */
    'auth_failed'             => 'Email, username, atau kata sandi yang Anda masukkan tidak sesuai.',
    'invalid_credentials'     => 'Kredensial yang Anda masukkan tidak cocok dengan catatan kami.',
    'invalid_password'        => 'Kata sandi yang Anda masukkan salah.',
    'unauthenticated'         => 'Sesi Anda belum terautentikasi.',
    'throttled'               => 'Terlalu banyak percobaan masuk. Silakan coba lagi dalam :seconds detik.',
    'throttle_error'          => 'Terlalu banyak percobaan. Silakan coba lagi dalam :seconds detik.',
    'account_locked'          => 'Akun Anda sementara dikunci karena alasan keamanan.',
    'logged_out'              => 'Anda berhasil keluar dari sistem.',
    'password_history'        => 'Anda tidak dapat menggunakan kata sandi yang pernah dipakai sebelumnya.',
    'captcha_failed'          => 'Verifikasi CAPTCHA gagal atau kedaluwarsa. Silakan ulangi.',

    /*
    |--------------------------------------------------------------------------
    | Pesan 2FA & Sesi
    |--------------------------------------------------------------------------
    */
    'invalid_two_factor_code' => 'Kode autentikasi dua langkah atau kode pemulihan tidak valid.',
    'two_factor_enabled'      => 'Autentikasi dua langkah (2FA) berhasil diaktifkan.',
    'two_factor_disabled'     => 'Autentikasi dua langkah (2FA) telah dinonaktifkan.',
    'session_revoked'         => 'Sesi perangkat berhasil dicabut.',
    'other_sessions_revoked'  => 'Semua sesi pada perangkat lain berhasil dikeluarkan.',

    /*
    |--------------------------------------------------------------------------
    | Pesan OTP
    |--------------------------------------------------------------------------
    */
    'otp_sent'                => 'Kode verifikasi telah dikirimkan ke email Anda.',
    'otp_invalid'             => 'Kode verifikasi tidak valid atau sudah kedaluwarsa.',
    'otp_expired'             => 'Kode OTP telah kedaluwarsa. Silakan minta kode baru.',
    'otp_resent'              => 'Kode verifikasi baru telah dikirim ulang.',

    /*
    |--------------------------------------------------------------------------
    | Pesan Reset Password
    |--------------------------------------------------------------------------
    */
    'password_reset_sent'     => 'Tautan untuk mengatur ulang kata sandi telah dikirimkan ke email Anda.',
    'password_reset_done'     => 'Kata sandi Anda berhasil diperbarui. Silakan masuk kembali.',
    'password_reset_invalid'  => 'Tautan reset password tidak valid atau sudah kedaluwarsa.',

    /*
    |--------------------------------------------------------------------------
    | Pesan Registrasi
    |--------------------------------------------------------------------------
    */
    'registered'              => 'Akun berhasil dibuat. Silakan masuk.',
    'email_taken'             => 'Alamat email ini sudah digunakan oleh akun lain.',
    'username_taken'          => 'Username ini sudah digunakan oleh akun lain.',

    /*
    |--------------------------------------------------------------------------
    | Label UI
    |--------------------------------------------------------------------------
    */
    'sign_in'                 => 'Masuk ke Akun',
    'sign_in_subtitle'        => 'Silakan masukkan kredensial Anda untuk melanjutkan.',
    'sign_in_btn'             => 'Masuk',
    'sign_in_otp'             => 'Masuk tanpa password via Kode OTP',
    'no_account'              => 'Belum memiliki akun?',
    'register_now'            => 'Daftar sekarang',
    'forgot_password'         => 'Lupa password?',
    'remember_me'             => 'Ingat sesi saya',
    'identifier_label'        => 'Email atau Username',
    'identifier_placeholder'  => 'nama@domain.com atau username',
    'password_label'          => 'Kata Sandi',
    'password_placeholder'    => 'Masukkan kata sandi',

    'register_title'          => 'Buat Akun Baru',
    'register_subtitle'       => 'Lengkapi informasi di bawah untuk mendaftarkan akun.',
    'register_btn'            => 'Daftar Akun',
    'already_account'         => 'Sudah memiliki akun?',
    'login_here'              => 'Masuk ke sistem',
    'terms_agree'             => 'Saya menyetujui',
    'terms_label'             => 'Syarat & Ketentuan',

    'otp_request_title'       => 'Masuk Tanpa Kata Sandi',
    'otp_request_subtitle'    => 'Masukkan email Anda untuk menerima kode OTP verifikasi sekali pakai.',
    'otp_request_btn'         => 'Kirim Kode Verifikasi',
    'back_to_login'           => 'Masuk dengan kata sandi biasa',
    'back_to_login_arrow'     => 'Kembali ke halaman masuk',

    'otp_verify_title'        => 'Verifikasi Kode Masuk',
    'otp_verify_subtitle'     => 'Masukkan 6 digit kode keamanan yang telah dikirimkan ke email Anda.',
    'otp_verify_btn'          => 'Verifikasi & Masuk',
    'otp_resend_hint'         => 'Tidak menerima kode?',
    'otp_resend_btn'          => 'Kirim ulang',
    'remember_device'         => 'Ingat sesi saya pada perangkat ini',

    'two_factor_title'        => 'Autentikasi Dua Langkah',
    'two_factor_subtitle'     => 'Buka aplikasi Authenticator (Google Auth/Authy) dan masukkan kode 6-digit.',
    'two_factor_btn'          => 'Verifikasi & Lanjutkan',
    'two_factor_use_recovery' => 'Gunakan Kode Pemulihan Cadangan',
    'two_factor_use_totp'     => 'Gunakan Kode Aplikasi Authenticator',
    'trust_device_label'      => 'Percayai perangkat ini selama 30 hari',

    'confirm_password_title'    => 'Konfirmasi Kata Sandi',
    'confirm_password_subtitle' => 'Ini adalah area sensitif. Harap konfirmasikan kata sandi Anda sebelum melanjutkan.',
    'confirm_password_btn'      => 'Konfirmasi Kata Sandi',

    'sessions_title'          => 'Perangkat & Sesi Aktif',
    'sessions_subtitle'       => 'Kelola dan cabut akses sesi perangkat yang sedang login ke akun Anda.',
    'current_session'         => 'Perangkat Saat Ini',
    'last_active'             => 'Aktivitas Terakhir',
    'revoke_btn'              => 'Cabut Akses',
    'revoke_others_btn'       => 'Keluarkan Semua Perangkat Lain',

    'forgot_title'            => 'Pemulihan Kata Sandi',
    'forgot_subtitle'         => 'Masukkan email terdaftar Anda dan kami akan mengirimkan tautan untuk mengatur ulang kata sandi.',
    'forgot_btn'              => 'Kirim Tautan Reset',

    'reset_title'             => 'Atur Ulang Kata Sandi',
    'reset_subtitle'          => 'Silakan masukkan kata sandi baru untuk akun Anda.',
    'reset_btn'               => 'Simpan Kata Sandi Baru',
    'full_name'               => 'Nama Lengkap',
    'full_name_placeholder'   => 'Nama lengkap Anda',
    'email_label'             => 'Alamat Email',
    'email_placeholder'       => 'nama@domain.com',
    'confirm_password_label'  => 'Konfirmasi Kata Sandi',
    'confirm_password_placeholder' => 'Ulangi kata sandi',

    'passkey_sign_in'         => 'Masuk dengan Passkey',
    'passkey_btn'             => 'Masuk dengan Passkey / Sidik Jari / Face ID',
    'passkey_register_btn'    => 'Daftarkan Passkey Baru',
    'passkey_title'           => 'Kunci Sandi (Passkeys / WebAuthn)',
    'passkey_subtitle'        => 'Masuk dengan aman tanpa kata sandi menggunakan Touch ID, Face ID, atau Kunci Keamanan FIDO2.',
    'passkey_name'            => 'Nama Perangkat Passkey',
    'passkey_registered'      => 'Passkey berhasil didaftarkan.',
    'passkey_deleted'         => 'Passkey berhasil dihapus.',
    'passkey_failed'          => 'Autentikasi Passkey gagal atau dibatalkan.',
    'passkey_not_supported'   => 'Passkey tidak didukung oleh browser atau perangkat ini.',
    'passkey_none_registered' => 'Belum ada Passkey yang terdaftar untuk akun ini. Silakan masuk menggunakan kata sandi.',

    /*
    |--------------------------------------------------------------------------
    | Pesan Validasi Form Input
    |--------------------------------------------------------------------------
    */
    'validation_required'     => ':attribute wajib diisi.',
    'validation_email'        => ':attribute harus berupa alamat email yang valid.',
    'validation_min'          => ':attribute minimal harus berisikan :min karakter.',
    'validation_max'          => ':attribute maksimal berisikan :max karakter.',
    'validation_confirmed'    => 'Konfirmasi :attribute tidak cocok.',
    'validation_string'       => ':attribute harus berupa teks valid.',
    'validation_unique'       => ':attribute ini sudah digunakan.',
    'validation_numeric'      => ':attribute harus berupa angka valid.',
    'validation_digits'       => ':attribute harus berisikan :digits digit angka.',

    'divider'                 => 'ATAU',
];
