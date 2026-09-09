# ISP Billing Database Design

This document records the approved first-release billing schema from `MASTER.md`.

## Principles

- MySQL is the authoritative persistent store.
- Every tenant-owned table has `organization_id`.
- Internal joins use `BIGINT UNSIGNED`; public API resources expose ULIDs.
- Money is stored as integer minor units with an ISO-4217 currency code.
- Financial records are not hard-deleted.
- Invoices, payments, allocations, credits, adjustments, and ledger entries are transaction-protected.
- Tenant context is resolved server-side and is never accepted as trusted frontend input.

## Relationship map

```text
Organization -> Users, Customers, Plans, BillingCycles, AuditLogs
Customer -> BillingAccounts, SubscriberServices
BillingAccount -> SubscriberServices, Subscriptions, Invoices, Payments, Credits, Adjustments, BalanceTransactions
Plan -> PlanVersions -> Subscriptions
SubscriberService -> Subscriptions -> InvoiceItems
Invoice -> InvoiceItems, PaymentAllocations, Adjustments, BalanceTransactions
Payment -> PaymentAllocations, BalanceTransactions
```

## Core tables

| Table | Primary key | Important foreign keys | Purpose |
|---|---|---|---|
| organizations | id | — | ISP tenant root |
| users | id | — | Authentication identity |
| organization_user | organization_id + user_id | organization_id, user_id | Tenant membership |
| roles | id | organization_id | Tenant-scoped roles |
| permissions | id | — | Permission catalog |
| role_assignments | id | organization_id, user_id, role_id | User permissions |
| role_permissions | role_id + permission_id | role_id, permission_id | Role permissions |
| customers | id | organization_id | Customer identity |
| billing_accounts | id | organization_id, customer_id | Account receivable boundary |
| billing_cycles | id | organization_id | Recurring billing schedule |
| plans | id | organization_id, billing_cycle_id | Product catalog |
| plan_versions | id | organization_id, plan_id | Historical plan pricing |
| subscriber_services | id | organization_id, customer_id, billing_account_id | Service delivered |
| subscriptions | id | organization_id, subscriber_service_id, billing_account_id, plan_version_id | Service-plan contract |
| invoices | id | organization_id, billing_account_id | Financial charge document |
| invoice_items | id | organization_id, invoice_id, subscription_id | Invoice lines |
| payments | id | organization_id, billing_account_id | Received money |
| payment_allocations | id | organization_id, payment_id, invoice_id | Payment-to-invoice allocation |
| credits | id | organization_id, billing_account_id | Available account credit |
| adjustments | id | organization_id, billing_account_id, invoice_id | Audited corrections |
| balance_transactions | id | organization_id, billing_account_id, invoice/payment/credit/adjustment | Append-only ledger |
| audit_logs | id | organization_id, actor_user_id | Security and business audit |

## Integrity and performance

Tenant-aware unique keys include `organization_id`, such as `(organization_id, customer_number)` and `(organization_id, invoice_number)`. Tenant-aware child relationships validate the parent organization in application policies and composite database constraints where practical. Indexes begin with `organization_id` followed by the fields used for status, search, date, and pagination filters.

