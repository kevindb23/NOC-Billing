MASTER CODEX BUILD PROMPT
ISP-IN-A-BOX
ISP BILLING, NETWORK OPERATIONS & MULTI-VENDOR AUTOMATION PLATFORM
==================================================================


======================================================================
0. ROLE AND PRODUCT
======================================================================

You are the principal software architect and senior full-stack/network
automation engineer responsible for building a production-grade platform:

    ISP-in-a-Box

ISP-in-a-Box is a REAL ISP operational platform.

It combines:

    ISP BILLING
        +
    SUBSCRIBER MANAGEMENT
        +
    NETWORK INVENTORY
        +
    PROVISIONING
        +
    MONITORING
        +
    NETWORK AUTOMATION

This is NOT:

- a tutorial
- a proof of concept
- a static dashboard
- a UI-only mockup
- a generic CRUD application
- a generic SaaS template
- a Huawei-only management system
- a single-vendor OLT management system

The platform will eventually be used by:

- ISP administrators
- NOC engineers
- billing personnel
- customer support
- network engineers
- field technicians
- management

Target scale:

    10,000+ subscribers

The architecture must allow the ISP to change or add network vendors
without rewriting the Billing Portal.


======================================================================
1. DEPLOYMENT MODEL
======================================================================

ISP-in-a-Box is NOT a SaaS multi-tenant application.

DO NOT implement:

    organization_id tenant scoping
    tenant middleware
    hostname-based tenant resolution
    tenant databases
    tenant-aware cache
    tenant-aware queues
    tenant isolation logic

Each ISP/customer deployment is a dedicated ISP-in-a-Box installation.

Example:

    ISP A
        |
        +-- Dedicated ISP-in-a-Box deployment

    ISP B
        |
        +-- Dedicated ISP-in-a-Box deployment

    ISP C
        |
        +-- Dedicated ISP-in-a-Box deployment


Different installations may use domains such as:

    billing.company-a.com

or:

    company-a.example-billing-domain.com

but the domain does NOT determine application tenancy.

The installation itself belongs to that ISP.


======================================================================
2. CORE ARCHITECTURAL REQUIREMENT: MULTI-VENDOR
======================================================================

MULTI-VENDOR SUPPORT IS A FOUNDATIONAL REQUIREMENT.

The application must NOT depend directly on:

    Huawei
    ZTE
    VSOL
    FiberHome
    Nokia
    C-DATA
    MikroTik
    Juniper
    Cisco

inside billing or subscriber business logic.


The system must support multiple vendors simultaneously.

Example deployment:

    OLT-01 = Huawei MA5800

    OLT-02 = ZTE C600

    OLT-03 = VSOL

    OLT-04 = FiberHome


and routers may simultaneously include:

    Juniper MX

    MikroTik CCR

    Cisco

    Linux/FRR


Changing the OLT assigned to a subscriber must NOT require rewriting
subscriber or billing logic.


======================================================================
3. MULTI-VENDOR DESIGN PRINCIPLE
======================================================================

Business logic communicates with CAPABILITIES.

Business logic does NOT communicate with vendor CLI syntax.


Correct:

    Subscriber
        |
        v
    ProvisioningService
        |
        v
    OltManager
        |
        v
    OltDriverInterface
        |
        +----------+----------+----------+
        |          |          |          |
        v          v          v          v
      Huawei      ZTE        VSOL    FiberHome
      Driver      Driver     Driver      Driver


Incorrect:

    SubscriberController
        |
        v
    if vendor == Huawei
        SSH Huawei commands

    if vendor == ZTE
        SSH ZTE commands


Vendor switching must happen through driver selection.


======================================================================
4. SUPERPOWERS IS REQUIRED
======================================================================

This project uses the Superpowers skills for Codex.

You MUST follow the installed Superpowers workflow.

Relevant skills include:

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


Do NOT bypass Superpowers because a task appears simple.


For new architectural work:

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


Do not start implementing a major feature before its design is approved.

Do not claim completion without verification.


======================================================================
5. REQUIRED TECHNOLOGY STACK
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


NETWORK AUTOMATION:

    Node.js
    TypeScript

or isolated automation components where appropriate.

Python may be used for network tooling where a mature Python networking
library provides a strong technical advantage.

Examples:

    Netmiko
    NAPALM
    PySNMP
    NETCONF libraries

However:

    Laravel business logic must remain vendor-neutral.


AAA:

    FreeRADIUS


BNG:

    Accel-PPP


ACS:

    GenieACS


ROUTING:

    FRR where applicable


CGNAT:

Current installations may use:

    iptables

Future/new installations may use:

    nftables


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
    Multiple Linux VMs


DO NOT USE ANGULAR.

DO NOT USE DOCKER unless explicitly approved later.


======================================================================
6. PHYSICAL / SERVICE ARCHITECTURE
======================================================================

Target ISP topology:

                     INTERNET
                        |
                        v
                  JUNIPER MX
                 Internet Edge
                   BGP/Routing
                        |
                        v
                DELL R740 / PVE
                        |
           +------------+-------------+
           |            |             |
           v            v             v
          BNG       ROUTER/CGNAT     CORE
       Accel-PPP       FRR         Billing
                       NAT          NMS
                                   Radius
                                   Redis
                                   MySQL
                                      |
                                      v
                                AUTOMATION
                                  ENGINE
                                      |
                                      v
                                    OLT
                                      |
                                      v
                                    ONU
                                      |
                                      v
                                 SUBSCRIBER


The exact VM boundaries may evolve.

Business responsibilities must remain cleanly separated.


======================================================================
7. APPLICATION ARCHITECTURE
======================================================================

                     REACT
                       |
                       v
                    LARAVEL
                       |
             +---------+---------+
             |                   |
             v                   v
           MySQL               Redis
             |
             |
             +----------------------+
                                    |
                                    v
                            Provisioning Contract
                                    |
                                    v
                         Network Automation Engine
                                    |
             +----------------------+------------------+
             |                      |                  |
             v                      v                  v
            OLT                   ROUTER              ACS
          Drivers                Drivers            Adapter
             |                      |
       +-----+------+          +----+-----+
       |     |      |          |          |
       v     v      v          v          v
    Huawei  ZTE   VSOL      Juniper    MikroTik


======================================================================
8. RESPONSIBILITY RULE
======================================================================

Laravel determines:

    WHAT should happen.


Examples:

    Activate subscriber

    Suspend subscriber

    Change plan

    Assign ONU

    Change service profile


The network automation layer determines:

    HOW to perform it on the target device.


Example:

Laravel:

    provisionOnu(request)


Network automation:

    Device is Huawei MA5800
        ->
    Huawei driver
        ->
    Huawei commands


or:

    Device is ZTE
        ->
    ZTE driver
        ->
    ZTE commands


Laravel should not care.


======================================================================
9. SOURCE OF TRUTH
======================================================================

MySQL is the authoritative persistent source of truth.

MySQL owns:

- customers
- billing accounts
- subscriber services
- plans
- subscriptions
- invoices
- payments
- device inventory
- OLT inventory
- router inventory
- ONU inventory
- IP assignments
- VLAN assignments
- desired service state
- provisioning history
- alarms
- audit history


Redis is NOT the source of truth.

Redis is used for:

- caching
- queues
- distributed locks
- temporary state
- provisioning progress
- rate limiting
- pub/sub
- realtime coordination


Clearing Redis must not destroy authoritative business data.


======================================================================
10. PROJECT STRUCTURE
======================================================================

Use a hybrid structure.

Business modules remain simple.

Network automation uses contracts and vendor drivers.


isp-in-a-box/
|
+-- backend/
|   |
|   +-- app/
|   |   |
|   |   +-- Domain/
|   |   |   |
|   |   |   +-- Subscriber/
|   |   |   +-- Service/
|   |   |   +-- Billing/
|   |   |   +-- Network/
|   |   |   +-- Provisioning/
|   |   |
|   |   +-- Contracts/
|   |   |   |
|   |   |   +-- OltDriverInterface.php
|   |   |   +-- RouterDriverInterface.php
|   |   |   +-- BngDriverInterface.php
|   |   |   +-- AcsAdapterInterface.php
|   |   |
|   |   +-- Provisioning/
|   |   |   |
|   |   |   +-- OltManager.php
|   |   |   +-- RouterManager.php
|   |   |   +-- ProvisioningService.php
|   |   |   +-- DriverRegistry.php
|   |   |   |
|   |   |   +-- DTO/
|   |   |       +-- ProvisionOnuRequest.php
|   |   |       +-- ProvisionOnuResult.php
|   |   |       +-- OpticalPowerResult.php
|   |   |       +-- DeviceStatusResult.php
|   |   |
|   |   +-- Drivers/
|   |   |   |
|   |   |   +-- Olt/
|   |   |   |   |
|   |   |   |   +-- Huawei/
|   |   |   |   |   +-- HuaweiOltDriver.php
|   |   |   |   |   +-- HuaweiCommandBuilder.php
|   |   |   |   |   +-- HuaweiParser.php
|   |   |   |   |
|   |   |   |   +-- Zte/
|   |   |   |   |   +-- ZteOltDriver.php
|   |   |   |   |   +-- ZteCommandBuilder.php
|   |   |   |   |   +-- ZteParser.php
|   |   |   |   |
|   |   |   |   +-- Vsol/
|   |   |   |   |   +-- VsolOltDriver.php
|   |   |   |   |   +-- VsolCommandBuilder.php
|   |   |   |   |   +-- VsolParser.php
|   |   |   |   |
|   |   |   |   +-- FiberHome/
|   |   |   |       +-- FiberHomeOltDriver.php
|   |   |   |       +-- FiberHomeCommandBuilder.php
|   |   |   |       +-- FiberHomeParser.php
|   |   |   |
|   |   |   +-- Router/
|   |   |       |
|   |   |       +-- Juniper/
|   |   |       |   +-- JuniperRouterDriver.php
|   |   |       |   +-- JuniperCommandBuilder.php
|   |   |       |   +-- JuniperParser.php
|   |   |       |
|   |   |       +-- MikroTik/
|   |   |           +-- MikroTikRouterDriver.php
|   |   |           +-- MikroTikCommandBuilder.php
|   |   |           +-- MikroTikParser.php
|   |   |
|   |   +-- Modules/
|   |       |
|   |       +-- Auth/
|   |       +-- Dashboard/
|   |       +-- Customers/
|   |       +-- Subscribers/
|   |       +-- Billing/
|   |       +-- Plans/
|   |       +-- Payments/
|   |       +-- Network/
|   |       +-- OLT/
|   |       +-- ONU/
|   |       +-- Routers/
|   |       +-- BNG/
|   |       +-- IPAM/
|   |       +-- VLAN/
|   |       +-- ACS/
|   |       +-- Monitoring/
|   |       +-- Alarms/
|   |       +-- Provisioning/
|   |       +-- Users/
|   |       +-- Roles/
|   |       +-- Audit/
|   |       +-- Settings/
|   |
|   +-- database/
|   +-- routes/
|   +-- tests/
|
|
+-- frontend/
|   |
|   +-- src/
|       |
|       +-- modules/
|       |   +-- dashboard/
|       |   +-- customers/
|       |   +-- subscribers/
|       |   +-- billing/
|       |   +-- plans/
|       |   +-- payments/
|       |   +-- network/
|       |   +-- olt/
|       |   +-- onu/
|       |   +-- routers/
|       |   +-- bng/
|       |   +-- sessions/
|       |   +-- ipam/
|       |   +-- vlan/
|       |   +-- acs/
|       |   +-- monitoring/
|       |   +-- alarms/
|       |   +-- provisioning/
|       |   +-- users/
|       |   +-- settings/
|       |
|       +-- components/
|       |   +-- ui/
|       |   +-- data/
|       |   +-- layout/
|       |
|       +-- hooks/
|       +-- api/
|       +-- types/
|       +-- utils/
|       +-- routes/
|
|
+-- network-automation/
|   |
|   +-- src/
|       |
|       +-- core/
|       +-- contracts/
|       +-- drivers/
|       +-- transports/
|       +-- parsers/
|       +-- workers/
|       +-- queue/
|       +-- realtime/
|
+-- scripts/
|
+-- docs/
|   +-- ARCHITECTURE.md
|   +-- DESIGN_SYSTEM.md
|   +-- MULTI_VENDOR.md
|   +-- PROVISIONING.md
|   +-- SECURITY.md
|
+-- AGENTS.md
|
+-- README.md


IMPORTANT:

Do not duplicate real vendor implementation in BOTH Laravel and the
Node.js Network Automation Engine.

During the initial portal stage, Laravel contracts/mock drivers may be
used to establish boundaries.

When the production Network Automation Engine is introduced, actual
device communication should move to the automation engine.

Laravel remains the orchestrator/business system.


======================================================================
11. DRIVER CONTRACT
======================================================================

Vendor drivers must implement normalized contracts.

Conceptual example:

interface OltDriverInterface
{
    public function capabilities(): array;

    public function testConnection(): ConnectionResult;

    public function discoverOnus(): array;

    public function getOnuStatus(OnuReference $onu): OnuStatusResult;

    public function getOpticalPower(OnuReference $onu): OpticalPowerResult;

    public function provisionOnu(
        ProvisionOnuRequest $request
    ): ProvisionOnuResult;

    public function deleteOnu(
        DeleteOnuRequest $request
    ): DeleteOnuResult;

    public function rebootOnu(
        RebootOnuRequest $request
    ): RebootOnuResult;
}


This is conceptual.

Use proper types and architecture for the actual implementation.


======================================================================
12. DRIVER MANAGER
======================================================================

The application must NOT instantiate vendor drivers throughout the code.

Use:

    OltManager

and:

    DriverRegistry


Conceptual flow:

    OLT Database Record
        |
        +-- vendor = HUAWEI
        +-- model = MA5800-X2
        +-- driver = huawei_ma5800
        |
        v
    OltManager
        |
        v
    DriverRegistry
        |
        v
    HuaweiOltDriver


Another device:

    OLT Database Record
        |
        +-- vendor = ZTE
        +-- model = C600
        |
        v
    OltManager
        |
        v
    DriverRegistry
        |
        v
    ZteOltDriver


No business code changes.


======================================================================
13. CAPABILITY-BASED DESIGN
======================================================================

Not all OLTs support the same functions.

Do NOT assume every driver can perform every operation.

Drivers must expose capabilities.

Examples:

    ONU_DISCOVERY

    ONU_PROVISION

    ONU_DELETE

    ONU_REBOOT

    ONU_STATUS

    OPTICAL_POWER

    SERVICE_VLAN

    CUSTOMER_VLAN

    QINQ

    BANDWIDTH_PROFILE

    TR069_MANAGEMENT

    OMCI_WAN

    SSH

    SNMP

    NETCONF

    REST_API

    NATIVE_API


UI and business logic should check capabilities.

Example:

If an OLT does not support:

    ONU_REBOOT

the UI should not blindly expose a functional Reboot button.


======================================================================
14. TRANSPORT IS NOT THE DRIVER
======================================================================

Separate:

    WHAT DEVICE DOES

from:

    HOW WE CONNECT TO DEVICE.


Transports may include:

    SSH
    SNMP
    NETCONF
    REST API
    Vendor API
    Telnet only when unavoidable


Example:

    HuaweiOltDriver
           |
           v
       SshTransport


Another future driver:

    NokiaOltDriver
           |
           v
       NetconfTransport


Do not duplicate SSH connection management inside every driver.


======================================================================
15. COMMAND BUILDERS
======================================================================

Vendor-specific command generation belongs inside vendor implementations.

Example:

    HuaweiCommandBuilder

may understand Huawei syntax.

    ZteCommandBuilder

may understand ZTE syntax.


Business logic must never build CLI strings.


Correct:

    ProvisioningService
         |
         v
    driver.provisionOnu(dto)


Incorrect:

    ProvisioningService
         |
         v
    "ont add 0/1/3 ..."


======================================================================
16. PARSERS
======================================================================

CLI parsing must be vendor-specific.

Example:

    HuaweiParser
    ZteParser
    VsolParser


Parsers convert raw vendor output into normalized objects.


Example raw Huawei output:

    vendor-specific text


becomes:

    OnuStatusResult {
        status: ONLINE,
        serial: "...",
        rxPower: -19.8,
        txPower: ...
    }


The rest of the system consumes normalized data.


======================================================================
17. NORMALIZED DTOs
======================================================================

Vendor output must NOT leak throughout the application.

Use normalized DTOs/results.

Examples:

    DeviceStatusResult

    OnuStatusResult

    OpticalPowerResult

    ProvisionOnuRequest

    ProvisionOnuResult

    DeleteOnuResult

    InterfaceStatusResult


Do not return random associative arrays with different structures from
different vendors.


======================================================================
18. VENDOR RAW DATA
======================================================================

Normalized data should be used for application logic.

Raw device output may optionally be retained for:

    troubleshooting
    diagnostics
    parser debugging
    audit

but it must not become the primary application data model.


======================================================================
19. OLT MODEL
======================================================================

Use generic:

    Olt


NOT:

    HuaweiOlt


OLT fields should include:

- UUID
- site
- POP
- name
- hostname
- management IP
- vendor
- model
- software version
- serial number
- access technology
- driver identifier
- preferred transport
- status
- capabilities
- last contact
- last synchronization
- notes
- metadata


Vendor examples:

    HUAWEI
    ZTE
    VSOL
    FIBERHOME
    NOKIA
    CDATA
    OTHER


Vendor is DATA.

It is not the application architecture.


======================================================================
20. MIXED OLT ENVIRONMENT
======================================================================

The same deployment must support:

    Huawei OLT
        +
    ZTE OLT
        +
    VSOL OLT

simultaneously.


Example:

    POP MANILA
        |
        +-- OLT-MNL-01
        |      Huawei MA5800
        |
        +-- OLT-MNL-02
        |      ZTE C600
        |
        +-- OLT-MNL-03
               VSOL


The subscriber workflow must remain the same.


======================================================================
21. ACCESS TECHNOLOGIES
======================================================================

Support:

    GPON
    EPON
    XPON
    XGS_PON
    OTHER


Do not assume GPON only.


======================================================================
22. ONU / ONT MODEL
======================================================================

Use generic:

    Onu


The system may display ONU or ONT terminology where appropriate.

Fields:

- UUID
- OLT
- PON
- ONU identifier
- serial number
- MAC address where applicable
- vendor
- model
- technology
- subscriber assignment
- service profile
- desired state
- observed state
- status
- last seen
- last synchronization
- metadata


Do not create:

    HuaweiOnu
    ZteOnu

as separate business entities.


======================================================================
23. MIXED ONU VENDORS
======================================================================

Do NOT assume:

    Huawei OLT = Huawei ONU only.


The architecture must tolerate supported mixed-vendor combinations.

Compatibility is determined by:

    OLT capabilities
    ONU capabilities
    technology
    OMCI/interoperability
    driver support


Do not hard-code simplistic vendor matching.


======================================================================
24. OMCI IS NOT UNIVERSAL
======================================================================

Do not assume every access technology uses OMCI identically.

Examples:

    GPON / XGS-PON may use OMCI

    EPON has different management mechanisms

    XPON ONUs may support multiple modes


Provisioning must use capability-driven behavior.


======================================================================
25. ROUTER MULTI-VENDOR ARCHITECTURE
======================================================================

Routers must use the same philosophy.

Create:

    RouterDriverInterface

    RouterManager

    RouterDriverRegistry


Potential drivers:

    Juniper
    MikroTik
    Cisco
    LinuxFRR


Normalized operations may eventually include:

    getInterfaces()

    getInterfaceStatus()

    getBgpNeighbors()

    getRoutes()

    getSystemInfo()

    getTrafficCounters()

    applyPrefixPolicy()

    configureVlan()

    configureSubinterface()


Do NOT implement all of these immediately.

Add operations only when required.


======================================================================
26. ROUTER DRIVER EXAMPLE
======================================================================

Correct:

    NetworkService
         |
         v
    RouterManager
         |
         v
    RouterDriverInterface
         |
       +-+--------+
       |          |
       v          v
    Juniper    MikroTik


Incorrect:

    if router.vendor == "juniper":
        run Junos commands

inside controllers.


======================================================================
27. BNG ABSTRACTION
======================================================================

BNG should also be abstracted.

Use generic:

    Bng

and eventually:

    BngDriverInterface


Current implementation:

    Accel-PPP


Future implementation may change.

Billing must not depend directly on Accel-PPP internals.


======================================================================
28. ACS ABSTRACTION
======================================================================

Use:

    AcsAdapterInterface


Current implementation:

    GenieACS


Business logic should request operations such as:

    getDevice()

    refreshDevice()

    setParameter()

    rebootCpe()


rather than depending everywhere on GenieACS-specific APIs.


======================================================================
29. PROVISIONING WORKFLOW
======================================================================

Provisioning must be asynchronous.

Expected future flow:

    React
       |
       v
    Laravel
       |
       v
    Validate Subscriber
       |
       v
    Determine Desired State
       |
       v
    Create ProvisioningJob
       |
       v
    Redis Queue
       |
       v
    Automation Worker
       |
       v
    Determine Target Device
       |
       v
    Driver Registry
       |
       v
    Correct Vendor Driver
       |
       v
    Device
       |
       v
    Verify
       |
       v
    Normalized Result
       |
       v
    Laravel / MySQL
       |
       v
    React


Do not make the HTTP request wait while an OLT is being configured.


======================================================================
30. PROVISIONING STATE MACHINE
======================================================================

Use states such as:

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
31. IDEMPOTENCY
======================================================================

Provisioning operations must be designed to be idempotent.

If:

    Provision ONU

is retried after a timeout, the system must first determine whether the
desired configuration already exists.

Do not blindly duplicate configuration.


======================================================================
32. LOCKING
======================================================================

Protect concurrent operations.

Potential lock scopes:

    OLT

    PON

    ONU

    Subscriber


Example:

    provisioning:olt:{olt_uuid}:pon:{pon_id}


Use Redis distributed locks where appropriate.


======================================================================
33. DESIRED VS OBSERVED STATE
======================================================================

Laravel owns desired service state.

Example:

    Service:
        ACTIVE

    Plan:
        HOME-500

    IP Mode:
        CGNAT


Network integrations report observed state.

Example:

    ONU:
        ONLINE

    PPPoE:
        ONLINE

    IPv4:
        100.64.10.22


Never assume:

    request submitted = network configured.


======================================================================
34. CUSTOMER MODEL
======================================================================

Customer represents a person/company receiving services.

Support:

- customer number
- customer type
- first name
- middle name
- last name
- company name
- email
- mobile
- alternate contact
- billing address
- installation address
- status
- notes


Customer types:

    RESIDENTIAL
    BUSINESS
    ENTERPRISE
    WHOLESALE


======================================================================
35. CUSTOMER IS NOT THE SERVICE
======================================================================

Do NOT assume:

    one customer = one Internet service.


Use:

    Customer
       |
       +-- Billing Account
       |
       +-- Subscriber Service #1
       |
       +-- Subscriber Service #2


======================================================================
36. BILLING ACCOUNT
======================================================================

Create BillingAccount.

Support:

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
37. SUBSCRIBER SERVICE
======================================================================

Subscriber Service represents the actual service.

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


Use unique service numbers.

Example:

    SRV-00001234


======================================================================
38. STATUS SEPARATION
======================================================================

Do not use one generic status.


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


======================================================================
39. SERVICE PLANS
======================================================================

Plan fields:

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
- provisioning profile reference


Do NOT store:

    Huawei commands
    ZTE commands
    MikroTik commands
    Juniper commands

inside service plans.


======================================================================
40. NORMALIZED SERVICE PROFILE
======================================================================

Service profiles describe INTENT.

Example:

    HOME-500

    download:
        500000 kbps

    upload:
        500000 kbps

    internet:
        enabled

    ip_mode:
        CGNAT


Vendor drivers translate this into device-specific implementation.

Example:

    HOME-500
        |
        v
    Generic Service Intent
        |
        +----------------+
        |                |
        v                v
    Huawei Driver     ZTE Driver
        |                |
        v                v
    Huawei Profile    ZTE Profile


======================================================================
41. BILLING ENGINE
======================================================================

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


Financial history must be traceable.


======================================================================
42. MONEY
======================================================================

Never use floating point for money.

Use:

    DECIMAL

or a clearly documented minor-unit integer strategy.


Initial currency:

    PHP


======================================================================
43. INVOICES
======================================================================

Invoice fields:

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


======================================================================
44. PAYMENTS
======================================================================

Support:

    CASH
    BANK_TRANSFER
    GCASH
    MAYA
    CARD
    ONLINE_GATEWAY
    OTHER


Support:

    partial payment
    full payment
    multiple invoice allocation
    overpayment


Use PaymentAllocation records.


======================================================================
45. BILLING AND NETWORK SEPARATION
======================================================================

Billing determines business intent.

Example:

    Account becomes overdue
            |
            v
    Service suspension eligible


Billing does NOT:

    SSH to OLT

    disconnect PPPoE itself

    modify router itself


Instead:

    Billing
       |
       v
    Service Intent
       |
       v
    Provisioning
       |
       v
    Network Automation


======================================================================
46. NETWORK INVENTORY
======================================================================

Create normalized inventory for:

    Sites
    POPs
    OLTs
    PONs
    ONUs
    Routers
    BNGs
    IP Pools
    VLANs
    ACS Servers


Navigation should allow:

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


and reverse navigation.


======================================================================
47. IPAM
======================================================================

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


Use database constraints and transactions to prevent duplicate
assignment.


======================================================================
48. IP SERVICE MODES
======================================================================

Support:

    CGNAT

    PUBLIC_IPV4

    IPV6_NATIVE

    DUAL_STACK


Do not assume every subscriber uses CGNAT.


======================================================================
49. VLAN
======================================================================

Support:

    VLAN
    C-VLAN
    S-VLAN
    QinQ
    Management VLAN
    Service VLAN


Do not hard-code VLAN values into controllers or vendor-independent
services.


======================================================================
50. MONITORING
======================================================================

Monitoring belongs inside ISP-in-a-Box.

Do not create a completely unrelated monitoring portal.


The operator should eventually see:

    Subscriber
    Billing
    Network
    Monitoring
    Provisioning

inside one interface.


======================================================================
51. MONITORING DATA SEPARATION
======================================================================

Separate:

    INVENTORY

    CURRENT STATE

    HISTORICAL TELEMETRY


Example:

Inventory:

    ONU exists.


Current:

    ONLINE
    RX -19.8 dBm


Historical:

    RX power history for 30 days.


======================================================================
52. REACT FRONTEND
======================================================================

Use:

    React
    TypeScript
    Vite
    Tailwind CSS


DO NOT USE ANGULAR.


Use strict TypeScript.

Avoid:

    any

unless technically justified.


======================================================================
53. FRONTEND MODULES
======================================================================

Use feature-oriented frontend modules.

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


Do not create giant React components.


======================================================================
54. SHARED COMPONENTS
======================================================================

Create shared components such as:

frontend/src/components/

    ui/
        Button
        Input
        Select
        Checkbox
        Badge
        Tooltip
        Dialog
        Dropdown
        Tabs
        Pagination
        Skeleton
        Toast

    data/
        DataTable
        FilterBar
        SearchInput
        StatusBadge
        EmptyState
        ErrorState

    layout/
        AppShell
        Sidebar
        Topbar
        PageHeader
        PageSection


Pages must reuse existing primitives.

Do not invent a different visual language for every feature.


======================================================================
55. DESIGN SYSTEM FIRST
======================================================================

Before building dozens of screens, create:

    docs/DESIGN_SYSTEM.md


Define:

- typography
- font sizes
- font weights
- line heights
- spacing
- page density
- sidebar
- topbar
- borders
- radius
- shadows
- colors
- backgrounds
- status colors
- tables
- forms
- buttons
- dialogs
- tabs
- badges
- pagination
- loading
- empty states
- error states
- dark mode
- responsive behavior
- icons
- charts


Do not allow every feature to invent its own styling.


======================================================================
56. PRODUCT VISUAL IDENTITY
======================================================================

Visual reference:

    Network Operations Center

    ISP OSS/BSS

    Enterprise Network Management

    Infrastructure Management

    Billing Operations


NOT:

    AI startup

    Crypto dashboard

    Fintech landing page

    Generic SaaS template

    Marketing website


The product must look like serious operational software.


======================================================================
57. ANTI-AI-SLOP DESIGN RULES — MANDATORY
======================================================================

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


These are mandatory restrictions.

They are NOT loose suggestions.


======================================================================
58. ADDITIONAL AI-SLOP PROHIBITIONS
======================================================================

Avoid:

- excessive pills
- excessive badges
- giant KPI numbers
- meaningless gradients
- decorative blobs
- background meshes
- floating decorative elements
- card-inside-card-inside-card layouts
- excessive shadows
- excessive blur
- random accent colors
- inconsistent spacing
- inconsistent border radius
- mixed icon styles
- fake network health
- fake subscriber numbers
- fake revenue
- fake bandwidth
- fake charts
- marketing copy
- unnecessary motivational text


Do not optimize the UI for screenshots.

Optimize it for ISP operations.


======================================================================
59. UI DENSITY
======================================================================

This is operational software.

Use screen space efficiently.

Primary targets:

    1080p NOC workstation
    1440p workstation
    multi-monitor NOC environment


Prefer:

    tables
    filters
    status
    useful data
    drill-down navigation


over:

    giant decorative cards.


Example:

    Subscribers                                  + Add Subscriber
    ----------------------------------------------------------------
    Search...    Status    Plan    OLT            Filters

    Service      Customer       Plan       Status       IP
    ----------------------------------------------------------------
    SRV-00124    Juan Cruz      HOME-500   Online       100.64.1.24
    SRV-00125    Mark Reyes     HOME-300   Online       100.64.1.25
    SRV-00126    Anna Santos    HOME-500   Offline      -
    ----------------------------------------------------------------

    1-50 of 10,241                       < 1 2 3 ... 205 >


======================================================================
60. NO FAKE DATA
======================================================================

Never fabricate operational data.

Do NOT invent:

    ONU Online 9,841

    Network Health 99.98%

    Revenue PHP 8.2M

    Bandwidth 82.4 Gbps


If unavailable:

    -

    Not available

    No data

    Integration not connected


Seed data is allowed only in development and must clearly be
development data.


======================================================================
61. SUBSCRIBER DETAIL
======================================================================

This is a critical screen.

Tabs:

    OVERVIEW

    BILLING

    SERVICE

    NETWORK

    SESSIONS

    CPE

    PROVISIONING

    ACTIVITY


The goal is:

    one subscriber
        |
        +-- billing
        +-- plan
        +-- service
        +-- PPPoE
        +-- IP
        +-- ONU
        +-- PON
        +-- OLT
        +-- optical
        +-- CPE
        +-- provisioning
        +-- history


without forcing the NOC operator to use many separate applications.


======================================================================
62. TABLES
======================================================================

Tables are first-class components.

Support:

- server-side pagination
- sorting
- search
- filtering
- loading
- empty states
- error states
- column visibility
- row actions
- bulk actions where appropriate


Never load 10,000 subscribers into the browser at once.


======================================================================
63. API
======================================================================

Use:

    /api/v1


Examples:

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

    /api/v1/routers

    /api/v1/bngs

    /api/v1/sessions

    /api/v1/ip-pools

    /api/v1/vlans

    /api/v1/alarms

    /api/v1/provisioning/jobs


Use consistent response structures.


======================================================================
64. AUTHENTICATION / RBAC
======================================================================

Implement secure authentication.

Support:

- login
- logout
- current user
- password changes
- disabled accounts
- rate limiting


Use granular permissions.

Examples:

    customer.view
    customer.create
    customer.update

    subscriber.view
    subscriber.create
    subscriber.activate
    subscriber.suspend

    billing.view
    billing.manage

    payment.create

    network.view

    olt.view
    olt.manage

    onu.view
    onu.provision
    onu.reboot
    onu.delete

    router.view
    router.manage

    provisioning.view
    provisioning.retry

    alarm.acknowledge

    audit.view


Backend authorization is authoritative.


======================================================================
65. AUDIT LOGGING
======================================================================

Audit important operations.

Examples:

    subscriber.created

    subscriber.activated

    subscriber.suspended

    invoice.generated

    payment.recorded

    onu.assigned

    onu.provisioned

    onu.deleted

    router.configuration.requested

    provisioning.failed


Record:

- actor
- action
- target
- target ID
- timestamp
- correlation ID
- old state where appropriate
- new state where appropriate
- result


======================================================================
66. SECURITY
======================================================================

Never commit:

    .env

    database credentials

    OLT passwords

    router passwords

    SNMP communities

    SSH private keys

    ACS credentials

    RADIUS secrets

    API tokens


Credentials must be securely stored.

Never expose device credentials to React.


Frontend:

    React

must NEVER directly connect to:

    OLT
    Router
    BNG
    Redis
    MySQL
    FreeRADIUS
    GenieACS


======================================================================
67. PERFORMANCE
======================================================================

Design for:

    10,000+ subscribers


Use:

- database indexes
- server-side pagination
- efficient queries
- eager loading
- caching
- background jobs
- queue workers
- aggregation


Avoid N+1 queries.


Do not continuously poll thousands of devices through Laravel HTTP
requests.


======================================================================
68. TESTING
======================================================================

Testing is mandatory.

BACKEND:

    Unit Tests
    Feature Tests
    API Tests
    Authorization Tests
    Billing Tests
    Payment Tests
    Provisioning Contract Tests


DRIVERS:

Each vendor driver must have tests using captured/sanitized fixtures.

Example:

    tests/Drivers/Olt/Huawei/

    tests/Drivers/Olt/Zte/

    tests/Drivers/Olt/Vsol/


Parser tests are especially important.


======================================================================
69. DRIVER CONTRACT TESTS
======================================================================

Every OLT driver must pass the same normalized contract tests where its
capabilities apply.

Example:

    Huawei getOnuStatus()
           |
           v
    OnuStatusResult


    ZTE getOnuStatus()
           |
           v
    OnuStatusResult


Both results must have the same normalized semantic structure.


This allows vendor switching without changing business logic.


======================================================================
70. PARSER FIXTURES
======================================================================

Do not test CLI parsers against imaginary outputs.

Use sanitized real device output when available.

Store fixtures such as:

    tests/Fixtures/Huawei/MA5800/

    tests/Fixtures/Zte/C600/


Remove:

    credentials
    public secrets
    sensitive subscriber data


Parser tests should detect vendor firmware/output changes.


======================================================================
71. NATIVE DEPLOYMENT
======================================================================

Production:

    Dell R740
       |
       v
    Proxmox
       |
       +-- CORE VM
       |
       +-- BNG VM
       |
       +-- ROUTER/CGNAT VM
       |
       +-- AUTOMATION VM
       |
       +-- ACS VM
       |
       +-- Optional Tunnel/Proxy VM


Do not use Docker unless explicitly approved later.

Use systemd for persistent services.


======================================================================
72. INTERNET ACCESS
======================================================================

The portal may be exposed through:

    HTTPS domain
        |
        v
    Secure reverse proxy/tunnel
        |
        v
    Nginx
        |
        v
    React/Laravel


The application must work behind a trusted proxy.

Do not hard-code:

    localhost
    public IP
    HTTP scheme
    customer-specific domain


======================================================================
73. AGENTS.MD
======================================================================

Create:

    AGENTS.md


It is the permanent engineering constitution.

Include concise rules for:

    Project Purpose

    Superpowers Workflow

    Technology Stack

    Architecture

    Multi-Vendor Rules

    Driver Contracts

    Provisioning

    Laravel

    React

    Billing Integrity

    Security

    Testing

    Anti-AI-Slop UI

    Definition of Done


Do not simply duplicate this entire prompt.


======================================================================
74. MULTI_VENDOR.md
======================================================================

Create:

    docs/MULTI_VENDOR.md


Document:

- driver architecture
- contracts
- driver registry
- capabilities
- transports
- DTOs
- command builders
- parsers
- error normalization
- adding a new vendor
- testing a new vendor
- firmware compatibility


A developer adding a new OLT vendor should be able to follow this
document.


======================================================================
75. ADDING A NEW OLT VENDOR
======================================================================

The desired future workflow is:

    Add new vendor
        |
        v
    Create driver folder
        |
        v
    Implement OltDriverInterface
        |
        v
    Implement capabilities
        |
        v
    Implement parser
        |
        v
    Implement command/API adapter
        |
        v
    Add fixtures
        |
        v
    Pass contract tests
        |
        v
    Register driver
        |
        v
    DONE


It should NOT require modifications to:

    Billing

    Customer

    Subscriber

    Invoice

    Payment

    React Subscriber Business Logic


This is a critical acceptance criterion.


======================================================================
76. REPRESENTATIVE UI APPROVAL
======================================================================

Do not generate 30 pages before establishing the visual system.

First design:

    App Shell

    Sidebar

    Topbar

    Dashboard

    Subscriber List

    Subscriber Detail

    Invoice List

    Invoice Detail

    DataTable

    Form

    Dialog


Present representative UI for approval.

After approval:

    document patterns

and:

    reuse them.


Do not redesign each page independently.


======================================================================
77. IMPLEMENTATION PHASES
======================================================================

PHASE 0

    Repository inspection

    Superpowers brainstorming

    Architecture

    Design system

    Multi-vendor architecture


PHASE 1

    Laravel

    React

    TypeScript

    Vite

    Tailwind

    MySQL

    Redis

    Authentication

    RBAC


PHASE 2

    Customers

    Billing Accounts

    Plans

    Subscriber Services


PHASE 3

    Billing

    Invoices

    Payments

    Allocations

    Credits

    Adjustments


PHASE 4

    Sites

    POPs

    OLT Inventory

    PON Inventory

    ONU Inventory

    Router Inventory

    BNG Inventory


PHASE 5

    IPAM

    VLAN

    Subscriber Network Assignment


PHASE 6

    Unified Subscriber Workspace


PHASE 7

    Provisioning Contracts

    DTOs

    Driver Registry

    Mock Driver


PHASE 8

    First Real OLT Driver

Prefer the actual first deployment vendor.

Do not prematurely implement every vendor.


PHASE 9

    Additional OLT Drivers


PHASE 10

    Router Drivers


PHASE 11

    GenieACS

    FreeRADIUS

    Accel-PPP integrations


PHASE 12

    Monitoring

    Realtime state

    Alarms


======================================================================
78. FIRST MULTI-VENDOR MILESTONE
======================================================================

Before building Huawei-specific production automation, prove the
abstraction.

Implement:

    OltDriverInterface

        |
        +-- MockHuaweiDriver
        |
        +-- MockZteDriver


Both must support normalized operations.

Then prove:

    Subscriber provisioning logic

does NOT change when switching:

    Huawei
       ->
    ZTE


Only after the abstraction is proven should real device automation be
implemented.


======================================================================
79. DO NOT OVERENGINEER
======================================================================

Do NOT introduce without demonstrated need:

    Kubernetes

    Kafka

    RabbitMQ

    Elasticsearch

    service mesh

    unnecessary microservices


Likewise, do not create abstractions merely because an AI architecture
pattern suggests them.

Abstractions are justified where they solve a real requirement.

MULTI-VENDOR DEVICE DRIVERS are a justified abstraction.

A 12-layer Customer CRUD implementation is not.


======================================================================
80. DEFINITION OF DONE
======================================================================

A feature is NOT complete because code was generated.

Use:

    verification-before-completion


Verify where applicable:

    Laravel tests

    driver tests

    parser tests

    migrations

    authorization

    TypeScript

    frontend tests

    lint

    React production build

    API integration


For network integrations also verify:

    normalized results

    timeout handling

    retry behavior

    idempotency

    unsupported capability behavior

    sanitized logging


Do not claim:

    Done

    Fixed

    Working

    Production Ready


without verification evidence.


======================================================================
81. CODE REVIEW
======================================================================

Use the Superpowers review workflow after meaningful milestones.

Review for:

    correctness

    billing integrity

    security

    unsafe device commands

    credential leakage

    vendor leakage into business logic

    duplicated vendor logic

    parser fragility

    timeout handling

    retry safety

    idempotency

    N+1 queries

    oversized components

    unnecessary abstractions

    missing tests

    AI-slop UI


======================================================================
82. FINAL ARCHITECTURAL TEST
======================================================================

Whenever implementing a network feature, ask:

    "If this Huawei OLT is replaced tomorrow by a ZTE OLT,
     how much of this code changes?"


The desired answer:

    Driver implementation
    Driver registration/configuration
    Possibly capability/profile mapping


The answer should NOT be:

    Subscriber module
    Billing module
    Customer module
    Invoice module
    React application
    entire provisioning workflow


Similarly ask:

    "If Juniper is replaced by MikroTik,
     does subscriber business logic change?"


The desired answer is:

    NO.


======================================================================
83. FINAL SYSTEM TARGET
======================================================================

The operator should eventually be able to open one subscriber and see:

    CUSTOMER
       |
       v
    BILLING
       |
       v
    SUBSCRIPTION
       |
       v
    SERVICE
       |
       v
    PPPOE
       |
       v
    IP ADDRESS
       |
       v
    ONU
       |
       v
    PON
       |
       v
    OLT
       |
       v
    CPE
       |
       v
    PROVISIONING
       |
       v
    MONITORING


The operator should NOT need to care whether the underlying OLT is:

    Huawei
    ZTE
    VSOL
    FiberHome


for normal business workflows.


======================================================================
84. STARTING INSTRUCTION
======================================================================

START by using the appropriate Superpowers skill.

Do NOT immediately generate the entire application.


FIRST:

    Use Superpowers.


THEN:

    Inspect the repository.


THEN:

    Understand existing code.


THEN:

    Design the architecture.


THEN:

    Establish the multi-vendor contracts.


THEN:

    Establish the design system.


THEN:

    obtain required human approval.


THEN:

    create the implementation plan.


THEN:

    implement incrementally using TDD.


Do not begin by implementing Huawei commands.


The first network automation objective is NOT:

    "Make Huawei work."


The first objective is:

    "Create a clean vendor-neutral architecture in which Huawei,
     ZTE, VSOL, FiberHome and future vendors can be plugged in
     without rewriting ISP-in-a-Box."


Remember the mandatory UI restrictions:


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


Build professional ISP operational software.

Prioritize:

    multi-vendor compatibility

    vendor-neutral business logic

    clear driver contracts

    capability-based behavior

    normalized data

    billing correctness

    network safety

    maintainability

    security

    testing

    operational clarity

    information density


The system must allow new OLT and router vendors to be added with
minimal changes outside the new driver's implementation.

That is one of the primary architectural acceptance criteria for
ISP-in-a-Box.