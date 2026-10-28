@extends('layout')
@section('title','Verify tracking code')
@section('content')
<section class="portal-verify">
    <div class="portal-verify-icon">SMS</div>
    <span class="lead-kicker">SECURE ACCESS</span>
    <h1>Enter your tracking code</h1>
    <p>We sent a six-digit code to <strong>{{ $maskedPhone }}</strong>.</p>
    <form method="POST" action="/track/verify">
        @csrf
        <label>Six-digit code
            <input class="portal-code-input" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus>
        </label>
        <button>Open customer tracker →</button>
    </form>
    <a href="/track">Use a different mobile number</a>
</section>
@endsection
