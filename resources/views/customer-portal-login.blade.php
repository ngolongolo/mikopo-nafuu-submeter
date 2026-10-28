@extends('layout')
@section('title','Customer sign in')
@section('content')
<section class="portal-access">
    <div class="portal-access-copy">
        <span class="lead-kicker">CUSTOMER TRACKER</span>
        <h1>Your application, loan and repayments—in one place.</h1>
        <p>Use the mobile number on your application. We will send a secure six-digit code before showing any financial information.</p>
        <div class="portal-trust-list">
            <div><span>01</span><strong>Application progress</strong><small>See confirmation, supplier and approval status.</small></div>
            <div><span>02</span><strong>Loan position</strong><small>Review your balance and repayment schedule.</small></div>
            <div><span>03</span><strong>Payment history</strong><small>Track receipts and payment confirmation.</small></div>
        </div>
    </div>
    <div class="portal-access-card">
        <span class="portal-lock">✓</span>
        <h2>Customer sign in</h2>
        <p>Enter the Tanzanian mobile number used on your application.</p>
        <form method="POST" action="/track/code">
            @csrf
            <label>Mobile number
                <input name="phone" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel" placeholder="07XXXXXXXX" required autofocus>
            </label>
            <button>Send tracking code →</button>
        </form>
        <small>For your security, the code expires after 10 minutes.</small>
    </div>
</section>
@endsection
