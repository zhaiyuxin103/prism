# Prism

An image-generation app built with Laravel 13, Inertia 3 and Vue 3 for the [Pixelforge assessment](https://pixelforge.promptedit.dev/assessment). Users can request an estimate, submit a generation, and view private history with live status, images and final charges.

The provider is simulated: no real AI generation or payment processing takes place. Administrator reports include daily and per-user financial totals with provider reconciliation. Personal credit balances support simulated purchases, reservations and exact settlement.

**[Live demo](https://prism-production-xduavr.laravel.cloud/login)** · **[Assessment brief](https://pixelforge.promptedit.dev/assessment)** · **[Provider API docs](https://pixelforge.promptedit.dev/docs)**

## Review the assessment

Open the live demo and choose an account under **Try a demo account**. No password or email verification is needed for these accounts. Both stretch goals are implemented: the admin report and credit balances with simulated purchases.

| Login button     | Starting credits (USD) | What to review                                        |
| ---------------- | ---------------------: | ----------------------------------------------------- |
| Demo Admin       |                    $25 | Generation flow and the **Admin report** sidebar item |
| Demo User        |                    $10 | Generation flow, private history and credit activity  |
| Demo Low Balance |                 $0.001 | Insufficient-credit handling                          |
| Demo New User    |                     $0 | Empty state and simulated credit purchases            |

These are shared accounts. Balances and history change as people use them; the amounts above are their initial balances, not a reset guarantee. Sign out to switch accounts. You can also register and verify your own account for a separate history; new registrations start with zero credits and have no admin access.

Suggested walkthrough:

1. Sign in as **Demo User**. In **Studio**, enter a prompt such as `A lighthouse at dusk [succeed]`, choose size and quality, and request an estimate. Review the estimate and upper estimate before submitting, then watch the status and final charge update in history.
2. Try a prompt containing `[fail]`. Once it fails, its charge is zero and its credit reservation is released. `[duplicate] [succeed]` exercises repeated webhook delivery without a second settlement; `[slow]` makes the processing state easier to observe.
3. Open **Credits** to inspect purchases, reservations and settlement entries. With a zero-balance account, request an estimate and check that generation is blocked until you buy simulated credits. Purchases require no payment details.
4. Sign out, choose **Demo Admin**, and open **Admin report**. Review daily and per-user spend, provider costs, profit and reconciliation over a UTC date range.

The demo key was also used for earlier local testing. Historical provider totals therefore include records outside the deployed database, and the admin report can show a real difference. The [verification snapshot](#verification-snapshot) records the combined comparison; reconciliation does not alter local charges to hide differences. Images use provider URLs that expire after 24 hours, so older images may be unavailable while their history and charges remain visible.

## Code guide

| Area                                    | Entry points                                                                                                                                                                                                               |
| --------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Estimates and exact billing             | [GenerationPricing](app/Actions/GenerationPricing.php), [CreateGeneration](app/Actions/CreateGeneration.php)                                                                                                               |
| Provider requests, retries and recovery | [PixelforgeClient](app/Actions/PixelforgeClient.php), [SyncGeneration](app/Jobs/SyncGeneration.php), [RecoverGenerations](app/Console/Commands/RecoverGenerations.php)                                                     |
| Webhook verification and settlement     | [PixelforgeWebhookController](app/Http/Controllers/PixelforgeWebhookController.php), [ProcessPixelforgeWebhook](app/Jobs/ProcessPixelforgeWebhook.php), [ApplyGenerationSnapshot](app/Actions/ApplyGenerationSnapshot.php) |
| Credit balances and reservations        | [CreditLedger](app/Actions/CreditLedger.php)                                                                                                                                                                               |
| Reports and provider reconciliation     | [BuildAdminReport](app/Actions/BuildAdminReport.php), [FetchPixelforgeUsage](app/Actions/FetchPixelforgeUsage.php)                                                                                                         |
| User interface                          | [Studio](resources/js/pages/Dashboard.vue), [Credits](resources/js/pages/Credits.vue), [Admin report](resources/js/pages/AdminReport.vue)                                                                                  |
| Regression coverage                     | [Feature tests](tests/Feature), [frontend tests](resources/js)                                                                                                                                                             |

The sections below explain [setup](#local-setup), [billing choices](#money-and-estimates), [asynchronous processing](#asynchronous-processing), [credits](#balances-and-simulated-purchases), [reports](#admin-report), [checks](#tests-and-checks) and [deployment](#laravel-cloud).

## Local setup

Requirements: PHP 8.5 with BCMath, Composer, Node.js 22.18+ or 24.11+, and npm. SQLite is the local default; PostgreSQL is suitable for deployment.

```sh
composer install
cp .env.example .env
php artisan key:generate --no-interaction
touch database/database.sqlite
php artisan migrate --no-interaction
php artisan db:seed --no-interaction
npm ci
npm run build
```

Set `APP_URL` to your local application's URL, and set `PIXELFORGE_API_KEY` and `PIXELFORGE_WEBHOOK_SECRET` in `.env`. These credentials belong on the server, never in frontend code. Seeding creates the four verified demo accounts above, each with a personal team and an opening balance. It does not create sample generations or reset existing accounts when rerun.

Demo login is enabled only in `local`, `testing` and `demo` environments, and only on the host configured in `APP_URL`. In `production`, seeding creates no demo accounts and quick login is disabled. To test regular registration, create your own account and verify its email. The default `MAIL_MAILER=log` writes verification emails to `storage/logs/laravel.log`.

With Laravel Herd serving the app, run these in separate terminals:

```sh
npm run dev
php artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=30
php artisan schedule:work
```

Open the site's Herd URL. Without Herd, configure your PHP web server with `public/` as its document root. The Vite process is optional if you use the built assets from `npm run build`; the queue worker and scheduler are needed for background processing and recovery. Use `QUEUE_CONNECTION=database`, `CACHE_STORE=database` and `SESSION_DRIVER=database`. Delayed work needs an asynchronous queue; `sync` does not provide delayed retries.

Expose the local app through a public HTTPS tunnel. Set the Pixelforge key's webhook URL to `https://YOUR-PUBLIC-HOST/webhooks/pixelforge`. The endpoint must respond directly, without an HTTP-to-HTTPS redirect. Use a separate API key and signing secret for each environment. Account rate limits are still shared across those keys.

## Money and estimates

All monetary columns contain **USD decimal strings with 18 fractional digits**, stored as `VARCHAR(64)`. API responses also use strings. BCMath performs all financial arithmetic; neither PHP floats nor JavaScript numbers participate in billing. String storage keeps SQLite from coercing decimal values into binary floating point.

For each successful generation:

```text
provider_cost = sum(tokens[type] × captured_rate[type]) / 1,000,000
user_charge   = provider_cost × 1.25
```

All four token categories are included. Rates allow up to nine integer and nine fractional digits. Division by one million requires up to 15 fractional digits; the exact 25% markup requires up to 17. The chosen 18-digit scale therefore preserves every supported amount without rounding. Failed and definitively rejected generations have zero charges. Rates from the generation snapshot determine final billing, even if current pricing has changed.

The displayed estimate uses the midpoint of the reasoning range. The upper estimate uses the maximum reasoning tokens, rounds the text-token variance upward to a whole token, and includes fixed image-input/output tokens. Both include the markup. The upper estimate applies to current rates and is **not an enforced spending cap**. Actual usage and the provider's captured rates determine settlement.

Quotes are encrypted, bound to the user and normalized input, and expire after five minutes. Submission rechecks pricing. An expired or changed quote preserves the form and requires a refreshed estimate and another confirmation. Invalid upstream pricing produces a generic 503; diagnostic field names remain in server logs. Pricing responses are cached for 60 seconds.

### Database schema

Run `php artisan migrate` to create the database schema. Generations retain their request and estimate snapshots; settlements are unique per generation, and webhook events are unique per provider event ID.

For future reports, sum with `bcadd`, or explicitly cast to an exact decimal database type. Do not use SQLite `SUM` on these strings or cast them to floats. Group provider costs by **`provider_completed_at` in UTC**, not `settled_at`, to reconcile with `GET /v1/usage`. `settled_at` records when Prism posted the local settlement.

## Asynchronous processing

1. Submission stores the input snapshot and signed estimate, then queues `SyncGeneration` after commit.
2. Every provider submission uses the local generation UUID as its `Idempotency-Key` and `metadata.local_generation_id`. Retrying the same request preserves that identity.
3. A signed webhook is saved once by event ID and queued for processing. Its HTTP route is outside the `web` middleware group: it starts no session and sets no session cookies. Signatures are verified over the raw body with constant-time comparison and a five-minute timestamp tolerance.
4. Known events require a valid envelope. Unknown signed event types are recorded as `ignored` and acknowledged with 204. Malformed events return JSON 4xx rather than redirects.
5. Snapshot application locks the generation. Terminal states cannot regress, and the unique settlement constraint allows only one settlement per generation. Metadata matching handles webhooks arriving before the submission response.
6. Retries and subsequent provider checks are dispatched with delays. Queue uniqueness lasts until processing starts; a separate generation lock prevents concurrent processing. `generations:recover` runs every minute to recover due work whose queue dispatch was lost.

### Limits and uncertain results

Local request-budget waits and provider frequency/concurrency limits do not consume the submission/poll retry budget. Concurrency limits retry after 5–8 seconds, or longer when required by `Retry-After`; frequency limits retain exponential backoff. All capacity waits respect `Retry-After` before another provider request and have a 15-minute window. Expiry pauses automatic checks without falsely declaring failure. “Resume checking” resets that window and preserves the original request identity.

Overload and other recoverable errors have bounded attempts and backoff. A network timeout leaves the submission **uncertain**: it may already exist at the provider. Exhausting retries pauses checking; it does not create a zero-cost terminal failure. Even a later rejection is not treated as proof that the original timed-out request never existed. This deliberately favors recoverable uncertainty over a potentially incorrect settlement. A late webhook can still finish a paused generation.

The page refreshes every three seconds while checks are active, or every 30 seconds when only paused generations remain. This page refresh does not restart provider polling. Signed image URLs expire after 24 hours; history and charges remain available.

## Balances and simulated purchases

New users start with zero credits. Open **Credits** from the sidebar or use the balance card in the studio to simulate a $10, $25 or $50 purchase. No card details or real payment are involved. Credits belong to the individual user, independently of the selected team.

Available credits equal balance minus reserved credits. Submitting a new generation requires and reserves the full upper estimate in the same transaction as generation creation. The server checks funds even if a stale browser enables submission. On success, the actual charge is deducted and the full reservation is released; confirmed failure or rejection releases it without a charge. Uncertain submissions and paused checks keep the reservation, and resuming does not reserve again. If captured provider rates produce a charge above the reservation, Prism records the exact charge, even if it leaves a negative balance, and requires sufficient available credits before another generation.

Purchases are idempotent per user and request ID. Retrying an unconfirmed purchase in the current page retains the same amount and ID. User/account locks serialize purchases and reservations; generation/account locks and unique ledger references prevent repeated settlements. The credit history lists purchases, reservations and settlements with exact balance snapshots. Deleted users' credit accounts and ledger entries remain available for settling in-flight work.

Run migrations before using balances. Generations created before this feature remain in financial reports but do not debit the new credit accounts retroactively. New generations have an explicit account and reservation marker. Existing users also start at zero; no credits are granted automatically.

## Admin report

Platform administrators can open **Admin report** from the sidebar. Access requires a verified email and `users.is_admin`; team roles do not grant financial-report access. New users are not administrators.

After running migrations, grant or revoke access with:

```sh
php artisan users:admin                       # show recent users, search and select
php artisan users:admin operator@example.com  # grant by email (case-insensitive)
php artisan users:admin 42                    # grant by user ID
php artisan users:admin 42 --revoke            # revoke by ID or email
php artisan users:admin --revoke              # search and select an account to revoke
```

The interactive picker searches names, emails and exact IDs, shows current admin status, and offers cancellation. For scripts, pass an ID or email with `--no-interaction`; omitting it fails without changing permissions. The command only updates existing accounts.

Reports cover an inclusive UTC date range of up to 92 days (30 by default). Daily totals and paginated user summaries include credit spend, provider cost and profit; expand a user to see daily figures. They use provider completion time and settled amounts, not submission time or current pricing. Failed provider generations are free. Pre-acceptance rejections have no provider completion date and are excluded. Deleted users' costs remain in a separate aggregate.

Each refresh compares local costs and successful-generation counts with `GET /v1/usage` for the configured key, by day as well as for the whole period. Differences are local minus provider; opposing daily differences cannot cancel into a matched report. Days with no activity on either side are hidden by default and can be expanded; failed generations, reconciliation differences and unchecked days remain visible. Validated provider usage is cached for 30 seconds per endpoint, API key and date range, including across user pagination; local settlements are still read on every request. Provider failures or incomplete/inconsistent responses leave local figures available with an unavailable reconciliation status. Requests share the generation client's budget and honor provider `Retry-After` without immediate retries.

Keep one dedicated API key per environment/database. The report compares this database's settlements with the **currently configured key**; it does not reconstruct historical key assignments. Rotating keys or sharing a key across databases can produce differences. Pending or delayed generation results may also produce temporary differences, especially today. Refresh after those results settle. Reconciliation only reports differences; it never overwrites settlements to force a match.

## Tests and checks

```sh
php artisan test --compact
npm test
npm run types:check
npm run check
vendor/bin/phpstan analyse
npm run build
```

For the optional concurrency test, create a disposable PostgreSQL database named `prism_credits_test_*`, set its `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`, and run `php artisan test --compact tests/Feature/CreditConcurrencyTest.php`. It commits fixtures so independent processes can exercise actual row locks. Never point this test at application data. The SQLite suite skips it.

Run the build before or after PHP tests, not concurrently: the build replaces assets that HTTP tests render. `composer ci:check` runs frontend checks/tests and the backend checks/tests. PHP tests use an isolated in-memory SQLite database and fake provider HTTP responses. Queue integration tests use real database queue dispatch/processing to exercise uniqueness and delayed work. Frontend tests exercise the real Inertia `useHttp` behavior and render the price presentation.

Regression coverage includes duplicate/out-of-order and early webhooks, expired quotes, upstream failures, high-precision settlement, initial money schema and rollback/rebuild, capacity waiting, lost responses, delayed retries, paused recovery and owner isolation.

### Verification snapshot

<details>
<summary>Deployed app checks recorded on November 17, 2026 (Asia/Shanghai)</summary>

The deployed app was checked through the browser, followed by read-only production database queries and a `GET /v1/usage` request using the deployed API key.

| Scenario                | Generation ID prefix | Result                                  | Provider cost (USD) | User charge (USD) |
| ----------------------- | -------------------- | --------------------------------------- | ------------------: | ----------------: |
| `[fail]`                | `fc92e931`           | Failed; one zero-cost settlement        |                   0 |                 0 |
| `[duplicate] [succeed]` | `588c2044`           | Succeeded; image loaded; one settlement |         0.012110000 |       0.015137500 |

For the duplicate scenario, the processing and success events each had one stored event record, both marked `processed` without errors. All ten stored events across the five production generations were processed without errors. This verifies the application-side outcome; individual delivery attempts in the provider dashboard were not independently inspected.

The usage query covered **November 16–17, 2026 UTC**. All successful generations in this snapshot were billed on November 16 UTC; November 17 had zero usage. The deployed key's usage includes six earlier successful local test generations retained in the old SQLite database, so it must not be compared with production-only totals without accounting for that history.

| Source                  | Successful generations | Provider cost (USD) |
| ----------------------- | ---------------------: | ------------------: |
| Production PostgreSQL   |                      4 |         0.493597500 |
| Historical local SQLite |                      6 |         0.632780000 |
| Combined records        |                     10 |         1.126377500 |
| Pixelforge usage        |                     10 |         1.126377500 |

The combined records matched usage exactly in both cost and all four token categories: 143 input-text tokens, 1,530 input-image tokens, 22,670 output tokens and 16,285 reasoning tokens. Each production success was recalculated from its captured rates; production user charges totaled **$0.616996875**, exactly 125% of provider costs. The failed generation contributed no cost or charge.

These are verification-time totals, not live counters. For a new environment, use a dedicated provider key. If the current key is replaced, reconcile pre-switch and post-switch records against their respective keys rather than comparing all historical records with the new key's usage.

</details>

## Laravel Cloud

See [Cloud deployment settings](https://laravel.com/cloud/docs/environments) and [task scheduling](https://laravel.com/cloud/docs/scheduled-tasks).

The live demo uses PostgreSQL for application data and **Redis for queues and cache**. Its webhook endpoint is `https://prism-production-xduavr.laravel.cloud/webhooks/pixelforge`. It is a dedicated demo deployment with quick login enabled; the production settings below disable that access.

1. Create an application from the repository, select the intended branch, and use PHP 8.5 and a compatible Node version.
2. Attach PostgreSQL and a Redis-compatible cache. Let Cloud inject their connection variables; do not carry local database or Redis credentials into production.
3. Configure the environment variables below. Use a dedicated Pixelforge key for new environments; the current demo's historical usage is documented above.
4. Set the build and deploy commands, configure a queue worker, and enable the App cluster's **Scheduler** toggle. Start with one replica; multiple scheduler replicas need `onOneServer` coordination.
5. Deploy, set the key's webhook URL to the deployed HTTPS domain plus `/webhooks/pixelforge`, and send a signed test event from Pixelforge.
6. Register and verify a user. Check a successful generation, a `[fail]` generation, and duplicate webhook delivery. Confirm final charges and the environment key's usage report.

Build commands:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci --include=dev
npm run build
php artisan optimize
```

Deploy command for a fresh installation or subsequent compatible migrations:

```sh
php artisan migrate --force
```

Background worker command:

```sh
php artisan queue:work redis --sleep=1 --tries=3 --timeout=30
```

The worker connection must match `QUEUE_CONNECTION=redis`. A worker listening on `database` will not consume tasks dispatched to Redis. The command above uses the configured Redis queue name. Keep the app and worker connected to the same Redis queue and shared cache.

Cloud restarts workers on deployment. Enabling Scheduler runs `schedule:run` every minute; no separate `schedule:work` process is needed there. Keep the Redis queue reservation timeout (`retry_after`, 90 seconds by default) longer than the 30-second worker timeout. No object storage is required for the current implementation, which displays the provider's signed image URLs directly.

| Variable                                                   | Production value / purpose                                         |
| ---------------------------------------------------------- | ------------------------------------------------------------------ |
| `APP_NAME`                                                 | `Prism`                                                            |
| `APP_ENV`                                                  | `production`                                                       |
| `APP_DEBUG`                                                | `false`                                                            |
| `APP_KEY`                                                  | Stable production key; generate once, never rotate on every deploy |
| `APP_URL`                                                  | `https://prism-production-xduavr.laravel.cloud`                    |
| `DB_*`                                                     | Cloud-injected database connection values                          |
| `REDIS_*`                                                  | Cloud-injected Redis connection values                             |
| `QUEUE_CONNECTION`                                         | `redis`                                                            |
| `CACHE_STORE`                                              | `redis`                                                            |
| `SESSION_DRIVER`                                           | `database`                                                         |
| `SESSION_SECURE_COOKIE`                                    | `true`                                                             |
| `LOG_CHANNEL`                                              | `stderr`                                                           |
| `LOG_LEVEL`                                                | `warning` or `info`                                                |
| `PIXELFORGE_BASE_URL`                                      | `https://pixelforge.promptedit.dev/v1`                             |
| `PIXELFORGE_API_KEY`                                       | This environment's key                                             |
| `PIXELFORGE_WEBHOOK_SECRET`                                | Signing secret belonging to that key                               |
| `MAIL_MAILER`                                              | `smtp` or your configured mail transport                           |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` | Mail provider settings for verification and password resets        |
| `MAIL_SCHEME`                                              | Match the mail provider's TLS settings                             |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`                      | Verified sender address and `Prism`                                |
| `VITE_APP_NAME`                                            | `Prism`                                                            |

Keep secrets in Cloud environment settings, never in the repository. Redeploy after environment or worker configuration changes. The live demo and production verification snapshot are documented above.
