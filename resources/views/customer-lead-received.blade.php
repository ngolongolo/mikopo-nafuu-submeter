@extends('layout')
@section('title','Request '.$lead->reference)
@section('content')
@php
$status = match($lead->status) {
    'unconfirmed' => ['label'=>'Confirmation required','title'=>'Your application is saved, but not confirmed.','message'=>'Enter the six-digit code sent by SMS. Suppliers cannot see or accept the application until you confirm your mobile number.'],
    'accepted' => ['label'=>'Supplier assigned','title'=>'A supplier has accepted your request.','message'=>($lead->supplier?->company_name ?? 'Your assigned supplier').' will contact you to complete verification and onboarding.'],
    'onboarded' => ['label'=>'Application submitted','title'=>'Your onboarding is complete.','message'=>'Your financing application has been created and is progressing through review.'],
    default => ['label'=>'Finding a supplier','title'=>'Your request is safely in the queue.','message'=>'An eligible supplier will accept your request and contact you using the details you provided.'],
};
$confirmed = $lead->status !== 'unconfirmed';
$accepted = in_array($lead->status,['accepted','onboarded'],true);
$onboarded = $lead->status==='onboarded';
@endphp
<section class="request-confirmation">
 <div class="request-confirmation-hero">
  <div class="request-hero-copy"><div class="request-success-icon {{ $confirmed?'':'sms' }}">{{ $confirmed?'✓':'SMS' }}</div><span class="lead-kicker">{{ $confirmed?'REQUEST RECEIVED':'APPLICATION SAVED' }}</span><h1>{{ $lead->first_name ? 'Thank you, '.$lead->first_name.'.' : 'Thank you.' }}<br>{{ $status['title'] }}</h1><p>{{ $status['message'] }}</p><div class="request-status-pill"><span></span>{{ $status['label'] }}</div></div>
  <div class="request-reference-card"><small>Your request reference</small><strong id="request-reference">{{ $lead->reference }}</strong><button type="button" class="request-copy" id="copy-reference">Copy reference</button><p>Keep this reference for any conversation with your supplier.</p></div>
 </div>

 @if($lead->status==='unconfirmed')
 <section class="otp-confirmation-card">
  <div><span class="lead-kicker">MOBILE CONFIRMATION</span><h2>Enter the SMS code</h2><p>We sent a code to {{ substr($lead->phone_normalized,0,6) }}•••{{ substr($lead->phone_normalized,-3) }}. It expires 10 minutes after it is sent.</p>@if(session('otp_error'))<div class="error">{{ session('otp_error') }}</div>@endif</div>
  <div><form method="POST" action="/requests/{{ $lead->public_id }}/confirm" class="otp-inline-form">@csrf<label>Six-digit code<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus></label><button>Confirm application</button></form><form method="POST" action="/requests/{{ $lead->public_id }}/resend" class="otp-resend-form">@csrf<button class="quiet">Send a new code</button></form></div>
 </section>
 @endif

 <div class="request-layout">
  <div class="request-main">
   <section class="request-card"><div class="request-card-heading"><div><span class="lead-kicker">REQUEST SNAPSHOT</span><h2>{{ $lead->product->name }}</h2></div><span class="request-category">{{ ucfirst($lead->product->category) }}</span></div><div class="request-snapshot"><div><small>Requested value</small><strong>{{ number_format($lead->financing_amount) }} TZS</strong></div>@if($lead->request_type==='submeter')<div><small>Meters requested</small><strong>{{ $lead->meter_quantity }}</strong></div>@else<div><small>Meter number</small><strong>{{ $lead->meter_number }}</strong><span>Pending supplier database check</span></div>@endif<div><small>Upfront at onboarding</small><strong>{{ number_format($lead->terms['upfront']) }} TZS</strong></div><div><small>Service</small><strong>{{ ucfirst($lead->utility_type) }}</strong></div>@if($lead->request_type==='submeter')<div><small>Location</small><strong>{{ $lead->ward }}, {{ $lead->district }}</strong><span>{{ $lead->region }}</span></div>@else<div><small>Verification</small><strong>{{ $lead->meter_verified_at ? 'Meter confirmed' : 'Supplier check pending' }}</strong><span>{{ $lead->meter_verified_at?->format('d M Y, H:i') }}</span></div>@endif<div><small>Submitted</small><strong>{{ $lead->created_at->format('d M Y') }}</strong><span>{{ $lead->created_at->format('H:i') }}</span></div></div></section>

   <section class="request-card"><div class="request-card-heading"><div><span class="lead-kicker">PROGRESS</span><h2>What happens next</h2></div></div><div class="request-timeline"><div class="done"><span>✓</span><div><strong>Application saved</strong><small>Your request and accepted terms were securely recorded.</small></div></div><div class="{{ $confirmed?'done':'current' }}"><span>{{ $confirmed?'✓':'2' }}</span><div><strong>Confirm mobile number</strong><small>Enter the SMS code so suppliers can see the application.</small></div></div><div class="{{ $accepted?'done':($confirmed?'current':'') }}"><span>{{ $accepted?'✓':'3' }}</span><div><strong>{{ $lead->request_type==='token' ? 'Supplier verifies meter' : 'Supplier accepts request' }}</strong><small>{{ $lead->request_type==='token' ? 'A supplier checks that the meter exists and is eligible for financing.' : 'An eligible supplier claims the request and contacts you.' }}</small></div></div><div class="{{ $onboarded?'done':($accepted?'current':'') }}"><span>{{ $onboarded?'✓':'4' }}</span><div><strong>Complete onboarding</strong><small>Confirm KYC, meter details and any applicable upfront collection.</small></div></div><div class="{{ $onboarded?'current':'' }}"><span>5</span><div><strong>Financing review</strong><small>Your completed application proceeds for approval.</small></div></div></div></section>
  </div>

  <aside class="request-side">
   <section class="request-card request-prepare"><span class="lead-kicker">BE READY</span><h2>Prepare for onboarding</h2><ul><li><span>01</span><div><strong>Valid identification</strong><small>National ID, passport, driving licence or voter ID.</small></div></li><li><span>02</span><div><strong>Installation details</strong><small>Your meter number, address and device details where applicable.</small></div></li>@if((int)$lead->terms['upfront']>0)<li><span>03</span><div><strong>{{ number_format($lead->terms['upfront']) }} TZS upfront</strong><small>Pay only during verified supplier onboarding and keep the receipt.</small></div></li>@endif</ul></section>
   <section class="request-help"><strong>Want to follow every update?</strong><p>Sign in securely to see application, loan, and repayment progress.</p><a class="button" href="/track">Customer sign in</a><a class="request-signin" href="/">View financing options →</a></section>
  </aside>
 </div>
</section>
<script>document.addEventListener('DOMContentLoaded',()=>{const button=document.getElementById('copy-reference'),reference=document.getElementById('request-reference');button.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(reference.textContent.trim());button.textContent='Copied ✓';setTimeout(()=>button.textContent='Copy reference',1800)}catch(error){button.textContent='Reference selected';const range=document.createRange();range.selectNodeContents(reference);window.getSelection().removeAllRanges();window.getSelection().addRange(range)}})});</script>
@endsection
