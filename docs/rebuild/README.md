# Tapoda Rebuild Discovery Pack

**Status:** Initial BA/SA source package for the new Tapoda repository

**Repository baseline:** `uat-20260526` at `3d2c3a4`

**Prepared:** 2026-07-28

This package records the current product before UX/UI redesign or implementation. It separates verified behavior, inferred behavior, dormant artifacts, production unknowns, and proposed target design.

## Documents

- [`../product/tapoda-rebuild-prd.md`](../product/tapoda-rebuild-prd.md) — consolidated product requirements, target workflow, schema, migration, security, and acceptance.
- [`../../CONTEXT.md`](../../CONTEXT.md) — canonical product language.
- [`current-page-inventory.md`](current-page-inventory.md) — every routed page and every Blade/static page artifact, classified and mapped.
- [`current-page-inventory.csv`](current-page-inventory.csv) — machine-readable page register.
- [`current-flow-ledger.md`](current-flow-ledger.md) — current visitor, applicant, staff, reviewer, check-in, report, email, and maintenance flows.
- [`module-blueprint.md`](module-blueprint.md) — current-to-target module ownership and deepening plan.
- [`ci-design-system.md`](ci-design-system.md) — one Corporate Identity and CSS system for web, email, and print.
- [`coverage-matrix.md`](coverage-matrix.md) — page/flow/module/parity reconciliation and unresolved production gates.

The visual architecture report is included as [`architecture-review.html`](architecture-review.html).

## Coverage contract

A current behavior is not considered captured until its ledger entry contains:

1. Stable page or flow ID.
2. Actor and authorization context.
3. Entry route or trigger.
4. Pages, actions, and branch conditions.
5. Reads, writes, state transitions, and external effects.
6. Success, empty, validation, denied, expired, and failure outcomes.
7. Source evidence.
8. Owning target module.
9. Behavior-preserving parity proof or approved retirement gate.

## Evidence status

| Status | Meaning |
|---|---|
| `verified-code` | Directly supported by route, controller, view, configuration, command, or test evidence |
| `verified-artifact` | File exists but no active route/use was proven |
| `inferred` | Strongly implied by several code paths; runtime confirmation required |
| `dormant` | Implementation exists without an active route or required dependency |
| `unknown-production` | Repository cannot prove deployed schema, data, traffic, or operator behavior |
| `candidate-retirement` | No active use proven; requires telemetry, owner, retention, and rollback decision |

## Repository coverage baseline

- 109 concrete source route declarations: 99 web and 10 API. Runtime availability is conditional: `W099` is registered only in local environment, `W010` returns 404 outside local, and `W057` targets a missing action. One additional `/signin` declaration is commented.
- 91 Blade files plus one view README.
- 8 root static HTML prototypes and two root PHP entry artifacts under `public/`.
- 12 user-facing PDF assets.
- 22 controllers, including one unmounted `NewFlow` controller.
- 21 models.
- 21 SQL files: 19 change/runbook scripts under `db_scripts` plus root `country.sql` and `thailand.sql` reference datasets.
- 12 test-support PHP files: 10 `*Test.php` cases plus `TestCase.php` and `CreatesApplication.php`.
- Repository migrations do not recreate the deployed database.

Counts are repository facts, not production-usage proof.

## Change-control rules

- Preserve this pack uncommitted until copied into the new Tapoda repository.
- Do not delete a page, route, email, PDF, report, scheduled flow, or compatibility link from absence of code references alone.
- Record production contradictions in `coverage-matrix.md`.
- Update the page register and flow ledger in the same change as any scope decision.
- Use canonical terms from `CONTEXT.md`.
- Use module/interface/implementation/depth/deep/shallow/seam/adapter/leverage/locality for architecture.
- Treat the approved PRD and decision gates as the implementation source of truth.
