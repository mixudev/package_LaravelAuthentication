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
    'verify_email_title' => 'Verifikasi alamat email Anda',
    'verify_email_subtitle' => 'Periksa kotak masuk Anda untuk tautan verifikasi.',
    'verify_email_resend' => 'Kirim ulang email verifikasi',

    /*
    |--------------------------------------------------------------------------
    | Pesan Error Autentikasi
    |--------------------------------------------------------------------------
    */
    'auth_failed'             => 'Email, username, atau kata sandi yang Anda masukkan tidak sesuai.',
    'invalid_credentials'     => 'Kredensial yang Anda masukkan tidak cocok dengan catatan kami.',
    'invalid_password'        => 'Kata sandi yang Anda masukkan salah.',
    'throttled'               => 'Terlalu banyak percobaan masuk. Silakan coba lagi dalam :seconds detik.',
    'throttle_error'          => 'Terlalu banyak percobaan. Silakan coba lagi dalam :seconds detik.',
    'throttle_error_unknown'  => 'Terlalu banyak percobaan. Silakan coba lagi nanti.',
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
    'two_factor_required'     => 'Diperlukan kode autentikasi dua langkah.',
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
    'otp_expired'             => 'Kode OTP telah kedaluwarsa. Silakan minta kode baru.',
    'otp_resent'              => 'Kode verifikasi baru telah dikirim ulang.',
    'mail_otp_title'          => 'Kode Verifikasi Masuk',
    'mail_otp_greeting'       => 'Halo',
    'mail_otp_body'           => 'Gunakan kode verifikasi berikut untuk masuk ke akun Anda:',
    'mail_otp_expiry'         => 'Kode ini berlaku selama :minutes menit. Demi keamanan, jangan bagikan kode ini kepada siapa pun.',
    'mail_otp_ignore'         => 'Jika Anda tidak meminta kode ini, abaikan email ini.',
    'mail_new_device_title'   => 'Pemberitahuan Masuk dari Perangkat Baru',
    'mail_new_device_greeting' => 'Halo',
    'mail_new_device_body'    => 'Kami mendeteksi aktivitas masuk ke akun Anda dari perangkat atau lokasi baru:',
    'mail_device_browser'     => 'Perangkat & Browser:',
    'mail_device_ip'          => 'Alamat IP:',
    'mail_device_location'    => 'Perkiraan Lokasi:',
    'mail_device_time'        => 'Waktu:',
    'mail_new_device_safe'    => 'Jika ini memang Anda, abaikan email ini. Jika Anda tidak melakukan aktivitas ini, segera amankan akun Anda:',
    'mail_secure_account'     => 'Amankan Akun & Cabut Sesi',
    'mail_new_device_footer'  => 'Email ini dikirim secara otomatis untuk membantu menjaga keamanan akun Anda.',

    /*
    |--------------------------------------------------------------------------
    | Pesan Reset Password
    |--------------------------------------------------------------------------
    */
    'password_reset_link_sent' => 'Jika terdapat akun dengan email tersebut, tautan pengaturan ulang kata sandi telah dikirim. Silakan periksa kotak masuk Anda.',
    'auth_throttled_later' => 'Terlalu banyak percobaan masuk. Silakan coba lagi nanti.',
    'email_verification_forbidden' => 'Tautan verifikasi ini bukan milik akun Anda.',
    'email_verification_invalid' => 'Tautan verifikasi email tidak valid. Hash tidak cocok dengan alamat email Anda saat ini.',
    /*
    |--------------------------------------------------------------------------
    | Label Atribut Form
    |--------------------------------------------------------------------------
    */
    'attribute_name' => 'nama',
    'attribute_email' => 'email',
    'attribute_password' => 'kata sandi',
    'attribute_password_confirmation' => 'konfirmasi kata sandi',
    'attribute_new_password' => 'kata sandi baru',
    'attribute_otp' => 'kode OTP',
    'attribute_identifier' => 'email atau nama pengguna',
    'authenticated' => 'Berhasil masuk.',
    'account_locked_support' => 'Akun Anda sedang dikunci sementara. Silakan hubungi dukungan.',
    'confirm_password_required' => 'Silakan konfirmasi kata sandi Anda.',
    'password_confirmed' => 'Kata sandi berhasil dikonfirmasi.',
    'email_already_verified' => 'Alamat email Anda sudah diverifikasi.',
    'email_verified' => 'Alamat email Anda berhasil diverifikasi.',
    'verification_link_sent' => 'Tautan verifikasi baru telah dikirim ke email Anda.',
    'otp_disabled' => 'Autentikasi dengan kode sekali pakai sedang dinonaktifkan.',
    'otp_sent_generic' => 'Jika terdapat akun dengan identitas tersebut, kode verifikasi telah dikirim.',
    'otp_send_failed' => 'Kami tidak dapat mengirim kode verifikasi saat ini. Silakan coba lagi nanti.',
    'otp_verified' => 'Verifikasi berhasil.',
    'otp_verify_failed' => 'Kami tidak dapat memverifikasi kode saat ini. Silakan minta kode baru dan coba lagi.',
    'passkey_throttled' => 'Terlalu banyak permintaan passkey. Silakan coba lagi nanti.',
    'password_reset_disabled' => 'Pemulihan kata sandi sedang dinonaktifkan.',
    'password_reset_generic' => 'Jika terdapat akun dengan email tersebut, tautan pengaturan ulang kata sandi telah dikirim.',
    'registration_failed' => 'Kami tidak dapat membuat akun Anda saat ini. Silakan coba lagi nanti.',
    'two_factor_challenge_required' => 'Autentikasi dua faktor diperlukan untuk melanjutkan.',
    'two_factor_session_invalid' => 'Sesi dua faktor Anda tidak valid atau sudah kedaluwarsa. Silakan masuk kembali.',
    'two_factor_success' => 'Autentikasi dua faktor berhasil.',
    'two_factor_already_enabled' => 'Autentikasi dua faktor sudah aktif pada akun Anda.',
    'social_provider_disabled' => 'Masuk dengan :provider tidak tersedia atau tidak didukung.',
    'social_auth_success' => 'Berhasil masuk menggunakan :provider.',
    'social_auth_failed' => 'Gagal masuk dengan :provider. Silakan coba lagi.',
    'password_reset_sent'     => 'Tautan untuk mengatur ulang kata sandi telah dikirimkan ke email Anda.',
    'password_reset_done'     => 'Kata sandi Anda berhasil diperbarui. Silakan masuk kembali.',
    'password_reset_invalid'  => 'Tautan reset password tidak valid atau sudah kedaluwarsa.',

    /*
    |--------------------------------------------------------------------------
    | Pesan Registrasi
    |--------------------------------------------------------------------------
    */
    'registered'              => 'Akun berhasil dibuat. Silakan masuk.',
    'registration_disabled'   => 'Pendaftaran akun saat ini dinonaktifkan.',
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
    'new_password_label'      => 'Kata Sandi Baru',
    'new_password_placeholder' => 'Masukkan kata sandi baru',

    'passkey_sign_in'         => 'Masuk dengan Passkey',
    'passkey_btn'             => 'Masuk dengan Passkey / Sidik Jari / Face ID',
    'passkey_register_btn'    => 'Daftarkan Passkey Baru',
    'passkey_title'           => 'Kunci Sandi (Passkeys / WebAuthn)',
    'passkey_subtitle'        => 'Masuk dengan aman tanpa kata sandi menggunakan Touch ID, Face ID, atau Kunci Keamanan FIDO2.',
    'passkey_name'            => 'Nama Perangkat Passkey',
    'passkey_registered'      => 'Passkey berhasil didaftarkan.',
    'passkey_deleted'         => 'Passkey berhasil dihapus.',
    'passkey_failed'          => 'Autentikasi Passkey gagal atau dibatalkan.',
    'passkey_registration_failed' => 'Gagal mendaftarkan passkey ini. Silakan coba lagi atau gunakan authenticator lain.',
    'passkey_not_supported'   => 'Passkey tidak didukung oleh browser atau perangkat ini.',
    'passkey_none_registered' => 'Belum ada Passkey yang terdaftar untuk akun ini. Silakan masuk menggunakan kata sandi.',
    'processing'              => 'Memproses...',
    'disable_2fa_password_placeholder' => 'Kata sandi saat ini',
    'setup_totp_placeholder'  => 'Contoh: 123456',

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

    /*
    |--------------------------------------------------------------------------
    | Pesan Validasi PasswordRule
    |--------------------------------------------------------------------------
    */
    'password_must_be_string'    => ':attribute harus berupa teks yang valid.',
    'password_min_length'        => ':attribute minimal harus terdiri dari :min karakter.',
    'password_require_uppercase' => ':attribute harus mengandung setidaknya satu huruf kapital.',
    'password_require_lowercase' => ':attribute harus mengandung setidaknya satu huruf kecil.',
    'password_require_number'    => ':attribute harus mengandung setidaknya satu angka.',
    'password_require_symbol'    => ':attribute harus mengandung setidaknya satu karakter khusus (:charset).',

    /*
    |--------------------------------------------------------------------------
    | Pesan Validasi LoginIdentifierRule
    |--------------------------------------------------------------------------
    */
    'identifier_must_be_string'  => ':attribute harus berupa teks yang valid.',
    'identifier_length'          => ':attribute harus terdiri dari 3 hingga 255 karakter.',
    'identifier_invalid_chars'   => ':attribute mengandung karakter yang tidak diizinkan.',

    /*
    |--------------------------------------------------------------------------
    | Pesan Validasi SecurityPolicyRule
    |--------------------------------------------------------------------------
    */
    'security_null_byte'         => ':attribute mengandung karakter null byte yang dilarang.',

    /*
    |--------------------------------------------------------------------------
    | String countdown / hitung mundur
    |--------------------------------------------------------------------------
    */
    'retry_wait'             => 'Terlalu banyak percobaan. Silakan coba lagi dalam',
    'retry_wait_finished'    => 'Waktu tunggu telah berakhir. Silakan coba masuk kembali.',
    'retry_minutes'          => ':minutes menit',
    'retry_minutes_seconds'  => ':minutes menit :seconds detik',
    'retry_seconds_only'     => ':seconds detik',

    'divider'                 => 'ATAU',
    'otp_disabled_runtime' => 'Autentikasi OTP sedang dinonaktifkan.',
    'otp_recently_requested' => 'OTP baru saja diminta. Silakan tunggu sebelum meminta kode baru.',
    'otp_expired_or_invalid' => 'Kode OTP telah kedaluwarsa atau tidak valid.',
    'otp_too_many_attempts' => 'Terlalu banyak percobaan tidak valid. Silakan minta kode OTP baru.',
    'otp_incorrect' => 'Kode OTP yang diberikan salah.',
    'passkey_challenge_used' => 'Tantangan pendaftaran passkey telah kedaluwarsa atau sudah digunakan. Silakan coba lagi.',
    'passkey_challenge_expired' => 'Tantangan pendaftaran passkey telah kedaluwarsa. Silakan coba lagi.',
    'passkey_credential_missing' => 'ID kredensial WebAuthn tidak ditemukan.',
    'passkey_payload_invalid' => 'Payload pendaftaran WebAuthn tidak memiliki kunci publik atau objek attestation.',
    'passkey_challenge_missing' => 'Challenge tidak ditemukan dalam clientDataJSON WebAuthn.',
    'passkey_assertion_invalid' => 'Challenge passkey telah kedaluwarsa atau tidak valid.',
    'passkey_clone_detected' => 'Authenticator WebAuthn hasil kloning terdeteksi: sign counter tidak valid.',
    'password_must_differ' => 'Kata sandi baru harus berbeda dari kata sandi saat ini.',
    'auth_disabled_runtime' => 'Layanan autentikasi sedang dinonaktifkan.',
    'auth_too_many_attempts' => 'Terlalu banyak percobaan masuk.',
    'strategy_unsupported' => 'Strategi autentikasi yang diminta tidak didukung.',
    'registration_disabled_runtime' => 'Pendaftaran akun sedang dinonaktifkan.',
    'otp_account_missing' => 'OTP berhasil diverifikasi, tetapi akun untuk identitas tersebut tidak ditemukan.',
    'account_locked_runtime' => 'Akun sedang dikunci sementara.',
    'password_reset_token_invalid' => 'Token pengaturan ulang kata sandi tidak valid atau sudah kedaluwarsa.',
    'passkey_payload_incomplete' => 'Payload assertion passkey tidak lengkap atau tidak valid.',
    'passkey_ceremony_invalid' => 'Jenis ceremony assertion passkey tidak valid.',
    'unauthenticated' => 'Anda belum terautentikasi.',
    'otp_invalid' => 'Kode verifikasi salah atau sudah kedaluwarsa.',
];
