<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verifikasi Kode OTP — Console</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#12181F;
    --panel-dark:#131C24;
    --panel-dark-2:#1B2732;
    --accent:#E8A33D;
    --accent-soft:#F2C57C;
    --ok:#4ADE80;
    --danger:#EF4444;
    --paper:#FAF9F6;
    --card:#FFFFFF;
    --line:#E7E3DA;
    --muted:#8D97A5;
    --muted-2:#6B7684;
    --text:#171B20;
    --radius:14px;
    --shadow: 0 20px 60px -20px rgba(18,24,31,0.25);
  }
  *{box-sizing:border-box;}
  html,body{height:100%;}
  body{
    margin:0;
    font-family:'Inter',sans-serif;
    background:var(--paper);
    color:var(--text);
    -webkit-font-smoothing:antialiased;
  }
  a{color:inherit; text-decoration:none;}

  .wrap{
    min-height:100vh;
    display:grid;
    grid-template-columns: 44% 56%;
  }

  .brand{
    position:relative;
    background:
      radial-gradient(120% 140% at 15% 0%, #1E2A36 0%, var(--panel-dark) 45%, #0E141A 100%);
    color:#EDEFF2;
    padding: 56px 56px 40px;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    overflow:hidden;
  }
  .brand::before{
    content:"";
    position:absolute;
    inset:0;
    background-image:
      linear-gradient(rgba(255,255,255,0.035) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.035) 1px, transparent 1px);
    background-size: 34px 34px;
    mask-image: radial-gradient(circle at 20% 10%, black, transparent 70%);
    pointer-events:none;
  }

  .brand-top{position:relative; z-index:1;}

  .logo{
    display:flex;
    align-items:center;
    gap:10px;
    font-family:'Space Grotesk',sans-serif;
    font-weight:700;
    font-size:18px;
    letter-spacing:0.2px;
  }
  .logo-mark{
    width:30px;height:30px;
    border-radius:8px;
    background:linear-gradient(135deg, var(--accent-soft), var(--accent));
    display:flex;align-items:center;justify-content:center;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.25);
  }
  .logo-mark svg{width:16px;height:16px;}

  .status-badge{
    margin-top:44px;
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 12px 7px 10px;
    background:rgba(255,255,255,0.05);
    border:1px solid rgba(255,255,255,0.09);
    border-radius:999px;
    font-family:'IBM Plex Mono',monospace;
    font-size:12px;
    color:#C7D0DA;
    width:fit-content;
  }
  .dot{
    width:7px;height:7px;border-radius:50%;
    background:var(--ok);
    box-shadow:0 0 0 0 rgba(74,222,128,0.6);
    animation:pulse 2.2s infinite;
    flex-shrink:0;
  }
  @keyframes pulse{
    0%{box-shadow:0 0 0 0 rgba(74,222,128,0.55);}
    70%{box-shadow:0 0 0 8px rgba(74,222,128,0);}
    100%{box-shadow:0 0 0 0 rgba(74,222,128,0);}
  }

  .brand h1{
    font-family:'Space Grotesk',sans-serif;
    font-weight:600;
    font-size:clamp(28px,3vw,38px);
    line-height:1.18;
    margin: 26px 0 14px;
    max-width: 380px;
    letter-spacing:-0.3px;
  }
  .brand p{
    font-size:15px;
    line-height:1.6;
    color:#A6B0BC;
    max-width:340px;
    margin:0;
  }

  .pulse-wrap{
    position:relative;
    z-index:1;
    margin-top:36px;
  }
  .pulse-line{width:100%; height:64px; display:block;}
  .pulse-caption{
    display:flex;
    justify-content:space-between;
    font-family:'IBM Plex Mono',monospace;
    font-size:11px;
    color:#5C6773;
    margin-top:6px;
  }

  .brand-foot{
    position:relative; z-index:1;
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    font-family:'IBM Plex Mono',monospace;
    font-size:11.5px;
    color:#5C6773;
    padding-top:28px;
    border-top:1px solid rgba(255,255,255,0.08);
    margin-top:28px;
  }
  .brand-foot b{color:#98A3AF; font-weight:500;}

  /* ===== Right form panel ===== */
  .stage{
    display:flex;
    align-items:center;
    justify-content:center;
    padding: 40px 28px;
  }
  .card{
    width:100%;
    max-width:420px;
  }
  .card-head{margin-bottom:24px;}
  .card-head h2{
    font-family:'Space Grotesk',sans-serif;
    font-size:25px;
    font-weight:600;
    margin:0 0 8px;
    letter-spacing:-0.2px;
  }
  .card-head p{
    margin:0;
    font-size:14.5px;
    color:var(--muted-2);
    line-height: 1.5;
  }
  .card-head p b{
    color: var(--text);
  }

  .alert{
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 13.5px;
    margin-bottom: 18px;
    line-height: 1.45;
  }
  .alert-success{
    background: #ECFDF5;
    border: 1px solid #A7F3D0;
    color: #065F46;
  }
  .alert-danger{
    background: #FEF2F2;
    border: 1px solid #FECACA;
    color: #991B1B;
  }

  form{display:flex; flex-direction:column; gap:16px;}

  .field label{
    display:block;
    font-size:13px;
    font-weight:600;
    margin-bottom:7px;
    color:var(--text);
  }
  .field .input-shell{
    position:relative;
    display:flex;
    align-items:center;
  }
  .field input.code-input{
    width:100%;
    padding:14px 14px;
    font-size:22px;
    font-family:'IBM Plex Mono',monospace;
    font-weight: 700;
    letter-spacing: 12px;
    text-align: center;
    color:var(--text);
    background:var(--card);
    border:1.5px solid var(--line);
    border-radius:10px;
    outline:none;
    transition: border-color .15s ease, box-shadow .15s ease;
  }
  .field input.code-input:focus{
    border-color:var(--accent);
    box-shadow:0 0 0 4px rgba(232,163,61,0.16);
  }
  .field .is-invalid{
    border-color: var(--danger) !important;
  }
  .field-error{
    color: var(--danger);
    font-size: 12.5px;
    margin-top: 5px;
    text-align: center;
  }

  .row-between{
    display:flex;
    align-items:center;
    justify-content:space-between;
    font-size:13.5px;
    margin-top:2px;
  }
  .remember{
    display:flex;
    align-items:center;
    gap:8px;
    color:var(--muted-2);
    user-select:none;
    cursor:pointer;
  }
  .remember input{
    width:16px;height:16px;
    accent-color:var(--ink);
    cursor:pointer;
  }

  .btn-primary{
    margin-top:6px;
    width:100%;
    padding:12.5px 14px;
    background:var(--ink);
    color:#fff;
    border:none;
    border-radius:10px;
    font-size:14.5px;
    font-weight:600;
    font-family:'Inter',sans-serif;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    transition: transform .12s ease, background .15s ease, box-shadow .15s ease;
  }
  .btn-primary:hover{background:#232D38;}
  .btn-primary:active{transform:translateY(1px);}
  .btn-primary:focus-visible{
    outline:none;
    box-shadow:0 0 0 4px rgba(18,24,31,0.22);
  }
  .btn-primary svg{width:15px;height:15px; transition: transform .15s ease;}
  .spinner{
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
  }
  @keyframes spin {
    to { transform: rotate(360deg); }
  }

  .btn-primary:disabled{
    opacity: 0.7;
    cursor: not-allowed;
  }

  .resend-box{
    margin-top: 20px;
    text-align: center;
    font-size: 13.5px;
    color: var(--muted-2);
  }
  .resend-box button{
    background: none;
    border: none;
    color: var(--ink);
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    font-size: inherit;
    border-bottom: 1.5px solid var(--accent);
    padding: 0 0 1px 0;
  }

  .back-row{
    margin-top: 14px;
    text-align: center;
    font-size: 13.5px;
    color: var(--muted-2);
  }
  .back-row a{
    color: var(--muted-2);
    border-bottom: 1px solid transparent;
  }
  .back-row a:hover{
    color: var(--text);
    border-bottom-color: var(--text);
  }

  @media (max-width: 980px){
    .wrap{grid-template-columns:1fr;}
    .brand{padding:34px 28px 26px; min-height:auto;}
    .brand h1{font-size:24px; max-width:100%;}
    .brand p{max-width:100%;}
    .pulse-wrap{margin-top:24px;}
    .brand-foot{margin-top:22px; padding-top:18px;}
    .stage{padding:34px 22px 48px;}
  }
  @media (max-width: 480px){
    .brand{padding:28px 20px 22px;}
    .brand-foot{flex-direction:column; align-items:flex-start; gap:6px;}
    .card-head h2{font-size:22px;}
  }
</style>
</head>
<body>

<div class="wrap">

  <aside class="brand">
    <div class="brand-top">
      <div class="logo">
        <span class="logo-mark">
          <svg viewBox="0 0 24 24" fill="none"><path d="M12 2L4 6v6c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6l-8-4z" fill="#131C24"/></svg>
        </span>
        Sentra
      </div>

      <span class="status-badge"><span class="dot"></span> Verifikasi Kode Sekali Pakai</span>

      <h1>Satu langkah lagi untuk masuk ke dasbor Anda.</h1>
      <p>Masukkan kode verifikasi 6 digit yang telah kami kirimkan ke email atau nomor akun Anda.</p>
    </div>

    <div>
      <div class="pulse-wrap">
        <svg class="pulse-line" viewBox="0 0 400 64" preserveAspectRatio="none">
          <path d="M0,32 L60,32 L75,10 L90,54 L105,32 L400,32" fill="none" stroke="#2A3540" stroke-width="1.5"/>
          <path class="pulse-path" d="M0,32 L60,32 L75,10 L90,54 L105,32 L400,32" fill="none" stroke="#E8A33D" stroke-width="1.5" stroke-dasharray="60 340" stroke-dashoffset="0">
            <animate attributeName="stroke-dashoffset" from="400" to="0" dur="3.2s" repeatCount="indefinite"/>
          </path>
        </svg>
        <div class="pulse-caption"><span>VERIFIKASI TIMING-SAFE</span><span>MAX {{ config('authentication.features.otp.max_attempts', 3) }} ATTEMPTS</span></div>
      </div>

      <div class="brand-foot">
        <span>© {{ date('Y') }} Sentra Console</span>
        <span><b>Keamanan</b> Terverifikasi</span>
      </div>
    </div>
  </aside>

  <main class="stage">
    <div class="card">
      <div class="card-head">
        <h2>Masukkan Kode OTP</h2>
        <p>Kode telah dikirimkan ke <b>{{ $identifier }}</b></p>
      </div>

      @if (session('status'))
        <div class="alert alert-success">
          {{ session('status') }}
        </div>
      @endif

      @if ($errors->any())
        <div class="alert alert-danger">
          {{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ route('otp.verify') }}">
        @csrf
        <input type="hidden" name="identifier" value="{{ $identifier }}">

        <div class="field">
          <label for="code">Kode Verifikasi ({{ config('authentication.features.otp.length', 6) }} digit)</label>
          <div class="input-shell">
            <input type="text" id="code" name="code" maxlength="{{ config('authentication.features.otp.length', 6) }}" placeholder="••••••" autocomplete="one-time-code" required autofocus class="code-input @error('code') is-invalid @enderror">
          </div>
          @error('code')
            <div class="field-error">{{ $message }}</div>
          @enderror
        </div>

        <div class="row-between">
          <label class="remember">
            <input type="checkbox" name="remember" value="1" checked>
            Ingat saya di perangkat ini
          </label>
        </div>

        <button type="submit" class="btn-primary" id="btnSubmit">
          <span id="btnText">Verifikasi & Masuk</span>
          <svg id="btnIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </button>
      </form>

      <div class="resend-box">
        <form method="POST" action="{{ route('otp.send') }}" style="display:inline;">
          @csrf
          <input type="hidden" name="identifier" value="{{ $identifier }}">
          Tidak menerima kode? <button type="submit">Kirim ulang kode</button>
        </form>
      </div>

      <div class="back-row">
        <a href="{{ route('login') }}">← Kembali ke Masuk Biasa</a>
      </div>
    </div>
  </main>

</div>

<script>
  const form = document.querySelector('form');
  const btnSubmit = document.getElementById('btnSubmit');
  const btnText = document.getElementById('btnText');

  if (form && btnSubmit) {
    form.addEventListener('submit', () => {
      btnSubmit.disabled = true;
      btnText.innerHTML = '<span class="spinner"></span> Memverifikasi...';
    });
  }
</script>

</body>
</html>
