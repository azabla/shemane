---
title: Robit Handloom — Complete Product, Design, Engineering and Operations Blueprint
subtitle: Heritage storytelling, artisan-first commerce and production operations on Liberu Ecommerce
version: 1.0
status: Implementation specification; not deployed software
prepared: 2026-11-11
baseline: Liberu Ecommerce (verify pinned Git revision before implementation)
---

# ROBIT HANDLOOM
## Complete Product, Design, Engineering and Operations Blueprint

**Edition 1.0 — 11 November 2026**  
**Purpose:** Single authoritative implementation specification for the *entire* Robit Handloom business platform, not just the homepage hero.  
**Target:** A standalone, curated, single-merchant, heritage-led direct-to-consumer commerce experience built on the MIT-licensed Liberu Ecommerce repository, with Laravel, Livewire, Filament, Tailwind, Alpine.js, GSAP, Docker and a relational database.  
**Status:** A design and implementation plan. No claim is made that the features described here already exist, have been deployed, or have been tested in the user's repository.

> **Read this first:** Liberu's published `SECURITY.md` describes its current repository as *pre-production and mid-migration*. Its recorded `CONFORMANCE.md` snapshot identifies authorization, tenancy, API exposure, checkout, and structural issues. The current checkout may have resolved some of these, but **never launch real payments or public customer data without repeating the audit on the exact pinned commit and proving fixes with tests**. Read `SECURITY.md`, `docs/CONFORMANCE.md`, `docs/MIGRATION_PLAN.md`, and `docs/OPERATIONS.md` in the repository before modification.

---

# 0. How to use this specification

This blueprint is written for the owner, an engineering team, and AI coding agents. It is structured so that requirements can become issue tickets, migrations, tests and implementation phases.

**Companion files:**

- `ROBIT_HANDLOOM_AI_EXECUTION_GUIDE.md`: precise operating instructions, audit-first workflow and milestone prompts for Codex or another AI agent.
- `ROBIT_HANDLOOM_DATA_AND_API_CONTRACTS.md`: proposed data dictionary, relationships, integrity constraints, API contracts, example events and state transitions.
- `ROBIT_HANDLOOM_DOCKER_DEVOPS_RUNBOOK.md`: reproducible Docker development, Git, environment, backups, deployment and operational troubleshooting.
- `ROBIT_HANDLOOM_IMPLEMENTATION_BACKLOG.csv`: prioritized trackable implementation work with acceptance evidence.

**Interpretation of statements:**

- **MUST:** required for launch or safety.
- **SHOULD:** strongly recommended.
- **LATER:** intentionally out of initial launch scope.
- **VERIFY:** an external or repository-specific fact that must be proven; not an assumption.
- **P0/P1/P2:** launch critical / near-term / expansion.
- All source-code paths, class names and tables in the proposed design are **design targets**, not assertions that Liberu currently uses those names. A repository audit must map to real names first.

**Sequence:** (1) verify legal/payment/logistics feasibility, (2) pin and audit Liberu, (3) make Docker dev work, (4) harden baseline, (5) deliver one end-to-end sale, (6) add artisan and editorial layers, (7) validate security/performance, (8) controlled launch, (9) expand.

---

# 1. Founding vision, problem and outcomes

## 1.1 The founder's perspective

The project originates from personal experience growing up in **Robit, North Wollo, Amhara Region, Ethiopia**, and participating in making garments such as *Gabi* and *Erbo*. Traditional handloom weaving (*shimninna*) is woven into the town's working life, including cotton preparation, spinning, making and setting up looms, weaving fabrics, and patterned finishing (*tilet*). This is a founder perspective, not permission to publish particular people's names, likenesses or cultural claims without review.

## 1.2 Core problem

Artisans often depend on intermediaries and have limited systematic access to customers outside their locality. Informal social selling may lack trusted specifications, inventory, secure payment, shipping clarity, order history and accessible product storytelling. Customers lack an obvious, reliable path from discovery to purchase. The actual market size, margins, proportion of handloom income and demand from diaspora groups **must be validated in fieldwork**; do not turn founder observations into fabricated quantitative claims.

## 1.3 Mission and promise

**Mission:** Make Robit's weaving heritage visible, documented and commercially accessible while providing measurable value and transparent compensation to the people who make the textiles.

**Suggested positioning:** *Woven in Robit. Carried Across Generations.*  
**Suggested hero line:** *Every Thread Has a Story.*  
**Secondary copy:** *Traditional handwoven textiles from Robit, Ethiopia—discover the craft, meet the makers and shop with clarity.*

**Customer promise:** accurate, maker-attributed garments, transparent product specifications, clear production and delivery expectations, reachable customer support, and secure checkout using verified providers.

**Artisan promise:** voluntary participation, approved stories and media, agreed compensation, predictable records, fair issue handling and private financial data.

## 1.4 Success measures and non-goals

**North-star metric:** fulfilled orders meeting promised standards while preserving agreed artisan economics. Supporting measures: order conversion, checkout completion, paid-to-delivered lead time, on-time production, refunds, damaged shipments, artisan payout timeliness, catalog data completeness, repeat purchases, consent compliance and Core Web Vitals.

**Do not optimize solely for:** page animation, visitor counts, gross merchandise value without contribution margin, or social traffic without fulfillment capability.

**Initial model:** curated single merchant. NOT open multi-vendor marketplace, instant artisan payouts, international 'everywhere' shipping, AI-generated artisan identities, or speculative certifications. Those require validated operations and separate releases.

---

# 2. Audience and user journeys

## 2.1 Primary users

| Persona | Need | Critical experience |
|---|---|---|
| Diaspora shopper | Confidence, authenticity, international fulfillment | Heritage → products → verified payment → trackable shipping |
| Ethiopia-based shopper | ETB pricing, accessible delivery, local payment | Mobile browse → local address → supported payment |
| Ceremonial/custom buyer | Special patterns, quantity, lead time | Inquiry → quote → approval → production |
| First-time cultural visitor | Meaning and context | Documentary home → craft chapters → artisan stories |
| Artisan | Fair attribution and agreed compensation | Consented profile, job details, completed work and payout records |
| Workshop lead | Practical mobile task tracking | Today's jobs → assignments → photos → inspection |
| Operations admin | Accurate order lifecycle | Paid orders → production → QC → shipment → exception resolution |
| Finance admin | Reconciliation and payment governance | Provider verification → ledger entries → artisan payables → approved payout |
| Content editor | Publish genuine heritage media | Draft → consent/source review → localization → publish |

## 2.2 Journey A: ready-to-ship item

Arrival from TikTok or search → product or collection → inspect photos, specifications, price, maker and lead time → add variant and quantity → guest checkout → delivery address → shipping quote → verified supported payment → payment-confirmed order → pick/QC/pack → carrier dispatch → delivery updates → receipt, care guidance and support.

**Failure paths:** expired stock reservation, payment pending, provider redirect abandoned, duplicate webhook, shipping quote changes, address not serviceable, courier exception, lost shipment, refund or return. Each must have a documented recovery process.

## 2.3 Journey B: made-to-order garment

Discovery → select permitted options → see lead time → place order or request quote → production requirements captured and approved → payment/accepted deposit according to policy → workshop assignment → status updates → QC and rework if needed → shipping → delivery and aftercare.

The system must not promise a production start before capacity and payment conditions are met.

## 2.4 Journey C: custom order

Inquiry → structured requirements and reference media → scope feasibility → written quote with expiry, materials, quantity, dimensions, deposit/refund terms and estimated completion → customer approval → payment → production → inspection → shipment. For uncertain custom work, use a quote workflow instead of forcing checkout as a normal variant.

## 2.5 Journey D: artisan and workshop

Enrollment → explain participation/consent → create private record → verify identity and public name preference → document skills/capacity → agree compensation → assign work → progress → QC → mark payable → finance approval → disbursement reference → reconciliation → optional public attribution.

---

# 3. Complete information architecture and routes

## 3.1 Public experience

| Route (proposed) | Description | Launch |
|---|---|---|
| `/` | Cinematic brand home | P0 |
| `/our-story` | Founding story and Robit context | P0 |
| `/the-craft` | Interactive craft and glossary | P0 |
| `/artisans` | Approved artisan directory | P0 |
| `/artisans/{slug}` | Consented artisan profile | P0 |
| `/shop` | Product search and filters | P0 |
| `/collections/{slug}` | Curated product collections | P0 |
| `/products/{slug}` | Shoppable, maker-attributed PDP | P0 |
| `/cart` and `/checkout` | Cart, guest checkout and payment | P0 |
| `/order/lookup` | Secure guest order lookup | P0 |
| `/account/...` | Account, orders, addresses, wishlist | P1 |
| `/journal` and `/journal/{slug}` | Editorial features | P1 |
| `/custom-orders` | Requirements and quote intake | P1 |
| `/impact` | Defensible, aggregated impact | P1 |
| `/wholesale` | Business inquiries | P2 |
| `/contact`, `/faq`, `/policies/...` | Service and legal information | P0 |

Prefer language prefixes `/en/...` and `/am/...` where the current routing architecture permits them; consistent URL design is more important than any particular route macro. Protect guest order lookup with unguessable signed tokens and optional verification; do not expose orders by sequential number alone.

## 3.2 Operational panels

Retain Liberu's existing `/admin` and `/app` panel conventions where appropriate. Decide whether `/app` becomes the Robit operations portal with policies and role-scoped resources. A separate `/workshop` Filament panel is OPTIONAL and should only be introduced if it materially simplifies mobile operations; otherwise use a customized role-based view inside the merchant panel. **Never assume Filament navigation filtering provides authorization.**

## 3.3 Navigation and footer

Desktop primary navigation: Shop · Our Story · The Craft · Artisans · Journal (when launched) · Search · Language · Cart. Sticky behavior must not obscure content. Mobile: single-thumb accessible drawer, prominent shop action, search, cart indicator, language choice, keyboard focus management.

Footer: brand mission, shop categories, story, support, shipping/returns/privacy/terms, verified payment information, social channels, newsletter consent, contact and location context. Do not display unverified logos or shipping promises.

---

# 4. Visual identity and design system

## 4.1 Creative direction

**Creative concept:** *The Living Loom*. Narratively map the journey of thread → pattern → fabric → maker → garment → recipient. Combine documentary realism with editorial restraint; avoid imitation of generic luxury sites or invented scenes.

**Color tokens (proposals):** `--cotton: #FDFBF7`, `--linen: #E7D8C4`, `--wood: #795235`, `--indigo: #26394C`, `--tilet-red: #A33F34`, `--ink: #20201D`, `--muted: #6C6B66`. Do not flood all components with decorative colors. Contrast-check all text/state combinations.

**Typography:** editorial serif (Cormorant Garamond or Fraunces), practical sans (Inter), Amharic (Noto Sans Ethiopic). Fonts must have legal redistribution paths and use `font-display: swap`; prefer system fallbacks under constrained networks.

**Component tokens:** fluid spacing scale, 8/12/20px radii, 44x44px minimum touch targets when feasible, consistent focus ring, understated button hierarchy, disabled/hover/busy/error/success states. Never rely on color alone to convey information.

**Media treatment:** high-quality natural light; actual artisans; macro fabric texture; separate lifestyle, proof, measurement and packaging views; descriptive captions. No unsourced model-generated images masquerading as real artisans.

## 4.2 Responsive layouts

Design at 360, 390, 768, 1024, 1440px and test awkward intermediate widths. Use `clamp()` for headlines, a consistent content max-width, safe area insets for mobile, thumb-friendly buttons, and dynamic viewport units where compatible.

**Mobile first:** critical content and primary CTA rendered in HTML, only one prominent CTA per screen, photos with well-defined aspect ratio, thumbnails beneath or within gallery, no excessive scroll-pinning, no autoplay audio. Low-bandwidth fallback is a still image with full copy and a working shop action.

## 4.3 Accessibility and performance budgets

Meet WCAG 2.2 AA as a product target. Honor `prefers-reduced-motion`, keyboard navigation, visible focus, contrast requirements, captions/transcripts for documentary media, skip navigation link, alt text, labeled form fields, clear errors and focus restoration after dynamic dialogs.

Performance targets, measured at the 75th percentile: LCP ≤2.5 seconds, INP ≤200ms, CLS ≤0.1. Keep hero first-load media small; publish a poster image, use adaptive video, and avoid blocking JavaScript. No full-screen loader, scroll-hijack or forced video. Use automated and real-device tests.

---

# 5. Cinematic homepage: complete storyboard and content

This section **supersedes the prior hero-only pack** and links the hero to the complete site rather than treating it as an isolated animation demo.

## 5.1 Chapter sequence

| Chapter | Message | Visual | Interactive behavior | Conversion link |
|---|---|---|---|---|
| 1 Hero | Every thread has a story | Genuine weaving footage or poster | Subtle text reveal and movement | Shop / Explore story |
| 2 Place | Welcome to Robit | Landscape and local workshop | Image shift; no excessive parallax | Our Story |
| 3 Process | Craft takes time and skill | Cotton → yarn → loom → cloth → tilet | Optional scroll chapter | The Craft |
| 4 People | Meet our weavers | Consented portraits and quotes | Clean portrait reveals | Artisan profiles |
| 5 Product | Wear the story | Accurate product imagery | Filtered collection cards | Shop |
| 6 Provenance | Know who made it | Maker/product process detail | Detail accordion and QR preview | Product page |
| 7 Impact | Better connections | Verified operations and figures | No unsupported claims | Our Impact |
| 8 Journal | Living heritage | Documentaries and stories | Curated editorial cards | Journal |
| 9 Final CTA | Discover Robit's craft | Quiet full-bleed textile detail | Simple CTA | Shop |

## 5.2 Hero composition

- **Header:** transparent over media where contrast allows, accessible contrast alternative, clear logo, Shop and Story links, search/cart and language control.
- **Visual:** authentic 6–12 second muted loop *only if original, permission-cleared footage exists*; otherwise optimized photo with subtle motion. Capture hands, threads, loom, tactile fabric and natural daylight. Avoid direct photomontage of unrelated crafts.
- **Overlays:** dark gradient only where necessary for text contrast; modest texture and indigo/wood accent.
- **Eyebrow:** “ROBIT · NORTH WOLLO · ETHIOPIA”.
- **Headline:** “Every Thread Has a Story.”
- **Supporting text:** “Discover the people, processes, and traditional handwoven textiles of Robit.”
- **Primary CTA:** “Shop Handwoven Textiles” → `/shop`.
- **Secondary CTA:** “Discover the Craft” → `/the-craft`.
- **Mute/audio:** start muted; no auto sound. If sound exists, explicit and visible control.
- **Scroll cue:** decorative only; natural scroll always works.

## 5.3 Animation storyboard (non-blocking)

**0–200ms:** static poster, visible text and controls already in document; browser can paint immediately.  
**200–700ms:** progressive image enhancement; no black screen.  
**350–1100ms:** GSAP headline fade/translate of ≤24px, only when motion allowed; headline remains readable without JS.  
**700–1400ms:** subtitle and CTA ease in; no dependency on video decoding.  
**On scroll:** hero depth and dark overlay change at small amplitude; chapter 2 enters. Disable scroll-driven changes on reduced motion and narrow/low-memory devices.  
**On navigate away:** animation contexts, listeners and video resources clean up.  
**On return/back:** no duplicate initialization, CTA remains interactive.

## 5.4 Five-step educational craft journey

1. **Cotton and fiber preparation** — raw material sourcing, cleaning and handling; verify which steps local artisans actually perform.
2. **Spinning and yarn preparation** — show methods and locally preferred terminology (*mefitel*, *zihay*, *mag* where correct).
3. **Loom preparation** — introduce local names for loom parts and *misri* according to interviewed makers.
4. **Weaving** — show Gabi, Erbo and Netela construction only where documented.
5. **Patterns, finishing and quality** — *tilet*, finishing and inspection. Distinguish woven decoration from embroidery if applicable.

Write copy in first-hand, precise language. Pair each chapter with one media asset, one short text section, one glossary item and one link to a product or artisan. Ensure a full text view/transcript for accessibility and low data.

## 5.5 Production media specification

| Asset | Capture guidance | Publication requirements |
|---|---|---|
| Hero loop | Landscape 16:9; steady shot; no camera shake | Muted, short, poster image, adaptive format |
| Mobile hero | Portrait composition or separate image | Responsive crop; text safe zone |
| Workshop documentary | Process footage with natural sound | Subtitles, transcript, consent |
| Artisan portrait | Natural setting, neutral accurate caption | Consent, name preference, revocation process |
| Macro textile | Weave, border, color and texture | Neutral/consistent lighting, accurate product identity |
| Product set | Front, back, edge, scale, folded, care tag | Exact variant linkage and alt text |

**CMS controls:** media type, language, alt text, caption, copyright/licence, consent ID, focal point, poster, responsive sources, duration, status, expiration/withdrawal, checksum and fallback. A missing video must not prevent publishing the hero if the poster and copy are valid.

## 5.6 Laravel/Livewire/GSAP implementation plan

- Render the hero with **Blade server-side**; do not hide essential content behind JavaScript initialization.
- Create a `HeroSection` block/DTO validated by Filament, transformed into a Blade component with safe typed fields.
- Use Livewire for user-driven UI such as cart, language/search, and content settings only when it adds value; avoid a reactive network request for each scroll frame.
- Bundle GSAP via Vite. Create an `initHero(root)` function returning `cleanup()`. Use `gsap.context()` and `gsap.matchMedia()`; respect reduced motion and avoid global `killAll()` that would destroy other page animations.
- On Livewire navigation, wire initialization and teardown to documented browser lifecycle events, testing actual version. Prevent duplicate listeners with a per-element lifecycle registry or `WeakMap` and register cleanup on navigation/remove.
- Use Alpine for small controls; use `wire:ignore` only on isolated DOM owned by GSAP, not on the main cart or navigation tree.
- Do not add a smooth scroll library by default. Measure if Lenis adds value; keep it disabled when reduced motion or platform behavior is degraded.

**Minimal safe enhancement example:**

```js
// Illustrative structure - adapt lifecycle hooks to the pinned Livewire version.
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
gsap.registerPlugin(ScrollTrigger);
const active = new WeakMap();
export function mountHero(root) {
  if (!root || active.has(root)) return active.get(root) ?? (() => {});
  const media = window.matchMedia('(prefers-reduced-motion: reduce)');
  const ctx = gsap.context(() => {
    if (!media.matches) {
      gsap.fromTo(root.querySelectorAll('[data-hero-reveal]'),
        { y: 16, autoAlpha: 0 },
        { y: 0, autoAlpha: 1, duration: 0.75, stagger: 0.1, clearProps: 'all' });
    }
  }, root);
  const cleanup = () => { ctx.revert(); active.delete(root); };
  active.set(root, cleanup);
  return cleanup;
}
```

**Requirement:** the CSS default is visible, including if JS fails; the example only enhances. Test back/forward, cart opening, mobile Safari behavior, and slow network. Avoid initializing GSAP before layout/media measurements are stable.

## 5.7 Homepage acceptance criteria

- Copy and CTAs visible without JavaScript; text contrasts in poster and video states.
- Hero CTA leads to working catalog in both languages.
- Low-data, failed video and reduced-motion modes render equivalent meaning.
- Media rights and alt text recorded; no imaginary maker shown as real.
- No duplicate animations after 10 repeated page navigations.
- Keyboard focus and shopping interactions unaffected by animation.
- Real-device mobile performance measured; no frame drops preventing interaction.

---

# 6. Storefront, collections and product detail pages

## 6.1 Catalog taxonomy

First collections: **Gabi**, **Erbo / Netela**, **Ceremonial Garments**, **Contemporary Handwoven Accessories**. Validate local production and classification with makers before publishing category claims. Separate evergreen product type taxonomy from editorial marketing collections (e.g., wedding season).

Core filtering: product type, material, dimensions, color/pattern, availability, price, artisan, workshop, ready-to-ship vs made-to-order, occasion, date added. Search English and Amharic names and transliterations; capture synonyms in managed terminology records.

## 6.2 Product types

- **Standard stocked** — multiple units of a variant with measurable stock.
- **Unique/one-of-a-kind** — individual item or serial tracked; prevent double sale.
- **Made-to-order** — no committed unit on hand; capacity and lead-time guarantee or review required.
- **Custom quote** — inquiry/approval flow, not priced as normal SKU by default.
- **Wholesale** (P2) — quote, MOQ, group pricing and contract terms.

Do not treat all products as variants of a generic clothing size. Measurements (length/width/weight) may be more useful than S/M/L.

## 6.3 Product detail page (PDP)

**Above fold:** media gallery, truthful title, SKU, price and currency, availability, optional maker, variant selector, dimensions, ETA, shipping availability, purchase action, support link.  
**Below fold:** texture and technique detail; verified material composition; actual weight and measurements; maker profile; production timeline if available; care guide; delivery and returns; reviews with moderation; related products.

Avoid statements like “100% hand-spun local cotton” or “handwoven in six days” unless verified for that exact unit or product batch. Mark missing facts as missing and block publication where essential.

**PDP acceptance:** selecting a variant updates truthful price, inventory, media, shipping weight and purchasable state. Out-of-stock unique items cannot be bought. All options work without animation.

## 6.4 Cart and checkout

Guest checkout is default; account creation optional after purchase. Cart items save variant ID, quantity, authoritative price quote/reference, option configuration and production type. Final checkout recalculates server-side prices, discounts, tax, shipping and stock; never trust client totals. Display full charged currency and shipping/duties policy before payment.

Flow: cart → address and contact → shipping eligibility/rate → review totals and policies → payment initiation → provider verification → confirmation/next steps. Provide a safe state for delayed provider callbacks: `payment_processing` with polling/manual reconciliation; never show paid until verified.

Support abandoned cart via expiring reservations, not infinite stock holds. For custom orders, use explicit scope and quote acceptance; for made-to-order, specify cancellation and deposit policy.

---

# 7. Artisan identity, consent and community governance

## 7.1 Profiles and public/private separation

Public approved fields: preferred name or pseudonym, agreed location precision, biography, skills, selected portraits, video excerpts, approved quotes and attributed products. Private fields: contacts, exact address, contract, payment details, identity documents, internal notes and payout history. Permission boundaries must prohibit public or customer access to private data.

**Consent workflow:** present in a language the artisan understands; identify intended website/social use, storage, attribution option, and how to request correction or removal. Store a versioned consent decision for each media/story use. Withdrawing public publication should unpublish media and trigger cache/CDN review, subject to legitimate record-retention obligations.

**Never** infer a person's ethnicity, religion or financial circumstances for marketing copy. Do not expose children in media without appropriate additional safeguards and permission.

## 7.2 Attribution and collaboration

One garment may involve spinning, base weaving, edging and finishing by different contributors. Model `product_artisan_contributions` with role, visibility and optional contribution descriptions rather than one mandatory `artisan_id`. A public profile may attribute only consenting contributors. This is distinct from internal payables.

## 7.3 Artisan financial agreements

Define ownership model per batch: purchase-on-completion, consignment or contracted production. Record versioned agreement, agreed unit rate, milestone terms, rejection/rework terms, material costs, delivery responsibility and dispute process. Finance must approve payable; payout is recorded with amount, currency, date and reference. Keep an immutable audit trail. Do not assert that a fixed share of the consumer price goes to makers unless the records prove it.

## 7.4 Community impact reporting

Possible metrics: number of active consenting artisans, number of items acquired, total verified artisan payments during period, payout timeliness, and number of documented techniques (if verifiable). Show methodology and period. Aggregate privacy-sensitive information and allow editorial review before publishing.

---

# 8. Workshop, production and quality assurance

## 8.1 Production domain

Separate `production_order` from `order` and `order_item`. Each customer item may generate multiple maker assignments or batches. Material availability, workshop capacity, due dates and dependencies affect scheduling. Use human approval for assignments at first; automated recommendations can come later.

**States:** `queued` → `approved` → `assigned` → `in_progress` → `ready_for_qc` → (`approved` OR `rework_required`) → `completed`; plus `on_hold`, `cancelled` and documented exceptions. No silent jumps; keep transition history with actor, time, notes, attachments, and reason.

## 8.2 Workshop manager mobile view

The first screen should show Today's Work, Late Work, Ready for QC and Actions Required. Each task needs a clear photo and order code, specifications, assigned maker, due date, progress buttons, quality checklist, issue reporting and support. Use generous touch targets. Restricted privileges: the workshop lead must not change customer payment totals or approve unrelated payouts.

## 8.3 Quality checks

Per-item checks: correct garment and variant; actual dimensions/weight tolerance; pattern and finishing quality; defects; color consistency; cleanliness; packaging; maker/provenance linkage; photo evidence for exceptions. Tolerances must be defined by product family, not invented globally. Rejected items need disposition: rework, replacement, discount-with-customer-consent, or refund under policy.

## 8.4 Procurement and inbound inventory

Create receiving records for goods brought from workshops to consolidation. Cross-check against signed agreement and product specification; record received quantity and accepted quantity separately. Record each stock movement (receipt, reservation, release, allocation, damaged, shipped, returned). Stock count is a derived balance—not the only audit evidence.

---

# 9. Pricing, currency, payments and reconciliation

## 9.1 Price model

A pricing decision includes artisan payment or cost, external materials, collection transport, inspection, packaging, operational overhead, payment fees, expected returns/defects, shipping strategy, tax implications and desired margin. Use a documented rate sheet, not fictional prices in seeders. Record price author and effective dates; current catalog price is not historical order price.

## 9.2 Three different currencies

- **Accounting currency:** bookkeeping/ledger reference chosen with accountant advice.
- **Display currency:** ETB/USD/EUR/GBP as preference only; label estimates accurately.
- **Provider charge/settlement currency:** what the merchant is authorized to charge/receive.

Currency conversion must be explicit and saved on the checkout quote. Use exact decimals or integer minor units and currency-aware rounding; never binary PHP floats for money. No silent conversion at final confirmation.

## 9.3 Provider feasibility gate

Stripe does not currently list Ethiopia as a supported Stripe merchant country. Liberu's existing Stripe/PayPal code does **not** establish merchant eligibility for an Ethiopia-registered business. Confirm gateway onboarding, beneficiary identity, accepted cards, countries, fee schedule, refund method, local compliance, foreign exchange treatment, chargebacks, settlement and taxes before enabling. For local methods, evaluate verified Ethiopian aggregators and bank arrangements; no specific provider is guaranteed.

**Provider-neutral contracts:** `PaymentGateway`, `PaymentAttempt`, `PaymentVerificationResult`, `RefundResult`, `PaymentWebhookEvent`, and per-provider adapters. Financial approval and final order transition remain application-owned.

## 9.4 Payment safety invariants

1. Every payment attempt has unique merchant reference and idempotency key.
2. Amount, currency and order snapshot are immutable for that attempt.
3. Provider callback is authenticated and deduplicated by unique event ID.
4. Provider verification confirms status, amount, currency, reference and merchant.
5. Checkout redirect is **not** evidence of payment.
6. Stock holds expire and are reconciled when payment succeeds late.
7. Confirmed payment posts payment records **exactly once**.
8. Captured payments that cannot fulfill stock go to explicit compensation/refund, never silently disappear.
9. Refunds have authorization, reason, unique reference and post-confirmation reconciliation.
10. Operator has an exception dashboard for missing/late callbacks, underpaid, overpaid, duplicated, abandoned and unknown transactions.

**Suggested statuses:** `initiated`, `redirected`, `processing`, `verified_paid`, `failed`, `cancelled`, `expired`, `refunded`, `partially_refunded`, `disputed`. Store provider events and audit trails independent of customer-visible order state.

## 9.5 Checkout transaction boundaries

Under a DB transaction: validate order line snapshots and amounts; acquire row locks or atomic inventory reservation; create pending order and payment attempt; commit. Call provider *outside* the DB transaction. On callback or verification: atomically lock attempt/order, validate expected values, make one authorized state transition, finalize reservation, record transaction and outbox event. Dispatch mail after commit. Run concurrency tests with 2 customers attempting the final unique unit.

## 9.6 Finance and artisan settlement

Finance dashboard must distinguish: sales revenue, payment received, refunds, chargebacks, shipping cost, tax liability, artisan payable, artisan paid and platform margin. A paid customer order does not imply artisan payout completed. Approved payouts should have dual authorization at scale and reconciliation against actual bank/mobile payment confirmation.

---

# 10. Shipping, customs, returns and fulfillment

## 10.1 Physical network

Robit workshop/collection → confirmed transport hub (may be regional or Addis Ababa) → packing/QC → approved domestic courier or international carrier → customer. This is a **proposed routing model**, not a guarantee of carrier availability. Pilot and document real transport and cost for at least one domestic and one supported international destination before public promises.

## 10.2 Rate calculation

Rate quote inputs: delivery country/region/city/postal code, product package weight/dimensions, carrier/service, insurance, production lead time, handling fee and any available shipping rules. A shipping estimate must have expiry, provider/source, currency and limitations; a missing rate should block automatic checkout or route to support, not silently become free shipping.

## 10.3 Fulfillment entities and split shipments

An order may contain several shipments. `shipment` owns package, shipping address snapshot, service, weight/dimensions, cost, tracking number, customs notes and public status. `shipment_item` allocates order items. Keep an append-only shipment event log and distinguish carrier-sourced scans from internal manual updates.

**Statuses:** `not_ready` → `packing` → `awaiting_hub` → `handed_to_carrier` → `in_transit` → `delivered`; exceptional `customs_hold`, `delivery_exception`, `lost`, `return_in_transit`, `returned`. No automatic delivered status from estimated delivery date.

## 10.4 Domestic and export pilot

Document pack method, weights/dimensions, invoice, customs declaration, tariff classification, export permission, duties/taxes responsibility, country eligibility, returns address, claims process and per-destination delivery range. Verify these with the carrier and qualified local advisers. Clearly disclose possible destination duties and customer responsibility before checkout.

## 10.5 Returns and post-delivery

Publish return windows, custom-product exceptions where lawful, defect handling, damage reporting evidence, responsibility for return cost, currency conversion consequences, refund timing, and support contact. An RMA links to order item, shipment and payment; stock restock happens only after condition assessment.

---

# 11. Customer accounts, support and communications

Guest orders use secure access tokens and email/phone verification as appropriate. Registered users can view their own orders, invoices, addresses, wishlists, return requests, production stages, support threads and communication preferences. Do not expose an order by sequential ID without checking user ownership or a signed token.

**Customer service channels:** contact form, reply-capable support email, FAQ, order-specific inquiry, optional Telegram/chat handoff after launch. Ticket fields: customer, order, subject, priority, state, assigned agent, response history, private attachments, SLA and resolution notes. Chatbot answers must not claim inventory/payment/delivery facts it cannot verify; handoff to humans for exceptions and custom requests.

**Events:** OrderPlaced, PaymentConfirmed, ProductionAssigned, QCApproved, ShipmentDispatched, CarrierDelivered, RefundConfirmed, ArtisanPayableApproved. Prefer events → idempotent notification jobs → template localization → sent/error log. Email only after state is committed. Mailpit in local; properly authenticated transactional sending in production.

---

# 12. Filament administration and authorization

## 12.1 Panels and navigation

Configure central and merchant panels based on pinned Liberu app semantics. Use a single merchant/team/store for initial Robit installation if repository supports a trustworthy `single` mode; disable self-service merchant signup. Avoid combining the brand with unrelated tourism sites unless intentional shared tenancy is required.

**Admin nav:** Overview; Orders/Payments; Catalog; Inventory; Production; Artisans/Workshops; Fulfillment; Finance/Payouts; Heritage CMS; Customers/Support; Marketing; Analytics; Roles/Settings/Audit.

## 12.2 Roles and policy scopes

| Role | Read scope | Write scope |
|---|---|---|
| Superadmin | Whole platform | Server-side authorized platform operations |
| Operations manager | Store orders, stock, shipments | Fulfillment, assignment and issue handling |
| Catalog manager | Catalog, allowed artisans | Draft/edit products; publish per approval |
| Content editor | Approved public media and story | Draft localized content; publish if permitted |
| Workshop lead | Assigned workshop/tasks | Assignment progress, media, QC request |
| QA inspector | Relevant items | Inspection and defect disposition |
| Finance | Orders, payments, payables | Refund/payout approvals with policy |
| Customer support | Assigned customer/order details | Safe support actions; no privileged pricing |
| Artisan portal (later) | Own approved assignments | Own progress within assigned scope |
| Customer | Own orders/profile | Own permitted account/support actions |

Enforce Policies/Gates on every action and record resolution; tenant/store scope is present in the query and in authorization checks. Test direct URL access, modified Livewire requests, API IDs, queued jobs, exports, bulk actions, cross-role access and unauthenticated paths. High-risk finance and security changes require additional confirmation/audit.

## 12.3 Filament resource standards

Every resource: policy, scoped query, ownership checks, validated forms, safe mass assignment, debounced/search pagination, appropriate indexes, export permission, audit log, lifecycle action guards. Never allow free editing of `payment_status` or `total_amount` independently of approved domain transitions.

## 12.4 Operational dashboards

Cards and queues: new paid orders, awaiting payment verification, production due/late, QC failure, awaiting dispatch, courier exceptions, unapproved artisan payables, failed webhooks, low stock, and unreviewed media consent. Clicking a metric must open actionable scoped records. Measure *actual* activity; no fabricated production dashboard data.

---

# 13. Heritage content management and publishing workflow

Content types: founder story, Robit history, weaving technique, artisan profile, documentary, photo essay, material guide, garment care, cultural event, verified impact article and product-linked story.

**Typed content blocks:** hero, heading, prose, portrait, gallery, caption, video/transcript, timeline, image/text split, craft step, artisan reference, product reference, quote, glossary, CTA and FAQ. Editors can rearrange and configure validated blocks; never execute arbitrary HTML or JavaScript submitted through the CMS.

**Publishing stages:** draft → fact/consent review → translation review → editor approval → scheduled/published → archived/withdrawn. Media asset may be withdrawn independently of story. Previews use signed URLs; published content has stable slugs, canonical metadata, author/reviewer and revision history.

**Editorial truth rules:** make clear what is from interviews, founder memory and independently verified data. Avoid romanticized claims about poverty or invented makers. Obtain consent for portraits, recordings and direct quotes. Local experts review spelling and terminology for *shimninna*, *zihay*, *mag*, *misri*, *tilet*, *Gabi*, *Erbo* and *Netela*.

---

# 14. Localization, currency display and cultural correctness

English default; Amharic full translation parity for navigation, checkout, transactional emails and crucial policy pages before claiming support. Store translated product names/descriptions, CMS sections, aria labels and SEO metadata. Amharic fonts must be tested on Android browsers and system fallbacks. Use localized number formatting as appropriate without changing authoritative charged amount.

Schema guidance: short UI strings in framework language files; durable editorial/product translations in explicit translation table or repository-supported localization strategy. Never store raw localized money strings in database. Add glossary synonyms and region-appropriate definitions reviewed by makers.

Maintain locale-correct canonical/hreflang pairs and exclude untranslated placeholders from search indexes.

---

# 15. Search, SEO, discovery, analytics and marketing

## 15.1 Search

Start with the search capability already in Liberu if valid; otherwise PostgreSQL or MySQL full-text plus structured filters, indexed variant attributes and synonyms. Confirm database choice first (do not mix SQL dialect assumptions). Use stable pagination, search relevance tests and proper query sanitization. Consider Meilisearch only when measurable catalog/search needs justify it.

## 15.2 SEO

Render server-side product, collection and editorial pages. Product schema, BreadcrumbList, Organization, Article or VideoObject only when eligible; truthful stock/price data. Add XML sitemaps, canonical/hreflang, localized OG metadata, clean URLs, crawl rules for filters, image/video optimization, 404/redirect policy, and Search Console instrumentation. Avoid indexing customer, cart, checkout, admin and preview routes.

## 15.3 Marketing

Social deep-links should land on specific PDP, artisan or story sections. Provide tracking with consent and UTM parameters. Launch culturally relevant campaigns carefully; don't imply universal demand or shipping coverage. Newsletter needs double opt-in when appropriate, unsubscribe, clear sender identity and consent log. Product review moderation and verified purchase indicators must be accurate.

## 15.4 Analytics

Track documented events: `view_story`, `view_artisan`, `view_product`, `select_variant`, `add_to_cart`, `begin_checkout`, `payment_initiated`, `purchase_verified`, `shipping_quote_failed`, `production_delayed`, `delivered`. Never treat a redirect as purchase. Minimize personal data in tracking events and respect consent/retention policy. Create dashboard filters by country, language, collection, channel and period, with appropriate anonymization.

---

# 16. Technical architecture on Liberu Ecommerce

## 16.1 Make the repository the starting point, not a black box

**Known from published documentation:** Liberu describes Laravel 13 / PHP 8.5 / Livewire 4 / Filament 5, modular files under `modules/`, `/admin` and `/app` panels, a Dockerfile and Compose setup, catalog/cart/checkout/payments/shipping features, and an MIT license. Documentation includes migration/security findings. These claims are **not a line-by-line audit of your cloned revision**.

**Required baseline report:** exact SHA and clone date; Composer/npm versions; module list; actual published routes; DB engine; tenancy mode; payment/checkout graph; existing stock semantics; test results; scanner output; deployment assumptions; security findings status; license and third-party package notices. Keep `docs/robit/BASELINE_AUDIT.md` up to date.

**Recommended deployment model:** one standalone application, one Robit brand and store, one relational database, Redis, object storage, background workers, external payment/courier adapters. Do not build microservices without a demonstrated need.

## 16.2 Architecture boundaries

```
Browser (EN/AM, desktop/mobile)
  ├─ Blade SSR + Livewire storefront + Alpine UI + GSAP enhancement
  ├─ Customer account and checkout
  └─ Filament admin / workshop surfaces
                 │
              Laravel app
  ├─ Liberu audited commerce domain (catalog, cart, checkout, orders)
  ├─ Robit heritage & consent (stories, artisans, content, provenance)
  ├─ Robit production & finance (assignments, QC, payables)
  ├─ Fulfillment adapters (shipping, tracking, returns)
  └─ Application services, policies, events, outbox
          ├─ MySQL or PostgreSQL (one chosen for this app)
          ├─ Redis (cache/queue)
          ├─ S3-compatible media storage
          └─ External payment/courier/email services
```

## 16.3 Proposed domain organization

Prefer Liberu-compatible modules over an entirely parallel reimplementation. Candidate new modules: `RobitHeritage`, `RobitArtisans`, `RobitProduction`, `RobitProvenance`, `RobitOperations` (or the architecture's exact naming convention). Use application Actions/Services and policies rather than business logic inside controllers, Filament resources or Eloquent observers.

**Anti-corruption layer:** separate Robit domain services from unstable upstream internal classes. Wrap provider integrations. Extend products with keyed extension tables or composition, not scattered non-reversible edits to upstream migration history. Never fork an existing money/order service unnecessarily. Make upstream changes in small documented commits.

## 16.4 Single-store security

Single-store configuration reduces cross-merchant exposure but **does not fix missing authorization, private API response fields, improperly protected admin routes or dangerous payment edits**. Decide and test merchant/team/store resolution for every public route, API, Filament record, queued job and sitemap query. Disabled SaaS signup should stay inaccessible on direct request.

## 16.5 Asynchronous processing

Redis queues: payment webhook processing (fast acknowledge + persisted event), notification delivery, media conversions, shipping tracking sync, production reminders and analytics aggregation. Ensure jobs are idempotent, bounded, observable, store-scoped and `afterCommit()` when dispatched within DB transactions. Configure retries/backoff, timeouts and dead-letter/error monitoring. Run scheduler once per installation.

## 16.6 Media storage

Spatie Media Library if compatible and correctly integrated. Dev: local or S3-compatible MinIO; production: approved S3/R2 bucket with private originals and CDN-managed public derivatives. Access policies for contracts, identity and payout records. Store checksums, image dimensions, orientation and consent metadata. Preserve originals when authorized; generate transformed AVIF/WebP and thumbnails asynchronously.

---

# 17. Data architecture summary

Detailed extension schema, constraints and examples are in `ROBIT_HANDLOOM_DATA_AND_API_CONTRACTS.md`.

**Identity and organization:** users, roles/permissions, Liberu teams/stores/channels as actually implemented; artisans, workshops and private contacts.  
**Catalog:** products, variants, collections, materials, craft techniques, maker contributions, product provenance and media.  
**Commerce:** carts, orders, immutable line amounts, order addresses, payment attempts/verified transactions, refund/RMA.  
**Operations:** production orders, assignments, updates, QC checks, inventory locations/movements/reservations, shipment/shipments items/events, artisan agreements/payables/payouts.  
**Content:** stories, chapters, content blocks, translations, media consent, revision workflow.  
**Support:** cases, messages, notification deliveries, audit logs, webhook inbox and outbox events.

**Do not create duplicate tables** where audited Liberu tables already own the domain. Introduce compatibility mapping, additive migrations, foreign keys and backfill plans. Prefer explicit unique constraints and database transactions over fragile application-only checks.

---

# 18. Docker-based local development

**Primary local environment:** Ubuntu in UTM with Docker Compose; host's PHP does not need to match image PHP, but Docker and sufficient disk/memory are required. Compose currently documents an `app`, `mysql`, `redis`, `mailpit` and optional `worker`/`horizon`/`reverb` profile. Verify your exact commit and `docker compose config --services`.

**Do first:** clone project, create your private GitHub `origin`, retain upstream remote and MIT notices, pin SHA, review `.env.example`, confirm Compose service names and port conflicts, copy `.env` locally, set proper DB credentials, build image and inspect logs, run migrations/seeds **once on an empty database**. Liberu warns many seeders are not idempotent.

**Dev overlay:** additive `compose.dev.yaml` supporting bind-mounted source, named vendor/node_modules volumes if needed, an explicit Vite service with HMR exposed to the VM host/browser, worker, and stable DB/Redis volumes. Use service discovery names (`mysql`, `redis`), not `127.0.0.1`, from inside app containers. Run `docker compose -f docker-compose.yml -f compose.dev.yaml config` to validate. Keep host ports distinct from other projects.

**Important:** Liberu's base Compose may be oriented to prebuilt images without source bind mounting. An image rebuild after every Blade edit is not acceptable for normal development; implement the development overlay **after auditing the Dockerfile** so it does not hide installed vendors or break the container entrypoint.

Detailed commands and a decision checklist are in the DevOps runbook. Never use `down -v` casually; it removes persistent DB volumes. `APP_DEBUG=false` and no public DB/Redis ports in production.

---

# 19. Security, privacy and compliance gates

## 19.1 Immediate upstream audit, before features

Audit published Liberu issues first: merchant/team/store isolation outside Filament; authorization policies for all resources and endpoints; dangerous freely editable order/payment fields; public APIs/GraphQL and sensitive response leakage; checkout race conditions; missing route handlers; and migration consistency. Findings are from a historical repository snapshot, **not definitive proof of current vulnerabilities**. Reproduce or close each against the pinned Git SHA with automated tests.

**Baseline isolation tests:** user A cannot read, modify, search or export user B's orders; merchant staff cannot access platform resources; anonymous visitor sees only the intended public product catalog; public CMS never leaks private maker data; any API route honors the same rules; payment callbacks cannot mutate unrelated orders.

## 19.2 Security controls

- HTTPS, secure cookie/session and CSRF protection.
- Admin MFA, strong auth, audited invitations and least privilege.
- Rate limits for login, checkout, guest order lookup, contact and API.
- Strict content security policy tailored to media/payments, validation and upload scanning.
- Sensitive private media storage with expiring signed access.
- Safe logging, secret redaction and no production debug display.
- Third-party package audits, dependency pinning, image scanning and CI gates.
- Authorization for exports, bulk actions, custom Livewire events, background jobs and webhooks.
- Tested restore procedures, incident response, retention and account deletion/anonymization policy.

## 19.3 Privacy and local legal review

Before taking real orders, get jurisdiction-specific advice on merchant registration, consumer obligations, tax/VAT, cross-border exports, payment regulation, privacy, consent and retention. The software spec does **not** constitute legal or tax advice. Publish policies matching actual business operations and support capabilities. Maintain administrative correction/removal process for artisan media and customer information.

---

# 20. Testing strategy and release acceptance

## 20.1 Required test suites

**Unit:** pricing math, currency rounding, variant validation, production transitions, artisan compensation rules, rate selection.  
**Feature/integration:** cart/checkout/stock, payment webhook deduplication and verification, return/refund, shipping updates, artisan permissions, guest order access, localized CMS publishing.  
**Concurrency:** final unique item purchased by two customers; parallel retries of same webhook; refund/settlement race; repeated artisan payout approval.  
**Security:** horizontal/vertical access to orders, exports, private documents, API/GraphQL and Filament resources; malicious file uploads, forged redirects, rate limiting.  
**Browser:** checkout on Android and iOS, navigation, captions, no-JS hero, GSAP cleanup, mobile form errors, keyboard access.  
**Operational:** failed email gateway, lost network callback, delayed courier, partial shipments, unpaid order expiry, production rework and disaster restore.

## 20.2 Demonstration scenarios

1. **Domestic ready-to-ship:** browse → quoted ETB → verified approved payment → accurate invoice → packed → delivered.
2. **International:** destination eligible → true charge currency and rate → confirmed provider transaction → export documentation → carrier tracking.
3. **One-of-one race:** 2 simultaneous attempts; maximum one successful stock allocation; any duplicate capture produces controlled refund/exception.
4. **Made-to-order:** paid → assigned → progress → QC reject/rework → approved → shipped → maker payable reconciled.
5. **Custom quote:** submit → approve scope/quote → pay → lock agreed requirements → produce.
6. **Privacy:** non-owner cannot access order, private artisan payout, support attachment or consent record by direct URL/API.
7. **Content:** unapproved/missing consent media stays unpublished; Amharic route, metadata and video transcript work.
8. **Site reliability:** video fails but hero CTA is visible; queue restarts without duplicate mail/payment.

## 20.3 Definition of done, every module

- Functional contract and state transitions approved.
- Database schema, backfill, rollback strategy and indexes reviewed.
- Policies, validation, authorization and audit implemented.
- Happy path plus error and concurrency tests pass.
- Mobile/accessibility/performance test evidence attached.
- Operational monitoring and runbook documented.
- No secrets, placeholder customer data or invented cultural claims in production content.
- End-to-end manual demonstration recorded.

---

# 21. Production infrastructure, CI/CD and monitoring

**Environments:** local Docker, staging (production-like but no live charges), production. Independent secrets/databases/buckets/keys in each; do not copy production PII into test without approved anonymization.

**Build pipeline:** Composer install from lock, npm ci/build from lock, lint/format, static analysis, unit/feature/security tests, migration compatibility test, Docker image build/scan, release artifact pinned by commit SHA, smoke test then rollout. Do not automatically deploy `latest` images to production.

**Production minimum:** supported PHP runtime, reverse proxy + TLS, relational DB with backup, Redis, object storage, queue worker(s), scheduler, restricted admin, payment webhooks, monitored logs/alerts. Use appropriate host resources and worker concurrency for the workload. Ensure domain, email sender identities, CDN and provider callbacks are configured correctly.

**Monitoring:** app exceptions, latency, queue age/failed jobs, payment callback reconciliation, rate-quote failures, inventory negative balance, duplicate provider events, artisan payouts pending, shipping exceptions, media processing and backup success. Every critical alert needs an owner and response playbook.

**Recovery:** automated DB backups, periodic restore drills, signed media backup policy, secret rotation, rollback runbook, queue drain procedure, forward-compatible DB changes, environment change log, and incident notification process.

---

# 22. Implementation plan (complete product, phased delivery)

The phases below have real dependencies. A phase is not complete because a screen appears; it is complete when its acceptance tests and operations work.

| Phase | Scope | Exit gate |
|---|---|---|
| M0 | Fieldwork, SKU/price/media/consent, local payment and shipping feasibility | Real products and valid merchant path |
| M1 | Pin and audit Liberu; test baseline security and architecture | Known defects recorded, no critical unreviewed exposure |
| M2 | Git ownership, Docker+HMR, environments, CI skeleton | Reproducible local/staging builds |
| M3 | Single-store setup, scopes, policies, checkout hardening | Authorization and payment tests pass |
| M4 | Design tokens, navigation, header/footer, localization skeleton | Responsive, accessible shell |
| M5 | Catalog, product extensions and stock model | Real 15–20 items published safely |
| M6 | Cart, guest checkout, payment adapter and reconciliation | Complete sandbox purchase including failures |
| M7 | Domestic rates, packages and tracking | Verified local delivery test |
| M8 | Artisan/workshop private/public profiles and consent | Approved profiles, no private data leakage |
| M9 | Production assignment, QC, inventory handoff | End-to-end made-to-order flow |
| M10 | Finance payables, maker agreements and payout audit | Payout reconciliation demo |
| M11 | Hero, story pages, five-stage craft, CMS/media | Truthful, consented cinematic experience |
| M12 | Customer account, support, refunds and returns | Complete exception and support handling |
| M13 | Search, SEO, analytics, email, campaigns | Findable and measured with privacy controls |
| M14 | Shipping export and international gateway gate | Real provider/courier approval and pilot |
| M15 | Pentest/QA/performance/accessibility/recovery | Release gates and signed runbooks |
| M16 | Controlled launch, operations monitor and customer feedback | Repeatable paid-to-delivered fulfillment |
| M17 | Custom commissions, wholesale, deeper provenance | Only after verified demand and capacity |

**Delivery priority:** P0 = M0–M10 + essential M11/M12/M13/M15/M16. P1 = richer journal, impact, commissions. P2 = wholesale automation, artisan self-service, deeper logistics/AI.

### Pilot requirements

Start with ~15–20 real cataloged items and ~5–10 willing, consenting artisans **only if actual local supply supports it**. These are planning targets, not confirmed inventory. Validate product dimensions and weight, artisan terms, at least one delivery path, supported payment collection, refund policy and practical support coverage. Begin with supported destinations only.

---

# 23. Business operating procedures

## 23.1 Morning operations

Review payment exception queue → confirm new paid orders → reconcile stock → assign/confirm production → inspect late jobs → prepare shipments → scan courier exceptions → confirm artisan payables → respond to tickets → review failed jobs and backups.

## 23.2 Weekly operations

Product quality review; test purchase; reconcile payment/provider statements and payouts; sample shipment events; verify content consent; review customer feedback, returns and prices; publish approved story; review security updates and support response time.

## 23.3 Incident: paid but order not confirmed

Record external transaction ID; freeze duplicate manual captures; independently verify with provider; compare amount/currency/reference; locate webhook inbox; run idempotent reconciliation; update order or initiate refund; contact customer with verified status. Never “fix” by hand-setting `paid` in Filament.

## 23.4 Incident: artisan unavailable or delayed

Notify operations; review alternate capacity and existing agreement; revise promised ETA with customer agreement; reschedule or refund per policy; preserve stage history and compensation implications. Do not silently replace named artisan where buyer selected a specific maker.

## 23.5 Incident: wrong/damaged parcel

Open case with shipment/packing photos; confirm item and claim deadlines; identify remedy and payer; file carrier claim if available; update RMA/refund; inspect returned stock before restock; root-cause review.

---

# 24. Non-functional requirements and engineering decisions

- **Reliability:** checkout and payment webhook reliability outrank animation richness; design safe degraded modes.
- **Consistency:** DB state transitions and idempotency over frontend optimism.
- **Maintainability:** keep upstream compatibility map; avoid copying whole Liberu modules for cosmetic changes.
- **Privacy:** maker consent and minimum necessary customer data.
- **Observability:** trace an order from cart to provider event, stock change, QC, shipment and payout without manual guesswork.
- **Portability:** business services independent of provider SDKs; object storage and queue abstraction.
- **Internationalization:** text and formatting first-class, not retrofitted.
- **Honesty:** product truth and verified business claims are required product features.

**Architecture decisions to record:** ADR-001 adopt Liberu baseline; ADR-002 single merchant/tenant; ADR-003 DB engine; ADR-004 payments/settlement currency; ADR-005 product attribution model; ADR-006 inventory ledger/reservations; ADR-007 media consent; ADR-008 queue/outbox; ADR-009 public CMS; ADR-010 deployment model.

---

# 25. Open decisions and approval gates

| Decision | Owner | Evidence required | Blocker |
|---|---|---|---|
| Business entity and merchant identity | Founder + qualified adviser | Registration/payment onboarding | Real payments |
| Domestic gateway | Founder + provider | Approved sandbox and settlement test | Domestic checkout |
| International card path | Founder + provider | Merchant eligibility and real allowed pilot | International checkout |
| Delivery destinations | Operations + carriers | Rates, customs/export terms, transit pilot | Selling to that destination |
| Initial products and prices | Artisans + operations | Inventory and cost sheet | Catalog publish |
| Media consent | Artisans + editor | Recorded approval | Portrait/story publish |
| DB engine | Tech lead | Repository compatibility and query tests | Migrations |
| Tenancy/isolation | Tech lead | Access matrix and security tests | Public deploy |
| Returns/defects policy | Business + adviser | Written customer terms | Checkout launch |
| Refund/payment reconciliation | Finance + tech | Provider support and test ledger | Live charges |

A requirement can be implemented with a **disabled/placeholder integration** while waiting for validation, but the user-facing checkout must not imply the provider works.

---

# 26. Source links, validation and change management

**Official upstream resources checked during drafting:**

- Liberu repository: https://github.com/liberusoftware/ecommerce-laravel
- README: https://github.com/liberusoftware/ecommerce-laravel/blob/main/README.md
- Installation: https://github.com/liberusoftware/ecommerce-laravel/blob/main/docs/INSTALLATION.md
- Security: https://github.com/liberusoftware/ecommerce-laravel/blob/main/SECURITY.md
- Conformance snapshot: https://github.com/liberusoftware/ecommerce-laravel/blob/main/docs/CONFORMANCE.md
- Migration plan: https://github.com/liberusoftware/ecommerce-laravel/blob/main/docs/MIGRATION_PLAN.md
- Operations: https://github.com/liberusoftware/ecommerce-laravel/blob/main/docs/OPERATIONS.md
- Compose: https://github.com/liberusoftware/ecommerce-laravel/blob/main/docker-compose.yml
- Livewire navigation: https://livewire.laravel.com/docs/4.x/navigate
- Livewire JavaScript integration: https://livewire.laravel.com/docs/4.x/javascript
- Laravel queues: https://laravel.com/framework/docs/13.x/queues
- GSAP ScrollTrigger: https://gsap.com/docs/v3/Plugins/ScrollTrigger/
- Stripe supported regions: https://stripe.com/global

**Freshness caveat:** Public `main` changes rapidly and some GitHub URLs redirect to a renamed repository. All work must use the actual pinned source SHA. Statements in the source documentation can refer to historical snapshots and may conflict as migration proceeds. Your agent must measure the checked-out code, not claim the whole app is safe because the README says “production-ready.”

**Document control:** edit this file via PR, record version/date, attach updated acceptance criteria and migration implications. Never use an AI-generated guide as a substitute for provider confirmation, legal advice, original artisan interviews, or actual test evidence.

---

# Appendix A. Suggested UX microcopy

**Hero:** Every Thread Has a Story.  
**Primary CTA:** Shop Handwoven Textiles  
**Secondary CTA:** Discover the Craft  
**Artisan preview:** Meet the Hands Behind the Weave  
**Craft:** From Cotton to Cloth  
**Provenance:** Discover How This Piece Was Made  
**Made-to-order caution:** Made especially for you. Confirm the estimated production period before ordering.  
**Shipping disclaimer:** Available destinations and actual charges are confirmed at checkout.  
**Impact note:** We publish only verified production and artisan-payment information.

# Appendix B. Go-live checklist

- [ ] Merchant registration, gateway contract and refund process approved.
- [ ] Export/domestic courier process successfully tested for enabled destinations.
- [ ] All P0 modules pass acceptance and security tests.
- [ ] No open critical access, payment or stock-integrity vulnerabilities.
- [ ] Prod `.env`, app key, secret store, backups and restore tested.
- [ ] Domain, TLS, CDN, private bucket access and email authentication working.
- [ ] Payment webhooks authenticated and replay protected; settlement reconciled.
- [ ] Actual SKUs, weights, dimensions, prices and availability verified.
- [ ] Artisan agreements and published media consent documented.
- [ ] Customer legal policies and support channel reachable.
- [ ] 2 languages tested on real devices; no misleading translations.
- [ ] Video fallback, reduced motion, performance and accessibility checked.
- [ ] Queue worker/scheduler running with alerting.
- [ ] Customer order, return and support workflows demonstrably functional.
- [ ] Staff trained for production, QC, stock, packing and payout workflows.
- [ ] Controlled launch limit and rollback/no-charge contingency agreed.

**End of master specification.**
