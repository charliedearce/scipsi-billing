# Online development environment

Phase 0 addition, 2026-09-17; Docker application profile added 2026-09-19. The user authorized Docker where needed for PostgreSQL, Redis, and interpreted Laravel Reverb. The default Compose invocation starts only development data services; `--profile app` adds the PHP API, worker, scheduler and Reverb processes.

## Start and inspect

From `online-billing/` in PowerShell:

```powershell
./scripts/Start-DevelopmentServices.ps1
docker compose ps
docker compose exec -T postgres psql -U online_billing_dev -d online_billing_dev -c 'SELECT version();'
docker compose exec -T redis redis-cli ping
docker compose stop
```

Compose project `scipsi-online-billing-dev` has independent named volumes and loopback ports: PostgreSQL 55432 and Redis 56379. It does not reuse the existing RestaurantOS containers or host SQL Server/PostgreSQL services. Images are pinned to the locally available PostgreSQL 18/Redis 8 image digests for reproducibility; review security updates during Phase 1.

The start script generates a local PostgreSQL password under ignored `.local/postgres-password` once. It is mounted as a Compose secret and never printed. Retain it with the development volume: generating a new file does not rotate an initialized database password. This development role initializes the container and is not the production privilege model. Redis is unauthenticated on loopback for synthetic development data only; do not expose these ports or import production data. Container storage persists when stopped. Volume deletion is destructive and is not part of the normal stop workflow.

Connection from the Compose application profile: `postgres:5432`, `redis:6379`. From host tools: `127.0.0.1:55432`, `127.0.0.1:56379`. API credentials must load from ignored local configuration. Create an isolated test database before integration testing; do not run reset/migration tests against a shared developer dataset.

## Host PHP API (preferred for day-to-day coding)

Keep Docker for **PostgreSQL**, **Redis**, and optionally **Reverb**. Run Laravel on the host so source edits apply immediately (no `docker cp`).

```powershell
# Data services only (no app profile)
./scripts/Start-DevelopmentServices.ps1
# Stop stale Docker PHP app containers if they were started earlier:
# docker stop scipsi-online-billing-dev-api-1 scipsi-online-billing-dev-worker-1 scipsi-online-billing-dev-scheduler-1

cd apps/api
composer install
# .env: DB_HOST=127.0.0.1 DB_PORT=55432 REDIS_HOST=127.0.0.1 REDIS_PORT=56379
# REDIS_CLIENT=predis   # Windows host PHP often lacks phpredis
# REVERB_HOST=127.0.0.1 REVERB_PORT=58080 when Reverb stays in Docker
php artisan serve --host=127.0.0.1 --port=8000
# optional second terminal:
php artisan queue:work redis --sleep=1 --tries=3

cd ../web
# .env.development: VITE_API_PROXY_URL = http://127.0.0.1:8000
pnpm.cmd dev
```

Health check: `Invoke-WebRequest http://127.0.0.1:8000/api/v1/health -UseBasicParsing`.

Private artifact files live under `apps/api/storage/app/private` on the host. PDFs previously written only inside the Docker API volume are not automatically visible to the host process.

## Docker PHP application runtime

The `app` Compose profile supplies the same PHP runtime to the API, queue worker, scheduler and optional Reverb process. The image includes PostgreSQL, BCMath, PCNTL, mbstring, ZIP and Redis extensions, so local operational checks do not depend on the host PHP installation.

```powershell
cd online-billing/apps/api
if (!(Test-Path .env)) { Copy-Item .env.example .env; php artisan key:generate }
cd ../..
docker compose --profile app up -d --build
docker compose --profile app exec api php artisan migrate --force
docker compose --profile app ps
Invoke-WebRequest http://127.0.0.1:18000/api/v1/health -UseBasicParsing
docker compose --profile app logs --tail=50 api worker scheduler reverb
docker compose --profile app stop
```

The profile reads `apps/api/.env` for non-secret application settings and mounts the ignored `.local/postgres-password` only as a Compose secret. The API is exposed on `127.0.0.1:18000`; Reverb is exposed on `127.0.0.1:58080`. Do not publish these ports beyond the local machine or put the password in Compose YAML, image layers or committed files.

## Phase 1 application scaffold

The first web scaffold is now present under `apps/`:

```powershell
cd online-billing/apps/api
composer install
if (!(Test-Path .env)) { Copy-Item .env.example .env; php artisan key:generate }
$env:DB_PASSWORD = (Get-Content ..\..\.local\postgres-password -Raw).Trim()
php artisan route:list --path=api
php artisan migrate --force

cd ../web
pnpm.cmd install --frozen-lockfile
pnpm.cmd dev
pnpm.cmd build
```

The API is Laravel 13 with Sanctum installed for the planned session/authentication boundary. Its current public probe is `GET /api/v1/health`; domain, identity, and billing endpoints remain Phase 1 tasks. The UI uses the MIT-licensed Art Design Pro Vue/TypeScript/Vite/Element Plus/Tailwind shell as its starting point. Demo routes and mock data must be replaced or explicitly disabled before user acceptance.

Copy `apps/web/.env.example` to a local `.env.development` before starting Vite. The example points its API proxy at the Docker API port `http://127.0.0.1:18000`; the production profile uses same-origin `/` API routing. If running Laravel directly on the host instead of the Docker `app` profile, override `VITE_API_PROXY_URL` locally to that host port. Deployment-specific URLs belong in deployment configuration, not committed secrets.

Customer registration is OTP-free in the local development profile (`REGISTRATION_REQUIRE_OTP=false`) so test accounts can be created without an SMS provider. The API still contains the production OTP path; set `REGISTRATION_REQUIRE_OTP=true` in staging/production and keep the verification tests enabled.

## Phase 1 service design

W27 planning confirmation, clarified 2026-09-19: Laravel Reverb is the shared realtime layer for any app workflow needing instant updates, including customer/teller chat and notifications. P1-08 covers the event/channel contract, affected-data refresh, durable messages/notifications, private channel authorization, outbox publication and reconnect recovery. Owning modules add events as implemented; live refresh does not require a bell notification for every change. See the [customer service lifecycle](discovery/CUSTOMER_SERVICE_LIFECYCLE.md). This planning revision installs no packages and starts no services.

W31 planning confirmation, 2026-09-19: SkySMS is a separate external after-commit worker adapter for eligible Customer/VIP transactional messages. P1-09 establishes contact/preference/template/delivery records and a fake provider; P2-11/P3-12 attach committed lifecycle events. Do not put SkySMS credentials in Compose, `.env.example`, browser settings or test output. The documented normal send endpoint has no ordinary idempotency parameter, so unknown timeouts reconcile rather than blind retry; Reverb remains the realtime/app-state layer and neither service is a financial authority. See [SMS notifications](discovery/SMS_NOTIFICATIONS.md). This planning revision installs no SMS package, configures no credential and starts no external service.

W32 planning confirmation, 2026-09-19: P1-10 adds a separate customer-registration/buyer-profile/contact-verification boundary. Keep full name, company / registered buyer name, e-mail and mobile validation server-side; use a purpose-bound OTP adapter/fake provider and never expose codes, provider verification URLs or credentials to the browser/logs. The SkySMS generic W31 template module does not implement authentication. Customer/profile snapshots remain application records that P2/P3 posting captures; no schema or provider is installed by this planning revision. See [customer registration](discovery/CUSTOMER_REGISTRATION.md).

W33/W34 implementation status, 2026-09-19: P1-11 now emits the reviewed installable PWA shell; only static assets are cacheable, never `/api`, auth/CSRF, OTP, artifacts, private uploads or financial commands. P1-12 now provides durable in-app announcement records, scoped audience evaluation, versioned Admin authoring, interaction state, and after-commit Reverb/API recovery. Neither feature enables offline billing, browser Web Push, e-mail, SMS or a service-worker replay queue. See [PWA readiness](discovery/PWA_READINESS.md), [announcements](discovery/ANNOUNCEMENTS.md), and [P1-12 evidence](evidence/P1-12.md). Browser/device and live Reverb acceptance remain separate gates.

- Build the PHP application image with required PostgreSQL/decimal extensions; use that same image for API, queue worker, scheduler, and optional Reverb process. No separate financial service.
- Redis supports queues/cache and, if later needed, broadcast scaling; durable outbox and financial records stay in PostgreSQL.
- Reverb provides websocket notifications for approval changes, document-ready events, and UI refresh hints. It runs from the Laravel app via `php artisan reverb:start`; it is not a standalone data store or a substitute for the API. Install and wire it only after the application scaffold exists.
- Broadcast only after commit, use authorized private channels and explicit allowed origins, keep payloads minimal, and reload authoritative API state after reconnect. Missed/duplicate events must not lose or repeat financial work.
- Verify broadcasting authorization and API permissions independently. Reverb failure must not block posting; the UI can poll document/approval status.
- Plan a distinct loopback websocket port (candidate 58080) after checking availability. No other project's Reverb configuration or credentials are reused.

### Local worker and realtime lifecycle

Run these as separate foreground processes after the API `.env` points at the loopback Redis service. They are operational processes, not additional data stores:

```powershell
cd online-billing/apps/api
php artisan queue:work redis --queue=default --sleep=1 --tries=1 --timeout=120
php artisan schedule:work
php artisan reverb:start --host=127.0.0.1 --port=58080
```

For a local Reverb exercise, set `BROADCAST_CONNECTION=reverb`, provide only local generated Reverb app values, and align the web `VITE_REVERB_*` values. Keep `BROADCAST_CONNECTION=log` in the default example until a developer explicitly enables the server. A worker or websocket outage must not be treated as confirmation of a bill, payment, receipt, queue, or announcement state; clients recover by calling the authoritative API. Staging must use separate Redis/PostgreSQL instances, private channels, HTTPS/WSS, restricted origins, secret-manager values, and supervised worker/Reverb processes.

Laravel's [Reverb documentation](https://laravel.com/docs/13.x/reverb) describes its Artisan server, origin configuration, and broadcasting integration. [Laravel Sail](https://laravel.com/docs/13.x/sail) is another Docker development option; this project currently uses explicit Compose for the isolated data services. Production hosting and realtime requirements remain separate decisions.
