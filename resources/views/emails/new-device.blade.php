<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pemberitahuan Masuk dari Perangkat Baru</title>
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
      max-width: 480px;
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
      margin: 0 0 20px;
    }
    .warning-badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 9999px;
      background-color: #FEF3C7;
      color: #92400E;
      font-size: 12px;
      font-weight: 600;
      margin-bottom: 16px;
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
      margin: 0 0 20px;
    }
    .device-box {
      background-color: #F8FAFC;
      border: 1px solid #E2E8F0;
      border-radius: 8px;
      padding: 16px;
      margin: 0 0 24px;
    }
    .device-row {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      margin-bottom: 8px;
    }
    .device-row:last-child {
      margin-bottom: 0;
    }
    .device-label {
      color: #64748B;
      font-weight: 500;
    }
    .device-value {
      color: #0F172A;
      font-weight: 600;
    }
    .btn {
      display: inline-block;
      width: 100%;
      box-sizing: border-box;
      background-color: #EF4444;
      color: #FFFFFF;
      font-size: 14px;
      font-weight: 600;
      text-align: center;
      text-decoration: none;
      padding: 12px 20px;
      border-radius: 8px;
      margin-bottom: 20px;
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
    <div class="warning-badge">⚠️ Masuk dari Perangkat Baru</div>

    <p class="greeting">
      Halo @if(isset($user) && !empty($user->name))<strong>{{ $user->name }}</strong>,@else,@endif
    </p>

    <p class="text">
      Kami mendeteksi adanya aktivitas masuk ke akun Anda dari perangkat atau lokasi baru:
    </p>

    <div class="device-box">
      <div class="device-row">
        <span class="device-label">Perangkat & Browser:</span>
        <span class="device-value">{{ $device->device_name }}</span>
      </div>
      <div class="device-row">
        <span class="device-label">Alamat IP:</span>
        <span class="device-value">{{ $device->ip_address }}</span>
      </div>
      @if($device->location)
      <div class="device-row">
        <span class="device-label">Perkiraan Lokasi:</span>
        <span class="device-value">{{ $device->location }}</span>
      </div>
      @endif
      <div class="device-row">
        <span class="device-label">Waktu:</span>
        <span class="device-value">{{ now()->format('d M Y, H:i T') }}</span>
      </div>
    </div>

    <p class="text" style="font-size: 13px;">
      Jika ini memang Anda, Anda dapat mengabaikan email ini. Namun jika Anda merasa tidak melakukan aktivitas ini, segera amankan akun Anda:
    </p>

    <a href="{{ $secureUrl }}" class="btn">Amankan Akun & Cabut Sesi</a>

    <div class="divider"></div>

    <p class="footer">
      Email ini dikirim secara otomatis untuk menjaga keamanan akun Anda.<br>
      © {{ date('Y') }} {{ $appName }}.
    </p>

  </div>
</div>

</body>
</html>
