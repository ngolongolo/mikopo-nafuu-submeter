@extends('layout')
@section('title','Customer leads')
@section('content')
<style>.meter-check-form{width:min(100%,680px);padding:17px;border:1px solid #c7ddd4;border-radius:12px;background:#f4faf7}.meter-check-form>strong,.meter-check-form>small{display:block}.meter-check-form>small{margin:4px 0 12px;color:var(--muted)}.meter-checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-bottom:10px}.meter-checks label{display:flex;gap:8px;align-items:center;padding:10px;border-radius:8px;background:#fff;border:1px solid var(--line)}.meter-checks input{width:auto;margin:0}.meter-check-form textarea{min-height:70px;margin-bottom:10px}.meter-confirmed{display:flex;gap:9px;align-items:center;color:#247342;font-weight:750}.meter-confirmed:before{content:'✓';display:grid;place-items:center;width:25px;height:25px;border-radius:50%;background:#ddf3e4}@media(max-width:700px){.meter-checks{grid-template-columns:1fr}.meter-check-form{margin-top:14px}}</style>
<div class="section-head">
    <div><div class="eyebrow">CUSTOMER ACQUISITION</div><h1>Customer leads</h1><p class="subtitle">Accept confirmed customer requests, complete KYC, and book applicable upfront collections.</p></div>
    <a class="button quiet" href="/customers/onboard">Onboard walk-in customer</a>
</div>
<div class="grid">
    @foreach($stats as $label=>$value)<article class="card"><small>{{ $label }}</small><div class="stat">{{ number_format($value) }}</div><small>customer requests</small></article>@endforeach
</div>
<form class="panel filters" method="GET">
    <input name="q" placeholder="Reference, customer or phone" value="{{ request('q') }}">
    <select name="status"><option value="">All statuses</option><option value="new" @selected(request('status')==='new')>Available</option><option value="accepted" @selected(request('status')==='accepted')>Accepted</option><option value="onboarded" @selected(request('status')==='onboarded')>Onboarded</option></select>
    <button>Filter</button><a class="button quiet" href="/customer-leads">Clear</a>
</form>
<section class="lead-queue">
    @forelse($leads as $lead)
    <article class="panel lead-queue-card">
        <div class="lead-queue-head">
            <div><span class="badge {{ $lead->status==='new'?'pending':($lead->status==='onboarded'?'paid':'active') }}">{{ $lead->status==='new'?'Available':ucfirst($lead->status) }}</span><h2>{{ trim($lead->first_name.' '.$lead->middle_name.' '.$lead->last_name) ?: 'Token financing applicant' }}</h2><p>{{ $lead->reference }} · Confirmed {{ $lead->confirmed_at?->diffForHumans() }}</p></div>
            <div class="lead-queue-amount"><small>Requested</small><strong>{{ number_format($lead->financing_amount) }} TZS</strong>@if($lead->request_type==='submeter')<span>{{ $lead->meter_quantity }} meter{{ $lead->meter_quantity===1?'':'s' }}</span>@endif</div>
        </div>
        <div class="lead-queue-details">
            <div><small>Product</small><strong>{{ $lead->product->name }}</strong><span>{{ ucfirst($lead->product->category) }}</span></div>
            <div><small>Contact</small><strong>{{ $lead->phone }}</strong><span>{{ $lead->email ?: 'No email supplied' }}</span></div>
            @if($lead->request_type==='token')<div><small>Meter to verify</small><strong>{{ $lead->meter_number }}</strong><span>Check existence and eligibility in your meter database</span></div>@else<div><small>Location</small><strong>{{ $lead->ward }}, {{ $lead->district }}</strong><span>{{ $lead->region }} · {{ $lead->address }}</span></div>@endif
            <div><small>Customer summary</small><strong>{{ number_format($lead->terms['upfront']) }} TZS upfront</strong><span>{{ number_format($lead->terms['repayment_total']) }} TZS repayments</span></div>
        </div>
        <div class="lead-queue-action">
            @if($lead->status==='new')
                @if($lead->request_type==='token')
                <form method="POST" action="/customer-leads/{{ $lead->public_id }}/accept" class="meter-check-form">@csrf<strong>Verify meter {{ $lead->meter_number }}</strong><small>Check the meter against your database before accepting this application.</small><div class="meter-checks"><label><input type="checkbox" name="meter_exists" value="1" required> Meter exists in our database</label><label><input type="checkbox" name="meter_eligible" value="1" required> Meter is eligible for financing</label></div><textarea name="meter_verification_note" placeholder="Optional verification note or account detail"></textarea><button>Confirm meter &amp; accept customer →</button></form>
                @else
                <form method="POST" action="/customer-leads/{{ $lead->public_id }}/accept">@csrf<button>Accept & onboard customer →</button></form>
                @endif
            @elseif($lead->status==='accepted')
            <a class="button" href="/customers/onboard?lead={{ $lead->public_id }}">Continue onboarding →</a>@if($lead->request_type==='token')<span class="meter-confirmed">Meter verified {{ $lead->meter_verified_at?->diffForHumans() }}</span>@else<span class="muted">Accepted {{ $lead->claimed_at?->diffForHumans() }}</span>@endif
            @else
            <a class="button quiet" href="/applications/{{ $lead->application?->public_id }}">View application ↗</a><span class="muted">Onboarded {{ $lead->onboarded_at?->diffForHumans() }}</span>
            @endif
        </div>
    </article>
    @empty
    <div class="panel empty">No confirmed customer leads match this view.</div>
    @endforelse
</section>
@include('partials.pagination',['items'=>$leads])
@endsection
