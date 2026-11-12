# Robit Handloom — upstream mapping and gap matrix

Baseline: `liberusoftware/ecommerce-laravel` at `befbc58` (main, 2026-11-10). Every "exists" below was
read in the source at that SHA; nothing is taken from the README or the Robit pack. See
[`BASELINE_AUDIT.md`](./BASELINE_AUDIT.md) for versions and evidence, and
`private/SECURITY_GATE.md` (not in git) for the security status of each surface.

Legend — **Reuse**: use as is. **Extend**: keep the upstream owner, add a Robit extension table or
service. **Repair**: upstream owns it but it fails a Robit invariant. **New**: no upstream owner.

## 1. Ownership map

| Concept | Upstream owner at `befbc58` | Decision | Notes |
| --- | --- | --- | --- |
| Identity, staff | `User`, Jetstream teams, Fortify, Spatie `Role`/`Permission`, `App\Support\StoreAccess` | Reuse | 2FA available through Fortify; not enforced for staff |
| Merchant boundary | `Team` → `Store` → `Channel` → `ChannelDomain`; `ResolveChannel` middleware, `ChannelResolver`, `StoreContext` | Reuse, configure | One Team, one Store, one Channel for Robit. Initial channel migration binds `APP_URL` host, `localhost`, `127.0.0.1` |
| Tenancy enforcement | `IsStoreScoped` (18 models), `IsTenantModel` (12 models), global scopes | Reuse | Must still be proven per surface (M3) |
| Catalogue | `Product`, `ProductCategory`, `ProductCollection`, `ProductImage`, `Tag` | Extend | Textile specs, provenance and maker attribution go in `rh_*` extension tables keyed on `products.id` |
| Variants and options | `ProductVariant`, `ProductOptionGroup`, `ProductOptionChoice`, `ProductOptionResolver`, `ProductVariantService` | Extend | Physical dimensions, lead time and production type per variant |
| Stock | `products.inventory_count` / `product_variants.inventory_count`, `App\Services\Inventory::adjust()` (guarded atomic decrement) + `InventoryLog` | Reuse, extend | Counted stock is atomic. No reservations with expiry, no locations, no physical units |
| Cart | Session cart (`CartService`) and `CartItem` (GraphQL/REST) | Reuse | Mirrored stock guards on both paths |
| Checkout | `CheckoutController` and `HeadlessCheckoutService` → `CheckoutService` | Repair | Money core is shared; payment boundary does not meet Robit §9.4 (see §3) |
| Payments | `PaymentGatewayFactory` → `StripeGateway`, `PayPalGateway` (`PaymentGatewayInterface`); Stripe/PayPal webhook controllers | Repair / New adapter | No payment-attempt ledger, no webhook inbox, float amounts. Neither provider is shown eligible for an Ethiopian merchant |
| Orders | `Order`, `OrderItem` (name/SKU/price snapshot), `OrderStatusHistory`, `Order::transitionTo()` | Reuse, extend | Upstream states differ from Robit §6.1; map rather than replace |
| Invoices, refunds | `Invoice`, `Refund`, `RefundItem` | Reuse | Verify refund idempotency in M6 |
| Returns | `ReturnRequest`, `ReturnRequestItem` | Extend | QC disposition before restock is Robit-specific |
| Shipping | `ShippingMethod`, `ShippingQuote`, `ShippingService`, `CarrierRateInterface`, `app/Services/Shipping` | Extend | No shipments, shipment items or carrier events |
| Fulfilment | `OrderFulfilment`, `Fulfilment\OrderFulfilments`, `DispatchDropshippingOrder` | Not used initially | Printful/DropXL supplier path; Robit fulfils from its own workshops |
| Tax | `TaxClass`, `TaxRate`, `TaxCalculator`, EU VAT/OSS/VIES | Not applicable as is | EU-specific; Ethiopian tax treatment needs adviser input (M0) |
| CMS pages, menus | `Page`, `Menu`, `MenuItem`, menu-builder plugin | Reuse for policy pages | Heritage CMS (typed blocks, translations, consent) is New |
| Themes | `channels.theme` → `themes/<name>/` view overrides (`docs/THEMES.md`) | Reuse | Robit storefront should be a theme, not edits to core views |
| Modules | `liberusoftware/module-manager`, per-team activation | Reuse | Robit domains may become modules later; start in-app |
| Customers, GDPR | `Customer`, `GdprExportService`, `GdprErasureService` | Reuse | |
| Reviews, wishlist, chat, analytics | `ProductReview`, `ProductRating`, `Wishlist`, `Chat*`, `AnalyticsService` | Defer | Not in the Robit P0 path |
| Subscriptions, memberships | Cashier, `PaypalSubscription`, `Membership` | Out of scope | Disable for Robit |
| Facebook catalogue | `Facebook*` | Out of scope | |
| Localisation | `lang/` (Laravel files), single `en` locale | New | No Amharic, no translation tables, no locale-prefixed routes |
| Artisans, workshops, consent | — | New | `rh_artisans`, `rh_artisan_private`, `rh_workshops`, `rh_media_consents`, `rh_artisan_contributions` |
| Production and QC | — | New | `rh_production_orders`, `rh_production_assignments`, `rh_production_events`, `rh_quality_checks` |
| Artisan finance | — | New | `rh_artisan_agreements`, `rh_artisan_payables`, `rh_artisan_payouts` |
| Payment attempts, webhook inbox, reconciliation | — | New | `rh_payment_attempts`, `rh_payment_webhook_inbox`, `rh_payment_reconciliations` |
| Shipments and carrier events | — | New | `rh_shipments`, `rh_shipment_items`, `rh_shipment_events` |
| Domain audit, outbox | `OrderStatusHistory` covers orders only | New | `rh_domain_audit`, `rh_outbox_events` |

## 2. Gap matrix

| Requirement (blueprint §) | Already implemented | Needs modification | New Robit work | Risk | Test | Milestone |
| --- | --- | --- | --- | --- | --- | --- |
| Single merchant, no self-service merchants (§12.1, §16.4) | Team/Store/Channel resolution by host | Disable Jetstream team creation and public registration of merchants | Robit store bootstrap command | High | Direct-request tests on `/app/new`, team create | M3 |
| Policies on every surface (§12.2) | `strictAuthorization()` on both panels, 29 policies, `StoreAccess` | Role matrix differs from Robit's 10 roles | Workshop lead, QA, finance roles | High | Per-role direct-ID tests | M3 |
| No free money/status edits (§12.3) | Order form disables and does not dehydrate `status`, `payment_status`, `total_amount` | Regression test against a crafted Livewire payload | — | High | Livewire payload test | M3 |
| Immutable order snapshots (§9.5) | `order_items` stores name, SKU, price, options | Address snapshot is spread across order columns; no FX snapshot | — | Medium | Reprice-after-order test | M6 |
| Atomic stock (§6.2, §9.5) | Guarded decrement in `Inventory::adjust()` | Pending orders hold stock with no expiry | Reservations with expiry; unique physical units | High | Two-buyer last-unit test | M5/M6 |
| Payment attempt ledger and idempotency (§9.4) | — | Checkout calls gateway with float amount and no idempotency key | `rh_payment_attempts` | Critical before live money | Duplicate submit test | M6 |
| Webhook inbox and dedup (§9.4) | Stripe signature verified; transitions guarded by state machine | No event-id dedup; amount not compared | `rh_payment_webhook_inbox` | High | Replay test | M6 |
| Ethiopian provider (§9.3) | — | — | Provider-neutral adapter, disabled until onboarding | External | Fake-adapter tests | M0/M6 |
| Product types: stocked/unique/MTO/quote (§6.2) | Counted stock only | — | `rh_variant_specs.production_type` | Medium | Type-specific purchase rules | M5 |
| Textile specs and publish gate (§6.3) | — | — | `rh_product_specs`, publish validation | Medium | Publish-blocked test | M5 |
| Artisan profiles and consent (§7) | — | — | Artisans module | High (PII) | Serialisation allowlist tests | M8 |
| Production and QC (§8) | — | — | Production module | Medium | Transition ledger tests | M9 |
| Artisan payables/payouts (§9.6) | — | — | Finance extension | High | No double payout, dual control | M10 |
| Shipments and tracking (§10) | Rate quotes only | — | Shipments module | Medium | Quantity allocation test | M7 |
| EN/AM localisation (§14) | `en` only | — | Translations, fonts, routing | Medium | Locale route tests | M4/M11 |
| Cinematic home and heritage CMS (§5, §13) | Default storefront home | — | Robit theme, typed blocks | Low (security), High (truth) | No-JS, reduced-motion | M11 |
| Guest order lookup (§11) | Order history for authenticated users | — | Signed token lookup | High | Enumeration test | M12 |
| Dev environment with HMR (§18) | Production-style image, no bind mount, no Vite service | — | `compose.dev.yaml` | Low | HMR acceptance | M2 |

## 3. What not to reuse as is

- **Stripe and PayPal checkout paths.** The code works as an integration, but it is not evidence that
  either provider will onboard an Ethiopian merchant (blueprint §9.3). Keep them disabled for the Robit
  channel. The PayPal path also takes its amount from the browser (SECURITY_GATE S-07).
- **EU VAT, OSS, VIES.** Built for EU sellers. Do not present it to Robit customers until a tax adviser
  has defined Ethiopian treatment.
- **Dropshipping suppliers.** Printful/DropXL are installed packages; Robit products must not name a
  supplier.
