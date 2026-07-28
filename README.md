# Tapoda Next

Production-shaped Laravel modular monolith for the Tapoda course lifecycle.

## Local runtime

Requirements: Docker Desktop with Compose. Host PHP, Composer, Node, PostgreSQL, and Redis are not used.

```sh
bin/bootstrap-env
docker compose build
docker compose up -d postgres redis
docker compose --profile tools run --rm migrate
docker compose up -d web worker scheduler
bin/smoke http://127.0.0.1:8080
```

Open <http://127.0.0.1:8080>. Stop with `docker compose stop`.

## Checks

```sh
docker compose --profile tools run --rm test
docker run --rm -v "$PWD":/app -w /app node:24-bookworm-slim npm run typecheck
docker run --rm -v "$PWD":/app -w /app node:24-bookworm-slim npm run lint
docker run --rm -v "$PWD":/app -w /app node:24-bookworm-slim npm run build
```

See [platform operations](docs/runbooks/platform-operations.md) for deploy, monitoring, rollback, and restore procedures.
