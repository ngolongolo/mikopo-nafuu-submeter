@extends('layout')
@section('title','API integration')
@section('content')
<div class="section-head"><div><div class="eyebrow">SUPPLIER INTEGRATION</div><h1>API documentation</h1><p class="subtitle">Connect {{ $supplier->company_name }} securely to customer onboarding, loan, meter, and repayment services.</p></div><span class="badge active">API v1</span></div>

<section class="panel api-credentials">
 <div class="section-head"><div><h2>Your credentials</h2><p class="subtitle">Read-only access. Copy credentials into your secured supplier system.</p></div></div>
 <div class="notice">Treat the API secret like a password. Never place it in browser code, URLs, screenshots, or public repositories.</div>
 @forelse($credentials as $credential)
  <article class="credential-card">
   <div class="credential-title"><div><strong>{{ $credential->name }}</strong><small>Created {{ $credential->created_at->format('d M Y') }} · Last used {{ $credential->last_used_at?->format('d M Y H:i') ?? 'never' }}</small></div><span class="badge active">Active</span></div>
   <div class="credential-fields">
    <div><label>Fixed API key</label><div class="copy-field"><code id="api-key-{{ $credential->id }}">{{ $credential->key_id }}</code><button type="button" class="button secondary copy-button" data-copy="api-key-{{ $credential->id }}">Copy</button></div></div>
    <div><label>API secret</label><div class="copy-field"><code id="api-secret-{{ $credential->id }}">{{ $credential->plain_secret ?: 'Secret unavailable — ask an administrator to create new credentials.' }}</code>@if($credential->plain_secret)<button type="button" class="button secondary copy-button" data-copy="api-secret-{{ $credential->id }}">Copy</button>@endif</div></div>
   </div>
  </article>
 @empty
  <div class="empty-state"><strong>No active signed API credentials</strong><p>Ask an administrator to generate credentials for {{ $supplier->company_name }}.</p></div>
 @endforelse
</section>

<section class="panel api-guide"><h2>1. Authenticate and sign every request</h2><p>Send the fixed key, current Unix timestamp, and calculated signature in these headers:</p><pre><code>X-API-Key: mnaf_live_your_fixed_key
X-Timestamp: 1791244800
X-Signature: calculated_hmac_sha256_signature
Accept: application/json
Content-Type: application/json</code></pre><p>Create the signature using <code>HMAC-SHA256(canonical_string, API_SECRET)</code>. The canonical string contains four lines:</p><pre><code>UNIX_TIMESTAMP
HTTP_METHOD
/api/v1/supplier/request-path
SHA256_HEX_OF_EXACT_RAW_REQUEST_BODY</code></pre><div class="notice">The timestamp must be within five minutes of server time. The path excludes the domain and query string.</div></section>

<section class="panel"><h2>2. Available endpoints</h2><div class="api-endpoints">
 <article><div><span class="http-method post">POST</span><code>/api/v1/supplier/customers/onboard</code></div><h3>Onboard customer</h3><p>Submit KYC, Tanzania address, next of kin, product, meter, consent, and applicable upfront-payment details.</p></article>
 <article><div><span class="http-method get">GET</span><code>/api/v1/supplier/loans/details</code></div><h3>Loan details</h3><p>Search using <code>meter_number</code>, <code>phone_number</code>, or both.</p></article>
 <article><div><span class="http-method post">POST</span><code>/api/v1/supplier/repayments</code></div><h3>Submit repayment</h3><p>Submit token purchase amount, reference, receipt, meter, phone, and collected amount.</p></article>
 <article><div><span class="http-method get">GET</span><code>/api/v1/supplier/meters/status</code></div><h3>Meter status</h3><p>Returns last purchase date, last purchase amount, and meter-related loan statuses.</p></article>
</div></section>

<section class="panel api-guide"><h2>3. Customer onboarding example</h2><pre><code>POST /api/v1/supplier/customers/onboard

{
  "first_name": "Asha",
  "middle_name": "Juma",
  "last_name": "Mushi",
  "id_type": "national_id",
  "id_number": "19900101-00000-00001-00",
  "date_of_birth": "1990-01-01",
  "gender": "female",
  "phone_number": "255700000000",
  "alternative_phone": "255710000000",
  "email": "asha@example.com",
  "region": "Arusha",
  "district": "Arumeru",
  "ward": "Akheri",
  "address": "House 10, Example Street",
  "occupation": "Shop owner",
  "next_of_kin_name": "Juma Mushi",
  "next_of_kin_phone": "255720000000",
  "product_code": "SUBMETER",
  "utility_type": "electricity",
  "financing_amount": 100000,
  "meter_number": "MTR-001",
  "device_serial": "SERIAL-001",
  "meter_brand_model": "LIPACHAP",
  "terms_accepted": true,
  "quotation_confirmed": true,
  "upfront_amount": 45000,
  "upfront_channel": "mobile_money",
  "upfront_paid_at": "2026-10-06",
  "upfront_receipt": "MOBILE-TXN-001"
}</code></pre><p>Email and alternative phone are optional. For token financing, omit the serial number, brand, and all <code>upfront_*</code> fields. The response states whether the application was submitted for manual review or approved automatically.</p><pre><code>{
  "message": "Customer onboarded and application submitted for review.",
  "data": {
    "customer_id": "01K...",
    "application_id": "01K...",
    "application_reference": "APP-ABC123",
    "application_status": "submitted",
    "approval_mode": "manual",
    "loan_id": null,
    "loan_reference": null
  }
}</code></pre></section>

<section class="panel api-guide"><h2>4. Loan details example</h2><pre><code>GET /api/v1/supplier/loans/details?meter_number=MTR-001&amp;phone_number=255700000000</code></pre><p>Provide either <code>meter_number</code>, <code>phone_number</code>, or both. When both are supplied, they must identify the same loan. The response includes balances, arrears, statuses, and the repayment schedule.</p><pre><code>{
  "data": {
    "loan_id": "01K...",
    "reference": "MN-ABC123",
    "meter_number": "MTR-001",
    "status": "active",
    "paid_amount": 1500,
    "outstanding_amount": 4500,
    "past_due_amount": 0,
    "past_due_days": 0,
    "schedule": []
  }
}</code></pre></section>

<section class="panel api-guide"><h2>5. Repayment example</h2><pre><code>POST /api/v1/supplier/repayments

{
  "amount_purchased": 10000,
  "reference": "TXN-10001",
  "receipt": "Receipt number 10001",
  "meter_number": "MTR-001",
  "phone_number": "255700000000",
  "collected_amount": 1500
}</code></pre><p>The reference must be unique. A valid repayment is confirmed automatically and applied to the loan schedule. Early repayment is supported, but it cannot exceed the outstanding balance.</p><pre><code>{
  "message": "Repayment recorded and applied successfully.",
  "data": {
    "reference": "TXN-10001",
    "receipt": "Receipt number 10001",
    "status": "confirmed",
    "collected_amount": 1500,
    "outstanding_amount": 4500,
    "loan_status": "active"
  }
}</code></pre></section>

<section class="panel api-guide"><h2>6. Meter status example</h2><pre><code>GET /api/v1/supplier/meters/status?meter_number=MTR-001</code></pre><p>The meter must belong to a customer onboarded by your supplier. Purchase fields are <code>null</code> until the first token-purchase repayment is received.</p><pre><code>{
  "data": {
    "meter_number": "MTR-001",
    "last_purchase_date": "2026-10-06T10:30:00+03:00",
    "last_purchase_amount": 10000,
    "status": "active",
    "installation_status": "not_required",
    "disbursement_status": "pending"
  }
}</code></pre></section>

<section class="panel"><h2>Response status codes</h2><div class="status-grid"><div><strong>200 / 201</strong><span>Request succeeded</span></div><div><strong>401</strong><span>Credentials or signature invalid</span></div><div><strong>404</strong><span>Loan or meter not found</span></div><div><strong>422</strong><span>Validation or business rule failed</span></div><div><strong>429</strong><span>Rate limit exceeded</span></div></div></section>

<script>document.addEventListener('click',async event=>{const button=event.target.closest('[data-copy]');if(!button)return;const value=document.getElementById(button.dataset.copy)?.textContent;if(!value)return;await navigator.clipboard.writeText(value.trim());const original=button.textContent;button.textContent='Copied';setTimeout(()=>button.textContent=original,1400)});</script>
@endsection
