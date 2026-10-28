@extends('layout')
@section('title','Apply for meter or token financing')
@section('content')
<style>.token-meter-section{border-color:#b9d8cd;background:linear-gradient(145deg,#fff 55%,#eff8f3)}.meter-verification-info{display:flex;gap:12px;align-items:flex-start;margin-top:18px;padding:15px;border-radius:11px;background:#e8f4ef;color:#214a43}.meter-verification-info>span{display:grid;place-items:center;flex:0 0 28px;height:28px;border-radius:50%;background:var(--teal);color:#fff;font-weight:800}.meter-verification-info strong,.meter-verification-info small{display:block}.meter-verification-info small{margin-top:3px;color:#55716c}</style>
<section class="lead-hero"><div class="lead-hero-copy"><span class="lead-kicker">POWER WITHIN REACH</span><h1>Get your meter or electricity token today. Pay over time.</h1><p>Choose what you need, see every cost before you continue, and send your request to an approved supplier near you.</p><div class="lead-hero-actions"><a class="button lead-primary" href="#apply">Start your request</a><a class="lead-text-link" href="#how-it-works">How it works ↓</a></div><div class="lead-trust"><span>✓ Clear pricing</span><span>✓ Local supplier support</span><span>✓ No hidden application fee</span></div></div><div class="lead-hero-visual"><div class="energy-orbit"><span class="energy-icon">↯</span><div class="orbit-card orbit-meter"><small>SUBMETER</small><strong>Installed power control</strong><span>Flexible repayment</span></div><div class="orbit-card orbit-token"><small>ENERGY TOKEN</small><strong>Power when you need it</strong><span>From 2,000 TZS</span></div></div></div></section>

<section id="how-it-works" class="lead-process"><div><span>01</span><strong>Choose</strong><small>Select a meter or token request.</small></div><div><span>02</span><strong>Review</strong><small>See your upfront and repayment summary.</small></div><div><span>03</span><strong>Confirm</strong><small>Verify your mobile number with an SMS code.</small></div><div><span>04</span><strong>Connect</strong><small>An eligible supplier accepts your confirmed request.</small></div></section>

<section id="apply" class="lead-application"><div class="lead-section-heading"><span class="lead-kicker">START YOUR REQUEST</span><h2>Choose a financing option</h2><p>Your request is free. A supplier will contact you to complete verification.</p></div>
@if($products->isEmpty())
<div class="panel empty">Meter and token requests are temporarily unavailable. Please check again soon.</div>
@else
<form method="POST" action="/requests" id="lead-request-form">@csrf
<div class="lead-product-grid">
@foreach($products as $product) @php($terms=$product->quote())
<label class="lead-product-card"><input type="radio" name="product_id" value="{{ $product->id }}" @checked((string)old('product_id',(string)$products->first()->id)===(string)$product->id)><span class="lead-product-icon">{{ $product->category==='token'?'↯':'▦' }}</span><span class="badge">{{ ucfirst($product->category) }}</span><strong>{{ $product->name }}</strong><small>{{ $product->description }}</small><span class="lead-product-price">{{ number_format($terms['value']) }} TZS</span><span class="lead-product-detail">{{ number_format($terms['upfront']) }} upfront · {{ $terms['installments'] }} month{{ $terms['installments']===1?'':'s' }}</span></label>
@endforeach
</div>

<div class="lead-form-layout"><div class="lead-form-main">
<section class="panel lead-form-section token-meter-section" id="token-meter-section" hidden><div class="lead-step-title"><span>1</span><div><h3>Enter your electricity meter</h3><p>The supplier will check that this meter exists in their database and qualifies for token financing.</p></div></div><div class="grid two"><label class="full-span">Meter number<input name="meter_number" id="token-meter-number" value="{{ old('meter_number') }}" required disabled inputmode="numeric" autocomplete="off" placeholder="Enter the meter number"><small class="muted">Enter the number exactly as shown on the meter or previous token receipt.</small></label><label>Mobile number<input name="phone" value="{{ old('phone') }}" required disabled autocomplete="tel" placeholder="07XXXXXXXX"><small class="muted">We will send an SMS confirmation code.</small></label><label>Email <small class="muted">Optional</small><input name="email" type="email" value="{{ old('email') }}" disabled autocomplete="email" placeholder="you@example.com"></label></div><div class="meter-verification-info"><span>✓</span><div><strong>Supplier verification follows</strong><small>Your meter number is sent to an eligible supplier. Financing only proceeds after the supplier confirms the meter exists and is eligible.</small></div></div></section>

<section class="panel lead-form-section" id="customer-details-section"><div class="lead-step-title"><span>1</span><div><h3>Tell us about you</h3><p>Use the contact details where the supplier can reach you.</p></div></div><div class="name-row"><label>First name<input name="first_name" value="{{ old('first_name') }}" required autocomplete="given-name"></label><label>Middle name <small class="muted">Optional</small><input name="middle_name" value="{{ old('middle_name') }}" autocomplete="additional-name"></label><label>Last name<input name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name"></label></div><div class="grid two"><label>Mobile number<input name="phone" value="{{ old('phone') }}" required autocomplete="tel" placeholder="07XXXXXXXX"></label><label>Email <small class="muted">Optional</small><input name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="you@example.com"></label></div></section>

<section class="panel lead-form-section" id="location-details-section"><div class="lead-step-title"><span>2</span><div><h3>Where do you need it?</h3><p>This helps us connect you with an eligible supplier.</p></div></div><script type="application/json" id="lead-locations">@json($locations)</script><div class="grid two"><label>Region<select name="region" id="lead-region" required data-old="{{ old('region') }}"><option value="">Select region</option></select></label><label>District<select name="district" id="lead-district" required data-old="{{ old('district') }}" disabled><option value="">Select district</option></select></label><label>Ward<select name="ward" id="lead-ward" required data-old="{{ old('ward') }}" disabled><option value="">Select ward</option></select></label><label>Utility<select name="utility_type" id="lead-utility" required><option value="electricity" @selected(old('utility_type','electricity')==='electricity')>Electricity</option><option value="water" @selected(old('utility_type')==='water')>Water</option></select></label><label class="full-span">Street, building or installation address<textarea name="address" required placeholder="House, street, landmark">{{ old('address') }}</textarea></label></div></section>

<section class="panel lead-form-section"><div class="lead-step-title"><span id="confirmation-step-number">3</span><div><h3>Confirm your request</h3><p>Review the summary before accepting the terms.</p></div></div><div class="grid two"><label id="meter-quantity-wrap">Number of meters<input name="meter_quantity" id="meter-quantity" type="number" min="1" max="5" value="{{ old('meter_quantity',1) }}" required><small class="muted">Choose between 1 and 5 meters.</small></label><label id="lead-amount-wrap">Requested amount (TZS)<input name="financing_amount" id="lead-amount" type="number" value="{{ old('financing_amount') }}" required><small class="muted" id="lead-amount-help"></small></label></div><input type="hidden" name="terms_fingerprint" id="terms-fingerprint"><div class="lead-terms"><h3>Terms and conditions</h3><p id="lead-terms-copy">This is a request for financing, not a guarantee of approval. Your supplier will verify your identity, location, product details, and ability to pay. Final terms are confirmed during onboarding. Any applicable upfront amount must be paid and receipted by the supplier.</p><label class="lead-accept"><input type="checkbox" name="accepted" value="1" required @checked(old('accepted'))><span>I confirm that my information is correct and I accept the financing summary and terms above.</span></label></div></section>
</div>

<aside class="lead-summary"><span class="lead-kicker">YOUR SUMMARY</span><h2 id="summary-product">Financing summary</h2><div class="lead-summary-total"><small>Requested value</small><strong id="summary-value">0 TZS</strong></div><dl><div id="summary-meter-number-row" hidden><dt>Meter number</dt><dd id="summary-meter-number">—</dd></div><div id="summary-meters-row"><dt>Meters requested</dt><dd id="summary-meters">1</dd></div><div><dt>Upfront payment</dt><dd id="summary-upfront">0 TZS</dd></div><div><dt>Repayment period</dt><dd id="summary-term">—</dd></div><div><dt>Estimated instalment</dt><dd id="summary-installment">0 TZS</dd></div></dl><div class="lead-summary-note" id="lead-summary-note">After saving, confirm your mobile number with the SMS code before a supplier can accept your request.</div><button type="submit" class="lead-submit">Save &amp; send confirmation code →</button></aside></div>
</form>
<script type="application/json" id="lead-products">@json($productOptions)</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const products = JSON.parse(document.getElementById('lead-products').textContent);
    const form = document.getElementById('lead-request-form');
    const radios = [...form.querySelectorAll('input[name="product_id"]')];
    const amount = document.getElementById('lead-amount');
    const amountHelp = document.getElementById('lead-amount-help');
    const quantity = document.getElementById('meter-quantity');
    const quantityWrap = document.getElementById('meter-quantity-wrap');
    const utility = document.getElementById('lead-utility');
    const tokenMeter = document.getElementById('token-meter-number');
    const tokenSection = document.getElementById('token-meter-section');
    const customerSection = document.getElementById('customer-details-section');
    const locationSection = document.getElementById('location-details-section');
    const termsCopy = document.getElementById('lead-terms-copy');
    const summaryNote = document.getElementById('lead-summary-note');
    const fingerprint = document.getElementById('terms-fingerprint');
    const money = value => new Intl.NumberFormat('en-TZ').format(Math.round(value)) + ' TZS';
    const selected = () => products.find(product => String(product.id) === radios.find(radio => radio.checked)?.value);
    const toggleSection = (section, visible) => {
        section.hidden = !visible;
        section.querySelectorAll('input, select, textarea').forEach(field => field.disabled = !visible);
    };

    function render() {
        const product = selected();
        if (!product) return;
        radios.forEach(radio => radio.closest('.lead-product-card').classList.toggle('selected', radio.checked));
        const isMeter = product.category === 'submeter';
        const isToken = product.category === 'token';
        toggleSection(tokenSection, isToken);
        toggleSection(customerSection, !isToken);
        toggleSection(locationSection, !isToken);
        if (!isToken) {
            district.disabled = !region.value;
            ward.disabled = !district.value;
        }
        document.getElementById('confirmation-step-number').textContent = isToken ? '2' : '3';
        const meters = isMeter ? Math.min(5, Math.max(1, Number(quantity.value) || 1)) : 1;
        quantity.value = meters;
        quantityWrap.hidden = !isMeter;
        quantity.disabled = !isMeter;
        document.getElementById('summary-meters-row').hidden = !isMeter;
        document.getElementById('summary-meter-number-row').hidden = !isToken;
        document.getElementById('summary-meter-number').textContent = tokenMeter.value.trim() || 'Not entered';

        if (isMeter) {
            amount.value = product.value * meters;
            amount.readOnly = true;
            amount.min = amount.value;
            amount.max = amount.value;
            amountHelp.textContent = 'Fixed value for ' + meters + ' meter' + (meters === 1 ? '.' : 's.');
        } else {
            amount.readOnly = false;
            amount.min = 2000;
            amount.max = product.value;
            if (!amount.value || Number(amount.value) < 2000 || Number(amount.value) > product.value) amount.value = product.value;
            amountHelp.textContent = 'Choose an amount from 2,000 to ' + money(product.value) + '.';
        }

        termsCopy.textContent = isToken
            ? 'This request proceeds only after a supplier confirms that the meter exists in its database and is eligible for token financing. Final customer KYC and financing terms are completed during onboarding.'
            : 'This is a request for financing, not a guarantee of approval. Your supplier will verify your identity, location, product details, and ability to pay. Final terms are confirmed during onboarding. Any applicable upfront amount must be paid and receipted by the supplier.';
        summaryNote.textContent = isToken
            ? 'After SMS confirmation, a supplier will verify the meter number and financing eligibility before onboarding.'
            : 'After saving, confirm your mobile number with the SMS code before a supplier can accept your request.';

        const value = Number(amount.value);
        const principal = isMeter ? product.principal * meters : value - product.down_payment + (product.commission_financed ? product.commission : 0);
        const repayment = principal + product.finance_charge * meters;
        fingerprint.value = product.fingerprint;
        document.getElementById('summary-product').textContent = product.name;
        document.getElementById('summary-value').textContent = money(value);
        document.getElementById('summary-meters').textContent = meters;
        document.getElementById('summary-upfront').textContent = money(product.upfront * meters);
        document.getElementById('summary-term').textContent = product.installments + ' month' + (product.installments === 1 ? '' : 's');
        document.getElementById('summary-installment').textContent = money(repayment / product.installments);
    }

    radios.forEach(radio => radio.addEventListener('change', render));
    amount.addEventListener('input', render);
    quantity.addEventListener('input', render);
    tokenMeter.addEventListener('input', render);

    const locations = JSON.parse(document.getElementById('lead-locations').textContent);
    const region = document.getElementById('lead-region');
    const district = document.getElementById('lead-district');
    const ward = document.getElementById('lead-ward');
    const fill = (select, items, placeholder, chosen = '') => {
        select.innerHTML = '<option value="">' + placeholder + '</option>';
        items.forEach(item => select.add(new Option(item, item, false, item === chosen)));
        select.disabled = !items.length;
    };
    fill(region, Object.keys(locations), 'Select region', region.dataset.old);
    const loadDistricts = chosen => fill(district, Object.keys(locations[region.value] || {}), 'Select district', chosen);
    const loadWards = chosen => fill(ward, (locations[region.value] || {})[district.value] || [], 'Select ward', chosen);
    region.addEventListener('change', () => { loadDistricts(''); fill(ward, [], 'Select ward'); });
    district.addEventListener('change', () => loadWards(''));
    if (region.value) {
        loadDistricts(district.dataset.old);
        if (district.value) loadWards(ward.dataset.old);
    }
    render();
});
</script>
@endif
</section>
@endsection
