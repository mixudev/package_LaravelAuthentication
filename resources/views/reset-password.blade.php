<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Atur Ulang Kata Sandi — Console</title>
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

  .alert{
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 13.5px;
    margin-bottom: 18px;
    line-height: 1.45;
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

      <span class="status-badge"><span class="dot"></span> Pembaharuan Kredensial</span>

      <h1>Buat kata sandi baru yang kuat.</h1>
      <p>Pastikan kata sandi baru Anda unik, kuat, dan belum pernah digunakan sebelumnya untuk melindungi akun Anda.</p>
    </div>

    <div>
      <div class="pulse-wrap">
        <svg class="pulse-line" viewBox="0 0 400 64" preserveAspectRatio="none">
          <path d="M0,32 L60,32 L75,10 L90,54 L105,32 L400,32" fill="none" stroke="#2A3540" stroke-width="1.5"/>
          <path class="pulse-path" d="M0,32 L60,32 L75,10 L90,54 L105,32 L400,32" fill="none" stroke="#E8A33D" stroke-width="1.5" stroke-dasharray="60 340" stroke-dashoffset="0">
            <animate attributeName="stroke-dashoffset" from="400" to="0" dur="3.2s" repeatCount="indefinite"/>
          </path>
        </svg>
        <div class="pulse-caption"><span>PROTEKSI REHASH OTOMATIS</span><span>KEAMANAN ZERO-TRUST</span></div>
      </div>

      <div class="brand-foot">
        <span>© {{ date('Y') }} Sentra Console</span>
        <span><b>Sistem Keamanan</b> Aktif</span>
      </div>
    </div>
  </aside>

  <main class="stage">
    <div class="card">
      <div class="card-head">
        <h2>Atur ulang kata sandi</h2>
        <p>Silakan masukkan kata sandi baru untuk akun Anda.</p>
      </div>

      @if ($errors->any())
        <div class="alert alert-danger">
          {{ $errors->first() }}
        </div>
      @endif

      <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
          <label for="email">Alamat Email</label>
          <div class="input-shell">
            <input type="email" id="email" name="email" value="{{ $email ?? old('email') }}" placeholder="nama@email.com" autocomplete="email" required autofocus class="@error('email') is-invalid @enderror">
          </div>
          @error('email')
            <div class="field-error">{{ $message }}</div>
          @enderror
        </div>

        <div class="field">
          <label for="password">Kata Sandi Baru</label>
          <div class="input-shell">
            <input type="password" id="password" name="password" placeholder="Minimal 8 karakter" autocomplete="new-password" required class="@error('password') is-invalid @enderror">
            <button type="button" class="toggle-pass" id="togglePass" aria-label="Tampilkan kata sandi">
              <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          @error('password')
            <div class="field-error">{{ $message }}</div>
          @enderror
        </div>

        <div class="field">
          <label for="password_confirmation">Konfirmasi Kata Sandi Baru</label>
          <div class="input-shell">
            <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Ulangi kata sandi baru" autocomplete="new-password" required class="@error('password_confirmation') is-invalid @enderror">
          </div>
          @error('password_confirmation')
            <div class="field-error">{{ $message }}</div>
          @enderror
        </div>

        <button type="submit" class="btn-primary">
          Perbarui Kata Sandi
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </button>
      </form>
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
</script>

</body>
</html>
