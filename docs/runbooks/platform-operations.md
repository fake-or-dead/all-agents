# Platform operations runbook

## Artifact and release identity

Build once from a clean, reviewed commit:

```sh
bin/build-artifact 2026.07.29.1 FULL_GIT_COMMIT
```

Record the image digest emitted by `docker image inspect`. Promote that exact digest to migration, web, worker, and scheduler processes. Do not rebuild per environment. Inject secrets and environment-specific adapter names at runtime.

Required production adapters:

- `PLATFORM_ACTOR_ADAPTER=laravel-auth`
- `PLATFORM_COMPLETION_ADAPTER=structured-log` until a real provider contract is approved

`deterministic-fake` is for CI, local operation, and rehearsals. The fake and structured-log adapters implement the same idempotent correlation-ID contract. Neither embeds provider behavior in `PlatformOperations`.

## Deployment

1. Back up PostgreSQL and record the backup identifier.
2. Run `php artisan migrate --pretend` with the candidate image. Review SQL for backward compatibility.
3. Run `php artisan migrate --force` once with the candidate image.
4. Start web, Horizon worker, and scheduler from the same image digest.
5. Wait for `/health/ready` to report database, Redis, worker, scheduler, and migration status `ok`.
6. Run `bin/smoke https://TARGET_HOST`.
7. Record image digest, migration batch, deployment time, and smoke result.

Do not serve traffic from the migration process. Liveness proves the process can answer; readiness proves dependencies and operational heartbeats are current.

## Monitoring

Alert on:

- `/health/live` non-200;
- `/health/ready` non-200 or any stale component;
- Horizon queue wait or failed-job growth;
- unprocessed `outbox_events`, increasing attempts, or oldest-event age;
- missing scheduler/worker heartbeat for more than `PLATFORM_HEARTBEAT_MAX_AGE`;
- application exceptions grouped by deployment and correlation ID.

Health payloads contain statuses and truncated build identity only. Logs must use resource/correlation IDs, never personal data, credentials, DSNs, or connection hosts.

## Rollback and forward recovery

Before any incompatible write, route traffic back to the previously recorded image digest and rerun smoke checks. Do not automatically reverse a database migration. Migrations use expand/contract; stop and forward-fix when target writes have occurred. Keep the old application compatible through the rollback window.

Outbox delivery is at least once. Worker completion is idempotent by correlation ID. A crash can cause a repeated adapter call; adapters must return one effective result.

## Backup and restore drill

Create a custom-format backup without writing credentials to the command line:

```sh
docker compose exec -T postgres pg_dump --format=custom --no-owner --username=tapoda tapoda > tapoda.dump
```

Restore only into an isolated empty PostgreSQL instance:

```sh
createdb tapoda_restore
pg_restore --exit-on-error --no-owner --dbname=tapoda_restore tapoda.dump
```

Run migrations, readiness, smoke, row-count/checksum reconciliation, and the audited probe test against the restored instance. Record elapsed time as simulated evidence only. This does not establish production RPO/RTO.

## Production exclusions

Issue 09 does not approve or verify: cloud region/residency, managed PostgreSQL/Redis topology, TLS/DNS, WAF/CDN, secret manager, encryption/key ownership, automated backups, real restore timing, high availability, capacity/cost, real provider delivery, Account persistence/sign-in, production monitoring ownership, operational staffing, or production cutover. The Compose topology is single-host development infrastructure.
