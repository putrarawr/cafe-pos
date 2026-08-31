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
            <div class="logo-box">G</div>
        </div>

        {{-- HEADING --}}
        <div class="heading-container anim-fade-up" style="--i: 2">
            <h1 class="heading-title">Welcome Back</h1>
            <p class="heading-subtitle">Secure access to your warehouse system</p>
        </div>

        {{-- ERROR MESSAGE --}}
        @if ($errors->any())
            <div class="error-alert anim-shake">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- FORM --}}
        <form wire:submit="authenticate">
            
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
                    <input id="login-email" wire:model="data.email" type="text"
                        placeholder="Admin ID"
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
                    <input id="login-password" wire:model="data.password" type="password"
                        placeholder="••••••••"
                        autocomplete="current-password"
                        required
                        class="form-input form-input-pw">
                    {{-- toggle visibility --}}
                    <button type="button" id="btn-toggle-pw" class="toggle-pw-btn" tabindex="-1" aria-label="Toggle password visibility">
                        <svg id="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
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
                    <input type="checkbox" wire:model="data.remember" value="1" class="remember-checkbox">
                    <span class="remember-text">Remember Me</span>
                </label>
                <a href="#" class="forgot-link" onclick="alert('Fitur belum tersedia'); return false;">Forgot?</a>
            </div>

            {{-- SUBMIT --}}
            <div class="anim-fade-up" style="--i: 6">
                <button type="submit" class="btn-login" wire:loading.attr="disabled" wire:loading.class="opacity-70">
                    <span wire:loading.remove>Log In</span>
                    <span wire:loading>Logging in...</span>
                    <svg wire:loading.remove viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </div>
        </form>
    </div>
</div>
