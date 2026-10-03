@extends('layout')
@section('title','Customers')
@section('content')
<div class="section-head"><div><h1>Customers</h1><p class="subtitle">Review customer qualification and assign a new product loan.</p></div><a class="button action-icon" href="/customers/onboard">@include('partials.icon',['name'=>'user-plus']) <span>Onboard customer</span></a></div>
<div class="grid">@foreach($stats as $label=>$value)<article class="card"><small>{{ $label }}</small><div class="stat">{{ number_format($value) }}</div><small>customers</small></article>@endforeach</div>
<form class="panel filters" method="GET" action="/customers"><input name="q" value="{{ request('q') }}" placeholder="Search customer, email or phone"><button type="submit">Filter</button><a class="button secondary" href="/customers">Clear</a></form>
<div class="panel table-wrap"><table><thead><tr><th>Customer</th><th>Contact</th><th>Qualification</th><th>Location</th><th>Created</th><th>Actions</th></tr></thead><tbody>
@forelse($customers as $customer)<tr><td><a href="/customers/{{ $customer->public_id }}"><strong>{{ $customer->name }}</strong></a></td><td>{{ $customer->email }}<br>{{ $customer->phone }}</td><td>Submeter: {{ number_format($customer->submeter_qualifying_amount) }} TZS<br>Token: {{ number_format($customer->token_qualifying_amount) }} TZS</td><td>{{ $customer->region ?: '—' }}, {{ $customer->district }}<br><small>{{ $customer->ward }} · {{ $customer->address }}</small></td><td>{{ $customer->created_at->format('d M Y') }}</td><td><div class="inline"><a class="view-icon" href="/customers/{{ $customer->public_id }}">@include('partials.icon',['name'=>'view']) <span>View</span></a><a class="button action-icon" href="/customers/{{ $customer->public_id }}/assign-loan">@include('partials.icon',['name'=>'assign']) <span>Assign loan</span></a></div></td></tr>
@empty<tr><td colspan="6" class="empty">No customers found.</td></tr>@endforelse
</tbody></table></div>
@include('partials.pagination',['items'=>$customers])
@endsection
