# Platform operations runbook

## Artifact and release identity

Build once from a clean, reviewed commit:

```sh
export APP_BUILD_VERSION=2026.07.29.1
export APP_BUILD_COMMIT=FULL_GIT_COMMIT
bin/build-artifact "$APP_BUILD_VERSION" "$APP_BUILD_COMMIT"
export TAPODA_APP_IMAGE="$(docker image inspect --format '{{.Id}}' "tapoda-next:${APP_BUILD_VERSION}")"
```

Record the content-addressed image ID emitted by `docker image inspect`. Keep `TAPODA_APP_IMAGE`, `APP_BUILD_VERSION`, and `APP_BUILD_COMMIT` exported for every Compose command in the deployment or restore session. Promote that exact image ID to migration, web, worker, and scheduler processes. Do not rebuild per environment. Inject secrets and environment-specific adapter names at runtime.

The platform maintainer owns container-input refreshes. Resolve the Dockerfile syntax frontend and each declared version tag to a reviewed digest in a dedicated dependency PR, update `Dockerfile`, `compose.yaml`, `bin/bootstrap-env`, and documented tool commands together, then rerun clean test/runtime/Compose builds, audits, browser checks, artifact identity, and smoke before approval. Never refresh a digest during deployment.

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

The local immutable deployment rehearsal uses the same exported artifact for every process:

```sh
docker compose up --detach --wait --wait-timeout 60 postgres redis
docker compose --profile tools up --no-deps --abort-on-container-exit --exit-code-from migrate migrate
docker compose up --detach web worker scheduler
bin/assert-runtime-artifact "$APP_BUILD_VERSION" "$APP_BUILD_COMMIT" migrate web worker scheduler
bin/smoke http://127.0.0.1:8080
```

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

Structured-log completion reserves a durable `pending` receipt before the effect and marks it `delivered` only after the logger returns successfully. A failed effect leaves the receipt pending for retry. A process loss after the effect but before the delivered-state commit can repeat the effect, so every provider/log consumer must deduplicate the `delivery_key` correlation ID. The database receipt never converts a failed effect into success.

## Backup and restore drill

Use the reviewed artifact exports from **Artifact and release identity**. Compose refuses to start application services without `TAPODA_APP_IMAGE`.

Seed one pre-backup marker entirely through the source PostgreSQL container:

```sh
export RESTORE_MARKER_ID=00000000-0000-4000-8000-000000000909
docker compose exec -T postgres psql --username=tapoda --dbname=tapoda --command="INSERT INTO audit_events (id, actor_type, actor_id, action, resource_type, resource_id, outcome, correlation_id, context, occurred_at) VALUES ('${RESTORE_MARKER_ID}', 'restore-marker', 'pre-backup', 'platform.restore.marker', 'platform_restore', '00000000-0000-4000-8000-000000000910', 'recorded', '00000000-0000-4000-8000-000000000911', '{\"purpose\":\"non-destructive-restore-check\"}', CURRENT_TIMESTAMP) ON CONFLICT (id) DO NOTHING"
```

Create a custom-format backup without writing credentials to the command line:

```sh
docker compose exec -T postgres pg_dump --format=custom --no-owner --username=tapoda tapoda > tapoda.dump
```

Restore only into an isolated empty PostgreSQL instance:

```sh
docker compose --profile restore up --detach --wait --wait-timeout 60 --force-recreate postgres-restore redis-restore
docker compose --profile restore exec -T postgres-restore pg_restore --exit-on-error --no-owner --username=tapoda --dbname=tapoda_restore < tapoda.dump
docker compose --profile restore up --no-deps --abort-on-container-exit --exit-code-from restore-migrate restore-migrate
```

Reconcile the source and restored data before starting processes that write heartbeats. Both database commands execute inside their local PostgreSQL containers:

```sh
docker compose exec -T postgres sh -c 'for table in platform_probe_runs audit_events outbox_events platform_completion_receipts; do printf "%s\n" "$table"; psql --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --command="COPY (SELECT row_to_json(t)::text AS row FROM public.$table AS t ORDER BY row) TO STDOUT"; done | sha256sum'
docker compose --profile restore exec -T postgres-restore sh -c 'for table in platform_probe_runs audit_events outbox_events platform_completion_receipts; do printf "%s\n" "$table"; psql --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --command="COPY (SELECT row_to_json(t)::text AS row FROM public.$table AS t ORDER BY row) TO STDOUT"; done | sha256sum'
```

The digests must match. Confirm the pre-backup marker exists, start only the exact reviewed web artifact, and run the non-destructive verifier. It adds one uniquely correlated probe, two audit events, one processed outbox event, and the adapter-specific completion receipt delta without resetting the schema or deleting existing rows:

```sh
docker compose --profile restore exec -T postgres-restore psql --username=tapoda --dbname=tapoda_restore --tuples-only --no-align --command="SELECT count(*) FROM audit_events WHERE id = '${RESTORE_MARKER_ID}'"
docker compose --profile restore up --detach restore-web
bin/assert-runtime-artifact "$APP_BUILD_VERSION" "$APP_BUILD_COMMIT" restore-migrate restore-web
docker compose --profile restore exec -T restore-web php artisan platform:verify-restored-audited-path --marker-id="$RESTORE_MARKER_ID"
docker compose --profile restore exec -T postgres-restore psql --username=tapoda --dbname=tapoda_restore --tuples-only --no-align --command="SELECT count(*) FROM audit_events WHERE id = '${RESTORE_MARKER_ID}'"
```

Both marker queries must return `1`. Then start the remaining exact reviewed processes, assert every container image ID and revision label, and smoke the ready topology:

```sh
docker compose --profile restore up --detach restore-worker restore-scheduler
bin/assert-runtime-artifact "$APP_BUILD_VERSION" "$APP_BUILD_COMMIT" restore-migrate restore-web restore-worker restore-scheduler
bin/smoke http://127.0.0.1:18080
```

Record artifact assertions, restore, migration, checksum, marker preservation, audited-path deltas, readiness, smoke, and elapsed-time results as simulated evidence only. Remove the ephemeral restore topology with:

```sh
docker compose --profile restore stop postgres-restore redis-restore restore-migrate restore-web restore-worker restore-scheduler
docker compose --profile restore rm --force postgres-restore redis-restore restore-migrate restore-web restore-worker restore-scheduler
```

Its PostgreSQL data is tmpfs-backed and disappears with the removed container. The restore Redis/Horizon namespace is isolated so its worker heartbeat cannot be consumed by the primary local stack. This does not establish production RPO/RTO.

## Production exclusions

Issue 09 does not approve or verify: cloud region/residency, managed PostgreSQL/Redis topology, TLS/DNS, WAF/CDN, secret manager, encryption/key ownership, automated backups, real restore timing, high availability, capacity/cost, real provider delivery, Account persistence/sign-in, production monitoring ownership, operational staffing, or production cutover. The Compose topology is single-host development infrastructure.
