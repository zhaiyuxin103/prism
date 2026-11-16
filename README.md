# Prism

An image-generation app built with Laravel 13, Inertia 3 and Vue 3 for the [Pixelforge assessment](https://pixelforge.promptedit.dev/assessment). Users can request an estimate, submit a generation, and view private history with live status, images and final charges.

The provider is simulated: no real AI generation or payment processing takes place. Admin reports and credit balances are not implemented.

## Local setup

Requirements: PHP 8.5 with BCMath, Composer, Node.js 22.18+ or 24.11+, and npm. SQLite is the local default; PostgreSQL is suitable for deployment.

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm ci
npm run build
```

Set `PIXELFORGE_API_KEY` and `PIXELFORGE_WEBHOOK_SECRET` in `.env`. Register an account in Prism and verify its email. The default `MAIL_MAILER=log` writes verification emails to `storage/logs/laravel.log`; configure SMTP when deploying.

Run these in separate terminals:

```sh
php artisan serve
npm run dev
php artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=30
php artisan schedule:work
```

Laravel Herd can serve the app instead of `artisan serve`. Use `QUEUE_CONNECTION=database`, `CACHE_STORE=database` and `SESSION_DRIVER=database`. Delayed work needs an asynchronous queue; `sync` does not provide delayed retries.

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

## Tests and checks

```sh
php artisan test --compact
npm test
npm run types:check
npm run check
vendor/bin/phpstan analyse
npm run build
```

Run the build before or after PHP tests, not concurrently: the build replaces assets that HTTP tests render. `composer ci:check` runs frontend checks/tests and the backend checks/tests. PHP tests use an isolated in-memory SQLite database and fake provider HTTP responses. Queue integration tests use real database queue dispatch/processing to exercise uniqueness and delayed work. Frontend tests exercise the real Inertia `useHttp` behavior and render the price presentation.

Regression coverage includes duplicate/out-of-order and early webhooks, expired quotes, upstream failures, high-precision settlement, initial money schema and rollback/rebuild, capacity waiting, lost responses, delayed retries, paused recovery and owner isolation.

## Laravel Cloud

See [Cloud deployment settings](https://laravel.com/cloud/docs/environments) and [task scheduling](https://laravel.com/cloud/docs/scheduled-tasks).

1. Create an application from the repository, select the intended branch, and use PHP 8.5 and a compatible Node version.
2. Attach a persistent database, such as PostgreSQL. Let Cloud inject its connection variables; do not carry the local SQLite configuration into production.
3. Configure the environment variables below. Use a new Pixelforge key for this environment.
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
php artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=30
```

Cloud restarts workers on deployment. Enabling Scheduler runs `schedule:run` every minute; no separate `schedule:work` process is needed there. The default database queue reservation timeout is 90 seconds, longer than the worker timeout. Keep cache shared between the app and workers. No object storage is required for the current implementation, which displays the provider's signed image URLs directly.

| Variable                                                   | Production value / purpose                                         |
| ---------------------------------------------------------- | ------------------------------------------------------------------ |
| `APP_NAME`                                                 | `Prism`                                                            |
| `APP_ENV`                                                  | `production`                                                       |
| `APP_DEBUG`                                                | `false`                                                            |
| `APP_KEY`                                                  | Stable production key; generate once, never rotate on every deploy |
| `APP_URL`                                                  | Public HTTPS application URL                                       |
| `DB_*`                                                     | Cloud-injected database connection values                          |
| `QUEUE_CONNECTION`                                         | `database`                                                         |
| `CACHE_STORE`                                              | `database`                                                         |
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

Keep secrets in Cloud environment settings, never in the repository. Redeploy after environment changes. The app's core implementation is ready for deployment; this README does not assert that a public environment has been deployed.
