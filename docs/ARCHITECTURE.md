# Billing Foundation Architecture

```text
Browser -> Nginx -> frontend/dist (React static assets)
                 -> Laravel /api/v1 (PHP-FPM)
                     -> MySQL (authoritative data)
                     -> Redis (queue/cache/session readiness)
```

The API resolves the active organization from authenticated membership. Controllers query tenant-owned models through that organization, and policies/validation reject cross-tenant identifiers. The frontend does not choose an arbitrary tenant database or bypass API authorization.

The billing model keeps customer identity, billing accounts, subscriber services, subscriptions, invoices, payments, allocations, credits, adjustments, and ledger transactions separate. Future network automation will integrate through service contracts rather than vendor logic embedded in billing controllers.
