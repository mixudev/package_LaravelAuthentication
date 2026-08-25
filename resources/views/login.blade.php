<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — Console</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
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

  /* ===== Left brand panel ===== */
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
  }
  .card-head p a{
    color:var(--ink);
    font-weight:600;
    border-bottom:1.5px solid var(--accent);
    padding-bottom:1px;
  }

  .alert-container{
    margin-bottom: 18px;
    animation: fadeInDown 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  }
  @keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .alert{
    display:flex;
    align-items:flex-start;
    gap:10px;
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 13.5px;
    margin-bottom: 18px;
    line-height: 1.45;
  }
  .alert svg{
    width:18px; height:18px; flex-shrink:0; margin-top:1px;
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
  .field input{
    width:100%;
    padding:12px 14px;
    font-size:14.5px;
    font-family:'Inter',sans-serif;
    color:var(--text);
    background:var(--card);
    border:1.5px solid var(--line);
    border-radius:10px;
    outline:none;
    transition: border-color .15s ease, box-shadow .15s ease;
  }
  .field input::placeholder{color:#B7BFC8;}
  .field input:focus{
    border-color:var(--accent);
    box-shadow:0 0 0 4px rgba(232,163,61,0.16);
  }
  .field .is-invalid{
    border-color: var(--danger);
  }
  .field-error{
    color: var(--danger);
    font-size: 12.5px;
    margin-top: 5px;
  }

  .toggle-pass{
    position:absolute;
    right:12px;
    background:none;
    border:none;
    cursor:pointer;
    padding:4px;
    color:var(--muted);
    display:flex;
  }
  .toggle-pass:hover{color:var(--text);}
  .toggle-pass svg{width:18px;height:18px;}

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
  .row-between a{
    color:var(--muted-2);
    font-weight:500;
    border-bottom:1px solid transparent;
  }
  .row-between a:hover{
    color:var(--text);
    border-bottom-color:var(--text);
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
  .btn-primary:hover svg{transform:translateX(2px);}

  .btn-otp{
    margin-top:10px;
    width:100%;
    padding:11px 14px;
    background:transparent;
    color:var(--text);
    border:1.5px solid var(--line);
    border-radius:10px;
    font-size:13.5px;
    font-weight:600;
    font-family:'Inter',sans-serif;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    transition: border-color .15s ease, background .15s ease;
  }
  .btn-otp:hover{border-color:#C9C2B2; background:#FBFAF7;}

  .divider{
    display:flex;
    align-items:center;
    gap:12px;
    margin:20px 0 16px;
    color:var(--muted);
    font-size:12.5px;
  }
  .divider::before, .divider::after{
    content:"";
    flex:1;
    height:1px;
    background:var(--line);
  }

  .oauth-row{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
  }
  .btn-oauth{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:9px;
    padding:11px 10px;
    border:1.5px solid var(--line);
    border-radius:10px;
    background:var(--card);
    font-size:13.5px;
    font-weight:600;
    color:var(--text);
    cursor:pointer;
    transition: border-color .15s ease, background .15s ease;
  }
  .btn-oauth:hover{border-color:#C9C2B2; background:#FBFAF7;}
  .btn-oauth svg{width:16px;height:16px;}

  .legal{
    margin-top:24px;
    font-size:12px;
    line-height:1.6;
    color:var(--muted);
    text-align:center;
  }
  .legal a{text-decoration:underline; color:var(--muted-2);}

  /* ===== Responsive ===== */
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
    .oauth-row{grid-template-columns:1fr;}
    .card-head h2{font-size:22px;}
  }

  @media (prefers-reduced-motion: reduce){
    .dot{animation:none;}
    .pulse-path{animation:none !important;}
  }
</style>
</head>
<body>

<div class="wrap">

  <!-- LEFT: brand / monitoring panel -->
  <aside class="brand">
    <div class="brand-top">
      <div class="logo">
        <span class="logo-mark">
          <svg viewBox="0 0 24 24" fill="none"><path d="M12 2L4 6v6c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6l-8-4z" fill="#131C24"/></svg>
        </span>
        Sentra
      </div>

      <span class="status-badge"><span class="dot"></span> Semua sistem normal</span>

      <h1>Kendali penuh, dari satu tempat.</h1>
      <p>Masuk untuk memantau sistem, mengelola akses tim, dan menjaga semuanya tetap berjalan sebagaimana mestinya.</p>
    </div>

    <div>
      <div class="pulse-wrap">
        <svg class="pulse-line" viewBox="0 0 400 64" preserveAspectRatio="none">
          <path d="M0,32 L60,32 L75,10 L90,54 L105,32 L400,32" fill="none" stroke="#2A3540" stroke-width="1.5"/>
          <path class="pulse-path" d="M0,32 L60,32 L75,10 L90,54 L105,32 L400,32" fill="none" stroke="#E8A33D" stroke-width="1.5" stroke-dasharray="60 340" stroke-dashoffset="0">
            <animate attributeName="stroke-dashoffset" from="400" to="0" dur="3.2s" repeatCount="indefinite"/>
          </path>
        </svg>
        <div class="pulse-caption"><span>UPTIME 99.98%</span><span>LATENSI 42ms</span></div>
      </div>

      <div class="brand-foot">
        <span>© {{ date('Y') }} Sentra Console</span>
        <span><b>Sistem Keamanan</b> Aktif</span>
      </div>
    </div>
  </aside>

  <!-- RIGHT: form -->
  <main class="stage">
    <div class="card">
      <div class="card-head">
        <h2>Masuk ke akun Anda</h2>
        @if (config('authentication.features.registration.enabled', true))
          <p>Belum punya akun? <a href="{{ route('register') }}">Daftar gratis</a></p>
        @else
          <p>Masukkan kredensial Anda untuk melanjutkan.</p>
        @endif
      </div>

      @if (session('status'))
        <div class="alert-container">
          <div class="alert alert-success">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <div>{{ session('status') }}</div>
          </div>
        </div>
      @endif

      @if ($errors->any())
        <div class="alert-container">
          <div class="alert alert-danger">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <div>
              <strong>Gagal Masuk:</strong>
              <ul style="margin: 4px 0 0 0; padding-left: 18px;">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          </div>
        </div>
      @endif

      <form method="POST" action="{{ route('login.perform') }}" id="loginForm">
        @csrf

        <div class="field">
          <label for="identifier">Email / Nama Pengguna</label>
          <div class="input-shell">
            <input type="text" id="identifier" name="identifier" value="{{ old('identifier') }}" placeholder="nama@email.com atau username" autocomplete="username" required autofocus class="@error('identifier') is-invalid @enderror">
          </div>
        </div>

        <div class="field">
          <label for="password">Kata sandi</label>
          <div class="input-shell">
            <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required class="@error('password') is-invalid @enderror">
            <button type="button" class="toggle-pass" id="togglePass" aria-label="Tampilkan kata sandi">
              <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          @error('password')
            <div class="field-error">{{ $message }}</div>
          @enderror
        </div>

        <div class="row-between">
          <label class="remember">
            <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
            Ingat saya
          </label>
          @if (config('authentication.features.forgot_password.enabled', true))
            <a href="{{ route('password.request') }}">Lupa kata sandi?</a>
          @endif
        </div>

        <button type="submit" class="btn-primary" id="btnSubmit">
          Masuk
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </button>

        @if (config('authentication.features.otp.enabled', true))
          <a href="{{ route('otp.request.form') }}" class="btn-otp">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            Masuk dengan Kode OTP
          </a>
        @endif
      </form>

      @php
        $hasGoogle = config('authentication.features.social.enabled', true) && config('authentication.features.social.providers.google.enabled', true);
        $hasGithub = config('authentication.features.social.enabled', true) && config('authentication.features.social.providers.github.enabled', true);
      @endphp

      @if ($hasGoogle || $hasGithub)
        <div class="divider">atau lanjutkan dengan</div>

        <div class="oauth-row">
          @if ($hasGoogle)
            <a href="{{ route('social.redirect', 'google') }}" class="btn-oauth">
              <svg viewBox="0 0 24 24"><path fill="#4285F4" d="M23.5 12.27c0-.82-.07-1.6-.2-2.36H12v4.47h6.47a5.53 5.53 0 0 1-2.4 3.63v3h3.87c2.27-2.09 3.56-5.17 3.56-8.74z"/><path fill="#34A853" d="M12 24c3.24 0 5.96-1.08 7.94-2.92l-3.87-3c-1.08.72-2.46 1.15-4.07 1.15-3.13 0-5.78-2.11-6.73-4.96H1.28v3.1A12 12 0 0 0 12 24z"/><path fill="#FBBC05" d="M5.27 14.27a7.2 7.2 0 0 1 0-4.54v-3.1H1.28a12 12 0 0 0 0 10.74l3.99-3.1z"/><path fill="#EA4335" d="M12 4.77c1.76 0 3.34.6 4.58 1.79l3.44-3.44C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.69 1.28 6.63l3.99 3.1C6.22 6.88 8.87 4.77 12 4.77z"/></svg>
              Google
            </a>
          @endif
          @if ($hasGithub)
            <a href="{{ route('social.redirect', 'github') }}" class="btn-oauth">
              <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 .3a12 12 0 0 0-3.79 23.4c.6.1.82-.26.82-.58v-2.02c-3.34.73-4.04-1.6-4.04-1.6-.55-1.4-1.34-1.77-1.34-1.77-1.09-.75.08-.73.08-.73 1.2.08 1.84 1.24 1.84 1.24 1.07 1.84 2.8 1.3 3.49 1 .1-.78.42-1.3.76-1.6-2.67-.3-5.47-1.33-5.47-5.93 0-1.31.47-2.38 1.24-3.22-.12-.3-.54-1.52.12-3.18 0 0 1-.32 3.3 1.23a11.5 11.5 0 0 1 6 0c2.28-1.55 3.29-1.23 3.29-1.23.66 1.66.24 2.88.12 3.18.77.84 1.23 1.91 1.23 3.22 0 4.61-2.8 5.63-5.48 5.92.43.37.81 1.1.81 2.22v3.29c0 .32.22.69.83.58A12 12 0 0 0 12 .3z"/></svg>
              GitHub
            </a>
          @endif
        </div>
      @endif

      <p class="legal">Dengan masuk, Anda menyetujui Ketentuan Layanan dan Kebijakan Privasi kami.</p>
    </div>
  </main>

</div>

<script>
  const toggleBtn = document.getElementById('togglePass');
  const passInput = document.getElementById('password');
  const eyeIcon = document.getElementById('eyeIcon');

  if (toggleBtn && passInput && eyeIcon) {
    toggleBtn.addEventListener('click', () => {
      const isPassword = passInput.type === 'password';
      passInput.type = isPassword ? 'text' : 'password';
      toggleBtn.setAttribute('aria-label', isPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
      eyeIcon.innerHTML = isPassword
        ? '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.7 21.7 0 0 1 5.06-5.94M9.9 4.24A10.6 10.6 0 0 1 12 4c7 0 11 7 11 7a21.7 21.7 0 0 1-2.68 3.68M14.12 14.12a3 3 0 1 1-4.24-4.24" stroke-linecap="round" stroke-linejoin="round"/><line x1="1" y1="1" x2="23" y2="23"/>'
        : '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/>';
    });
  }
  const loginForm = document.getElementById('loginForm');
  const btnSubmit = document.getElementById('btnSubmit');

  if (loginForm && btnSubmit) {
    loginForm.addEventListener('submit', () => {
      btnSubmit.disabled = true;
      btnSubmit.innerHTML = '<span class="spinner"></span> Memproses...';
    });
  }
</script>

</body>
</html>