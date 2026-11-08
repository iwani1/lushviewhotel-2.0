# Phase 3 — Database Migration, Risk Register & Implementation Plan

**Depends on:** `01-phase1-technical-assessment.md`, `02-architecture-and-branding.md`
**Status:** PROPOSAL — awaiting approval. **No code has been written and nothing has been
deployed.**

---

## 1. The one rule that governs everything

> **Do not copy the 1.8.0 files over the live 1.7.0 installation.**

This is not caution for its own sake. It is what the audit measured:

- The 1.8.0 tree contains **no 1.7.0 → 1.8.0 database migration**.
  `install-dev/upgrade/sql/` stops at `1.6.1.0`, and
  `modules/hotelreservationsystem/upgrade/` ships only `upgrade-1.4.0.php`, `1.4.1`, `1.5.0`
  and `1.6.0` — nothing for 1.7.0 or 1.8.0.
- The core upgrader is keyed to the wrong version. `install-dev/upgrade/upgrade.php` gates on
  `_PS_INSTALL_VERSION_` (**1.6.1.23**, the PrestaShop base) rather than
  `_QLO_INSTALL_VERSION_` (**1.8.0.0**), so it believes there is nothing to do.
- The failure is **silent**. `Module::loadUpgradeVersionList()`
  (`classes/module/Module.php:615-670`) returns success when no matching upgrade file exists, so
  `hotelreservationsystem` gets stamped as version 1.8.0 in `qlo_module` **with no schema change
  and no error**.
- Meanwhile 1.8.0 code queries **36 tables and 23 columns that do not exist** in the live
  database (§5.1–5.2 of the assessment). The booking engine would fail on the first request that
  touched tourism tax, selling objects or source tracking.

The 1.8.0 tree is a **development foundation**, exactly as your brief says — not a drop-in
upgrade package.

---

## 2. Migration strategy

### 2.1 Two candidate paths

| | Path A — in-place upgrade | Path B — fresh install + data migration |
|---|---|---|
| Method | Run the QloApps **1-Click Upgrade** add-on from the existing 1.7.0 back office, then apply our own migration for the gaps | Install 1.8.0 clean on staging, then export/import hotel, guest, order and payment data |
| Preserves | Bookings, customers, orders, payments, config, images, URLs — everything in place | Only what we explicitly map |
| Risk | The add-on's behaviour on a 1.7.0 → 1.8.0 jump is **UNVERIFIED** — it is not vendored in this repo (`qloautoupgrade` is a 2-file stub in 1.7.0 and was removed entirely in 1.8.0 per #1345); it is fetched from QloApps' servers at runtime | High. 300 tables of relational data with 1,934 orders and 22,725 guest rows must be mapped by hand. 1.7.0 also changed CSV sample format from product id to room-type id (#1344) |
| Data loss exposure | Low if the add-on works; **the 36 missing tables still need our migration afterwards** | High — bed types, features, free-text payment history, and any customisation not in the database |
| Effort | Lower | Much higher |
| **Recommendation** | **Preferred**, staged, with our own gap-migration applied afterwards | Fallback only if Path A fails on staging |

**Neither path can be validated without a staging copy.** That is the next concrete step.

### 2.2 Migration script design

Version-controlled, additive, idempotent, resumable — because `max_execution_time` on shared
hosting may be well under the 500s the installer wants.

```
modules/lvhmigration/
├── install/
│   ├── 001_preflight.sql          ← read-only checks; aborts if unexpected
│   ├── 010_create_missing_tables.sql   ← the 36 tables, IF NOT EXISTS
│   ├── 020_add_missing_columns.sql     ← the 23 columns, guarded
│   ├── 030_backfill_defaults.sql       ← sensible defaults for new NOT NULL columns
│   ├── 040_migrate_features_to_amenities.sql
│   ├── 050_normalise_payment_methods.sql
│   ├── 060_fix_configuration_defects.sql
│   └── 090_verify.sql             ← row-count and FK assertions
└── rollback/
    ├── 020_drop_added_columns.sql
    └── 010_drop_created_tables.sql
```

Hard rules, from your brief and from what the audit found:

- **Additive only.** `CREATE TABLE IF NOT EXISTS`, `ALTER TABLE … ADD COLUMN`. **No `DROP`,
  no `TRUNCATE`, no `DELETE` without a `WHERE` that has been reviewed and approved.** Legacy
  tables (`htl_bed_type`, `htl_features`, …) are **left in place** even where 1.8.0 ignores
  them — they are the only copy of that data.
- **Idempotent.** Every statement must be safe to re-run, so an interrupted migration can
  resume rather than restart. Column additions are guarded by an
  `information_schema.COLUMNS` existence check.
- **Preflight and verify bookends.** `001_preflight.sql` asserts the database really is
  `QLO_VERSION_DB = 1.7.0.0`, prefix `qlo_`, and that expected row counts are in range
  (1,934 orders, 1,257 customers, 38 rooms, 8 room types). If any assertion fails, the
  migration stops rather than guessing. `090_verify.sql` re-counts afterwards and compares.
- **Chunked.** Any statement touching `qlo_guest` (22,725 rows) or `qlo_connections` (20,992)
  runs in batches with a resumable offset, so a shared-hosting timeout does not leave a
  half-finished migration.
- **Never run against production directly.** Staging database first; production only after
  sign-off, and only after a fresh backup.

### 2.3 The concrete schema work

**Create 36 tables** (full list in the assessment §5.1). Highest priority, because 1.8.0 code
calls them at runtime:

`tax_configuration`, `tax_validity_range`, `tax_price_tier`, `tax_child_range`,
`order_tax_detail`, `order_tax_exemption` — `TaxCalculator::getTaxesAmount()` calls
`TaxConfiguration::getByTaxId()`, so **every price calculation touches these**.
`source`, `source_lang`, `business_source`, `business_source_lang` — referenced from
`qlo_orders.id_source`.
`room_type_selling_object`, `room_type_selling_object_lang` — referenced from
`qlo_product.id_selling_object` and `qlo_htl_room_type.id_selling_object`.
`htl_booking_status`, `htl_booking_status_lang`, `htl_booking_status_history` — the reservation
lifecycle your Module 3 depends on.

**Add 23 columns to 17 tables** (full list in the assessment §5.2).

**Backfill strategy for new columns** — each needs a deliberate default, not `NULL` by accident:

| Column | Proposed backfill | Why |
|---|---|---|
| `qlo_orders.id_source` | New `source` row "Direct / legacy" | Preserves history; lets you tag new channels going forward |
| `qlo_product.id_tourism_tax_rules_group` | Ghana tourism-tax group, or NULL if not levied | Must match `localization/gh.xml` (GTA 1% levy, tax id 83) |
| `qlo_tax.is_tourism_tax` | `1` for the GTA levy row, `0` otherwise | Distinguishes levy from VAT/NHIL/GETFund |
| `qlo_customer.id_country` | `94` (Ghana) where address implies it, else NULL | 94 is the live Ghana country id |
| `qlo_htl_branch_info.id_property_type` | New "Hotel" property type | Only one property row exists |
| `qlo_htl_room_type.id_selling_object` | New default selling object per room type | 8 room types — small, reviewable |
| `qlo_access.kpi` | Grant to administrator profile only | Least privilege |
| `qlo_order_payment_detail.receipt_number` | Generated sequence from existing rows | Needed by Module 5 receipts |

**Each of these is a business decision, not a technical one.** I will propose values and you
approve them before the script runs.

### 2.4 Data migrations

**(a) Features → Amenities.** The live database has 42 `qlo_htl_features` rows (+42 `_lang`) and
7 `qlo_htl_branch_features` assignments. 1.8.0 replaced this model with `htl_amenity`,
`htl_amenity_lang`, `htl_branch_amenity`, `htl_room_type_amenity`. Migration:

```
htl_features           → htl_amenity            (map id, keep a cross-reference column)
htl_features_lang      → htl_amenity_lang       (map id_lang, name, description)
htl_branch_features    → htl_branch_amenity     (map hotel↔feature to hotel↔amenity)
```

Column mapping must be read from both schemas before writing the script — **I have both, so this
is fully specified work, not guesswork.** The old tables are retained afterwards.

**(b) Bed types — DECISION REQUIRED.** 9 `qlo_htl_bed_type` rows, 9 `_lang`, 8
`qlo_htl_room_type_bed_type` assignments. **1.8.0 removed the feature entirely**: the only
remaining references are in `dropTables()` at
`modules/hotelreservationsystem/classes/HotelReservationSystemDb.php:623-625`. There is no target
table. Your options:

1. **Keep the tables as orphans** and surface bed types through a custom `lvh*` module that
   reads them. Data preserved, feature preserved, cost: we maintain it ourselves.
2. **Re-model bed types as amenities** (`htl_amenity` rows like "King bed", "2 doubles").
   Data preserved in spirit, but the structured room-type↔bed assignment is flattened.
3. **Accept the loss.** Cheapest; the guest-facing room pages would stop showing bed
   configuration.

My recommendation is **option 1** — it is the only one that preserves the actual assignment data
(8 rows linking room types to bed types) without inventing a mapping you did not ask for. But
this is your hotel's product data, so you decide.

**(c) Payment method normalisation.** 2,058 `qlo_order_payment` rows carry **23 distinct
free-text `payment_method` values**, including one that is a phone number (`84265253757`). Plan:
create `lvh_payment_method` (id, code, label, is_active, surcharge_fixed, surcharge_var),
seed it from the observed values, add `qlo_order_payment.id_payment_method`, backfill by exact
string match, then report any row that did not match for manual review. The original text column
is **never** modified or dropped, so the mapping is auditable and reversible.

Proposed seed set from the live data:

| Code | Label | Rows today |
|---|---|---|
| `cash` | Cash | 1,006 (`Cash Payment` 993, `cash` 11, `Cash` 2) |
| `momo` | Mobile Money | 861 (`Momo` 629, `momo` 115, `MOMO` 104, `Momo payment` 12, `mom` 1) |
| `pos` | POS / Card terminal | 75 (`P.O.S` 49, `POS` 24, `P O S` 1, `P. O. S` 1) |
| `back_office` | Back-office order | 80 |
| `bank_wire` | Bank transfer | 5 |
| `cheque` | Cheque | 3 |
| `free` | Complimentary | 8 |
| `management` | Approved by management | 6 |
| `partial` | Partial payment | 2 |
| `pending` | Awaiting payment | 10 |
| `mixed` | Cash + Mobile Money | 1 |
| **(manual review)** | `84265253757` | 1 — data-entry error, likely a MoMo transaction |

This is the single highest-value data-quality fix in the project: it makes Module 8's payment
reports possible at all.

**(d) Configuration defects.** `PS_SHOP_NAME` `lushviewhotel.net` → `Lush View Hotel`;
`MB_CANCEL_URL` `http://www.yoursite.com` → real URL; branch latitude/longitude `0.00000000` →
actual Tema coordinates. Details and evidence in doc 02 §6.

**(e) `cashpayment` module.** Must be copied from the live server into the new deployment, with
its four `CONF_CASHPAYMENT_*` settings intact, or replaced by `lvhpayments`. See assessment §6.2.
**Blocked until the file backup arrives.**

---

## 3. Staging strategy on GreenGeeks cPanel

Your brief §9 is explicit that the live system must keep operating. On cPanel shared hosting:

| Requirement | How |
|---|---|
| Separate staging directory | Subdomain, e.g. `staging.lushviewhotel.net` → `/home/<user>/staging_lvh/` created in cPanel → Domains/Subdomains |
| Separate staging database | cPanel → MySQL Databases → create `lushview_stg` + a dedicated DB user with rights **only** on that database |
| Copy of live data | phpMyAdmin → Export live `lushview_qloapp` → Import into `lushview_stg`. If `upload_max_filesize` blocks a 15 MB import, raise it in cPanel → Select PHP Version → Options, or import in chunks, or use cPanel Terminal with `mysql < dump.sql` |
| Staging config | Its own `config/settings.inc.php` pointing at `lushview_stg` with the staging DB user |
| **No real guest email** | On staging set `PS_MAIL_METHOD` to a logging/sink configuration and **do not copy production SMTP credentials**. Test mails must go to your own address only |
| **No real payment requests** | Disable live payment modules on staging; `cashpayment`/`bankwire`/`cheque` are offline methods so they are safe, but any hosted gateway must be pointed at sandbox credentials |
| Live site untouched | Production directory and `lushview_qloapp` database are **read-only** for this project until deployment is approved |
| Original backup untouched | Your uploaded backup is never modified; all work happens on copies |
| Backups before each migration | cPanel → Backup, plus a `mysqldump --single-transaction --routines --triggers` before every migration run |

**Naming caution:** the live admin directory is `admin/` (1.7.0) but 1.8.0 uses `admin-dev/`.
Staging should use a *different, unguessable* name again — not `admin-dev` — because
`admin-dev` is a well-known scanner target and the audit already lists renaming it as a P2 item.

---

## 4. Risk register

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R1 | **1-Click Upgrade add-on fails or partially applies** on a 1.7.0→1.8.0 jump (behaviour UNVERIFIED — not vendored in this repo) | Medium | **Critical** | Test on staging only. Full DB backup first. Our gap-migration runs afterwards regardless, and is idempotent |
| R2 | **Silent module version bump** — `hotelreservationsystem` stamped 1.8.0 with no schema change (`classes/module/Module.php:615-670`) | **High** | **Critical** | Preflight asserts actual schema state, never the version string. `090_verify.sql` re-checks after |
| R3 | **`cashpayment` source unrecoverable** (file backup missing) | Medium | **High** — breaks ~49% of payments | Recover from live server via File Manager; fallback: rebuild from `modules/cheque/` (27 files) + the 4 config values; worst case: `lvhpayments` supersedes it |
| R4 | **Bed-type data has no target in 1.8.0** | Certain | Medium | Decision required (§2.4b). Recommend keeping tables + custom module |
| R5 | **Features→amenities mapping loses guest-facing content** (42 features) | Medium | Medium | Scripted mapping with a retained cross-reference; old tables kept; visual diff of room pages before/after |
| R6 | **Shared-hosting limits** — `max_execution_time`, `memory_limit`, `upload_max_filesize`, `soap`, `allow_url_fopen` | Medium | High | Verify first with the script in doc 02 §10. Chunked, resumable migrations |
| R7 | **Directory renames** `install/`→`install-dev/`, `admin/`→`admin-dev/` break `.htaccess`, cron, bookmarks | **High** | Medium | Checklist in the deployment runbook; grep all cron entries and `.htaccess` for old paths |
| R8 | **Channel-manager swap** — `qlochannelmanagerconnector` (`active=1`) → `qlocmconnector` (#1952) | Medium | **High** if OTAs are connected | Confirm with client which OTAs are live before upgrading; test the new connector on staging |
| R9 | **Live site compromise before upgrade lands** — `qlohotelreview` `active=1` with CVE-2025-67325 (upload → RCE), 4 fix commits absent | **High** | **Critical** | Disable `qlohotelreview` **now** (0 reviews exist, so nothing is lost). Apply the `docs/deploy/live-site-hardening.md` rules. Do not wait for this project |
| R10 | **Data exposure not yet closed** — the purged dump blob is still publicly fetchable by SHA | **Certain** until Support acts | **Critical** | Make repo private (owner action — the automation was denied with HTTP 403), file a GitHub Support purge request, rotate all 15 employee and 1,257 customer passwords, legal read on Act 843 / GDPR |
| R11 | **OSL-3.0 copyleft** limits making the derivative proprietary (§1(c)+§5) | Certain | Medium | Legal review (doc 02 §5). Branding is unaffected; ownership claims are |
| R12 | **Branding bulk-replace breaks booking** — the 6 `QloApps:*` jQuery event namespaces in `themes/hotel-reservation-theme/js/` | **High if attempted** | **Critical** | Surgical, file-by-file changes only. Never `sed` the theme. CI test of the booking flow |
| R13 | **Zero automated tests exist** (`tests/` has bootstrap only) | Certain | Medium | CI already added on this branch (PHP 8.2/8.3/8.4 lint + localization validation). Add workflow tests in Phase 6 |
| R14 | **Import corrupts referential integrity** (orders→details→payments→booking_details) | Medium | **Critical** | `090_verify.sql` asserts FK completeness and row counts against pre-migration snapshot |

---

## 5. Phased implementation plan

Gates are deliberate: **each phase ends with your sign-off before the next begins.**

### Phase 1 — Project audit · ✅ COMPLETE (this document set)
Deliverables 1, 2, 3. See `01-phase1-technical-assessment.md`.
**Blocked item:** old-system file backup unavailable.

### Phase 2 — Development architecture · ✅ COMPLETE as a proposal
Deliverable 4, 5. See `02-architecture-and-branding.md`.
**Gate:** you approve the module list, the `lvh` naming, the branding-config design and the
build order — and supply logo artwork.

### Phase 3 — Core customisation · ⬜ NOT STARTED
Rebrand, redesign dashboard, customise navigation, centralised settings, module standards.
Builds `lvhbranding` then `lvhsettings`.
**Gate:** branding renders correctly on staging; no core files modified; booking flow retested.

### Phase 4 — Custom modules · ⬜ NOT STARTED
Deliverable 6. Build in the order in doc 02 §9. One module at a time, each independently
installed, permission-gated, documented and tested before the next starts.
**Gate:** per-module test report (install, auth, permissions, DB, validation, errors, mobile,
existing functionality, cross-module compatibility, security, performance).

### Phase 5 — Database integration · ⬜ NOT STARTED
Deliverable 7. Write the migration scripts, import a **copy** of the live database into
staging, run them, verify.
**Gate:** `090_verify.sql` passes; 1,934 orders, 1,257 customers, 38 rooms, 2,058 payments all
present and correctly related; features→amenities verified; payment methods normalised with
zero unmatched rows.
**This phase cannot start until the bed-type decision (§2.4b) and the `cashpayment` route are
settled.**

### Phase 6 — Testing · ⬜ NOT STARTED
Deliverable 9. The four workflows from your brief:
- **Reservation:** guest booking → confirmation → payment → check-in → check-out
- **Walk-in:** receptionist → select room → register guest → create reservation → record payment → check-in
- **Housekeeping:** check-out → room dirty → cleaning assigned → inspected → available
- **Payment:** create payment → update balance → generate receipt → view history

Plus: all 15 employee accounts can log in with correct permissions; existing 1,934 reservations
render and can be modified; invoices and PDFs generate; emails send (to test addresses only);
mobile/tablet layouts; security review; performance under the shared-hosting limits.

### Phase 7 — Deployment · ⬜ NOT STARTED
Deliverables 8, 10, 11, 12. Runbook, fresh production backup, apply approved migrations,
deploy tested files, verify every record, rollback procedure documented and rehearsed.
**Gate:** explicit written approval from you. **No production change happens without it.**

---

## 6. Rollback procedure (to be rehearsed on staging before Phase 7)

1. **Stop.** Put the site in maintenance mode (rename the front controller or use an
   `.htaccess` maintenance rule) so no new bookings land mid-rollback.
2. **Restore the database** from the pre-migration `mysqldump` taken immediately before the
   migration ran. On cPanel: phpMyAdmin → Import, or `mysql lushview_qloapp < backup.sql`.
3. **Restore the files** from the pre-deployment archive of the production directory.
4. **Restore `config/settings.inc.php`** — it holds DB credentials and the `_COOKIE_KEY_`
   salt. Losing it invalidates every session and every password hash.
5. **Clear caches**: delete `cache/class_index.php`, `cache/smarty/compile/*`,
   `cache/smarty/cache/*`.
6. **Verify** with the read-only checks from `090_verify.sql`: row counts for orders,
   customers, rooms, payments match the pre-migration snapshot.
7. **Take the site out of maintenance mode** and confirm a test booking end-to-end.

Because every migration is additive, a **partial** rollback is also possible: the
`rollback/` scripts drop only the columns and tables we added, leaving all original data
untouched. That is a deliberate design choice — it means a failed migration is recoverable
without a full restore.

---

## 7. What I need from you to proceed

**Blocking:**
1. **Re-upload the complete old-system file backup** (all archive volumes, or a single
   non-split zip excluding `cache/`). Without it: no modified-core-file diff, no `cashpayment`
   source, no customisation inventory.
2. **Bed-type decision** — option 1, 2 or 3 in §2.4(b).
3. **`cashpayment` recovery route** — retrieve from live server, rebuild from `cheque`, or
   replace with `lvhpayments`.
4. **Application name and logo artwork** — I cannot invent your brand identity.
5. **Run the hosting verification script** (doc 02 §10) in cPanel and send me the output.

**Security, outstanding and time-critical (independent of this project):**
6. Make `iwani1/lushviewhotel-2.0` **private** — Settings → Danger Zone. The automated attempt
   was denied with HTTP 403 because the token lacks the GitHub App Administration permission.
7. File the **GitHub Support** purge request for the dangling commit and blob.
8. **Disable `qlohotelreview`** on the live site (0 reviews exist; it carries an RCE-class CVE).
9. Rotate the 15 employee passwords and force 1,257 customer resets.
10. Legal read on breach notification — Ghana Data Protection Act 2012 (Act 843), plus GDPR if
    any EU data subjects.

**Decisions needed but not blocking Phase 3:**
11. `lushviewhotel.com` vs `.net` — which is canonical? They carry different addresses and
    emails.
12. The Tema (`H71 Afariwa Rd`) vs Ashaiman (`H417 Nii Teeth Kple Street`) listing conflict —
    one property or two?
13. Channel manager: which OTAs are actually connected via `qlochannelmanagerconnector`?
14. `need_zip_code` — Ghana has no postal-code system; the field is currently mandatory and may
    cost you bookings. GhanaPostGPS codes like `GA-183-6412` validate fine, so the question is
    conversion, not validation.
15. Whether to install `qlositemap` (live `/sitemap.xml` currently 404s) and
    `qlogooglehotelconnector` (you have 123 Google reviews at 4.2).
