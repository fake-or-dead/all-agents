# Audited platform path

`PlatformOperations` owns probe transaction state and its outbox. `Audit` owns append-only audit events. `IdentityAccess` resolves an actor and capability. HTTP and queue classes are adapters.

```text
POST /platform/probes
  -> IdentityAccess ActorResolver
  -> PlatformProbes.request()
       -> one database transaction
          -> platform_probe_runs
          -> AuditLog.append()
          -> outbox_events
  -> scheduler relay
  -> Horizon ProcessPlatformProbe
  -> CompletionAdapter
  -> completion transaction + audit + worker heartbeat
  -> GET /platform/probes/{id}
```

Public module surface:

- `PlatformProbes`: request and observe.
- `AuditLog`: append.
- `ActorResolver`: resolve the authenticated actor.
- `CompletionAdapter`: one production adapter and one deterministic fake.

Persistence, retry state, structured logging, framework jobs, and health mechanics stay behind those interfaces. Tests drive the path through HTTP and the relay command, then observe it through HTTP.
