<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login Kasir - Toko PKL</title>
    <meta name="description" content="Login ke terminal kasir Toko PKL">
    <link rel="preconnect" href="https://api.fontshare.com">
    <link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700,900&display=swap" rel="stylesheet">
    <style>
        /* CSS Reset & Satoshi Font Setup */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Satoshi', ui-sans-serif, system-ui, -apple-system, sans-serif;
            background-color: #fafafa;
            color: #18181b;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-wrapper {
            width: 100%;
            max-width: 440px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #71717a;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 24px;
            transition: color 0.15s ease;
        }
        .back-btn:hover {
            color: #09090b;
        }
        .back-btn svg {
            width: 16px;
            height: 16px;
        }


        .login-card {
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f1f4;
            padding: 48px 40px;
        }

        .logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 24px;
        }

        .logo-box {
            width: 56px;
            height: 56px;
            background-color: #09090b;
            color: #ffffff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 900;
            user-select: none;
        }

        .heading-container {
            text-align: center;
            margin-bottom: 32px;
        }

        .heading-title {
            font-size: 28px;
            font-weight: 700;
            color: #09090b;
            letter-spacing: -0.03em;
            margin-bottom: 8px;
        }

        .heading-subtitle {
            font-size: 13px;
            color: #71717a;
            font-weight: 500;
        }

        .error-alert {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 24px;
            font-size: 13.5px;
            font-weight: 600;
            color: #dc2626;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #27272a;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            color: #a1a1aa;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .input-icon svg {
            width: 18px;
            height: 18px;
        }

        .form-input {
            width: 100%;
            height: 50px;
            background-color: #ffffff;
            border: 1px solid #e4e4e7;
            border-radius: 10px;
            padding: 0 16px 0 46px;
            font-size: 14px;
            font-weight: 500;
            color: #09090b;
            transition: all 0.15s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #09090b;
        }

        .form-input::placeholder {
            color: #a1a1aa;
            font-weight: 400;
        }

        /* Password input has space on the right for toggle button */
        .form-input-pw {
            padding-right: 48px;
        }

        .toggle-pw-btn {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            color: #71717a;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.15s ease;
        }

        .toggle-pw-btn:hover {
            color: #09090b;
        }

        .toggle-pw-btn svg {
            width: 20px;
            height: 20px;
        }

        .options-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }

        .remember-label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            user-select: none;
        }

        .remember-checkbox {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 1px solid #d4d4d8;
            cursor: pointer;
            accent-color: #09090b;
        }

        .remember-text {
            font-size: 13.5px;
            color: #52525b;
            font-weight: 500;
        }

        .forgot-link {
            font-size: 13.5px;
            font-weight: 700;
            color: #09090b;
            text-decoration: none;
            transition: color 0.15s ease;
        }

        .forgot-link:hover {
            color: #52525b;
        }

        .btn-login {
            width: 100%;
            height: 50px;
            background-color: #09090b;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.15s ease;
        }

        .btn-login:hover {
            background-color: #27272a;
        }

        .btn-login:active {
            transform: scale(0.985);
        }

        .btn-login svg {
            width: 16px;
            height: 16px;
        }

        /* Animations */
        @media (prefers-reduced-motion: no-preference) {
            .anim-fade-up {
                animation: fade-up 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
                animation-delay: calc(var(--i, 0) * 60ms);
            }
            .anim-shake {
                animation: shake 0.45s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
            }
        }
        @keyframes fade-up {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: none; }
        }
        @keyframes shake {
            10%, 90% { transform: translateX(-1px); }
            20%, 80% { transform: translateX(2px); }
            30%, 50%, 70% { transform: translateX(-3px); }
            40%, 60% { transform: translateX(3px); }
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        {{-- BACK BUTTON --}}
        <a href="/" class="back-btn anim-fade-up" style="--i: -1">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Kembali ke Beranda
        </a>

        {{-- CARD --}}
        <div class="login-card anim-fade-up" style="--i: 0">

            {{-- LOGO --}}
            <div class="logo-container anim-fade-up" style="--i: 1">
                <div class="logo-box">K</div>
            </div>

            {{-- HEADING --}}
            <div class="heading-container anim-fade-up" style="--i: 2">
                <h1 class="heading-title">Welcome Back</h1>
                <p class="heading-subtitle">Secure access to your retail terminal</p>
            </div>

            {{-- ERROR MESSAGE --}}
            @if ($errors->any())
                <div class="error-alert anim-shake">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- FORM --}}
            <form method="POST" action="{{ route('kasir.login.submit') }}">
                @csrf

                {{-- USERNAME / EMAIL --}}
                <div class="form-group anim-fade-up" style="--i: 3">
                    <label for="login-email" class="form-label">Username or Email</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                        <input id="login-email" name="email" type="text" value="{{ old('email') }}"
                            placeholder="Retailer ID"
                            autocomplete="username"
                            required autofocus
                            class="form-input">
                    </div>
                </div>

                {{-- PASSWORD --}}
                <div class="form-group anim-fade-up" style="--i: 4">
                    <label for="login-password" class="form-label">Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input id="login-password" name="password" type="password"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                            class="form-input form-input-pw">
                        {{-- toggle visibility --}}
                        <button type="button" id="btn-toggle-pw" class="toggle-pw-btn" tabindex="-1" aria-label="Toggle password visibility">
                            {{-- eye icon (visible when password hidden) --}}
                            <svg id="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            {{-- eye-off icon (visible when password shown) --}}
                            <svg id="icon-eye-off" class="hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- REMEMBER ME & FORGOT --}}
                <div class="options-row anim-fade-up" style="--i: 5">
                    <label class="remember-label">
                        <input type="checkbox" name="remember" value="1" class="remember-checkbox">
                        <span class="remember-text">Remember Me</span>
                    </label>
                    <a href="#" class="forgot-link" id="forgot-link">Forgot?</a>
                </div>

                {{-- SUBMIT --}}
                <div class="anim-fade-up" style="--i: 6">
                    <button type="submit" class="btn-login">
                        Log In
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL LUPAS PASSWORD --}}
    <div id="forgot-modal" style="display:none; position:fixed; inset:0; z-index:999; background:rgba(9,9,11,.45); align-items:center; justify-content:center; padding:24px;">
        <div style="background:#fff; border-radius:16px; max-width:360px; width:100%; padding:32px 28px; text-align:center; box-shadow:0 20px 50px rgba(0,0,0,.2);">
            <div style="width:56px; height:56px; margin:0 auto 16px; background:#f4f4f5; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#09090b;">
                <svg style="width:26px; height:26px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>
            <h2 style="font-size:15px; font-weight:700; letter-spacing:-0.01em; margin-bottom:8px; color:#09090b;">Lupa Password?</h2>
            <p style="font-size:12.5px; color:#52525b; line-height:1.6; margin-bottom:20px;">
                Untuk mereset password, silakan hubungi <strong>admin toko</strong>. Anda bisa mengatur ulang password melalui halaman admin pada menu Karyawan.
            </p>
            <button type="button" id="forgot-close"
                style="width:100%; height:44px; background:#09090b; color:#fff; font-weight:700; font-size:13.5px; border:none; border-radius:10px; cursor:pointer;">Mengerti</button>
        </div>
    </div>

    {{-- TOGGLE PASSWORD & FORGOT SCRIPT --}}
    <script>
        document.getElementById('btn-toggle-pw').addEventListener('click', function () {
            const input = document.getElementById('login-password');
            const iconEye = document.getElementById('icon-eye');
            const iconEyeOff = document.getElementById('icon-eye-off');
            const isPassword = input.type === 'password';
            
            input.type = isPassword ? 'text' : 'password';
            
            if (isPassword) {
                iconEye.style.display = 'none';
                iconEyeOff.style.display = 'block';
            } else {
                iconEye.style.display = 'block';
                iconEyeOff.style.display = 'none';
            }
        });

        // Modal "Lupa Password" — hubungi admin
        const forgotModal = document.getElementById('forgot-modal');
        const forgotLink = document.getElementById('forgot-link');
        const forgotClose = document.getElementById('forgot-close');

        function bukaForgot(e) {
            e.preventDefault();
            forgotModal.style.display = 'flex';
        }
        function tutupForgot() {
            forgotModal.style.display = 'none';
        }

        if (forgotLink) forgotLink.addEventListener('click', bukaForgot);
        if (forgotClose) forgotClose.addEventListener('click', tutupForgot);
        if (forgotModal) {
            forgotModal.addEventListener('click', function (e) {
                if (e.target === forgotModal) tutupForgot();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') tutupForgot();
            });
        }
    </script>
</body>
</html>

