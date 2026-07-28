# Tapoda Next

Production-shaped Laravel modular monolith for the Tapoda course lifecycle.

## Local runtime

Requirements: Docker Desktop with Compose. Host PHP, Composer, Node, PostgreSQL, and Redis are not used.

```sh
bin/bootstrap-env
export APP_BUILD_VERSION=local-reviewed
export APP_BUILD_COMMIT="$(git rev-parse HEAD)"
bin/build-artifact "$APP_BUILD_VERSION" "$APP_BUILD_COMMIT"
export TAPODA_APP_IMAGE="$(docker image inspect --format '{{.Id}}' "tapoda-next:${APP_BUILD_VERSION}")"
docker compose up -d --wait --wait-timeout 60 postgres redis
docker compose --profile tools up --no-deps --abort-on-container-exit --exit-code-from migrate migrate
docker compose up -d web worker scheduler
bin/assert-runtime-artifact "$APP_BUILD_VERSION" "$APP_BUILD_COMMIT" migrate web worker scheduler
SMOKE_OVERALL_TIMEOUT_SECONDS=60 \
SMOKE_CONNECT_TIMEOUT_SECONDS=2 \
SMOKE_REQUEST_TIMEOUT_SECONDS=5 \
SMOKE_RETRY_INTERVAL_SECONDS=1 \
bin/smoke http://127.0.0.1:8080
```

The smoke gate polls liveness, readiness, and the Thai home page under one 60-second deadline. Every request has a two-second connection timeout and five-second response timeout. Failure reports the endpoint, attempts, HTTP/curl result, and response size without printing response content. Open <http://127.0.0.1:8080>. Stop with `docker compose stop`.

## Checks

```sh
docker compose --profile tools run --rm test
docker run --rm -v "$PWD":/app -w /app node:24-bookworm-slim@sha256:6f7b03f7c2c8e2e784dcf9295400527b9b1270fd37b7e9a7285cf83b6951452d npm run typecheck
docker run --rm -v "$PWD":/app -w /app node:24-bookworm-slim@sha256:6f7b03f7c2c8e2e784dcf9295400527b9b1270fd37b7e9a7285cf83b6951452d npm run lint
docker run --rm -v "$PWD":/app -w /app node:24-bookworm-slim@sha256:6f7b03f7c2c8e2e784dcf9295400527b9b1270fd37b7e9a7285cf83b6951452d npm run build
```

See [platform operations](docs/runbooks/platform-operations.md) for deploy, monitoring, rollback, and restore procedures.
