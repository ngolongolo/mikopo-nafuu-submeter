@extends('layout')
@section('title','Customer tracker')
@section('body-class','customer-portal-page')
@section('content')
@php
    $loans = $applications->pluck('loan')->filter();
    $activeLoans = $loans->whereIn('status',['active','overdue','pending_disbursement']);
    $outstanding = $activeLoans->sum(fn($loan) => $loan->outstanding());
    $confirmedRepayments = $loans->sum(fn($loan) => $loan->payments->where('purpose','repayment')->where('status','confirmed')->sum('amount'));
    $maskedPhone = substr($phone,0,6).'•••'.substr($phone,-3);
@endphp
<section class="portal-dashboard">
    <div class="portal-dashboard-head">
        <div class="portal-dashboard-copy"><span class="lead-kicker">CUSTOMER TRACKER</span><h1>{{ $customer?->name ?: ($leads->first()?->first_name ?: 'Customer') }}, welcome back</h1><p><span>Verified mobile {{ $maskedPhone }}</span><span>Updated {{ now()->format('d M Y, H:i') }}</span></p></div>
        <form method="POST" action="/track/logout">@csrf<button class="quiet portal-signout">Sign out securely</button></form>
    </div>

    <div class="portal-stat-grid">
        <article><span class="portal-stat-icon">@include('partials.icon',['name'=>'applications'])</span><div><small>Applications</small><strong>{{ $leads->count() ?: $applications->count() }}</strong><span>submitted requests</span></div></article>
        <article><span class="portal-stat-icon">@include('partials.icon',['name'=>'loans'])</span><div><small>Active loans</small><strong>{{ $activeLoans->count() }}</strong><span>current facilities</span></div></article>
        <article><span class="portal-stat-icon">@include('partials.icon',['name'=>'reports'])</span><div><small>Outstanding</small><strong>{{ number_format($outstanding) }}</strong><span>TZS remaining</span></div></article>
        <article><span class="portal-stat-icon">@include('partials.icon',['name'=>'payments'])</span><div><small>Repayments</small><strong>{{ number_format($confirmedRepayments) }}</strong><span>TZS confirmed</span></div></article>
    </div>

    <section class="portal-section">
        <div class="portal-section-head"><div><span class="lead-kicker">APPLICATIONS</span><h2>Application progress</h2></div><a href="/">Request another financing type →</a></div>
        <div class="portal-application-list">
            @forelse($leads as $lead)
            @php
                $stage = match($lead->status){'unconfirmed'=>0,'new'=>1,'accepted'=>2,'onboarded'=>3,default=>0};
                $label = match($lead->status){'unconfirmed'=>'Confirmation required','new'=>'Waiting for supplier','accepted'=>'Supplier assigned','onboarded'=>'Onboarded',default=>ucfirst($lead->status)};
            @endphp
            <article class="portal-application-card">
                <div class="portal-application-top"><div><span class="badge {{ $lead->status }}">{{ $label }}</span><h3>{{ $lead->product->name }}</h3><p>{{ $lead->created_at->format('d M Y') }} · {{ $lead->reference }}</p></div><div><strong>{{ number_format($lead->financing_amount) }} TZS</strong>@if($lead->request_type==='submeter')<small>{{ $lead->meter_quantity }} meter{{ $lead->meter_quantity===1?'':'s' }}</small>@endif</div></div>
                <div class="portal-progress" aria-label="Application progress">
                    @foreach(['Confirmed','Supplier','Onboarding','Review'] as $number=>$step)
                    <div class="{{ $stage>$number?'done':($stage===$number?'current':'') }}"><span>{{ $stage>$number?'✓':$number+1 }}</span><small>{{ $step }}</small></div>
                    @endforeach
                </div>
                <div class="portal-application-foot"><span>{{ $lead->supplier?->company_name ?: 'Supplier not assigned yet' }}</span><a href="/requests/{{ $lead->public_id }}">View request details →</a></div>
            </article>
            @empty
            <div class="portal-empty">No public applications are linked to this number.</div>
            @endforelse
        </div>
    </section>

    <section class="portal-section">
        <div class="portal-section-head"><div><span class="lead-kicker">FINANCING</span><h2>Loans and repayments</h2></div></div>
        @forelse($applications as $application)
        <article class="portal-loan-card">
            <div class="portal-loan-head">
                <div><span class="badge {{ $application->loan?->status ?: $application->status }}">{{ $application->loan ? str_replace('_',' ',ucfirst($application->loan->status)) : 'Application '.ucfirst($application->status) }}</span><h3>{{ $application->product->name }}</h3><p>{{ $application->created_at->format('d M Y') }} · {{ $application->reference }}@if($application->loan) · {{ $application->loan->reference }}@endif</p></div>
                @if($application->loan)<div class="portal-balance"><small>Outstanding balance</small><strong>{{ number_format($application->loan->outstanding()) }} TZS</strong></div>@endif
            </div>
            @if($loan=$application->loan)
            @php($nextInstallment=$loan->installments->first(fn($installment)=>$installment->outstanding()>0))
            <div class="portal-loan-summary"><div><small>Total repayable</small><strong>{{ number_format($loan->terms['repayment_total']) }} TZS</strong></div><div><small>Next payment</small><strong>{{ $nextInstallment?number_format($nextInstallment->outstanding()).' TZS':'Fully paid' }}</strong><span>{{ $nextInstallment?'Due '.$nextInstallment->due_date->format('d M Y'):'No payment due' }}</span></div><div><small>Payment progress</small><strong>{{ number_format($loan->terms['repayment_total']-$loan->outstanding()) }} TZS</strong><span>allocated to the schedule</span></div></div>

            <details class="portal-details" open>
                <summary>Repayment schedule</summary>
                <div class="table-wrap"><table class="portal-data-table"><thead><tr><th>Due date</th><th>Instalment</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead><tbody>
                    @foreach($loan->installments as $installment)<tr><td data-label="Due date">{{ $installment->due_date->format('d M Y') }}</td><td data-label="Instalment">#{{ $installment->number }}</td><td data-label="Amount">{{ number_format($installment->principal+$installment->charge) }} TZS</td><td data-label="Paid">{{ number_format($installment->paid_principal+$installment->paid_charge) }} TZS</td><td data-label="Balance">{{ number_format($installment->outstanding()) }} TZS</td><td data-label="Status"><span class="badge {{ $installment->status }}">{{ ucfirst($installment->status) }}</span></td></tr>@endforeach
                </tbody></table></div>
            </details>
            <details class="portal-details">
                <summary>Payment history <span>({{ $loan->payments->count() }})</span></summary>
                <div class="table-wrap"><table class="portal-data-table"><thead><tr><th>Date</th><th>Receipt</th><th>Purpose</th><th>Channel</th><th>Amount</th><th>Status</th></tr></thead><tbody>
                    @forelse($loan->payments->sortByDesc('paid_at') as $payment)<tr><td data-label="Date">{{ $payment->paid_at->format('d M Y') }}</td><td data-label="Receipt">{{ $payment->reference }}</td><td data-label="Purpose">{{ ucfirst($payment->purpose) }}</td><td data-label="Channel">{{ str_replace('_',' ',ucfirst($payment->channel)) }}</td><td data-label="Amount">{{ number_format($payment->amount) }} TZS</td><td data-label="Status"><span class="badge {{ $payment->status }}">{{ ucfirst($payment->status) }}</span></td></tr>@empty<tr><td colspan="6" class="empty">No payments have been recorded.</td></tr>@endforelse
                </tbody></table></div>
            </details>
            @else
            <div class="portal-pending-note">Your application is {{ $application->status }}. Loan and repayment information will appear here after approval.</div>
            @endif
        </article>
        @empty
        <div class="portal-empty">Loan information will appear after supplier onboarding and financing approval.</div>
        @endforelse
    </section>
</section>
@endsection
