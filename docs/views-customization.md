# Views & UI Customization

The package comes pre-packaged with a responsive, developer console theme matching modern dark UI aesthetics.

---

## 1. Publishing Views

To customize the Blade views in your application:

```bash
php artisan vendor:publish --tag=authentication-views
```

This will publish all views into `resources/views/vendor/authentication/`:

```
resources/views/vendor/authentication/
├── login.blade.php
├── register.blade.php
├── forgot-password.blade.php
├── reset-password.blade.php
├── otp-request.blade.php
└── otp-verify.blade.php
```

---

## 2. Included Interactive UX Features

### Instant Client-Side Validation
- Real-time password criteria checklist (8+ characters, uppercase & lowercase, number, special symbol) updates dynamically as the user types.
- Live confirmation match validator for password confirmations.
- Visual validation feedback (`is-invalid` borders and field errors).

### Instant Form Submit Spinner
- Submit buttons automatically transition to a spinner state upon form submission (`btnSubmit.disabled = true; btnText.innerHTML = 'Memproses...'`) to give immediate visual feedback.

### Animated Alerts
- Session status and validation error summaries are displayed inside animated alert containers (`.alert-container` with `@keyframes fadeInDown`).

---

## 3. Design Tokens (CSS Variables)

All templates use standard CSS custom properties defined in `:root`:

```css
:root {
  --ink: #12181F;
  --panel-dark: #131C24;
  --panel-dark-2: #1B2732;
  --accent: #E8A33D;
  --accent-soft: #F2C57C;
  --ok: #10B981;
  --danger: #EF4444;
  --paper: #FAF9F6;
  --card: #FFFFFF;
  --line: #E7E3DA;
  --muted: #8D97A5;
  --muted-2: #6B7684;
  --text: #171B20;
  --radius: 14px;
}
```
You can easily adjust these tokens to align with your brand guidelines.
