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
      background-color: #F8FAFC;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      color: #334155;
      -webkit-font-smoothing: antialiased;
    }
    .wrapper {
      width: 100%;
      background-color: #F8FAFC;
      padding: 48px 16px;
    }
    .card {
      max-width: 460px;
      margin: 0 auto;
      background-color: #FFFFFF;
      border: 1px solid #E2E8F0;
      border-radius: 12px;
      padding: 36px 32px;
      box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04);
    }
    .app-title {
      font-size: 15px;
      font-weight: 700;
      color: #0F172A;
      margin: 0 0 24px;
      letter-spacing: -0.2px;
    }
    .greeting {
      font-size: 15px;
      line-height: 1.6;
      color: #1E293B;
      margin: 0 0 12px;
    }
    .text {
      font-size: 14px;
      line-height: 1.6;
      color: #475569;
      margin: 0 0 24px;
    }
    .otp-wrapper {
      background-color: #F8FAFC;
      border: 1px solid #E2E8F0;
      border-radius: 8px;
      padding: 16px 20px;
      text-align: center;
      margin: 0 0 24px;
    }
    .otp-code {
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
      font-size: 32px;
      font-weight: 700;
      letter-spacing: 6px;
      color: #0F172A;
      margin: 0;
      display: inline-block;
    }
    .footnote {
      font-size: 12.5px;
      line-height: 1.5;
      color: #64748B;
      margin: 0 0 24px;
    }
    .divider {
      border-top: 1px solid #F1F5F9;
      margin: 24px 0 20px;
    }
    .footer {
      font-size: 12px;
      color: #94A3B8;
      line-height: 1.5;
      margin: 0;
    }
  </style>
</head>
<body>

<div class="wrapper">
  <div class="card">
    
    <div class="app-title">{{ $appName }}</div>

    <p class="greeting">
      Halo @if(isset($user) && !empty($user->name))<strong>{{ $user->name }}</strong>,@else,@endif
    </p>

    <p class="text">
      Gunakan kode verifikasi berikut untuk masuk ke akun Anda:
    </p>

    <div class="otp-wrapper">
      <div class="otp-code">{{ $code }}</div>
    </div>

    <p class="footnote">
      ⏱ Kode ini berlaku selama <strong>{{ $expiryMinutes }} menit</strong>. Demi keamanan, jangan bagikan kode ini kepada siapapun.
    </p>

    <div class="divider"></div>

    <p class="footer">
      Jika Anda tidak meminta kode ini, abaikan email ini.<br>
      © {{ date('Y') }} {{ $appName }}.
    </p>

  </div>
</div>

</body>
</html>
