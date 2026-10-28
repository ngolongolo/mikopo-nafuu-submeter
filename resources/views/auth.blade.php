@extends('layout')
@section('title','Staff sign in')
@section('content')
<section class="auth-shell login-shell">
    <div class="auth-visual login-visual">
        <img src="/login_image.png" alt="Mikopo Nafuu financing workspace">
        <div class="login-brand"><span class="login-brand-mark">●</span><strong>Mikopo<span>Nafuu</span></strong></div>
        <div class="auth-visual-copy">
            <span>SECURE OPERATIONS</span>
            <h2>Finance with clarity.<br>Operate with confidence.</h2>
            <p>One protected workspace for customer onboarding, loan management, collections, and supplier settlements.</p>
            <div class="login-benefits"><span>✓ Role-based access</span><span>✓ Email verification</span><span>✓ Audited activity</span></div>
        </div>
    </div>
    <div class="auth-form login-form">
        <div class="login-form-mark">↯</div>
        <div class="eyebrow">STAFF &amp; PARTNER ACCESS</div>
        <h1>Welcome back</h1>
        <p class="subtitle">Sign in with your work credentials. We will verify access with a one-time email code.</p>
        <form method="POST" action="/login">
            @csrf
            <label>Email address
                <input name="email" type="email" value="{{ old('email') }}" required autocomplete="email" placeholder="name@company.com" autofocus>
            </label>
            <label>Password
                <span class="password-control">
                    <input id="login-password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password">
                    <button type="button" class="password-toggle" id="password-toggle" aria-label="Show password" aria-controls="login-password" aria-pressed="false">
                        <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Zm10 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg>
                        <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18M10.6 6.2A9.8 9.8 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-3 3.8M6.4 6.5C3.6 8.2 2 12 2 12s3.5 6 10 6c1 0 2-.2 2.8-.4M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                    </button>
                </span>
            </label>
            <div class="login-form-row"><label class="login-remember"><input type="checkbox" name="remember" value="1"><span>Keep me signed in</span></label><a href="/forgot-password">Forgot password?</a></div>
            <button class="auth-submit">Continue securely →</button>
        </form>
        <div class="login-customer-link"><span>Applying for financing?</span><a href="/track">Customer sign in →</a></div>
        <p class="login-security-note"><span>✓</span>Your password is encrypted and never shown to staff.</p>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded',()=>{const input=document.getElementById('login-password'),button=document.getElementById('password-toggle');button?.addEventListener('click',()=>{const visible=input.type==='text';input.type=visible?'password':'text';button.classList.toggle('visible',!visible);button.setAttribute('aria-pressed',String(!visible));button.setAttribute('aria-label',visible?'Show password':'Hide password');input.focus()})});
</script>
@endsection
