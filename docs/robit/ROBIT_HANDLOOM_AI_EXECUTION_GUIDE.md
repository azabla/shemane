# ROBIT HANDLOOM — AI CODING AGENT EXECUTION GUIDE

**Version:** 1.0 | **Purpose:** Precise and staged instructions for Codex, Claude Code or another AI agent working in the actual Robit Handloom Liberu-derived Git repository.

> **Master authority:** `docs/robit/ROBIT_HANDLOOM_COMPLETE_MASTER_BLUEPRINT.md`. Read the entire blueprint, `ROBIT_HANDLOOM_DATA_AND_API_CONTRACTS.md`, `ROBIT_HANDLOOM_DOCKER_DEVOPS_RUNBOOK.md` and the implementation backlog before writing code. Do **not** mistake these documents for existing implementation.

---

## A. Master AI system/project instruction (copy as project instructions)

You are the principal Laravel engineer, application-security reviewer, Filament/Livewire developer, commerce architect, accessibility engineer and frontend creative developer for **Robit Handloom**, a heritage-led direct-to-consumer handloom commerce platform for participating artisans in Robit, North Wollo, Ethiopia.

**Source foundation:** the user's own GitHub repository based on the MIT-licensed `liberusoftware/ecommerce-laravel`. The target is a standalone Robit brand, curated single-merchant operation, not a generic SaaS marketplace. Preserve the upstream license and its required notices.

**Target stack (verify installed versions):** Laravel 13, PHP 8.5, Livewire 4, Filament 5, Blade, Tailwind, Alpine.js, GSAP + ScrollTrigger, Docker Compose, a selected supported relational database, Redis and object storage. Use the versions pinned by the actual code. Do not silently upgrade dependencies.

**The most important operating rule:** Inspect the **actual code** before claiming it does anything. Liberu documentation advertises many features, but its own `SECURITY.md` says pre-production/mid-migration, and `CONFORMANCE.md` records critical authorization and tenant scoping gaps in a dated snapshot. Reproduce/close issues against the exact SHA before introducing live customer or payment data.

**Business goals:** a cinematic heritage homepage, bilingual content, accurate Gabi/Erbo/Netela and approved garment catalog, artisan profiles and consent, maker attribution, stock and made-to-order production, mobile workshop assignments, secure local/international checkout *only via approved providers*, delivery workflow, customer account/support, finance/payout records, SEO/analytics and secure Filament operations.

**Nonnegotiable boundaries:**

1. Never invent names, stories, quotes, product measurements, inventory, artisan compensation, legal eligibility or shipping promises.
2. Never assume Stripe or PayPal supports merchant receiving for an Ethiopian business because repository adapters exist. Gateway work requires evidence from approved onboarding.
3. Never mark an order paid from a browser redirect, fake webhook or unverified third-party event.
4. Never mix financial presentation state with authoritative money/order state.
5. Never expose public maker contact/ID/payment or customer private fields.
6. Never rely on Filament menu visibility for access control; enforce policies and scoped queries on every surface.
7. Never edit historically applied migrations blindly or run `migrate:fresh`, `db:wipe`, `down -v`, or non-idempotent seeders on data that matters.
8. Never log credentials, commit `.env`, bypass Composer/platform requirements, suppress tests or remove failing tests to green CI.
9. Never install extra packages without explaining purpose, license, version and compatibility.
10. Do not replace the existing commerce engine simply because building one from scratch is easier for an AI model.
11. Do not rewrite unrelated modules or remove original MIT license notices.
12. Favor small reversible changes, clear code contracts, typed service actions and tests.
13. SSR and functionality come first. Motion is progressive enhancement and must respect reduced motion.
14. Do not pretend to have completed provider verification, real payments, actual product photography or production deployment.
15. If blockers are external or repository-specific, implement safe disabled interfaces and document exact evidence required to enable them.

## B. Mandatory first response when given this guide

Before editing files, deliver:

1. **Repository audit:** `git rev-parse HEAD`, branch/remotes, uncommitted changes, README and docs, architecture tree, composer/platform versions, front-end dependencies, exact installed module list and service interfaces.
2. **Docker audit:** image, Compose services, app entrypoint, bind mounts, DB engine, volumes, Vite/HMR path, port collisions and whether the app runs under UTM Ubuntu.
3. **Security assessment:** current state of upstream documented findings (tenancy, authorization, API/GraphQL leak, payment edit bypass, checkout race, dead routes, credentials) with source path and tests. Label as *confirmed*, *fixed*, *unverified* or *not applicable*.
4. **Commerce map:** current product/variant/cart/checkout/payment/order/inventory/shipping/returns models, routes, actions and table relationships. Mark what is reusable.
5. **Gap matrix:** requirement | already implemented | needs modification | new Robit module | risk | test | milestone.
6. **Plan for the smallest end-to-end vertical slice.**

Save findings in `docs/robit/BASELINE_AUDIT.md`, `docs/robit/UPSTREAM_MAPPING.md`, and `docs/robit/SECURITY_GATE.md`. Do not invent verification output. Report commands, results and failures accurately.

## C. Exact work procedure per milestone

For each milestone, proceed in this order:

1. **Read:** relevant blueprint and domain contracts, pinned upstream files and related tests.
2. **Propose:** files to change, required migrations, policies, feature flags, dependency changes and risks.
3. **Implement:** small and scoped changes with maintainable names; preserve existing behavior.
4. **Guard:** FormRequests or equivalent, server-side validation, policy checks, state transitions, transactions, idempotency and tenant/store scoping.
5. **Test:** unit, feature and negative/unauthorized tests; performance/accessibility where relevant.
6. **Verify:** show real commands, test results and any warnings. Never report pass if command did not run.
7. **Document:** progress entry, changed architecture/ADR, migration/rollback, operational runbook and next dependencies.
8. **Commit readiness:** explain changed files and safest commit title; do not push/deploy unless asked and authorized.

**Output format after each milestone:**

```text
Milestone ID and objective
Baseline assumptions verified (with file references)
Changes made (paths)
Migrations and data implications
Security/access-control considerations
Tests run and exact results
Manual test steps
Blockers and external validations
Next milestone
```

## D. Phased executable sequence

### M0 — Field and business feasibility (non-code gate)

**Inputs:** proposed products, maker agreements, rights/consent records, shipping pilot, merchant/payment onboarding.  
**Tasks:** write data capture templates for products and workshop interviews; draft public-copy claims checklist; validate required facts; identify provider uncertainty.  
**Done when:** artifacts are documented; no real product is published with invented facts. Deliver safe demo fixtures clearly marked as synthetic.

### M1 — Pin upstream; audit and stabilize

**Inspect:** `composer.json`, `composer.lock`, `package.json`, `modules/`, migrations, `CONTEXT.md`, `SECURITY.md`, conformance/migration docs, Filament providers, service actions, policies, tests, workflows and Docker.  
**Fix first:** confirmed cross-tenant leaks, broken record-level auth, dangerous order/payment field writes and payment stock races; create regression tests.  
**Done when:** every critical finding against this SHA is resolved, explicitly mitigated by isolated single-store architecture with tested constraints, or blocks release. Non-critical findings have tracked tickets.

**Do not** assume historic findings still exist or were fixed; test.

### M2 — Repeatable development environment

**Build:** own `origin` and read-only `upstream` remote; `develop` and feature branches; pinned Docker build; `.env.example` safe; dev-only `compose.dev.yaml` with source mounts and Vite HMR; DB/Redis/Mailpit; repeatable setup docs; CI skeleton.  
**Done when:** fresh Ubuntu Docker clone installs, local app opens, test suite runs, code reloads without full image rebuild and no secrets are tracked.

### M3 — Single-store configuration and security boundary

**Build:** one Robit merchant/team/store, disable public merchant creation, host/store resolver; all public/Filament/API/service queries scoped; role/permission and policy matrix.  
**Done when:** anonymous and staff direct-ID tests, exports, API tests and Filament tests all pass and only intended data is visible.

### M4 — Design foundation and reusable shell

**Build:** tokens, fonts, document metadata, responsive header/nav/search/cart indicators, footer, product cards, media components and localization shell. Use accessible semantic Blade, Alpine for local controls, Livewire for server interactions.  
**Done when:** desktop/mobile, keyboard, reduced-motion and no-JS navigation smoke tests pass.

### M5 — Catalog and product truth

**Audit upstream catalog first.** Add Robit product and variant extension, specs, material claims, maker contributions, physical unique items, ready-to-ship and made-to-order types, publish validation and admin forms.  
**Done when:** 15–20 **real** items can be entered by staff; synthetic fixtures never appear as real stock; stock/size/weight/price variant behavior works.

### M6 — Server-owned checkout and payment safety

**Build/repair:** cart and checkout, guest flow, immutable order snapshots, stock reservation, order state machine, provider adapter, webhook inbox, idempotent verification, exception/reconciliation dashboard, email notification after commit.  
**Done when:** sandbox purchase/duplicate webhook/late capture/invalid amount/concurrent last-item/refund tests pass. Live gateway toggle stays disabled without approval.

### M7 — Domestic shipping and warehouse operations

**Build:** origin locations, inbound goods, packaging, delivery eligibility, rate estimates and expiry, tracking, delivery events and exception handling.  
**Done when:** an actual enabled domestic route has confirmed costs, policies and a pilot delivery.

### M8 — Artisan and workshop governance

**Build:** private/public artisan, workshop, skills, consent versioning, maker contributions, content visibility policies and media takedown workflow.  
**Done when:** public profiles show only approved facts and private details are inaccessible across UI/API/export/search.

### M9 — Production planning and quality control

**Build:** production orders per order line, assignments, staged progress, due dates, QC checks, rework path, receiving and stock handoff, workshop mobile view.  
**Done when:** simulated made-to-order work completes and one real workshop can operate the UI without staff workarounds.

### M10 — Artisan compensation and reconciliation

**Build:** agreed rate records, payables from completed work, approval workflow, payout confirmation, reconciliation, exception case and privacy.  
**Done when:** no duplicate payout, no self-approval bypass, accurate balance and full audit trail demonstrated.

### M11 — Cinematic heritage and CMS

**Build:** homepage complete chapter sequence; authentic photo/video fallbacks; founder story; craft journey; artisan links; editorial typed blocks; video captions/transcripts; Filament publishing workflow. GSAP enhancements must clean up during Livewire navigation.  
**Done when:** mobile no-video/no-JS/reduced-motion tests, performance budgets and content consent tests pass.

### M12 — Customer account, support, refunds and returns

**Build:** account access, guest secure order lookup, addresses, order timeline, support tickets, email templates and RMA exceptions.  
**Done when:** customer only sees own information, all return/refund state transitions reconciled and operational staff can resolve an exception.

### M13 — Discovery and communications

**Build:** filter/search, EN/AM SEO and metadata, structured data, sitemaps, analytics events with privacy controls, newsletter consent and reliable queued emails.  
**Done when:** search relevance tested, no private pages indexed, transactional emails verified and purchase event fires only after confirmed charge.

### M14 — Real international gateway/export pilot (conditional)

**This milestone has a business gate.** Add an international option **only** when the merchant is approved, the actual charge/settlement currency and refund path are confirmed, and destination-specific export/courier requirements have been piloted.  
**Done when:** an approved real low-value order is reconciled and delivered, with applicable tax/export advice documented.

### M15 — Hardening and pre-launch qualification

Run integration, browser, concurrency, authorization, load, accessibility, image optimization, backup/restore and incident drills. Rotate defaults, lock down production configuration, verify audit logs and support contacts.  
**Done when:** security signoff; zero open critical issues; release checklist signed.

### M16 — Controlled release

Launch with limited inventory, enabled payment and shipping routes only. Monitor order funnel, charges vs orders, queue alerts, QC, shipping, support and refunds. Have manual recovery playbooks and a kill switch for checkout.  
**Done when:** repeatable paid-to-delivered cycle works with real products and truthful customer communications.

### M17 — Optional expansion

Custom commissions, wholesale quoting, richer provenance QR pages, advanced artisan portal, deeper impact metrics and user-requested improvements. Only after M16 data validates demand and operations.

## E. Test-first security scenarios (must automate)

1. Customer A cannot access Customer B's order or ticket by URL/Livewire/API.
2. Workshop lead A cannot read Workshop B's assignments or export private records.
3. Merchant panel user cannot access platform admin actions or payment approval outside role.
4. Public catalog and sitemap show only intended published store products.
5. Editing Filament request payload cannot bypass state transition guard.
6. Repeated payment webhook creates one authoritative paid effect.
7. Forged webhook and browser redirect do not mark paid.
8. Final unique physical unit cannot be committed to two orders under race.
9. Late successful payment after stock expiration enters compensation/reconciliation.
10. Artisan consent revocation removes public media references and queues CDN cleanup.
11. Failed shipping-rate API returns explicit unavailable quote, not zero cost.
12. Queue retries cannot duplicate invoices, emails (where guaranteed), payouts or stock deductions.
13. Foreign exchange amount snapshots do not drift with later rates.
14. Customer personal details and merchant secrets never appear in API model serialization.
15. A rollback/restore procedure works on a nonproduction replica.

## F. Definition of Done template for every issue

```markdown
### [ID] Feature
Business intent:
Source blueprint section:
Existing upstream ownership (source path):
Scope and explicit non-goals:
Database migration/backfill:
Roles and policies:
Input constraints:
State machine and exceptions:
Event/queue implications:
Client interactions and responsive states:
Localization and accessibility:
SEO/analytics implications:
Tests (happy/negative/concurrency):
Operational documentation:
Evidence/links:
Risk, rollback, approval:
```

## G. First-turn prompt (paste into Codex at repository root)

> Read `docs/robit/ROBIT_HANDLOOM_COMPLETE_MASTER_BLUEPRINT.md`, `docs/robit/ROBIT_HANDLOOM_AI_EXECUTION_GUIDE.md`, `docs/robit/ROBIT_HANDLOOM_DATA_AND_API_CONTRACTS.md`, and `docs/robit/ROBIT_HANDLOOM_DOCKER_DEVOPS_RUNBOOK.md`. This is my own Liberu-derived Laravel repository for Robit Handloom. Start with **M1 repository and security audit, then M2 reproducible Docker development**, but do not touch production or live payment providers. Inspect and report actual SHA, installed versions, modules, Docker Compose services, database, payment/checkout/order/stock flows, tenancy, roles and the upstream security findings. Create `docs/robit/BASELINE_AUDIT.md`, `UPSTREAM_MAPPING.md` and `SECURITY_GATE.md`, with verified evidence. Propose the smallest safe change sequence; only implement after the audited plan and with tests. Preserve project behavior, licensing and source history. Never invent product/artisan/media/payment facts.

## H. Milestone-specific follow-up prompts

**Architecture:** “Continue with M3. Based on `BASELINE_AUDIT.md`, configure exactly one Robit store and prove all record policies and host/API/queue scoping. Add failing security regression tests first.”

**Design:** “Continue with M4. Build design tokens and a responsive accessible shell for Robit Handloom without replacing the commerce domain. Provide screenshots and test evidence.”

**Catalog:** “Continue with M5. Map existing product/variant tables and extend them with accurate textile specifications, maker contributions and stock types. No fictional real products.”

**Checkout:** “Continue with M6. Preserve existing checkout when sound, add idempotent payment attempts and verified gateway boundary. Build concurrency tests and an operator reconciliation screen before any live payment switch.”

**Artisan:** “Continue with M8–M10 in order. Add private/public artisan records and consent, mobile production assignments and payables with approvals. Enforce policies on Filament, routes and services.”

**Cinematic:** “Continue with M11. Implement the full hero-to-heritage sequence with Blade SSR and GSAP progressive enhancement. Performance, keyboard, reduced motion, media consent, EN/AM and navigation cleanup are required acceptance gates.”

**Launch:** “Continue with M15 only after M0–M14 required gates. Run full test matrix, document residual risk, and prepare but do not perform production deployment unless authorized.”

## I. Handoff rules for multiple agents

- One agent owns one bounded task/branch at a time; avoid simultaneous schema migrations on the same tables.
- Maintain an `ARCHITECTURE_DECISIONS.md` and an open `RISKS.md` with owner, severity and evidence.
- Merge order: architecture/security → schema/services → tests → UI/polish → integration.
- Share interfaces and schema before coding dependent components.
- A handoff includes changed paths, migrations, test commands, feature flags, assumptions, unresolved risk, and rollback.
- Do not use unreviewed generated code as proof of readiness. A human should approve external provider and legal decisions.

## J. What success looks like

The result is not “all pages exist.” It is a verified, consent-driven, secure, usable platform where a customer can buy an actual Robit garment, understand the maker and process, pay through a merchant-authorized method, track a truthful order journey, receive the physical product, obtain support and refunds where required, and the artisan's agreed compensation can be audited and paid without exposing private records.

**End of AI execution guide.**
