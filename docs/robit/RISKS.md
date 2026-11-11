# Robit Handloom — open risks

| ID | Risk | Severity | Evidence | Owner | Mitigation / next step |
| --- | --- | --- | --- | --- | --- |
| R-01 | Payment provider cannot onboard an Ethiopian merchant | Critical (business) | Blueprint §9.3; no onboarding evidence | Founder | M0 provider evaluation; keep gateways disabled |
| R-02 | Charged order not linked to its charge if the request dies mid-checkout | High | SECURITY_GATE S-09 | Tech | Payment-attempt ledger written before the provider call (M6) |
| R-03 | PayPal amount set by the browser and not verified | High | SECURITY_GATE S-07 | Tech | Keep PayPal disabled; server-created orders with amount check if ever enabled |
| R-04 | Pending orders hold stock indefinitely | High | SECURITY_GATE S-03 | Tech | Reservation expiry (M6) |
| R-05 | Tests run inside the app container could wipe the dev database | Closed | SECURITY_GATE S-12, §5 (it happened once) | Tech | `<server force>` in `phpunit.xml`; proved with a canary host |
| R-06 | Robit work pushed to the upstream repository, or audit detail published | Medium | `azabla/shemane` is public by the owner's choice | Owner | Upstream push URL disabled; `docs/robit/private/` gitignored and holds `SECURITY_GATE.md` |
| R-11 | Another local project's Vite holds host port 5173 | Low | `dejeneethiopiagifts` vite on `127.0.0.1:5173` | Tech | `VITE_PORT=5174` in `.env` |
| R-07 | Production image unbuildable without the RoadRunner packages in the committed lock | Medium | `BASELINE_AUDIT.md` §5 | Tech | Commit the lock change through the `composer-require` workflow |
| R-08 | Queue jobs dispatched inside transactions run before commit | Medium | SECURITY_GATE U-H6 | Tech | `after_commit` / `afterCommit()` before a real queue (M6) |
| R-09 | Upstream changes rapidly; merging upstream mid-build | Medium | Wave workflow in upstream `CLAUDE.md` | Tech | Pin `befbc58`; take upstream changes deliberately |
| R-10 | Invented product, artisan or cultural facts reach production | High | Blueprint §1, §13 | Content | Synthetic fixtures labelled; publish gate (M5) |
