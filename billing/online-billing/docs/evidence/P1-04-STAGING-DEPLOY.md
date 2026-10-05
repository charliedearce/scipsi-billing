# P1-04: Coolify staging deployment, 2026-10-05

## Scope and status

- Task / phase: P1-04 staging configuration and worker lifecycle exercise. P6-01 through P6-04 remain open.
- Status: deployed to isolated staging; production cutover unapproved.
- Tested revision: current uncommitted `online-billing` working-tree snapshot transferred to the VPS. No commit or push was made.
- Host: Debian 12 VPS at `scipsi.com` (2 vCPU, 4 GiB RAM, 50 GiB system disk, 100 GiB container-data disk, 2 GiB swap). Coolify 4.3.23, panel `https://coolify.scipsi.com`, staging `https://staging.scipsi.com`.
- Coolify project: `SCIPSI Online Billing`, environment `staging`, service UUID `v5txsjklyp7ejjfqivx9dmft`.

## Implementation and evidence

- Installed Coolify using its official installer; precreated the `root@scipsi.com` administrator account, configured dashboard HTTPS, and bound direct dashboard/realtime ports 8000/6001/6002 to localhost. Public application traffic uses the Coolify proxy on 80/443. The temporary Coolify API token was revoked and API access disabled after setup.
- Built the API image from a curated source snapshot that excluded `.env`, vendor, tests and development data. Built Vue with Node 24 and transferred only `dist`. The service runs PostgreSQL 18, Redis 8, Laravel API, queue worker, scheduler, Reverb, and Caddy/Vue gateway. The stack definition and secret files are under `/root/scipsi-staging/` on the VPS; no secrets are committed.
- The gateway initially required a shared staging password for the Vue pages. This was removed at the user's request on 2026-10-05; Laravel still requires its own authentication for protected API routes. Staging registration and contact verification endpoints remain blocked at the gateway until an OTP provider is ready. Reverb's application endpoint is exposed for WebSocket clients; private channel authorization stays in Laravel.
- Applied every migration to fresh `scipsi_staging` PostgreSQL, then ran `DatabaseSeeder` for synthetic organization/users/master data. Rotated all six seeded account passwords to random values immediately. No legacy SQL Server import or production data was used.
- Saved initial Coolify and staging database dumps plus the Coolify installation secret and SSH-key archive to `C:\Users\charlie-pc\Documents\SCIPSI-VPS-Credentials`, outside Git, with filesystem access limited to the owner, Administrators and SYSTEM. Plaintext handoff passwords were removed from the VPS after the local copy was verified; runtime secret files remain on the VPS. The first backup is a snapshot, not a scheduled offsite backup or restore drill.
- Formatted the previously blank 100 GiB `/dev/vdb` as XFS with `ftype=1`, mounted it at `/srv/container-storage`, and bind-mounted its `docker` and `containerd` directories at `/var/lib/docker` and `/var/lib/containerd`. UUID-based `/etc/fstab` entries and systemd mount requirements make both runtimes depend on the disk. Fresh Coolify and staging PostgreSQL dumps were checked before migration; the original data directories remain underneath the bind mounts on the 50 GiB system disk for rollback. These fresh dumps are still only on the VPS.

| Check | Environment | Actual result |
| --- | --- | --- |
| `pnpm.cmd build` with Node 24.19.0 | Local `apps/web`, current working tree | Passed: Vue typecheck, Vite bundle and PWA output |
| `docker build -t scipsi-online-api:staging-20261005` | Debian VPS, curated API snapshot | Passed |
| `docker compose config --quiet`; `caddy validate` | VPS staging configuration | Passed |
| `php artisan migrate --force`; `php artisan db:seed --force` | Fresh VPS PostgreSQL `scipsi_staging` | Passed |
| HTTPS and authorization probes | `staging.scipsi.com` | TLS valid; page 401 without gate and 200 with gate; API health 200; admin login/profile/logout 200; registration 403; WebSocket upgrade 101 |
| Container status | Coolify service | Gateway, API, worker, scheduler, Reverb up; PostgreSQL and Redis healthy |
| Storage migration smoke check | VPS after Docker restart | Both data paths resolve to `/dev/vdb`; all 13 containers restarted, all health checks passed, internal API health returned 200, staging gate returned 401 without credentials, Coolify HTTPS returned 200 |
| Basic Auth removal check | VPS gateway after config validation and restart | Staging home 200 without credentials, API health 200, protected profile 401 without Laravel login, registration 403, valid TLS |

The first migration probe through `docker exec` failed because that bypasses the API image entrypoint that loads its database password from the Docker secret. Re-running the migration command with the same secret in that one process succeeded. The first authenticated API probe failed because HTTP Basic and Laravel Bearer both need the `Authorization` header. Scoping the staging gate to the Vue pages resolved it; login/profile/logout were repeated and passed.

## Acceptance and handoff

- Build: Vue production build and VPS API image build passed. The legacy .NET build was not run because its code was not changed.
- Database workflow: fresh PostgreSQL migration and synthetic seed only; no financial posting, number allocation, legacy import, or restore test.
- Print/Crystal: not tested; the legacy application was not deployed.
- Browser/device: no authenticated browser walkthrough or multi-user Reverb check; HTTP and WebSocket protocol probes only.
- Business, provider, accountant, fiscal and cutover acceptance: open. Do not enter real financial data or use staging as the live billing system.
- Deployment limitation: the current service uses `php artisan serve` and manually transferred uncommitted source. Replace with a production PHP runtime, Git or immutable image delivery, scheduled offsite backup/restore, monitoring, and full acceptance before production.
- Pre-existing working-tree changes were preserved. Only this evidence file, `docs/PROGRESS.md`, and the hosting decision note in README were edited locally for this deployment record.
