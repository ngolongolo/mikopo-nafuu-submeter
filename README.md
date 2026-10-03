# Mikopo Nafuu — Laravel marketplace

Working Laravel 12 source for a simple fixed-value loan marketplace. Includes public product cards, customer registration/application/portal, admin product and financier management, scoped financier portal, approval/rejection, verified disbursements, monthly schedules, partial payments, administrator confirmation, two-person reversals, receipts, portfolio CSV, and audit history.

## Requirements
PHP 8.2+ (tested with PHP 8.3), Composer 2, extensions PDO, SQLite or MySQL, mbstring, XML/DOM, ctype, curl, fileinfo, openssl, tokenizer. MySQL 8 recommended for production; SQLite included as a local option. No Node build step: responsive CSS is included locally, with no CDN dependency.

## Local installation
```bash
composer install
cp .env.example .env
php artisan key:generate
# Create the local SQLite database (Windows: create an empty file manually).
touch database/database.sqlite
php artisan migrate --seed
php artisan app:create-admin
php artisan serve
```
Open http://localhost:8000. The admin command asks for name, email and a password of at least 12 characters. No shared/default admin password exists.

## First use
1. Log in as administrator. Edit the two seeded products, verify their commercial terms, and activate them.
2. Register a financier and link supported products. Create its user under Team & access if needed.
3. Customer registers via the public site and applies after accepting the displayed terms.
4. Admin or assigned financier approves; the system creates one loan with a frozen quote.
5. Operations/admin records the upfront payment for a submeter. Admin verifies and confirms it.
6. Admin/assigned financier records the verified disbursement, recipient, amount, date, reference, and device serial for submeters. A monthly schedule is generated.
7. Operations/admin records repayments. Admin confirms them to update balances and allocations. Customer and financier can see the results within their access scope.

### Seeded terms (inactive until reviewed)
- Submeter: TZS 100,000 device, 40,000 down payment + 5,000 platform commission upfront; 60,000 principal + 15,000 charge, repaid as 25,000 monthly for three months.
- Token: TZS 5,000 token + 500 financed platform commission; 5,500 principal + 1,000 charge; 6,500 due one month after disbursement.

## Commands and tests
```bash
vendor/bin/phpunit
php artisan loans:overdue
php artisan schedule:list
```
Configure cron: `* * * * * cd /absolute/path/to/app && php artisan schedule:run >> /dev/null 2>&1`. Business dates use Africa/Dar_es_Salaam. The overdue task runs daily at 00:10 and is repeatable. No background queue is needed for the current manual workflows.

## MySQL deployment
Set `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env`. Use a dedicated database and restricted credentials. Run migrations before opening the site. Set the web root to **public/**; never expose the project root. On cPanel, point the domain/subdomain document root at this folder.

Use HTTPS with `APP_ENV=production`, `APP_DEBUG=false`, the correct `APP_URL`, and `SESSION_SECURE_COOKIE=true`. Keep `.env` private, back up the database, and make storage/ and bootstrap/cache/ writable by the web process. Run `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.

## Financial controls
All money is integer TZS. Quotes are hashed for customer acceptance and snapshotted on submission and approval. Duplicate approval/disbursement/confirmation retries preserve original records. Financial services use transactions, row locks, unique references, and positive amount validation. Payments remain pending until verified by an admin. A different admin must reverse a confirmed payment; negative allocation lines preserve history. Upfront reversal after disbursement is blocked. Overpayments are rejected for manual entry. No automatic penalty fees or discounts. Financial records have no delete endpoints.

## Scope and limitations
This MVP supports fixed-value products and monthly terms. Payment collection is **manual**, with evidence references; bank/mobile-money APIs, callback authentication, actual transfers, token vending, and device supplier integration are not implemented. Disbursement confirmation records an externally verified transfer; it does not send money. Financed token commission is included in principal; allocation reports show received customer cash, not accounting profit.

Customer KYC is minimal (ID number, phone, address). Document uploads, password reset/email verification, variable-value products, advanced eligibility, lender wallets, late fees, restructuring, refunds after disbursement, user deactivation/password management, detailed monthly charts, notifications, and dedicated customer administration are future extensions. Review lending rules and partner integrations before live use. Source access and routes isolate customer/financier records; admins have all-system visibility.

Tests exercise terms, schedule dates/rounding, confirmation retries, partial/full payments, reversals, overpayment, scoped access, registration privilege protection, stale terms, and screen rendering. See TEST_RESULTS.md for the actual run in the build environment.
