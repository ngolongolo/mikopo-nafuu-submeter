@extends('layout')
@section('title','Customer profile')
@section('content')
<div class="section-head"><div><div class="eyebrow">CUSTOMER PROFILE</div><h1>{{ $customer->name }}</h1><p class="subtitle">Customer information, product qualification, and borrowing history.</p></div><a class="button secondary" href="/customers">← Customers</a></div>
<div class="grid two">
 <article class="card"><small>Submeter qualifying amount</small><div class="stat">{{ number_format($customer->submeter_qualifying_amount) }} TZS</div><small>Default 100,000 TZS</small></article>
 <article class="card"><small>Token qualifying amount</small><div class="stat">{{ number_format($customer->token_qualifying_amount) }} TZS</div><small>Default 5,000 TZS</small></article>
</div>
<div class="customer-profile-layout">
 <section class="panel customer-info">
  <header class="customer-identity">
   <div class="customer-avatar">{{ strtoupper(substr($customer->first_name?:$customer->name,0,1).substr($customer->last_name?:strrchr($customer->name,' ')?:'',0,1)) }}</div>
   <div class="customer-identity-copy"><div class="eyebrow">CUSTOMER INFORMATION</div><h2>{{ $customer->name }}</h2><div class="identity-badges"><span class="badge approved">Verified customer</span><span class="customer-number">{{ $customer->public_id }}</span></div></div>
   <div class="customer-owner"><small>Onboarded by</small><strong>{{ $customer->onboardedBy?->name ?? 'Self-registered' }}</strong></div>
  </header>
  <div class="customer-details">
   <article class="detail-section"><div class="detail-heading"><span class="detail-icon">@include('partials.icon',['name'=>'customers'])</span><div><h3>Contact details</h3><p>Customer communication channels</p></div></div><dl class="detail-list"><div><dt>Primary phone</dt><dd>{{ $customer->phone }}</dd></div><div><dt>Alternative phone</dt><dd>{{ $customer->alternative_phone ?: 'Not provided' }}</dd></div><div class="wide"><dt>Email address</dt><dd>{{ $customer->email ?: 'Not provided' }}</dd></div></dl></article>
   <article class="detail-section"><div class="detail-heading"><span class="detail-icon">@include('partials.icon',['name'=>'audit'])</span><div><h3>Identity & occupation</h3><p>Identification and personal details</p></div></div><dl class="detail-list"><div><dt>ID type</dt><dd>{{ $customer->id_type ? ucwords(str_replace('_',' ',$customer->id_type)) : 'Not provided' }}</dd></div><div><dt>ID number</dt><dd>{{ $customer->id_number ?: 'Not provided' }}</dd></div><div><dt>Occupation</dt><dd>{{ $customer->occupation ?: 'Not provided' }}</dd></div><div><dt>Gender</dt><dd>{{ $customer->gender ? ucfirst($customer->gender) : 'Not provided' }}</dd></div></dl></article>
   <article class="detail-section"><div class="detail-heading"><span class="detail-icon">@include('partials.icon',['name'=>'overview'])</span><div><h3>Residential address</h3><p>Customer location information</p></div></div><dl class="detail-list"><div class="wide"><dt>Street address</dt><dd>{{ $customer->address ?: 'Not provided' }}</dd></div><div><dt>Ward</dt><dd>{{ $customer->ward ?: 'Not provided' }}</dd></div><div><dt>District</dt><dd>{{ $customer->district ?: 'Not provided' }}</dd></div><div class="wide"><dt>Region</dt><dd>{{ $customer->region ?: 'Not provided' }}</dd></div></dl></article>
   <article class="detail-section"><div class="detail-heading"><span class="detail-icon">@include('partials.icon',['name'=>'team'])</span><div><h3>Next of kin</h3><p>Emergency contact information</p></div></div><dl class="detail-list"><div class="wide"><dt>Full name</dt><dd>{{ $customer->next_of_kin ?: 'Not provided' }}</dd></div><div class="wide"><dt>Phone number</dt><dd>{{ $customer->next_of_kin_phone ?: 'Not provided' }}</dd></div></dl></article>
  </div>
 </section>
 @if(auth()->user()->role==='admin')<section class="panel customer-editor"><div class="section-head"><div><h2>Edit customer & qualification</h2><p class="subtitle">Update core contact details and product qualification limits.</p></div></div><form method="POST" action="/customers/{{ $customer->public_id }}" class="customer-edit-grid">@csrf @method('PUT')<label>Name<input name="name" value="{{ old('name',$customer->name) }}" required></label><label>Email <small class="muted">Optional</small><input type="email" name="email" value="{{ old('email',$customer->email) }}"></label><label>Phone<input name="phone" value="{{ old('phone',$customer->phone) }}" required></label><label>Submeter qualifying amount (TZS)<input type="number" name="submeter_qualifying_amount" min="0" value="{{ old('submeter_qualifying_amount',$customer->submeter_qualifying_amount) }}" required></label><label>Token qualifying amount (TZS)<input type="number" name="token_qualifying_amount" min="0" value="{{ old('token_qualifying_amount',$customer->token_qualifying_amount) }}" required></label><div class="edit-action"><button>Save customer</button></div></form></section>@endif
</div>
<section class="panel">
 <h2>Product qualification</h2>
 <div class="table-wrap"><table><thead><tr><th>Product</th><th>Category</th><th>Financing value</th><th>Customer limit</th><th>Loan count</th><th>Eligibility</th></tr></thead><tbody>
 @foreach($products as $product)
  @php
   $limit = $product->category === 'token' ? $customer->token_qualifying_amount : $customer->submeter_qualifying_amount;
   $count = $customer->applications->where('product_id', $product->id)->whereIn('status', ['submitted', 'approved'])->count();
   $eligible = $product->value <= $limit && $count < $product->max_loans_per_customer;
  @endphp
  <tr><td>{{ $product->name }}</td><td>{{ ucfirst($product->category) }}</td><td>{{ number_format($product->value) }} TZS</td><td>{{ number_format($limit) }} TZS</td><td>{{ $count }} / {{ $product->max_loans_per_customer }}</td><td><span class="badge {{ $eligible?'approved':'rejected' }}">{{ $eligible?'Qualifies':'Not qualified' }}</span></td></tr>
 @endforeach
 </tbody></table></div>
</section>
<section class="panel"><h2>Applications</h2><div class="table-wrap"><table><thead><tr><th>Reference</th><th>Product</th><th>Status</th><th>Date</th></tr></thead><tbody>@forelse($customer->applications as $application)<tr><td><a href="/applications/{{ $application->public_id }}">{{ $application->reference }}</a></td><td>{{ $application->product->name }}</td><td><span class="badge {{ $application->status }}">{{ ucfirst($application->status) }}</span></td><td>{{ $application->created_at->format('d M Y') }}</td></tr>@empty<tr><td colspan="4">No applications yet.</td></tr>@endforelse</tbody></table></div></section>
@endsection
