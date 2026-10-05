# P1-04: Git-backed Coolify staging, 2026-10-06

## Scope and status

- Task: Replace manual source transfer with branch-based staging deployment.
- Status: Git-backed staging live at `https://staging.scipsi.com`; production cutover remains open.
- Branch: `codex/teller-customer-workflow` in `charliedearce/scipsi-billing`.
- Application: Coolify `duue6sfl9qr3ko2ynkpuohlv` in the existing staging environment. The old service `v5txsjklyp7ejjfqivx9dmft` is stopped, retained for rollback.
- Tested deployment: `gz9sim0drbendpv0v8rwewgs`, source commit `3c12b36`; a later documentation-only push deployed `e0dc5f5` automatically as `lxloalwod3kvajhe5ft1yfo8`.

## Implementation and evidence

- Committed customer photo PDF and PWA install offer as `92f70cd`. Added `deploy/staging.compose.yaml`, the Vue/Caddy Docker image and staging Caddy routes, then corrected Coolify build contexts, clean-container declaration generation, and protected Laravel environment mounting in later commits through `3c12b36`.
- Coolify uses the public Git repository, the named branch, Docker Compose build pack, base directory `/billing/online-billing`, and Compose location `/deploy/staging.compose.yaml`. Auto Deploy is enabled. GitHub push webhook `692571605` delivered pushes with HTTP 200 and started deployments automatically.
- Secrets remain at `/root/scipsi-staging/` on the VPS. The Git stack binds the existing Docker-volume data directories for PostgreSQL, Redis, and private uploads. Neither secret values nor customer data were committed.
- Before cutover, saved a fresh staging PostgreSQL dump and private-file archive in `/root/scipsi-staging/backups/`. The old service was stopped without volume cleanup. Initial failed deploys exposed the Compose context, declaration-generation order, and missing Laravel runtime environment; each was fixed and verified by the following Git deployment.

| Check | Actual result |
| --- | --- |
| `docker compose --project-directory . -f deploy/staging.compose.yaml config --quiet --no-env-resolution` | Passed locally. |
| Clean web Docker build and Caddy validation | Passed locally with Node 24 and generated PWA assets. |
| Coolify deployment `gz9sim0drbendpv0v8rwewgs` | Finished from `3c12b36`. |
| GitHub webhook deliveries | Push events returned HTTP 200 and Coolify recorded webhook-triggered deployments. |
| HTTPS probes | `/` 200; `/api/v1/health` 200; unauthenticated `/api/v1/auth/me` 401; blocked `/api/v1/auth/register` 403. |
| Staging runtime | Seven containers running; PostgreSQL/Redis healthy; worker had zero restarts. Laravel migration status reached the latest applied migration on the existing database. |

## Acceptance and handoff

- Database workflow: Existing synthetic staging database inspected; no financial posting or data import performed.
- Browser/device: Authenticated customer/teller flows, camera capture, install prompt, and multi-user WebSocket behavior not tested.
- Print/Crystal: Not tested; no print path changed.
- Security: Temporary Coolify setup API access was disabled, its token revoked, and the original API IP allowlist restored. Registration remains disabled in staging.
- Next: Run authenticated browser/mobile acceptance, then use a new push to confirm future unattended staging deployment and review deployment health.
