# SDD ledger — plan: docs/superpowers/plans/2026-09-13-router-real-device-communication-plan.md

## Setup

- Worktree: `/var/www/html/.worktrees/router-real-device-communication`
- Branch: `feat/router-real-device-communication`
- Starting commit: `1410df3`
- Existing main checkout is dirty with unrelated user changes; this worktree is clean.
- Baseline dependency check: `backend/vendor/autoload.php` and `frontend/node_modules/.bin/vitest` are absent in this worktree; baseline suites cannot run until dependencies are installed or made available.

## Plan scan

| Scope | Pair / task | Shared interface or file | Finding | Ruling |
|---|---|---|---|---|
| Pair | Task 1 -> Task 4 | Python contracts and transport/session interfaces | Task 1 produces the request/result/device models that Task 4 consumes. | Keep Pydantic contracts stable; Task 4 adapts external libraries behind them. |
| Pair | Task 1 -> Task 5 | Python operation/result contracts | Task 5 consumes operation literals and normalized results. | Drivers may add capability values but may not change generic operation names without updating the spec. |
| Pair | Task 2 -> Task 3 | RouterCredential and RouterOperation models | Task 3 depends on Task 2 persistence and encrypted casts. | Complete migrations/models before API validation work. |
| Pair | Task 3 -> Task 6 | Credential profiles and router transport validation | Task 6 must load the primary profile and dispatch only validated transport values. | Task 6 treats Task 3 as the source of truth; no duplicated secret validation in controllers. |
| Pair | Task 5 -> Task 6 | Driver IDs, transport IDs, capability names, normalized results | Laravel sends identifiers and consumes normalized results. | Laravel must not contain vendor command branches; Python owns driver behavior. |
| Pair | Task 6 -> Task 8 | Operation endpoints and capability/permission responses | React consumes operation lifecycle and normalized data. | Preserve existing connection-test/system-info compatibility routes while adding generic operation APIs. |
| Pair | Task 7 -> Task 6 | Router operation permissions | Task 6 routes need permission names provisioned by Task 7. | Implement route middleware using exact names from Task 7; run migrations/seeders before API verification. |
| Pair | Task 8 -> Task 3 | Nested credential profile request shape | React must submit the transport-specific credential payload accepted by Laravel. | Keep secrets write-only on edit; metadata is the only credential response. |
| Pair | Task 9 -> Task 1/4/5 | Python settings and service startup | Deployment settings must match gateway/session/transport modules. | Document actual environment names after implementation; no credentials in service env defaults. |
| Pair | Task 10 -> all tasks | Test commands and security invariants | Full verification depends on all earlier deliverables. | Do not claim live-device readiness without opt-in lab smoke-test evidence. |
| Own | Task 1 | Contract tests vs created files | Tests reference package models and app that the task creates. | Self-consistent. |
| Own | Task 2 | Migration/model tests vs persistence files | Tests reference two migrations and two models created by the task. | Self-consistent. |
| Own | Task 3 | API tests vs request/service/controller files | Tests exercise transport validation and metadata-only responses produced by the task. | Self-consistent. |
| Own | Task 4 | Session/transport tests vs registries/adapters | Tests use fake sessions and adapters while production adapters remain isolated. | Self-consistent. |
| Own | Task 5 | Driver fixture tests vs driver/parser files | Tests consume identifiers and normalized data produced by the task. | Self-consistent. |
| Own | Task 6 | Client/job/API tests vs routes/services | Tests exercise the exact gateway and operation lifecycle interfaces listed. | Self-consistent. |
| Own | Task 7 | Permission tests vs seeder/migration | Tests assert names created by the task. | Self-consistent. |
| Own | Task 8 | React tests vs credential/operation components | Tests exercise dynamic transport fields and capability-gated actions. | Self-consistent. |
| Own | Task 9 | Configuration tests vs settings/container/docs | Tests assert the settings defaults and deployment boundary created by the task. | Self-consistent. |
| Own | Task 10 | Verification commands vs all deliverables | Commands cover Python, Laravel, frontend, lint, and hygiene. | Self-consistent. |

## Rulings

- Ruling: Execute in task order because the Python contracts, database models, and API payloads are load-bearing interfaces — this costs some parallelism but prevents incompatible schemas.
- Ruling: Keep `mock` out of new Router validation and migrate existing mock transport rows to `api` — this may require credentials to be added to old records, but prevents the UI from presenting a non-live transport.
- Ruling: Use a local in-memory session pool per Python service process — this preserves credential/session safety and means a service restart reconnects on demand.

## Task status

- Task 1: complete (commits d1c4ff6, 7013364, 527fca4, ec11e89; accepted with TestClient/AnyIO environment concern)
- Task 2: complete (commits 156070c, 3c3615b, dd3ee2c, 0c1846f; accepted after redaction fix, PHPUnit environment limitation documented)
- Task 3: complete (commits a4ed81f, 4602263, 41f156d; accepted after resource, migration, and URL-safety fixes; live MySQL unavailable)
- Task 4: complete (commits d49defb, 9836ae3; 20 focused transport/session tests passed; review findings fixed)
- Task 6: complete (implementation added; focused Laravel checks passed; existing synchronous RouterApi expectations need migration)
- Task 5: complete (commit 8da9690; 48 focused driver tests and compileall passed; no dependencies installed)
- Task 6: complete (commit c42ef6b plus operation-dispatch hardening; 12 focused tests and 39 assertions passed)
- Task 7: complete (commit 6a7a084; 16 focused tests and 59 assertions passed; touched PHP lint clean; permission migration uses 000026 because 000024 was already occupied)
- Task 8: pending
- Task 9: pending
- Task 10: pending
