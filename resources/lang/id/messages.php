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
    'auth_failed'           => 'Email, username, atau kata sandi yang Anda masukkan tidak sesuai.',
    'throttled'             => 'Terlalu banyak percobaan masuk. Silakan coba lagi dalam :seconds detik.',
    'account_locked'        => 'Akun Anda sementara dikunci karena alasan keamanan.',
    'logged_out'            => 'Anda berhasil keluar dari sistem.',
    'password_history'      => 'Anda tidak dapat menggunakan kata sandi yang pernah dipakai sebelumnya.',

    /*
    |--------------------------------------------------------------------------
    | Pesan OTP
    |--------------------------------------------------------------------------
    */
    'otp_sent'              => 'Kode verifikasi telah dikirimkan ke email Anda.',
    'otp_invalid'           => 'Kode verifikasi tidak valid atau sudah kedaluwarsa.',
    'otp_expired'           => 'Kode OTP telah kedaluwarsa. Silakan minta kode baru.',
    'otp_resent'            => 'Kode verifikasi baru telah dikirim ulang.',

    /*
    |--------------------------------------------------------------------------
    | Pesan Reset Password
    |--------------------------------------------------------------------------
    */
    'password_reset_sent'   => 'Tautan untuk mengatur ulang kata sandi telah dikirimkan ke email Anda.',
    'password_reset_done'   => 'Kata sandi Anda berhasil diperbarui. Silakan masuk kembali.',
    'password_reset_invalid'=> 'Tautan reset password tidak valid atau sudah kedaluwarsa.',

    /*
    |--------------------------------------------------------------------------
    | Pesan Registrasi
    |--------------------------------------------------------------------------
    */
    'registered'            => 'Akun berhasil dibuat. Silakan masuk.',
    'email_taken'           => 'Alamat email ini sudah digunakan oleh akun lain.',
    'username_taken'        => 'Username ini sudah digunakan oleh akun lain.',

    /*
    |--------------------------------------------------------------------------
    | Label UI
    |--------------------------------------------------------------------------
    */
    'sign_in'               => 'Masuk ke Akun',
    'sign_in_subtitle'      => 'Silakan masukkan kredensial Anda untuk melanjutkan.',
    'sign_in_btn'           => 'Masuk',
    'sign_in_otp'           => 'Masuk tanpa password via Kode OTP',
    'no_account'            => 'Belum memiliki akun?',
    'register_now'          => 'Daftar sekarang',
    'forgot_password'       => 'Lupa password?',
    'remember_me'           => 'Ingat sesi saya',
    'identifier_label'      => 'Email atau Username',
    'identifier_placeholder'=> 'nama@domain.com atau username',
    'password_label'        => 'Kata Sandi',
    'password_placeholder'  => 'Masukkan kata sandi',

    'register_title'        => 'Buat Akun Baru',
    'register_subtitle'     => 'Lengkapi informasi di bawah untuk mendaftarkan akun.',
    'register_btn'          => 'Daftar Akun',
    'already_account'       => 'Sudah memiliki akun?',
    'login_here'            => 'Masuk ke sistem',
    'terms_agree'           => 'Saya menyetujui',
    'terms_label'           => 'Syarat & Ketentuan',

    'otp_request_title'     => 'Masuk Tanpa Kata Sandi',
    'otp_request_subtitle'  => 'Masukkan email Anda untuk menerima kode OTP verifikasi sekali pakai.',
    'otp_request_btn'       => 'Kirim Kode Verifikasi',
    'back_to_login'         => 'Masuk dengan kata sandi biasa',
    'back_to_login_arrow'   => 'Kembali ke halaman masuk',

    'otp_verify_title'      => 'Verifikasi Kode Masuk',
    'otp_verify_subtitle'   => 'Masukkan 6 digit kode keamanan yang telah dikirimkan ke email Anda.',
    'otp_verify_btn'        => 'Verifikasi & Masuk',
    'otp_resend_hint'       => 'Tidak menerima kode?',
    'otp_resend_btn'        => 'Kirim ulang',
    'remember_device'       => 'Ingat sesi saya pada perangkat ini',

    'forgot_title'          => 'Pemulihan Kata Sandi',
    'forgot_subtitle'       => 'Masukkan email terdaftar Anda dan kami akan mengirimkan tautan untuk mengatur ulang kata sandi.',
    'forgot_btn'            => 'Kirim Tautan Reset',

    'reset_title'           => 'Atur Ulang Kata Sandi',
    'reset_subtitle'        => 'Silakan masukkan kata sandi baru untuk akun Anda.',
    'reset_btn'             => 'Simpan Kata Sandi Baru',
    'new_password_label'    => 'Kata Sandi Baru',
    'new_password_ph'       => 'Minimal 8 karakter baru',
    'confirm_password_label'=> 'Konfirmasi Kata Sandi Baru',
    'confirm_password_ph'   => 'Ulangi kata sandi baru',

    'divider'               => 'ATAU',
];
