<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Gudang - Toko PKL</title>
    <meta name="description" content="Login ke sistem gudang Toko PKL">
    <link rel="preconnect" href="https://api.fontshare.com">
    <link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700,900&display=swap" rel="stylesheet">
    @livewireStyles
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
    {{ $slot }}

    @livewireScripts
    <script>
        document.addEventListener('click', function (e) {
            const toggleBtn = e.target.closest('#btn-toggle-pw');
            if (toggleBtn) {
                const input = document.getElementById('login-password');
                const iconEye = document.getElementById('icon-eye');
                const iconEyeOff = document.getElementById('icon-eye-off');
                if (input) {
                    const isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';
                    if (iconEye && iconEyeOff) {
                        iconEye.style.display = isPassword ? 'none' : 'block';
                        iconEyeOff.style.display = isPassword ? 'block' : 'none';
                    }
                }
            }
        });
    </script>
</body>
</html>
