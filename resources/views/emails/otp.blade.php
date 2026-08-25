<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kode Verifikasi Masuk</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background-color: #F4F5F7;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      color: #171B20;
      -webkit-font-smoothing: antialiased;
    }
    .wrapper {
      width: 100%;
      background-color: #F4F5F7;
      padding: 40px 0;
    }
    .container {
      max-width: 540px;
      margin: 0 auto;
      background-color: #FFFFFF;
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(18, 24, 31, 0.06);
      border: 1px solid #E7E3DA;
    }
    .header {
      background: #131C24;
      padding: 32px 36px 28px;
      text-align: left;
    }
    .logo-badge {
      display: inline-block;
      padding: 6px 12px;
      background: rgba(232, 163, 61, 0.15);
      border: 1px solid rgba(232, 163, 61, 0.35);
      border-radius: 6px;
      color: #F2C57C;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-bottom: 12px;
    }
    .header h1 {
      margin: 0;
      color: #FFFFFF;
      font-size: 22px;
      font-weight: 700;
      letter-spacing: -0.3px;
    }
    .content {
      padding: 36px;
    }
    .greeting {
      font-size: 16px;
      line-height: 1.5;
      color: #171B20;
      margin: 0 0 16px;
    }
    .instructions {
      font-size: 14.5px;
      line-height: 1.6;
      color: #5C6773;
      margin: 0 0 28px;
    }
    .otp-box {
      background: #FAF9F6;
      border: 2px dashed #E8A33D;
      border-radius: 12px;
      padding: 24px;
      text-align: center;
      margin: 0 0 28px;
    }
    .otp-label {
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1.2px;
      color: #8D97A5;
      margin-bottom: 8px;
    }
    .otp-code {
      font-family: 'SF Mono', Consolas, 'Liberation Mono', Menlo, Courier, monospace;
      font-size: 36px;
      font-weight: 700;
      letter-spacing: 8px;
      color: #131C24;
      margin: 0;
    }
    .expiry-note {
      font-size: 12.5px;
      color: #E8A33D;
      font-weight: 600;
      margin-top: 10px;
    }
    .security-notice {
      background: #FEF3F2;
      border-left: 4px solid #F04438;
      padding: 14px 16px;
      border-radius: 6px;
      margin: 0 0 28px;
    }
    .security-notice p {
      margin: 0;
      font-size: 13px;
      line-height: 1.5;
      color: #B42318;
    }
    .footer {
      background: #FAF9F6;
      border-top: 1px solid #E7E3DA;
      padding: 24px 36px;
      text-align: center;
      font-size: 12px;
      color: #8D97A5;
      line-height: 1.5;
    }
    .footer a {
      color: #5C6773;
      text-decoration: underline;
    }
  </style>
</head>
<body>

<div class="wrapper">
  <div class="container">
    
    <!-- Header -->
    <div class="header">
      <div class="logo-badge">{{ $appName }} Security</div>
      <h1>Verifikasi Masuk Akun</h1>
    </div>

    <!-- Main Content -->
    <div class="content">
      <p class="greeting">
        Halo{{ isset($user) && !empty($user->name) ? ', <strong>' . e($user->name) . '</strong>' : '' }},
      </p>

      <p class="instructions">
        Kami menerima permintaan masuk ke akun Anda menggunakan One-Time Password (OTP). Gunakan kode verifikasi di bawah ini untuk menyelesaikan proses masuk:
      </p>

      <!-- OTP Display -->
      <div class="otp-box">
        <div class="otp-label">Kode Verifikasi Anda</div>
        <div class="otp-code">{{ $code }}</div>
        <div class="expiry-note">⏱ Berlaku selama {{ $expiryMinutes }} menit</div>
      </div>

      <!-- Security Notice -->
      <div class="security-notice">
        <p>
          <strong>Peringatan Keamanan:</strong> Jangan pernah membagikan kode ini kepada siapa pun. Tim {{ $appName }} tidak akan pernah meminta kode verifikasi Anda.
        </p>
      </div>

      <p class="instructions" style="margin-bottom: 0; font-size: 13px;">
        Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini atau segera ubah kata sandi akun Anda.
      </p>
    </div>

    <!-- Footer -->
    <div class="footer">
      <p style="margin: 0 0 6px;">
        Email ini dikirim secara otomatis oleh sistem keamanan <strong>{{ $appName }}</strong>.
      </p>
      <p style="margin: 0;">
        © {{ date('Y') }} {{ $appName }}. Seluruh hak cipta dilindungi.
      </p>
    </div>

  </div>
</div>

</body>
</html>
