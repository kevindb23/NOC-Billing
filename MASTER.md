Yes. I would update the master prompt in four important ways from the previous version:

* Use the **simpler module-based folder structure** you approved.
* Make **multi-tenancy + subdomain isolation** foundational from day one.
* Explicitly require the **Superpowers workflow** for Codex.
* Make the **anti-AI-slop design rules mandatory**, not suggestions.

Use this as the master prompt you give Codex:

```text
MASTER CODEX BUILD PROMPT
ISP-IN-A-BOX — MULTI-TENANT ISP BILLING & OPERATIONS PORTAL
============================================================

ROLE
====

You are the principal software architect and senior full-stack engineer
responsible for building a production-grade ISP Billing and Operations
Portal named:

    ISP-in-a-Box

This is a REAL ISP operational platform intended for production use.

It is NOT:

- a tutorial
- a proof of concept
- a static dashboard
- a UI mockup
- a generic CRUD application
- a generic invoicing application
- a generic SaaS template

The system will eventually become the central operational portal used by:

- ISP administrators
- NOC engineers
- billing personnel
- customer support
- network engineers
- field technicians
- management

The platform must be designed from the beginning to support:

    10,000+ subscribers per ISP

and multiple ISP organizations/clients.

The application must remain understandable and maintainable as it grows.


======================================================================
1. SUPERPOWERS SKILLS ARE REQUIRED
======================================================================

This project uses the Superpowers skills for Codex.

You MUST use the appropriate Superpowers skill before performing
development work.

Do NOT ignore the Superpowers workflow just because a task appears easy.

Use the installed Superpowers skills according to their own instructions.

Important skills include:

    using-superpowers

    brainstorming

    writing-plans

    test-driven-development

    systematic-debugging

    verification-before-completion

    requesting-code-review

    receiving-code-review

    executing-plans

    using-git-worktrees

    subagent-driven-development

    dispatching-parallel-agents

    finishing-a-development-branch


The skill instructions take precedence over ad-hoc coding behavior.

For new features or architectural work:

    REQUEST
       |
       v
    BRAINSTORM
       |
       v
    DESIGN
       |
       v
    HUMAN APPROVAL
       |
       v
    IMPLEMENTATION PLAN
       |
       v
    TDD
       |
       v
    IMPLEMENTATION
       |
       v
    VERIFICATION
       |
       v
    CODE REVIEW
       |
       v
    COMPLETE


Do NOT immediately start generating code after receiving a large feature
request.

Do NOT skip design approval where the Superpowers workflow requires it.

Do NOT claim something is complete before verification.


======================================================================
2. CURRENT PROJECT SCOPE
======================================================================

Build ONLY the:

    ISP BILLING & OPERATIONS PORTAL

during this stage.

The portal includes:

- Authentication
- Users
- Roles
- Permissions
- Organizations / Tenants
- Customers
- Billing Accounts
- Subscribers
- Service Plans
- Subscriptions
- Invoices
- Payments
- Credits
- Adjustments
- Network Inventory
- Sites
- POPs
- OLT Inventory
- PON Inventory
- ONU Inventory
- BNG Inventory
- PPPoE information models
- IPAM
- VLAN management
- ACS/CPE information models
- Monitoring views/models
- Alarms
- Provisioning job models
- Audit logs
- Settings

DO NOT build the actual network automation engine yet.

DO NOT implement:

- Huawei CLI automation
- ZTE CLI automation
- VSOL automation
- FiberHome automation
- OLT SSH scraping
- SNMP polling engine
- Node.js OLT drivers
- Accel-PPP control
- GenieACS control
- FRR control
- iptables control
- nftables control

However, prepare clean contracts and models so these systems can be
integrated later.


======================================================================
3. REQUIRED TECHNOLOGY STACK
======================================================================

BACKEND:

    Laravel
    PHP
    MySQL
    Redis


FRONTEND:

    React
    TypeScript
    Vite
    Tailwind CSS


DO NOT USE ANGULAR.

DO NOT introduce another frontend framework.


SERVER:

    Linux
    Nginx
    PHP-FPM


DEPLOYMENT:

    Dell PowerEdge R740
        |
        v
    Proxmox
        |
        v
    Linux VM


DO NOT USE DOCKER.


======================================================================
4. DEPLOYMENT / INTERNET ACCESS MODEL
======================================================================

The application will run on-premises on the Dell R740 / Proxmox
environment.

The portal must be accessible through the Internet using HTTPS and
domain names.

The intended architecture is:

                        INTERNET
                           |
                           v
                  Public Edge / Tunnel
                           |
                           v
                       NGINX
                           |
                +----------+----------+
                |                     |
                v                     v
              React                Laravel
                                      |
                               +------+------+
                               |             |
                               v             v
                             MySQL         Redis


The application must NOT assume that the Laravel server itself has a
directly exposed public IP.

It must work correctly behind:

- reverse proxy
- HTTPS termination
- secure tunnel/proxy
- trusted proxy infrastructure


Do not hard-code protocol, hostnames, or public IP addresses.


======================================================================
5. MULTI-TENANCY IS A CORE REQUIREMENT
======================================================================

ISP-in-a-Box is a MULTI-TENANT application.

This is NOT something to add later.

Tenant isolation must be designed from the beginning.

Each ISP/customer organization is a tenant.

Examples:

    companya.billing.com

    companyb.billing.com

    companyc.billing.com


The hostname/subdomain identifies the tenant.

Example:

    companya.billing.com
             |
             v
       ResolveTenant
             |
             v
        Company A
             |
             v
      organization_id = 1


Another request:

    companyb.billing.com
             |
             v
       ResolveTenant
             |
             v
        Company B
             |
             v
      organization_id = 2


Company A must NEVER see Company B's data.


======================================================================
6. TENANT ROOT
======================================================================

Organization is the root tenant entity.

Conceptually:

    Organization
    |
    +-- Users
    |
    +-- Customers
    |    |
    |    +-- Billing Accounts
    |    |
    |    +-- Subscriber Services
    |
    +-- Sites
    |    |
    |    +-- POPs
    |         |
    |         +-- OLTs
    |         +-- BNGs
    |
    +-- ONUs
    |
    +-- IP Pools
    |
    +-- VLANs
    |
    +-- Invoices
    |
    +-- Payments
    |
    +-- ACS/CPE
    |
    +-- Alarms
    |
    +-- Provisioning
    |
    +-- Audit Logs
    |
    +-- Settings


Tenant ownership must be explicit.


======================================================================
7. TENANT DATABASE STRATEGY
======================================================================

Initially use:

    ONE APPLICATION

    ONE MYSQL DATABASE

    SHARED SCHEMA

    STRICT organization_id ISOLATION


Do NOT create one application installation per ISP.

Do NOT create separate source-code copies for:

    Company A
    Company B
    Company C


Tenant-owned tables should include organization_id where appropriate.

Examples:

    customers.organization_id

    billing_accounts.organization_id

    subscriber_services.organization_id

    invoices.organization_id

    payments.organization_id

    sites.organization_id

    pops.organization_id

    olts.organization_id

    onus.organization_id

    bngs.organization_id

    ip_pools.organization_id

    vlans.organization_id

    alarms.organization_id

    provisioning_jobs.organization_id


Use database constraints and application-level protection.

Do not rely only on React hiding data.


======================================================================
8. TENANT RESOLUTION
======================================================================

Implement centralized tenant resolution.

Conceptually:

    HTTP Request

        companya.billing.com

              |
              v

        ResolveTenant Middleware

              |
              v

        Organization lookup

              |
              v

        Tenant Context

              |
              v

        Authentication

              |
              v

        Authorization

              |
              v

        Tenant-scoped application


Do NOT manually parse and resolve tenants inside every controller.

Create one reliable tenant resolution mechanism.


======================================================================
9. TENANT SECURITY
======================================================================

Cross-tenant data leakage is a CRITICAL SECURITY FAILURE.

Protect against:

- changing URL IDs
- changing UUIDs
- manipulated API requests
- search endpoints
- exports
- reports
- background jobs
- queued jobs
- cached results
- audit queries
- network inventory queries


Example:

Company A requesting:

    /api/v1/subscribers/{company_b_uuid}

must return an appropriate not-found/unauthorized result.

It must NEVER return Company B data.


Tenant isolation must exist in backend logic.

Frontend tenant isolation is NOT sufficient.


======================================================================
10. PLATFORM ADMIN
======================================================================

Prepare for a separate platform administration hostname.

Example:

    admin.billing.com


This is for the owner/operator of ISP-in-a-Box.

Tenant portals:

    companya.billing.com
    companyb.billing.com


Platform administration:

    admin.billing.com


Platform administrators may eventually manage:

- organizations
- organization status
- tenant administrators
- subscription/license status
- system health
- deployment information


Tenant administrators manage only their own ISP.

Do not confuse:

    PLATFORM ADMIN

with:

    TENANT ADMIN.


======================================================================
11. HIGH-LEVEL APPLICATION ARCHITECTURE
======================================================================

                  ISP STAFF
                     |
                     v
              +-------------+
              |    React    |
              | Admin/NOC   |
              |   Portal    |
              +------+------+
                     |
                  REST API
                     |
                     v
              +-------------+
              |   Laravel   |
              |  Core API   |
              +------+------+
                     |
              +------+------+
              |             |
              v             v
            MySQL         Redis
          Persistent      Cache
           Source         Queue
          of Truth        Locks


Future integrations:

                    Laravel
                       |
          +------------+------------+
          |            |            |
          v            v            v
       Node.js     FreeRADIUS    GenieACS
       Network        AAA          ACS
      Automation
          |
          v
     OLT / BNG /
      NETWORK


Laravel determines:

    WHAT should happen.


Future Network Automation determines:

    HOW it happens.


======================================================================
12. MOST IMPORTANT NETWORK ARCHITECTURE RULE
======================================================================

NEVER place vendor-specific network commands inside the Billing Portal.

Laravel may determine:

    Activate subscriber.


Laravel must NOT contain:

    Huawei CLI
    ZTE CLI
    VSOL CLI
    SSH command sequences
    vendor parsers


Correct future architecture:

    React
       |
       v
    Laravel
       |
       v
    Subscriber Service
       |
       v
    Desired State
       |
       v
    Provisioning Job
       |
       v
    NetworkAutomationClient
       |
       v
    Node.js Network Automation Engine
       |
       v
    Vendor Driver
       |
       v
    Network


Incorrect:

    SubscriberController
       |
       v
    SSH directly to Huawei OLT


======================================================================
13. SOURCE OF TRUTH
======================================================================

MySQL is the authoritative persistent source of truth.

MySQL owns:

- organizations
- users
- customers
- billing accounts
- subscribers
- plans
- subscriptions
- invoices
- payments
- network assignments
- IP assignments
- VLAN assignments
- inventory
- desired service state
- provisioning history
- alarms
- audit history


Redis is NOT the source of truth.

Redis is used for:

- caching
- queues
- distributed locks
- rate limiting
- temporary state
- pub/sub
- job progress
- future realtime coordination


The application must not lose authoritative business information if
Redis is cleared.


======================================================================
14. SIMPLE PROJECT STRUCTURE
======================================================================

IMPORTANT:

Keep the project structure SIMPLE and EASY TO UNDERSTAND.

Do NOT create unnecessary architectural layers.

Do NOT spread one feature across many abstract directories.

Use a feature/module-oriented architecture.


Create:

isp-in-a-box/
|
+-- backend/
|   |
|   +-- app/
|   |   |
|   |   +-- Modules/
|   |   |   |
|   |   |   +-- Auth/
|   |   |   +-- Organizations/
|   |   |   +-- Dashboard/
|   |   |   +-- Customers/
|   |   |   +-- Subscribers/
|   |   |   +-- Billing/
|   |   |   +-- Plans/
|   |   |   +-- Payments/
|   |   |   +-- Network/
|   |   |   +-- OLT/
|   |   |   +-- ONU/
|   |   |   +-- BNG/
|   |   |   +-- IPAM/
|   |   |   +-- VLAN/
|   |   |   +-- ACS/
|   |   |   +-- Monitoring/
|   |   |   +-- Alarms/
|   |   |   +-- Provisioning/
|   |   |   +-- Users/
|   |   |   +-- Roles/
|   |   |   +-- Audit/
|   |   |   +-- Settings/
|   |   |
|   |   +-- Jobs/
|   |   +-- Events/
|   |   +-- Listeners/
|   |   +-- Providers/
|   |
|   +-- database/
|   |   +-- migrations/
|   |   +-- factories/
|   |   +-- seeders/
|   |
|   +-- routes/
|   |   +-- api.php
|   |   +-- web.php
|   |
|   +-- tests/
|   |
|   +-- composer.json
|
|
+-- frontend/
|   |
|   +-- src/
|   |   |
|   |   +-- modules/
|   |   |   +-- auth/
|   |   |   +-- organizations/
|   |   |   +-- dashboard/
|   |   |   +-- customers/
|   |   |   +-- subscribers/
|   |   |   +-- billing/
|   |   |   +-- plans/
|   |   |   +-- payments/
|   |   |   +-- network/
|   |   |   +-- olt/
|   |   |   +-- onu/
|   |   |   +-- bng/
|   |   |   +-- sessions/
|   |   |   +-- ipam/
|   |   |   +-- vlan/
|   |   |   +-- acs/
|   |   |   +-- monitoring/
|   |   |   +-- alarms/
|   |   |   +-- provisioning/
|   |   |   +-- users/
|   |   |   +-- roles/
|   |   |   +-- audit/
|   |   |   +-- settings/
|   |   |
|   |   +-- components/
|   |   |   +-- ui/
|   |   |   +-- data/
|   |   |   +-- layout/
|   |   |
|   |   +-- layouts/
|   |   +-- hooks/
|   |   +-- api/
|   |   +-- types/
|   |   +-- utils/
|   |   +-- routes/
|   |   +-- App.tsx
|   |   +-- main.tsx
|   |
|   +-- package.json
|   +-- vite.config.ts
|
|
+-- scripts/
|   +-- install.sh
|   +-- update.sh
|   +-- backup.sh
|   +-- restore.sh
|   +-- health.sh
|
+-- docs/
|   +-- ARCHITECTURE.md
|   +-- DESIGN_SYSTEM.md
|   +-- DATABASE.md
|   +-- MULTI_TENANCY.md
|   +-- SECURITY.md
|
+-- AGENTS.md
|
+-- README.md


======================================================================
15. SIMPLE BACKEND MODULE STRUCTURE
======================================================================

Each Laravel module should remain easy to understand.

Example:

backend/app/Modules/Subscribers/

    Controllers/
        SubscriberController.php

    Models/
        Subscriber.php

    Services/
        CreateSubscriberService.php
        UpdateSubscriberService.php
        ActivateSubscriberService.php
        SuspendSubscriberService.php

    Requests/
        CreateSubscriberRequest.php
        UpdateSubscriberRequest.php

    Resources/
        SubscriberResource.php

    Policies/
        SubscriberPolicy.php

    Enums/
        SubscriberStatus.php


Do not create empty directories just to satisfy architecture.

Only create a directory when it actually contains something.


======================================================================
16. EASY NAVIGATION RULE
======================================================================

A developer should be able to guess where code lives.


Subscriber backend:

    backend/app/Modules/Subscribers/


Subscriber frontend:

    frontend/src/modules/subscribers/


Billing backend:

    backend/app/Modules/Billing/


Billing frontend:

    frontend/src/modules/billing/


OLT backend:

    backend/app/Modules/OLT/


OLT frontend:

    frontend/src/modules/olt/


Monitoring backend:

    backend/app/Modules/Monitoring/


Monitoring frontend:

    frontend/src/modules/monitoring/


This consistency is intentional.

Do not unnecessarily complicate it.


======================================================================
17. DO NOT OVERENGINEER THE DIRECTORY STRUCTURE
======================================================================

Do not transform:

    Modules/Subscribers/

into unnecessary layers such as:

    Domain/
    Application/
    Infrastructure/
    UseCases/
    Commands/
    CommandHandlers/
    Queries/
    QueryHandlers/
    Repositories/
    RepositoryInterfaces/
    DTOs/
    Factories/


unless demonstrated complexity genuinely requires them.

Prefer:

    Modules/Subscribers/
        Controllers/
        Models/
        Services/
        Requests/
        Resources/
        Policies/


SIMPLE means:

    predictable
    organized
    understandable
    maintainable

Simple does NOT mean putting everything into controllers.


======================================================================
18. CONTROLLERS
======================================================================

Controllers must remain thin.

Use:

    Controller
       |
       v
    Service
       |
       v
    Model / Adapter


Controller handles:

- request
- authorization
- validated input
- service invocation
- API response


Controller must NOT contain:

- billing calculations
- invoice calculations
- complex SQL
- SSH
- network commands
- provisioning implementation


======================================================================
19. SERVICES
======================================================================

Use descriptive services.

Prefer:

    CreateCustomerService

    CreateSubscriberService

    ActivateSubscriberService

    SuspendSubscriberService

    GenerateInvoiceService

    RecordPaymentService

    AllocatePaymentService


instead of giant classes such as:

    BillingService.php

with hundreds of unrelated methods.


======================================================================
20. DATABASE
======================================================================

Use Laravel migrations.

Use:

- foreign keys
- unique constraints
- indexes
- transactions
- appropriate data types
- timestamps

Use UUIDs where appropriate.

Do not expose sequential database IDs as public identifiers.

Tenant-aware uniqueness must be considered.

Example:

Instead of globally assuming:

    customer_number UNIQUE

consider tenant scope where appropriate:

    UNIQUE (organization_id, customer_number)


======================================================================
21. CUSTOMER MODEL
======================================================================

Customer fields should support:

- organization_id
- customer number
- first name
- middle name
- last name
- company name
- customer type
- email
- mobile
- alternate contact
- billing address
- installation address
- notes
- status


Customer types:

    RESIDENTIAL
    BUSINESS
    ENTERPRISE
    WHOLESALE


Example customer number:

    CUST-00001234


======================================================================
22. CUSTOMER IS NOT THE SERVICE
======================================================================

Do NOT assume:

    one customer = one Internet connection


Use:

    Customer
       |
       +-- Billing Account
       |
       +-- Subscriber Service #1
       |
       +-- Subscriber Service #2


A business customer may eventually have multiple circuits.


======================================================================
23. BILLING ACCOUNT
======================================================================

Create BillingAccount.

Support:

- organization
- account number
- customer
- currency
- billing cycle
- billing status
- payment terms
- balance
- credit
- status


Example:

    ACC-00001234


======================================================================
24. SUBSCRIBER SERVICE
======================================================================

Subscriber Service represents an actual Internet/service connection.

Example:

    Customer
        |
        v
    Billing Account
        |
        v
    Subscriber Service
        |
        +-- Plan
        +-- PPPoE
        +-- ONU
        +-- IP
        +-- VLAN
        +-- BNG
        +-- CPE


Use a unique tenant-scoped service number.

Example:

    SRV-00001234


======================================================================
25. SEPARATE STATUS VALUES
======================================================================

Do NOT use one generic status for everything.


CUSTOMER:

    ACTIVE
    INACTIVE
    CLOSED


BILLING:

    CURRENT
    DUE
    OVERDUE
    CREDIT


SERVICE:

    PENDING
    PROVISIONING
    ACTIVE
    SUSPENDED
    FAILED
    TERMINATED


CONNECTION:

    ONLINE
    OFFLINE
    UNKNOWN


PROVISIONING:

    NOT_PROVISIONED
    QUEUED
    PROVISIONING
    COMPLETED
    FAILED


Each status represents a different concept.


======================================================================
26. SERVICE PLANS
======================================================================

Plan fields:

- organization
- code
- name
- description
- recurring price
- currency
- billing cycle
- download bandwidth
- upload bandwidth
- service type
- active status
- IPv4 policy
- IPv6 policy
- CGNAT policy
- future provisioning profile reference


Examples:

    HOME-100
    HOME-300
    HOME-500
    HOME-1000

    BUSINESS-500
    BUSINESS-1000


Do not store OLT CLI inside plans.


======================================================================
27. BANDWIDTH
======================================================================

Do not store:

    "500 Mbps"

as authoritative data.


Use normalized numeric values.

Example:

    download_kbps = 500000

    upload_kbps = 500000


Frontend may display:

    500 Mbps


======================================================================
28. SUBSCRIPTIONS
======================================================================

Subscription should track:

- organization
- billing account
- subscriber service
- plan
- start date
- end date
- recurring amount
- billing cycle
- status
- next billing date
- activation date
- suspension date
- termination date


Keep historical plan information.

Do not destroy history when changing plans.


======================================================================
29. BILLING ENGINE
======================================================================

Build a proper billing engine.

Core entities:

    BillingAccount
    BillingCycle
    Subscription
    Invoice
    InvoiceItem
    Payment
    PaymentAllocation
    Credit
    Adjustment
    BalanceTransaction


Do NOT implement billing simply as:

    customer.balance -= payment


Financial history must be traceable and auditable.


======================================================================
30. MONEY
======================================================================

NEVER use floating point for financial values.

Use:

    DECIMAL

or a clearly documented minor-unit integer strategy.


Currency must be explicit.

Initial currency:

    PHP


Do not scatter PHP-specific assumptions throughout business logic.


======================================================================
31. INVOICE
======================================================================

Invoice fields:

- organization
- UUID
- invoice number
- billing account
- billing period start
- billing period end
- issue date
- due date
- subtotal
- discount
- tax
- adjustment
- total
- amount paid
- remaining balance
- currency
- status


Statuses:

    DRAFT
    OPEN
    PARTIALLY_PAID
    PAID
    OVERDUE
    VOID


Example:

    INV-2026-00001234


======================================================================
32. INVOICE ITEMS
======================================================================

InvoiceItem:

- invoice
- description
- quantity
- unit price
- amount
- type
- subscriber service
- billing period


Types:

    RECURRING_SERVICE
    INSTALLATION
    EQUIPMENT
    DISCOUNT
    ADJUSTMENT
    OTHER


======================================================================
33. PAYMENTS
======================================================================

Payment fields:

- organization
- payment number
- billing account
- amount
- currency
- method
- reference
- transaction reference
- payment date
- received by
- notes
- status


Methods may include:

    CASH
    BANK_TRANSFER
    GCASH
    MAYA
    CARD
    ONLINE_GATEWAY
    OTHER


Do not tightly couple billing to one payment provider.


======================================================================
34. PAYMENT ALLOCATION
======================================================================

Support:

- full invoice payment
- partial invoice payment
- multiple invoice payment
- overpayment


Example:

    PAYMENT = PHP 2,000

          |
          +-- Invoice #1 = PHP 1,500
          |
          +-- Invoice #2 = PHP 500


Use explicit PaymentAllocation records.


======================================================================
35. CREDITS AND ADJUSTMENTS
======================================================================

Support:

- credits
- debit adjustments
- credit adjustments
- discounts
- corrections


Do not silently modify historical financial records.

Every financial change must be auditable.


======================================================================
36. BILLING CYCLES
======================================================================

Do not assume all subscribers have the same billing date.

Support configurable billing cycles.

Track:

- billing period
- issue date
- due date
- next billing date
- grace period


======================================================================
37. OVERDUE / SUSPENSION FLOW
======================================================================

Expected business flow:

    Invoice
       |
       v
    Due Date
       |
       v
    Grace Period
       |
       v
    OVERDUE
       |
       v
    Suspension Eligible


Billing determines:

    service SHOULD be suspended.


Billing does NOT directly disconnect the network.


======================================================================
38. PAYMENT / RESTORATION FLOW
======================================================================

Expected flow:

    Payment
       |
       v
    Payment Allocation
       |
       v
    Account Recalculation
       |
       v
    Billing CURRENT
       |
       v
    Restoration Eligible
       |
       v
    Restore Service Intent


Actual network restoration will later be performed by the Network
Automation Engine.


======================================================================
39. NETWORK INVENTORY
======================================================================

The portal eventually combines:

    BILLING
       +
    SUBSCRIBERS
       +
    NETWORK
       +
    MONITORING


Create normalized inventory for:

- Sites
- POPs
- OLTs
- PONs
- ONUs
- BNGs
- IP Pools
- VLANs
- ACS servers


Do not implement actual device automation yet.


======================================================================
40. OLT MODEL
======================================================================

Use generic:

    Olt


Do NOT use:

    HuaweiOlt

as the main domain model.


Fields:

- organization
- UUID
- site
- POP
- name
- hostname
- management IP
- vendor
- model
- software version
- access technology
- driver identifier
- transport preference
- status
- capabilities
- last contact
- last synchronization
- notes
- metadata


Potential vendor values:

    Huawei
    ZTE
    VSOL
    FiberHome
    C-DATA
    Nokia
    Dasan


These are DATA values.

Do not scatter vendor checks through business logic.


======================================================================
41. PON TECHNOLOGIES
======================================================================

Support:

    GPON
    EPON
    XPON
    XGS_PON
    OTHER


Do not assume GPON only.


======================================================================
42. PON PORT
======================================================================

Create normalized PonPort.

Support:

- organization
- OLT
- slot
- port
- canonical identifier
- technology
- admin state
- operational state
- ONU count
- capacity
- description


Do not make Huawei-specific frame/slot/port syntax the universal model.


======================================================================
43. ONU
======================================================================

Create generic:

    Onu


Fields:

- organization
- UUID
- OLT
- PON
- ONU identifier
- serial number
- MAC where applicable
- vendor
- model
- status
- subscriber assignment
- service profile reference
- desired state
- observed state
- last seen
- last synchronization
- metadata


Important searchable information must use proper columns.

Do not put everything inside JSON.


======================================================================
44. ONU ASSIGNMENT HISTORY
======================================================================

Track assignment history.

Example:

    ONU
      |
      +-- Subscriber A
      |
      +-- released
      |
      +-- Subscriber B


Record:

- organization
- ONU
- subscriber service
- assigned at
- assigned by
- released at
- release reason


======================================================================
45. BNG
======================================================================

Use generic:

    Bng


Do not make:

    AccelPpp

the primary business model.


Current implementation later may be:

    ACCEL_PPP


Fields:

- organization
- UUID
- site
- POP
- name
- management IP
- implementation type
- status
- capabilities
- metadata


======================================================================
46. PPPOE ACCOUNT
======================================================================

Prepare normalized PPPoE account data.

Fields:

- organization
- subscriber service
- username
- credential reference
- status
- BNG
- rate profile
- IPv4 policy
- IPv6 policy
- simultaneous-use policy


Never return PPPoE passwords in normal frontend/API responses.


======================================================================
47. PPPOE SESSION
======================================================================

Prepare for session information:

- organization
- session ID
- subscriber
- username
- BNG
- NAS
- IPv4
- IPv6
- start time
- last update
- uptime
- RX bytes
- TX bytes
- termination reason
- online/offline


Actual FreeRADIUS/Accel-PPP integration comes later.


======================================================================
48. IPAM
======================================================================

Create IP Address Management.

Support:

    IPv4 Pool
    IPv6 Pool
    CGNAT Pool
    Public IPv4 Pool
    Infrastructure Pool
    IPv6 Prefix Pool


Assignment states:

    AVAILABLE
    RESERVED
    ASSIGNED
    QUARANTINED


Protect against duplicate assignment using database constraints and
transactions.


======================================================================
49. IP SERVICE MODES
======================================================================

Support:

    CGNAT
    PUBLIC_IPV4
    IPV6_NATIVE
    DUAL_STACK


Do not assume every subscriber uses CGNAT.


======================================================================
50. VLAN
======================================================================

Support:

    VLAN
    C-VLAN
    S-VLAN
    QinQ
    Management VLAN
    Service VLAN


Track:

- organization
- VLAN ID
- site
- POP
- type
- purpose
- status
- assignment
- reservation


Do not hard-code VLAN values inside controllers.


======================================================================
51. ACS / CPE
======================================================================

Prepare:

    AcsServer
    CpeDevice


GenieACS will later implement ACS functionality.


CPE fields may include:

- organization
- manufacturer
- product class
- serial
- MAC
- software version
- status
- last inform
- subscriber
- ACS server


Do not expose CPE credentials.


======================================================================
52. MONITORING
======================================================================

Monitoring belongs inside the same portal.

Do NOT build a separate unrelated monitoring website.

React should eventually provide:

    Subscriber
    Billing
    Network
    Monitoring
    Provisioning

inside one operational interface.


======================================================================
53. MONITORING DATA TYPES
======================================================================

Separate:

    INVENTORY

    CURRENT STATE

    HISTORICAL DATA


Example:

Inventory:

    ONU 24 exists on OLT-01/PON-3


Current state:

    ONLINE
    RX -19.8 dBm


Historical:

    Optical power over previous 30 days


Do not treat these as the same data.


======================================================================
54. REDIS FOR LIVE STATE
======================================================================

Redis may later store temporary/live information such as:

    ONU current state
    latest optical reading
    OLT status
    recent health
    provisioning progress


Do not continuously write every polling result into core billing tables.


======================================================================
55. ALARMS
======================================================================

Create Alarm.

Fields:

- organization
- UUID
- severity
- category
- source type
- source ID
- site
- message
- opened time
- acknowledged time
- acknowledged by
- cleared time
- status
- correlation ID


Severity:

    INFO
    WARNING
    MINOR
    MAJOR
    CRITICAL


Status:

    OPEN
    ACKNOWLEDGED
    CLEARED


Never delete historical alarms merely because they clear.


======================================================================
56. PROVISIONING JOBS
======================================================================

Prepare provisioning infrastructure now.

ProvisioningJob:

- organization
- UUID
- correlation ID
- subscriber
- operation
- target type
- target ID
- desired state
- actual state
- status
- current stage
- progress
- retry count
- retryable
- failure code
- failure message
- requested by
- requested at
- started at
- completed at


======================================================================
57. PROVISIONING STATES
======================================================================

Use meaningful states:

    QUEUED
    VALIDATING
    ACQUIRING_LOCK
    CONNECTING
    READING_CURRENT_STATE
    APPLYING_CONFIGURATION
    CONFIGURING_RADIUS
    CONFIGURING_ACS
    VERIFYING
    COMPLETED
    FAILED
    CANCELLED


Do not reduce provisioning to:

    pending
    done


======================================================================
58. DESIRED VS ACTUAL STATE
======================================================================

Laravel owns desired service state.

Example:

    Plan:
        HOME-500

    Service:
        ACTIVE

    IP Mode:
        CGNAT


Future network integrations report actual state:

    ONU:
        ONLINE

    PPPoE:
        ONLINE

    IPv4:
        100.64.10.22


Never assume:

    button clicked = network successfully configured.


======================================================================
59. FUTURE INTEGRATION CONTRACTS
======================================================================

Prepare simple interfaces for:

    NetworkAutomationClient

    RadiusAdapter

    BngAdapter

    AcsAdapter

    CgnatAdapter


Do not implement the real external integrations yet.

Do not overcomplicate these interfaces prematurely.


======================================================================
60. REACT FRONTEND
======================================================================

Use:

    React
    TypeScript
    Vite
    Tailwind CSS


Do NOT use Angular.


Use strict TypeScript.

Avoid:

    any

unless genuinely necessary and documented.


Use:

- functional components
- hooks
- typed APIs
- typed props
- reusable components
- feature boundaries


Keep API logic out of purely visual components where practical.


======================================================================
61. FRONTEND MODULE STRUCTURE
======================================================================

Example:

frontend/src/modules/subscribers/

    pages/
        SubscriberListPage.tsx
        SubscriberDetailPage.tsx
        CreateSubscriberPage.tsx

    components/
        SubscriberTable.tsx
        SubscriberSummary.tsx
        SubscriberStatus.tsx

    hooks/
        useSubscribers.ts
        useSubscriber.ts

    api/
        subscribers.api.ts

    types/
        subscriber.types.ts


Do not create giant page components.


======================================================================
62. SHARED COMPONENTS
======================================================================

Shared components belong in:

    frontend/src/components/


Recommended organization:

    components/
    |
    +-- ui/
    |   +-- Button
    |   +-- Input
    |   +-- Select
    |   +-- Checkbox
    |   +-- Badge
    |   +-- Tooltip
    |   +-- Dialog
    |   +-- Dropdown
    |   +-- Tabs
    |   +-- Pagination
    |   +-- Skeleton
    |   +-- Toast
    |
    +-- data/
    |   +-- DataTable
    |   +-- FilterBar
    |   +-- SearchInput
    |   +-- StatusBadge
    |   +-- EmptyState
    |   +-- ErrorState
    |
    +-- layout/
        +-- AppShell
        +-- Sidebar
        +-- Topbar
        +-- PageHeader
        +-- PageSection


Pages must compose existing primitives whenever possible.

Do NOT invent a new visual language for every page.


======================================================================
63. DESIGN SYSTEM MUST COME BEFORE LARGE-SCALE UI
======================================================================

Before building many application pages, establish and document the
design system.

Create:

    docs/DESIGN_SYSTEM.md


Define:

- typography
- font sizes
- font weights
- line heights
- spacing scale
- layout widths
- sidebar dimensions
- topbar dimensions
- border radius
- border treatment
- shadows
- colors
- background hierarchy
- status colors
- tables
- forms
- buttons
- dialogs
- tabs
- badges
- pagination
- empty states
- error states
- loading states
- dark mode
- responsive behavior
- chart rules
- icon rules


Do NOT allow each feature to independently invent these values.


======================================================================
64. PRODUCT VISUAL IDENTITY
======================================================================

The visual reference category is:

    Network Operations Center

    ISP OSS/BSS

    Enterprise Network Management

    Infrastructure Management

    Billing Operations


The visual reference category is NOT:

    AI startup

    crypto dashboard

    fintech landing page

    generic SaaS template

    marketing website

    gaming dashboard


The application must feel like professional operational software.


======================================================================
65. ANTI-AI-SLOP DESIGN RULES — MANDATORY
======================================================================

The following are EXPLICITLY PROHIBITED as default design patterns.

NO:

    ❌ gradient everywhere

    ❌ purple/blue SaaS gradient

    ❌ glowing cards

    ❌ glassmorphism everywhere

    ❌ every container rounded-2xl

    ❌ huge border radius

    ❌ enormous dashboard cards

    ❌ random shadows

    ❌ oversized hero headings

    ❌ decorative charts with fake data

    ❌ excessive icons

    ❌ emoji as application icons

    ❌ excessive whitespace

    ❌ unnecessary animations

    ❌ fake metrics

    ❌ generic "Welcome back, Kevin 👋"

    ❌ marketing-page styling inside NOC screens


These rules are mandatory.

Do not reinterpret them as loose suggestions.


======================================================================
66. ADDITIONAL ANTI-AI-SLOP RULES
======================================================================

Also avoid:

- unnecessary pill-shaped containers
- excessive rounded badges
- giant KPI typography
- gradients used simply to make a page look "modern"
- meaningless colored blobs
- decorative background meshes
- floating decorative elements
- excessive card nesting
- card-inside-card-inside-card layouts
- excessive drop shadows
- excessive blur
- excessive animation
- animation without operational purpose
- excessive hover movement
- random accent colors
- inconsistent spacing
- inconsistent radius
- inconsistent icon styles
- excessive use of status colors
- giant page titles
- marketing copy inside operational pages
- fake testimonials
- fake activity
- fake network data
- fake charts
- fake subscriber counts
- fake revenue
- fake bandwidth statistics


Do not generate UI merely to make screenshots look impressive.

Optimize for actual ISP operations.


======================================================================
67. UI DENSITY
======================================================================

This is an operational application.

Information density is desirable when handled correctly.

Primary users may operate the portal on:

    1080p
    1440p
    multi-monitor NOC workstations


Do not waste half of the screen on decorative elements.


Example of desirable density:

    Subscribers                                      + Add Subscriber
    -----------------------------------------------------------------
    Search...     Status     Plan     OLT             Filters

    Service       Customer        Plan       Status      IP
    -----------------------------------------------------------------
    SRV-00124     Juan Cruz       HOME-500   Online      100.64.1.24
    SRV-00125     Mark Reyes      HOME-300   Online      100.64.1.25
    SRV-00126     Anna Santos     HOME-500   Offline     -
    SRV-00127     John Lim        BIZ-1000   Online      126.x.x.x
    -----------------------------------------------------------------

    1-50 of 10,241                          < 1 2 3 4 ... 205 >


Prefer useful tables and operational information over giant cards.


======================================================================
68. DASHBOARD CARDS
======================================================================

Cards are allowed.

But cards should be compact and useful.

Do not create giant cards merely to display:

    10,241

Use compact operational summaries.

Dashboard should maximize information per useful screen area while
remaining readable.


======================================================================
69. TYPOGRAPHY
======================================================================

Typography should prioritize readability.

Use a restrained type scale.

Do not use:

    48px
    60px
    72px

marketing-style headings inside operational application screens.

Page headings should generally be compact.

Table text should remain readable and dense.

Use font weight intentionally.

Do not bold everything.


======================================================================
70. BORDER RADIUS
======================================================================

Use restrained radius.

Do NOT automatically use:

    rounded-2xl
    rounded-3xl

on every element.


Use consistent smaller radii appropriate for enterprise software.

Buttons, inputs, tables, cards, and dialogs should belong to the same
visual system.


======================================================================
71. SHADOWS
======================================================================

Use shadows only when they communicate elevation.

Do not randomly place:

    shadow-lg
    shadow-xl
    shadow-2xl

throughout the application.

Prefer:

    borders
    background hierarchy
    spacing

for most structural separation.


======================================================================
72. COLORS
======================================================================

Use a restrained enterprise palette.

Color should primarily communicate:

- hierarchy
- action
- state
- warning
- severity


Do not use five unrelated accent colors merely for visual variety.

Status colors must have consistent semantic meaning.


Example:

    GREEN
        healthy / active / online

    YELLOW/AMBER
        warning

    RED
        critical / failed / overdue where appropriate

    NEUTRAL
        inactive / unknown


Do not rely only on color to communicate state.


======================================================================
73. ICONS
======================================================================

Use one consistent icon library.

Do not mix multiple icon styles.

Do not use emoji as application navigation icons.

Icons should improve scanning.

Do not add an icon to every piece of text.


======================================================================
74. ANIMATION
======================================================================

Animation must have a functional purpose.

Allowed examples:

- dialog transition
- dropdown transition
- loading indicator
- subtle state transition


Avoid:

- bouncing cards
- floating widgets
- continuous background animations
- decorative particle effects
- excessive page transitions
- hover transforms on every card


NOC interfaces should feel stable.


======================================================================
75. NO FAKE DATA
======================================================================

NEVER fabricate operational or financial metrics.

Do not invent:

    ONU Online       9,841

    Network Health   99.98%

    Revenue          PHP 8.2M

    Bandwidth        82.4 Gbps


unless those values actually come from backend data.


If data is unavailable, display:

    -

    Not available

    No data

    Integration not connected


Development seed/mock data is allowed ONLY in development environments
and must be clearly identifiable as development data.


======================================================================
76. DASHBOARD
======================================================================

Dashboard eventually shows real:

SUBSCRIBERS

    Total
    Active
    Suspended
    Online
    Offline


BILLING

    Outstanding
    Collections Today
    Overdue
    Open Invoices


NETWORK

    OLT Online
    OLT Offline
    ONU Online
    ONU Offline


SESSIONS

    Active PPPoE


ALARMS

    Critical
    Major
    Warning


PROVISIONING

    Queued
    Running
    Failed


Do not fabricate unavailable metrics.


======================================================================
77. TABLES ARE FIRST-CLASS COMPONENTS
======================================================================

Tables are critical to ISP-in-a-Box.

Create a reusable DataTable system.

Support:

- server-side pagination
- sorting
- search
- filters
- loading
- empty states
- error states
- row actions
- column visibility
- bulk selection where appropriate


Never load all 10,000 subscribers into the browser.


======================================================================
78. SUBSCRIBER LIST
======================================================================

Useful columns:

    Service Number

    Customer

    Plan

    Billing

    Service Status

    Connection

    PPPoE

    IP

    OLT

    PON

    ONU

    Balance


Allow configurable columns where appropriate.


======================================================================
79. SUBSCRIBER DETAIL PAGE
======================================================================

This is one of the most important screens.

The operator should be able to troubleshoot a subscriber without opening
multiple unrelated applications.


Use tabs/sections:

    OVERVIEW

    BILLING

    SERVICE

    NETWORK

    SESSIONS

    CPE

    PROVISIONING

    ACTIVITY


Example:

    Juan Dela Cruz
    SRV-00001234

    Plan             HOME-500
    Service          ACTIVE
    Billing          CURRENT
    Connection       ONLINE


    BILLING

    Balance          PHP 0.00
    Next Invoice     ...
    Due Date         ...


    NETWORK

    Site             MANILA
    OLT              OLT-MNL-01
    PON              0/1/3
    ONU              24
    ONU Status       ONLINE
    RX               -19.8 dBm


    PPPOE

    Status           ONLINE
    Username         ...
    IPv4             100.64.10.22
    Uptime           03:20:14


    CPE

    Status           ONLINE
    Last Inform      30 seconds ago


Do not fabricate unavailable network information.


======================================================================
80. CUSTOMER CREATION
======================================================================

Provide a clear workflow:

    Customer Information
       |
       v
    Address
       |
       v
    Billing Account
       |
       v
    Plan
       |
       v
    Subscriber Service
       |
       v
    Review
       |
       v
    Create


Do not require network/ONU assignment merely to create the customer.


======================================================================
81. INVOICE UI
======================================================================

Provide:

- invoice list
- invoice detail
- customer
- period
- issue date
- due date
- items
- total
- paid
- balance
- status
- payment allocation
- history


Keep financial information dense and easy to audit.


======================================================================
82. PAYMENT UI
======================================================================

Workflow:

    Select Account
       |
       v
    Amount
       |
       v
    Method
       |
       v
    Reference
       |
       v
    Allocation
       |
       v
    Review
       |
       v
    Submit


Protect against accidental duplicate submission.


======================================================================
83. NETWORK NAVIGATION
======================================================================

Allow:

    Site
      ->
    POP
      ->
    OLT
      ->
    PON
      ->
    ONU
      ->
    Subscriber


And reverse navigation:

    Subscriber
      ->
    ONU
      ->
    PON
      ->
    OLT
      ->
    POP
      ->
    Site


======================================================================
84. SERVER STATE
======================================================================

Use an appropriate React server-state/query library.

Do not manually reinvent:

- request caching
- retry behavior
- invalidation
- loading management
- request deduplication


Keep:

    SERVER STATE

separate from:

    LOCAL UI STATE


Do not place every value into one giant global store.


======================================================================
85. ROUTING
======================================================================

Use proper React routing.

Support:

- authenticated routes
- tenant-aware routes
- permission-aware routes
- layouts
- 404
- unauthorized
- error handling


Laravel remains authoritative for security.


======================================================================
86. AUTHENTICATION
======================================================================

Use secure Laravel-compatible SPA authentication.

Implement:

- login
- logout
- current user
- password change
- disabled user handling
- rate limiting


Authentication must also respect tenant context.


A Company A user must not authenticate into Company B's portal unless
explicit platform/business rules allow that membership.


======================================================================
87. RBAC
======================================================================

Implement granular permissions.

Examples:

    dashboard.view

    customer.view
    customer.create
    customer.update

    subscriber.view
    subscriber.create
    subscriber.update
    subscriber.activate
    subscriber.suspend

    billing.view
    billing.manage

    invoice.view
    invoice.create
    invoice.void

    payment.view
    payment.create

    plan.view
    plan.manage

    network.view

    olt.view
    olt.manage

    onu.view
    onu.provision
    onu.reboot
    onu.delete

    session.view
    session.disconnect

    ipam.view
    ipam.manage

    provisioning.view
    provisioning.retry

    alarm.view
    alarm.acknowledge

    audit.view

    user.manage
    role.manage


Backend authorization is mandatory.


======================================================================
88. AUDIT LOGGING
======================================================================

Audit important operations.

Examples:

    customer.created
    customer.updated

    subscriber.created
    subscriber.activated
    subscriber.suspended

    plan.changed

    invoice.generated
    invoice.voided

    payment.recorded

    onu.assigned

    provisioning.requested
    provisioning.failed

    alarm.acknowledged

    user.created
    role.updated


Record:

- organization
- actor
- action
- target
- target ID
- timestamp
- source IP where appropriate
- correlation ID
- old state where appropriate
- new state where appropriate
- result


======================================================================
89. API
======================================================================

Use:

    /api/v1


Examples:

    /api/v1/auth

    /api/v1/dashboard

    /api/v1/customers

    /api/v1/billing-accounts

    /api/v1/subscribers

    /api/v1/plans

    /api/v1/subscriptions

    /api/v1/invoices

    /api/v1/payments

    /api/v1/sites

    /api/v1/pops

    /api/v1/olts

    /api/v1/pons

    /api/v1/onus

    /api/v1/bngs

    /api/v1/sessions

    /api/v1/ip-pools

    /api/v1/vlans

    /api/v1/alarms

    /api/v1/provisioning/jobs

    /api/v1/audit-logs

    /api/v1/users

    /api/v1/roles


Tenant is resolved by trusted request context/hostname.

Do not trust arbitrary organization_id supplied by the frontend for
authorization.


======================================================================
90. API PAGINATION
======================================================================

List APIs must support server-side:

- pagination
- search
- filtering
- sorting


Example:

    GET /api/v1/subscribers
        ?page=1
        &per_page=50
        &search=juan
        &service_status=ACTIVE
        &billing_status=CURRENT
        &site_id=...
        &sort=created_at
        &direction=desc


Whitelist allowed filters and sorting fields.


======================================================================
91. API RESPONSE FORMAT
======================================================================

Use consistent responses.


Success:

{
    "data": {},
    "meta": {},
    "correlation_id": "..."
}


Error:

{
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Request validation failed",
        "details": {}
    },
    "correlation_id": "..."
}


Never expose production stack traces.


======================================================================
92. SECURITY
======================================================================

Security requirements:

- secure authentication
- strict tenant isolation
- RBAC
- Laravel Policies
- server-side validation
- CSRF protection where applicable
- restricted CORS
- rate limiting
- secure password hashing
- encrypted sensitive data
- secure headers
- audit logging
- secret redaction


Never commit:

    .env
    production passwords
    database credentials
    OLT passwords
    SSH keys
    API tokens


Treat cross-tenant data leakage as a critical vulnerability.


======================================================================
93. DANGEROUS ACTIONS
======================================================================

Future actions such as:

    Suspend Subscriber

    Delete ONU

    Disconnect PPPoE

    Reboot ONU

    Retry Provisioning


must require:

- tenant validation
- permission
- backend authorization
- validation
- confirmation where appropriate
- audit logging


======================================================================
94. CONCURRENCY
======================================================================

Protect:

- invoice generation
- payment recording
- payment allocation
- IP assignment
- VLAN assignment
- ONU assignment
- account number generation
- service number generation


Use:

- transactions
- unique constraints
- locking where necessary
- idempotency where necessary


======================================================================
95. PERFORMANCE
======================================================================

Design for:

    10,000+ subscribers per tenant


and potentially many tenants.


Historical tables may contain millions of records.

Examples:

    invoices
    payments
    audit logs
    alarms
    provisioning history
    RADIUS accounting


Use:

- proper indexes
- pagination
- efficient queries
- eager loading
- caching where appropriate
- background jobs
- aggregation


Avoid N+1 queries.


Always consider organization_id in index design for tenant-owned
high-volume tables.


======================================================================
96. DASHBOARD PERFORMANCE
======================================================================

Do not execute dozens of expensive COUNT queries whenever the dashboard
loads.

Use efficient aggregation.

Cache expensive metrics where appropriate.

Cache keys MUST include tenant identity when data is tenant-specific.

Never leak cached data between tenants.


======================================================================
97. QUEUES
======================================================================

Use Laravel queues for suitable asynchronous tasks.

Examples:

    invoice batch generation
    notifications
    reports
    exports
    future provisioning
    synchronization


Queued jobs involving tenant data MUST carry explicit trusted tenant
context.

Do not accidentally execute tenant jobs without tenant scoping.


======================================================================
98. SCHEDULER
======================================================================

Use Laravel Scheduler for:

    invoice generation
    overdue evaluation
    grace period evaluation
    suspension eligibility
    restoration eligibility
    cleanup


Scheduled operations must be safe against duplicate execution.

Multi-tenant scheduled operations must process tenants safely.


======================================================================
99. ERROR HANDLING
======================================================================

Every frontend workflow must have:

    loading
    success
    empty
    validation
    error


Do not silently swallow errors.

Do not expose internal sensitive information.


======================================================================
100. TESTING
======================================================================

Testing is mandatory.

BACKEND:

    Unit Tests
    Feature Tests
    API Tests
    Authorization Tests
    Tenant Isolation Tests
    Billing Tests
    Payment Tests
    Transaction Tests


FRONTEND:

    Important Component Tests
    Authentication Tests
    Permission Tests
    Critical Workflow Tests


Physical network hardware must NOT be required for billing tests.


======================================================================
101. TENANT ISOLATION TESTS
======================================================================

Tenant isolation tests are mandatory.

Create:

    Company A
    Company B


Create data belonging to both.

Verify Company A cannot:

- retrieve Company B customer
- search Company B subscriber
- retrieve Company B invoice
- retrieve Company B payment
- retrieve Company B ONU
- retrieve Company B OLT
- modify Company B records
- export Company B data
- access Company B audit logs


Test both:

    normal API requests

and:

    manipulated identifiers.


======================================================================
102. CRITICAL BILLING TESTS
======================================================================

Test at minimum:

    Create Customer

    Create Billing Account

    Create Subscriber

    Create Subscription

    Generate Invoice

    Prevent Duplicate Invoice

    Partial Payment

    Full Payment

    Multiple Invoice Allocation

    Overpayment

    Credit

    Overdue Detection

    Suspension Eligibility

    Restoration Eligibility

    Invoice Void

    Duplicate Payment Prevention

    Concurrent Payment Handling


Financial logic must be deterministic.


======================================================================
103. DEVELOPMENT DATA
======================================================================

Create factories/seeders.

Generate clearly fake development data.

Useful scenarios:

    Tenant A
    Tenant B

    active subscriber
    suspended subscriber
    overdue subscriber
    paid subscriber
    customer with multiple services
    subscriber with ONU
    subscriber without ONU
    online mock subscriber
    offline mock subscriber


Never use real subscriber credentials.

Never present seeded values as live production network information.


======================================================================
104. NATIVE DEPLOYMENT
======================================================================

DO NOT USE DOCKER.


Production:

    Dell R740
        |
        v
    Proxmox
        |
        +-- Billing/NMS VM
        |      |
        |      +-- Nginx
        |      +-- PHP-FPM
        |      +-- Laravel
        |      +-- MySQL
        |      +-- Redis
        |      +-- React production build
        |
        +-- Optional dedicated secure-tunnel VM


Use systemd for Laravel workers and persistent application services.


Do NOT use:

    php artisan serve

in production.


Do NOT use:

    npm run dev

in production.


======================================================================
105. INSTALLATION
======================================================================

Eventually provide:

    scripts/install.sh


It should safely help install/configure:

- Nginx
- PHP
- PHP-FPM
- required PHP extensions
- Composer
- MySQL
- Redis
- Node/npm for frontend building
- Laravel dependencies
- React build
- systemd workers


Installation scripts must not silently destroy existing systems.


======================================================================
106. BACKUP
======================================================================

Provide:

    scripts/backup.sh

and:

    scripts/restore.sh


Backup:

- MySQL
- application configuration
- Laravel encryption key
- required environment configuration


Proxmox backup is additional protection.

It does not replace application-aware database backups.


======================================================================
107. UPDATE
======================================================================

Prepare:

    scripts/update.sh


Conceptual workflow:

    Preflight
       |
       v
    Backup
       |
       v
    Maintenance if required
       |
       v
    Update Code
       |
       v
    Composer Install
       |
       v
    Migrations
       |
       v
    React Build
       |
       v
    Laravel Cache
       |
       v
    Restart Workers
       |
       v
    Health Check


======================================================================
108. HEALTH CHECKS
======================================================================

Provide:

    /health

and/or:

    /ready


Check:

- Laravel
- MySQL
- Redis
- queue where appropriate


Future OLT outages must NOT mark the core billing application itself as
unhealthy.


======================================================================
109. DO NOT OVERENGINEER
======================================================================

Do NOT introduce without demonstrated need:

    Kubernetes
    Kafka
    RabbitMQ
    Elasticsearch
    service mesh
    unnecessary microservices


Keep the application primarily:

    Laravel
       +
    MySQL
       +
    Redis
       +
    React
       +
    TypeScript
       +
    Vite
       +
    Tailwind


======================================================================
110. AGENTS.MD IS REQUIRED
======================================================================

Create and maintain:

    AGENTS.md


This is the project's permanent engineering constitution.

It should contain concise rules for:

1. Project purpose
2. Required stack
3. Folder structure
4. Laravel architecture
5. React architecture
6. Multi-tenancy
7. Billing integrity
8. Network integration boundaries
9. Security
10. Testing
11. UI design rules
12. Anti-AI-slop rules
13. Verification requirements
14. Definition of Done


Do NOT allow AGENTS.md to become a huge duplicate of this master prompt.

It should contain the permanent rules Codex needs during everyday work.


======================================================================
111. ARCHITECTURE DOCUMENT
======================================================================

Create:

    docs/ARCHITECTURE.md


Document:

- application architecture
- backend/frontend boundaries
- module organization
- tenant architecture
- database architecture
- Redis role
- billing architecture
- future network automation boundary
- future RADIUS boundary
- future ACS boundary
- deployment architecture


Keep it updated when approved architecture changes.


======================================================================
112. MULTI-TENANCY DOCUMENT
======================================================================

Create:

    docs/MULTI_TENANCY.md


Document:

- tenant definition
- hostname resolution
- organization context
- tenant-scoped models
- authentication
- authorization
- queries
- queues
- cache isolation
- scheduler behavior
- testing
- platform administration


======================================================================
113. DESIGN SYSTEM DOCUMENT
======================================================================

Create:

    docs/DESIGN_SYSTEM.md


This document must contain the approved visual language.

It must explicitly preserve the anti-AI-slop rules.

Once a design pattern is approved:

    REUSE IT.


Do not redesign:

    tables
    page headers
    forms
    dialogs
    navigation
    status badges
    cards

on every new feature.


======================================================================
114. REPRESENTATIVE UI APPROVAL BEFORE MASS UI DEVELOPMENT
======================================================================

Do NOT independently design 30 screens before the visual system is
approved.

First establish representative screens/components.

Prioritize:

    Application Shell

    Sidebar

    Topbar

    Dashboard

    Subscriber List

    Subscriber Detail

    Invoice List / Detail

    Form Example

    Dialog Example

    DataTable


Use these to establish the product's visual language.

Once approved, document the patterns in DESIGN_SYSTEM.md.

Then reuse those patterns throughout the application.


======================================================================
115. DO NOT RANDOMLY REDESIGN EXISTING UI
======================================================================

Once the product design system has been established:

Do NOT redesign existing components just because a new design seems
"more modern."

Do NOT introduce a new visual style during unrelated feature work.

Feature requests should reuse existing primitives.

Any significant visual-system change requires explicit design discussion.


======================================================================
116. FIRST IMPLEMENTATION PHASE — REPOSITORY INSPECTION
======================================================================

Before coding:

Inspect the repository.

Determine:

- whether Laravel already exists
- whether React already exists
- versions
- database structure
- migrations
- authentication
- existing billing logic
- existing subscriber logic
- existing UI
- existing design patterns
- existing tests
- existing documentation


Do NOT delete or rewrite working code blindly.


======================================================================
117. IMPLEMENTATION ORDER
======================================================================

Do NOT build everything simultaneously.


PHASE 0

    Repository Inspection
    Requirements Review
    Architecture
    Multi-Tenancy Design
    Design System Foundation


PHASE 1

    Laravel Foundation
    React Foundation
    TypeScript
    Vite
    Tailwind
    MySQL
    Redis
    API Foundation
    Health Checks


PHASE 2

    Tenant Resolution
    Organizations
    Authentication
    Users
    Roles
    Permissions
    Tenant Isolation Tests


PHASE 3

    Application Shell
    Sidebar
    Topbar
    Core UI Components
    DataTable
    Design System Approval


PHASE 4

    Customers
    Billing Accounts


PHASE 5

    Plans
    Subscriber Services
    Subscriptions


PHASE 6

    Billing
    Invoices
    Invoice Items
    Payments
    Allocations
    Credits
    Adjustments


PHASE 7

    Billing Automation
    Due Dates
    Overdue
    Grace Period
    Suspension Eligibility
    Restoration Eligibility


PHASE 8

    Network Inventory
    Sites
    POPs
    OLT
    PON
    ONU
    BNG
    IPAM
    VLAN


PHASE 9

    Unified Subscriber Workspace


PHASE 10

    Monitoring Models
    Alarms


PHASE 11

    Provisioning Jobs
    Desired State
    Automation Contracts


Do NOT implement real network automation during these phases.


======================================================================
118. FIRST END-TO-END BUSINESS MILESTONE
======================================================================

Build:

    LOGIN
       |
       v
    TENANT RESOLUTION
       |
       v
    DASHBOARD
       |
       v
    CREATE CUSTOMER
       |
       v
    CREATE BILLING ACCOUNT
       |
       v
    SELECT PLAN
       |
       v
    CREATE SUBSCRIBER SERVICE
       |
       v
    CREATE SUBSCRIPTION
       |
       v
    GENERATE INVOICE
       |
       v
    RECORD PAYMENT
       |
       v
    VIEW SUBSCRIBER


Subscriber page initially contains working:

    Overview
    Billing
    Service


and integration-ready sections:

    Network
    Sessions
    ONU
    CPE
    Provisioning


======================================================================
119. SECOND MILESTONE
======================================================================

Add:

    SITE
       |
       v
    POP
       |
       v
    OLT
       |
       v
    PON
       |
       v
    ONU
       |
       v
    SUBSCRIBER ASSIGNMENT


No vendor commands are required.


======================================================================
120. THIRD MILESTONE
======================================================================

Add provisioning intent:

    Subscriber
       |
       v
    ACTIVATE
       |
       v
    Tenant Validation
       |
       v
    Authorization
       |
       v
    Billing Validation
       |
       v
    Desired State
       |
       v
    ProvisioningJob
       |
       v
    MockNetworkAutomationClient
       |
       v
    Result
       |
       v
    Audit Log


This proves the Billing Portal is ready for the future Network
Automation Engine.


======================================================================
121. FUTURE NETWORK AUTOMATION
======================================================================

Do NOT implement it now.

Preserve this architecture:

                   BILLING PORTAL
                         |
                         v
                       Laravel
                         |
                         v
                  Desired State
                         |
                         v
                 Provisioning Job
                         |
                         v
                       Redis
                         |
                         v
                NETWORK AUTOMATION
                     Node.js
                   TypeScript
                         |
               +---------+---------+
               |         |         |
               v         v         v
            Huawei      ZTE      VSOL
             Driver    Driver    Driver
               |
               v
             Network


Monitoring should eventually reuse the same vendor abstraction.

Do not create separate Huawei implementations for:

    Billing
    Provisioning
    Monitoring


======================================================================
122. FUTURE RADIUS
======================================================================

FreeRADIUS will later provide AAA.

Billing Portal owns:

    subscriber/business state


FreeRADIUS owns:

    authentication
    authorization
    accounting


Prepare clean integration boundaries.

Do not scatter RADIUS SQL throughout billing controllers.


======================================================================
123. FUTURE ACS
======================================================================

GenieACS will later manage CPE through TR-069.

Billing Portal:

    business/service intent


GenieACS:

    CPE configuration


OLT:

    PON/network provisioning


Keep these responsibilities separate.


======================================================================
124. FUTURE BNG
======================================================================

Accel-PPP will later provide BNG functionality.

Future flow:

    Subscriber
       |
       v
    Laravel
       |
       v
    FreeRADIUS
       |
       v
    Accel-PPP
       |
       v
    PPPoE Session


Billing may display session information without becoming the BNG itself.


======================================================================
125. FUTURE ROUTER / CGNAT
======================================================================

Current deployments may use:

    FRR for routing

and:

    iptables for CGNAT/NAT/firewall


Future/new deployments may use:

    nftables


Do not incorrectly treat FRR as the NAT engine.

Do not tightly couple the Billing Portal to either iptables or nftables.

Use a future:

    CgnatAdapter

or network automation contract.

The Billing Portal expresses intent.

The network layer implements it.


======================================================================
126. DEFINITION OF DONE
======================================================================

A feature is NOT complete simply because code was generated.

Before claiming completion, use the appropriate Superpowers verification
workflow.

At minimum verify where applicable:

BACKEND:

    Laravel tests pass

    migrations work

    authorization works

    tenant isolation works

    validation works


FRONTEND:

    TypeScript passes

    tests pass

    lint passes

    production build succeeds


APPLICATION:

    API works

    frontend/API integration works

    no obvious console errors

    no fake production data

    tenant boundaries remain intact


DOCUMENTATION:

    architecture updated if necessary

    design system updated if necessary


Do not say:

    "Done"

    "Fixed"

    "Working"

    "Production ready"


without evidence from verification.


======================================================================
127. CODE REVIEW
======================================================================

After significant milestones, use the appropriate Superpowers code-review
workflow.

Review specifically for:

- correctness
- tenant leakage
- authorization bypass
- billing integrity
- concurrency
- SQL/query efficiency
- N+1 queries
- duplicated logic
- oversized files
- oversized components
- unnecessary abstraction
- secrets
- unsafe logging
- missing tests
- inconsistent UI
- AI-slop UI patterns


Do not treat code review as ceremonial.


======================================================================
128. AI-GENERATED CODE QUALITY RULE
======================================================================

Do not generate code simply because more code appears productive.

Prefer:

    fewer clear files

over:

    many unnecessary abstractions.


Prefer:

    understandable code

over:

    clever code.


Prefer:

    tested business rules

over:

    large amounts of generated boilerplate.


Prefer:

    established UI primitives

over:

    custom components for every screen.


Prefer:

    incremental changes

over:

    massive rewrites.


======================================================================
129. HUMAN APPROVAL GATES
======================================================================

Respect Superpowers approval requirements.

In particular, do not make major architectural or visual decisions and
immediately implement them without approval.

Important approval points include:

    Architecture

    Multi-tenancy model

    Billing model

    Design system

    Representative UI

    Network integration contracts


When an approval is required:

    STOP

and wait for the human response.


======================================================================
130. STARTING INSTRUCTION
======================================================================

START NOW, but follow Superpowers.

Do NOT immediately build the entire application.


FIRST:

    Invoke/use the appropriate Superpowers skill.


THEN:

    Inspect the repository.


THEN determine whether this is:

    new project

or:

    existing project.


THEN establish:

    architecture

    multi-tenancy

    simple folder structure

    design system


before large-scale implementation.


The first architecture should remain:

    React
       |
       v
    Laravel
       |
       +---- MySQL
       |
       +---- Redis


with:

    organization/subdomain multi-tenancy


and future clean integration with:

    Node.js Network Automation

    FreeRADIUS

    Accel-PPP

    GenieACS

    FRR

    iptables / nftables


Do NOT implement those network systems yet.


The frontend MUST NOT look like a generic AI-generated SaaS dashboard.

Remember the mandatory visual prohibition:

NO:

    ❌ gradient everywhere
    ❌ purple/blue SaaS gradient
    ❌ glowing cards
    ❌ glassmorphism everywhere
    ❌ every container rounded-2xl
    ❌ huge border radius
    ❌ enormous dashboard cards
    ❌ random shadows
    ❌ oversized hero headings
    ❌ decorative charts with fake data
    ❌ excessive icons
    ❌ emoji as application icons
    ❌ excessive whitespace
    ❌ unnecessary animations
    ❌ fake metrics
    ❌ generic "Welcome back, Kevin 👋"
    ❌ marketing-page styling inside NOC screens


Build ISP-in-a-Box as professional operational software.

Prioritize:

    clarity
    consistency
    information density
    security
    tenant isolation
    billing correctness
    maintainability
    testability
    operational usefulness


The system must be easy for developers to understand.

The system must be safe for multiple ISP tenants.

The system must be capable of supporting 10,000+ subscribers per ISP.

The system must be ready for future network automation without embedding
network-vendor logic inside the Billing Portal.

Use Superpowers throughout the development lifecycle.

Do not rush ahead of the approved design.

Do not claim completion without verification.
```
