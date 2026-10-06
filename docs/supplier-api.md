# Supplier Integration API

Base URL: `/api/v1/supplier`

Generate a fixed API key and secret from **Admin → Suppliers → Supplier profile**. The secret is displayed only once.

For every request, create this canonical string:

```text
UNIX_TIMESTAMP
HTTP_METHOD
/api/v1/supplier/request-path
SHA256_HEX_OF_RAW_REQUEST_BODY
```

Generate `X-Signature` as the lowercase hexadecimal result of `HMAC-SHA256(canonical_string, API_SECRET)`, then send:

```http
X-API-Key: mnaf_live_your_fixed_key
X-Timestamp: 1791244800
X-Signature: calculated_hmac_sha256_signature
Accept: application/json
```

The server accepts timestamps within five minutes of its current time. For a GET request with no body, use the SHA-256 hash of an empty string. The exact path includes `/api` and excludes the domain and query string. Existing legacy Bearer keys remain supported during migration.

## Onboard a customer and submit a loan application

```http
POST /api/v1/supplier/customers/onboard
Content-Type: application/json

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
  "region": "Dar es Salaam",
  "district": "Kinondoni",
  "ward": "Kawe",
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
}
```

The API validates Tanzania region, district, and ward combinations. Customer phone and ID number must be unique. Email and alternative phone are optional. The selected active product must be assigned to the authenticated supplier and have an active financier.

For token financing, omit `device_serial`, `meter_brand_model`, and all `upfront_*` fields. Token `financing_amount` must be between 2,000 and 5,000 TZS. Submeter financing uses the product's fixed amount and requires the exact configured upfront payment. The response indicates whether the product automatically approved the application or submitted it for manual review.

## Retrieve loan details

At least one search field is required. When both fields are supplied, both must match the same customer loan.

```http
GET /api/v1/supplier/loans/details?meter_number=MTR-001&phone_number=255700000000
```

The response contains loan status, customer, product, paid and outstanding amounts, arrears, and the repayment schedule. A supplier can retrieve only loans belonging to customers onboarded by users assigned to that supplier.

## Submit token-purchase repayment

```http
POST /api/v1/supplier/repayments
Content-Type: application/json

{
  "amount_purchased": 10000,
  "reference": "TXN-10001",
  "receipt": "Receipt number 10001",
  "meter_number": "MTR-001",
  "phone_number": "255700000000",
  "collected_amount": 1500
}
```

`reciept` is also accepted as a backwards-compatible alias for `receipt`. The transaction reference must be unique. The collected amount is automatically confirmed and allocated to the active loan schedule. Early repayment is supported, but the amount cannot exceed the outstanding loan balance.

## Check meter status

```http
GET /api/v1/supplier/meters/status?meter_number=MTR-001
```

The response includes `last_purchase_date`, `last_purchase_amount`, loan `status`, `installation_status`, and `disbursement_status`.

## Common responses

- `401`: API key is missing, revoked, or belongs to an inactive supplier.
- `404`: no matching loan or meter exists within the supplier's portfolio.
- `422`: request validation or repayment business rule failed.
- `429`: more than 60 requests were made in one minute.
