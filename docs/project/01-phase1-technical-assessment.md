# Phase 1 — Technical Assessment

**Project:** Redevelop QloApps 1.7.0 (live) into an independently branded hotel management
platform on the QloApps 1.8.0 codebase.
**Client environment:** GreenGeeks cPanel shared hosting · live site `lushviewhotel.net`
**Status:** AUDIT ONLY — no files renamed, deleted or rewritten. Awaiting approval of the
implementation plan (see `03-migration-risks-and-plan.md`).
**Date:** 2026-11-06

> Every number in this document was measured from the actual repository tree and the actual
> production database export during this audit. Where something could not be verified it is
> labelled **UNVERIFIED** rather than assumed.

---

## 1. Files I can actually access

| Item | State | Evidence |
|---|---|---|
| **Latest GitHub source** (QloApps 1.8.0) | ✅ Present, complete | 8,318 tracked files at repo root on branch `arena/07df51c3-lushviewhotel-2-0` |
| **Live production database** | ⚠️ Accessible for analysis, **deliberately not stored in the repo** | 16,140,519-byte export, 300 tables. Purged from branch history on your instruction; analysed from a local copy outside the repository |
| **Old system FILES backup** | ❌ **NOT AVAILABLE** | See §2 — this is the single biggest gap in the project |
| Project brief text file | ❌ Deleted by you | `i have hotel managemet.txt` (431 lines) added in `b9785b2`, deleted in `0fd5324` |

### 2. The old-system file backup is missing — and this changes the plan

Your instructions say *"I have uploaded a recent backup of the live application, including the
old system files and database."* The database arrived. **The files did not.** Here is the
complete history of the `main` branch, recovered through the GitHub API (the working clone was
shallow and hid it):

| Commit | Date | Action |
|---|---|---|
| `b9785b2` | 2026-11-04 | Add `i have hotel managemet.txt` (431 lines) |
| `0fd5324` | 2026-11-05 | Delete `i have hotel managemet.txt` |
| `812fbf6` | 2026-11-05 | Add `split-archive.7z.001` — **20,971,520 bytes** |
| `1f276d0` | 2026-11-06 | Delete `split-archive.7z.001` |

Two independent problems:

1. **`main` currently contains zero files.** Its tree is empty. Every upload was subsequently
   deleted.
2. **The archive was incomplete even before deletion.** It is named `split-archive.7z.001` and
   is *exactly* 20 MiB (20,971,520 bytes = 20 × 1024 × 1024) — the signature of one chunk of a
   multi-volume 7-Zip split archive. **No `.002`, `.003`, … was ever uploaded.** A split archive
   cannot be opened from its first volume alone; 7-Zip stores its file-index metadata at the
   *end* of the last volume, so we cannot even list the filenames.

**Consequences you need to know about now:**

- I cannot diff your live files against the 1.8.0 source, so I cannot enumerate *modified core
  files* — one of your requested comparison items. That item is **blocked**, not skipped.
- I cannot recover the source of your one custom module, `cashpayment` (§6.2), even though I
  can prove from the database that it exists and is enabled.
- Any uploaded documents, custom images, or template edits on the live server are invisible to
  this audit.

**What I need from you:** re-upload the *complete* archive. Options, best first:
(a) all volumes `split-archive.7z.001 … .00N`; (b) a single non-split `.zip`/`.tar.gz` of the
live `public_html` (excluding `cache/`); (c) if the archive is large, upload it somewhere with
direct download and give me the URL. Note that GitHub's web UI caps a single file at 25 MB,
which is presumably *why* it was split — option (b) with `cache/` excluded will usually fit, or
use several zips (`files-1.zip`, `files-2.zip`) that are each independently complete.

---

## 3. Latest GitHub source — architecture and technology stack

**Identity.** QloApps **1.8.0.0**, built on a **PrestaShop 1.6.1.23** core. The tree in this
repository is byte-identical to upstream tag `v1.8.0`; GitHub reports `v1.8.0...develop` as
`identical` (0 ahead, 0 behind), so this is the stable release rather than a moving branch.
Upstream `develop` HEAD at snapshot time: `e502b63f83a3b1d9d40caea4993a5fe817b96396`.

**Licence.** Core is **OSL-3.0** (`LICENSE.md`, `composer.json` `"license": "OSL-3.0"`).
Modules are **AFL-3.0** (per-file headers, e.g. `modules/cheque/cheque.php:7,10,23`). This
constrains the rebranding — see `02-architecture-and-branding.md` §5. It is not a blocker, but
it is not "remove all branding" either.

**Runtime requirements.** Two authoritative sources agree:

- `composer.json` → `"php": ">=8.2 <8.6"` and extensions `curl`, `dom`, `gd`, `openssl`,
  `pdo_mysql`, `phar`, `simplexml`, `soap`, `zip`.
- `install-dev/controllers/http/system.php:88-103` — the installer's own gate, which is what
  actually blocks an install:

| Check | Requirement |
|---|---|
| `phpversion` | **8.2 – 8.5** |
| extensions | `gd`, `pdo_mysql`, `curl`, `soap`, `openssl`, `simplexml`, `zip` |
| `memory_limit` | **≥ 128M** |
| `max_execution_time` | **≥ 500** |
| `upload_max_filesize` | **≥ 16M** |
| `allow_url_fopen` | **On** |
| `upload` / `system` | must be able to upload files and create files & folders |

Note what is **not** required: `intl`, `mbstring`, and — after the fix already committed on
this branch — `ext-mcrypt` (removed from `composer.json`, because mcrypt does not exist in any
PHP ≥ 7.2 and its presence made `composer install` fail outright).

**Dependency and build model — good news for shared hosting.**

- `composer.json` has **zero package requirements** and no `autoload` section. `vendor/` does
  not exist and is not needed. Third-party libraries are **vendored in-tree** under `tools/`
  (`defuse`, `htmlpurifier`, `mailer/psr`, `geoip`, `minify_html`, `js_minify`, `mobile_detect`,
  `parser_sql`, `pear`, `CssSplitter.php`, `http_build_url`).
- There is **no Node/npm build step**: no `package.json`, no `Gruntfile.js`, no `gulpfile.js`.
  The admin theme ships precompiled `admin-dev/themes/default/css/` alongside its `sass/`
  sources.
- Autoloading is QloApps' own class-index mechanism (`cache/class_index.php`), not Composer's.

**Practical consequence:** deployment to cPanel is a file upload plus a database import. No
SSH-only tooling, no Composer, no root, no daemons. That fits GreenGeeks shared hosting.

**Directory layout — the rename that breaks naive deployments.** 1.7.0 used `install/` and
`admin/`; 1.8.0 uses **`install-dev/`** and **`admin-dev/`**. Any deploy script, `.htaccess`
rule, cron entry or bookmark referencing the old paths will break silently.

**Structure.**

```
index.php  init.php  header.php  footer.php   ← front controller bootstrap
classes/         ← ~core object model (Cart, Order, Customer, Tools, Validate, …)
controllers/     ← front/ and admin/ controllers
modules/         ← 61 module directories (the extension point we will use)
themes/          ← 1 theme: hotel-reservation-theme/
admin-dev/       ← back office (own theme: admin-dev/themes/default/)
install-dev/     ← installer + PrestaShop-era upgrade machinery
override/        ← supported core-extension point (classes/ controllers/)
config/          ← settings.inc.php is generated at install (gitignored)
localization/    ← 83 country packs + default.xml (+ gh.xml added on this branch)
tools/           ← vendored third-party libraries
pdf/ mails/ img/ js/ css/ translations/ cache/ upload/ download/ log/
```

---

## 4. The live system — database structure and version

**Provenance.** Export produced by **phpMyAdmin 5.2.3** from **MariaDB 11.4.13** on a host
running **PHP 8.4.27**, generated 2026-11-06 10:04, database name `lushview_qloapp`.

**Version — read from the database, not guessed.** QloApps records its own installed version
(feature added upstream in #1481):

| Key | Value |
|---|---|
| `PS_VERSION_DB` | `1.6.1.23` |
| `QLO_VERSION_DB` | **`1.7.0.0`** |

This confirms the live site is **1.7.0** and that this project is a **1.7.0 → 1.8.0** migration.
It also confirms the host PHP (**8.4.27**) is inside the required 8.2–8.5 window — **no PHP
upgrade is needed**, which removes what had looked like the largest schedule risk.

**Shape.** 300 tables, prefix **`qlo_`** (not the PrestaShop default `ps_` — every migration
script must use this prefix), 459 rows in `qlo_configuration`, 56 rows in `qlo_module`.

**Operational data that must survive:**

| Table | Rows | What it is |
|---|---|---|
| `qlo_guest` | 22,725 | Guest/session records |
| `qlo_connections` | 20,992 | Connection log |
| `qlo_mail` | 11,021 | Mail queue |
| `qlo_htl_cart_booking_data` | 2,570 | Booking-engine cart data |
| `qlo_htl_booking_detail` | 2,350 | Per-room booking lines |
| `qlo_order_detail` | 2,104 | Order lines |
| `qlo_order_payment` | 2,058 | Payment records |
| `qlo_orders` | 1,934 | **Reservations** |
| `qlo_customer` | 1,257 | Customer accounts |
| `qlo_htl_room_information` | 38 | **Individual rooms** |
| `qlo_htl_room_type` | 8 | **Room types** |
| `qlo_htl_room_type_service_product` | 12 | Extra services per room type |
| `qlo_employee` | 15 | Back-office accounts (all `active=1`) |
| `qlo_htl_branch_info` | 1 | **One property** (single-hotel install) |
| `qlo_qhr_hotel_review` | **0** | Hotel reviews — **empty** |

`qlo_qhr_hotel_review` having zero rows matters: the `qlohotelreview` module carries
CVE-2025-67325 (unauthenticated file upload → remote code execution), the fix is absent from
1.7.0, and the module is `active=1` in production. Because no reviews exist, **disabling it
costs the client nothing** — that is the highest-value, lowest-cost security action available
and it is a back-office toggle.

**Payment reality (from 2,058 `qlo_order_payment` rows).** Card columns `card_number`,
`card_holder`, `card_expiration`, `card_brand` are **empty in every row** — no card data is
stored, so this is not a PCI cardholder-data system. The `payment_method` column is **free
text** and contains **23 distinct spellings**:

| Method | Rows | |
|---|---|---|
| Cash | ~1,006 | `Cash Payment` 993, `cash` 11, `Cash` 2 |
| Mobile money | ~861 | `Momo` 629, `momo` 115, `MOMO` 104, `Momo payment` 12, `mom` 1 |
| Back-office order | 80 | |
| POS | ~75 | `P.O.S` 49, `POS` 24, `P O S` 1, `P. O. S` 1 |
| Awaiting payment | 10 | three spellings |
| Free order | 8 | |
| Approved by Management | 6 | |
| Bank wire | 5 | |
| Cheque | 3 | `Payment by check` |
| Partial payment | 2 | |
| Mixed | 1 | `Cash Payment and Momo` |
| **Data-entry error** | **1** | the value is a **phone number**, `84265253757` |

This is a concrete, measurable defect and it directly justifies Module 5 (Payment Management):
a normalised payment-method dictionary with a foreign key, plus a migration that maps these 23
strings onto it. Reporting (Module 8) cannot be built reliably on free text — today "cash"
would be under-reported by 13 rows and mobile money split five ways.

---

## 5. Old vs new — the differences that matter

### 5.1 Tables

| | Count |
|---|---|
| Live 1.7.0 database | 300 |
| 1.8.0 core schema (`install-dev/data/db_structure.sql` + `theme.sql`) | 249 |
| 1.8.0 module-created tables (DDL inside module PHP) | 71 |
| **1.8.0 total installable tables** | **320** |
| Shared by both | 284 |

**36 tables exist in 1.8.0 but not in the live database.** A migration must create all of them
before 1.8.0 code will run. Grouped by the feature they carry:

| Feature (upstream change) | Tables to create |
|---|---|
| **Tourism tax** (#1760) | `tax_configuration`, `tax_validity_range`, `tax_price_tier`, `tax_child_range`, `order_tax_detail`, `order_tax_exemption` |
| **Source / business-source tracking** (#1782) | `source`, `source_lang`, `business_source`, `business_source_lang` |
| **Selling objects** (new room/product abstraction) | `room_type_selling_object`, `room_type_selling_object_lang` |
| Cart-rule hotel restriction | `cart_rule_hotel` |
| **Amenities** (replaces "features") | `htl_amenity`, `htl_amenity_lang`, `htl_branch_amenity`, `htl_room_type_amenity` |
| **Booking status workflow** | `htl_booking_status`, `htl_booking_status_lang`, `htl_booking_status_history` |
| Property types / images | `htl_property_type`, `htl_property_type_lang`, `htl_header_image`, `htl_header_image_lang`, `htl_image_category`, `htl_image_category_lang` |
| Connected rooms (#1671) | `htl_connected_room` |
| Cron task manager (#1714) | `qctm_cron_task`, `qctm_cron_task_log` |
| Google Hotels connector (#1838) | `qghc_api_log`, `qghc_ari_updates`, `ari_updates` |
| Duitku payment | `qdp_duitku_order_lock`, `qdp_duitku_payment_transaction` |
| PayPal Commerce | `wk_paypal_commerce_order`, `wk_paypal_commerce_refund` |

Two of these deserve emphasis because they map onto your required modules:
**`htl_booking_status` + `htl_booking_status_history`** is a real status-workflow subsystem that
1.7.0 lacked — it is the natural foundation for Module 3 (reservation lifecycle) and Module 6
(housekeeping states), so we should build on it rather than invent a parallel one.

**16 tables exist in the live database but 1.8.0 never creates them.** These are 1.7.0 features
that upstream removed. Verified: the only remaining references are in
`modules/hotelreservationsystem/classes/HotelReservationSystemDb.php:584` `dropTables()` (the
uninstall list) and legacy `upgrade/` scripts — not in live code paths.

| Legacy table | Live rows | Verdict |
|---|---|---|
| `qlo_htl_features` | **42** | ⚠️ Replaced by `htl_amenity` — **data must be migrated** |
| `qlo_htl_features_lang` | **42** | ⚠️ Migrate to `htl_amenity_lang` |
| `qlo_htl_bed_type` | **9** | ⚠️ **No replacement in 1.8.0** — feature removed |
| `qlo_htl_bed_type_lang` | **9** | ⚠️ Same |
| `qlo_htl_room_type_bed_type` | **8** | ⚠️ Room-type↔bed assignments — no target table |
| `qlo_htl_branch_features` | **7** | ⚠️ Migrate to `htl_branch_amenity` |
| `qlo_htl_room_type_global_demand` | 1 | Legacy extra-service definition; superseded by service products (12 rows already in use) |
| `qlo_htl_room_type_global_demand_lang` | 1 | Same |
| `qlo_htl_booking_demands` | 0 | Empty — no action |
| `qlo_htl_booking_demands_tax` | 0 | Empty — no action |
| `qlo_htl_room_type_demand` | 0 | Empty — no action |
| `qlo_htl_room_type_demand_price` | 0 | Empty — no action |
| `qlo_htl_room_type_global_demand_advance_option` | 0 | Empty — no action |
| `qlo_htl_room_type_global_demand_advance_option_lang` | 0 | Empty — no action |
| `qlo_order_detail_tax` | 0 | Empty; the 1.8.0 code that would write it, `Order::updateOrderDetailTax()` (`classes/order/Order.php:2792`), is **dead** — its only two references are a commented-out call at `classes/PaymentModule.php:1676` and a `@deprecated` docblock at `classes/order/OrderDetail.php:380` |
| `qlo_qcmc_channel_manager_booking` | 0 | Belongs to the channel-manager connector being replaced |

So: **8 legacy tables hold data, and only two groups are genuinely at risk** — hotel *features*
(91 rows across 3 tables, which have a clear new home in the amenity tables) and *bed types*
(26 rows across 3 tables, which have **no** home in 1.8.0).

### 5.2 Columns

**17 shared tables are missing 23 columns that 1.8.0 expects.** Missing columns are the
dangerous kind: 1.8.0 PHP will issue queries against them and fail.

| Table | Missing columns | Driven by |
|---|---|---|
| `qlo_tax` | `is_tourism_tax` | tourism tax |
| `qlo_tax_rules_group` | `is_tourism_tax_rule_group` | tourism tax |
| `qlo_product` | `id_tourism_tax_rules_group`, `id_selling_object` | tourism tax, selling objects |
| `qlo_product_shop` | `id_tourism_tax_rules_group` | tourism tax |
| `qlo_order_detail` | `id_tourism_tax_rule_group` | tourism tax |
| `qlo_htl_branch_info` | `id_property_type`, `tourism_tax_collection_type` | property types, tourism tax |
| `qlo_htl_booking_detail` | `selling_object_name`, `selling_object_plural_name`, `property_type_name` | selling objects |
| `qlo_htl_room_type` | `id_selling_object` | selling objects |
| `qlo_htl_image` | `id_htl_image_category` | image categories |
| `qlo_orders` | `id_source` | source tracking |
| `qlo_customer` | `id_country` | customer country |
| `qlo_feature` | `logo_type`, `logo`, `is_featured` | feature logos |
| `qlo_cart_rule` | `hotel_restriction` | cart rules per hotel |
| `qlo_access` | `kpi` | permission for KPI panels |
| `qlo_order_payment_detail` | `receipt_number` | **receipt numbering — needed by your Module 5** |
| `qlo_order_return` | `event_type` | refunds |
| `qlo_order_slip` | `remark` | credit slips |

Only 2 shared tables carry **extra** live-only columns, which are normally harmless:
`qlo_htl_cart_booking_data.extra_demands` and `qlo_newsletter.id_shop` / `id_shop_group`.
`extra_demands` is worth a look during migration — it is a column 1.8.0 does not create, so it
either came from a 1.7.0 upgrade script or from a local customisation.

### 5.3 Modules

| | Count |
|---|---|
| Bundled in v1.7.0 | 57 |
| Bundled in v1.8.0 | 61 |
| Installed on the live site | 56 |

- **Added in 1.8.0:** `qlocmconnector`, `qlocrontaskmanager`, `qloduitkupayment`,
  `qlogooglehotelconnector`, `qlohotelreports`, `qlositemap`.
  `qlositemap` is directly relevant — the live site's `/sitemap.xml` currently 404s.
  `qlohotelreports` overlaps your Module 8 and should be evaluated before we build our own.
- **Removed in 1.8.0:** `qloautoupgrade`, `qlochannelmanagerconnector`.
- **Bundled in 1.7.0 but not installed live:** `qlocleaner`, `qlopaypalcommerce`.

**Three modules the live site depends on disappear in 1.8.0:**

| Module | Live state | Impact |
|---|---|---|
| `qlochannelmanagerconnector` | `active=1`, v1.0.1 | Replaced by `qlocmconnector` (#1952). If any OTA/channel manager is connected, this is a **breaking swap needing configuration and data migration** |
| `qloautoupgrade` | `active=1`, v1.0.5 | Removed from core (#1345); now a separately distributed add-on. This is also why the upgrade vehicle is not in this repo |
| **`cashpayment`** | `active=1`, **v2.6.8** | **Not a QloApps module at all** — see §6 |

### 5.4 What 1.8.0 gives you that maps onto your required modules

Worth stating plainly, because it changes how much we need to build:

| Your module | Already in 1.8.0 | Gap we must fill |
|---|---|---|
| 1 Dashboard | 11 `dash*` modules installed live (activity, occupancy, trends, availability, performance, goals, insights, products, guestcycle) | A redesigned, cohesive dashboard shell; quick actions |
| 2 Room Management | `htl_room_type`, `htl_room_information`, `htl_room_status`, `htl_room_disable_dates`, new `htl_connected_room`, `htl_amenity` | **Housekeeping status and maintenance workflow are not modelled** |
| 3 Reservations | Full booking engine, refunds (`htl_order_refund_rules`), new `htl_booking_status` + history | Walk-in/telephone channel tagging, modification UI |
| 4 Guests | `qlo_customer` (1,257), `qlo_guest` (22,725), new `customer.id_country` | ID document capture, preferences, special requests |
| 5 Payments | `qlo_order_payment`, `qlo_order_payment_detail` (+ new `receipt_number`), `qlo_order_slip`, `qlo_order_return` (+ `event_type`) | **Normalised payment methods** (23 free-text spellings today), MoMo, daily reports |
| 6 Housekeeping | `htl_room_status` only | **Largely net-new**: assignments, schedules, inspection, activity log |
| 7 Staff & Permissions | `qlo_employee`, `qlo_access`, `qlo_profile`, `qlo_profile_lang` (+ new `access.kpi`) | Role presets (receptionist/housekeeping/accounting), **activity & login audit log is net-new** |
| 8 Reports | New `qlohotelreports` module (#1791), 15 `stats*` modules | Evaluate `qlohotelreports` first; Excel/PDF export |
| 9 Settings | `qlo_configuration` (459 rows), `qlo_htl_branch_info`, localization packs (GHS pack already added on this branch as `localization/gh.xml`) | Centralised hotel-settings screen, check-in 13:00 / check-out 12:00 defaults |

**Conclusion:** roughly 60–70% of your functional requirement already exists in 1.8.0 and should
be *reused and re-skinned*, not rebuilt. The genuinely net-new work is housekeeping, the
activity/audit log, guest identity capture, and payment normalisation. Your own instruction
("integrate with the existing application rather than duplicate existing functionality") points
the same way.

---

## 6. Existing customisations found in the live system

### 6.1 No custom database tables
All 300 live tables are standard QloApps 1.7.0 tables. Every one of the 16 "1.8.0 does not
create these" tables is a known 1.7.0 table. **No client-authored tables exist.**

### 6.2 Exactly one custom module: `cashpayment`
Proven from the database, and it is business-critical:

- `qlo_module` row `(55, 'cashpayment', 1, '2.6.8')` — **enabled**.
- It appears in **neither** upstream tag: `GET /repos/QloApps/QloApps/contents/modules/cashpayment`
  returns **404 for both `v1.7.0` and `v1.8.0`**.
- It registers two front controllers in `qlo_meta`: rows 39 and 40,
  `module-cashpayment-payment` and `module-cashpayment-validation`.
- It has **four settings of its own** in `qlo_configuration` (ids 440–443, all written
  2025-11-20 15:20:06):

| Key | Value |
|---|---|
| `CONF_CASHPAYMENT_FIXED` | `0.2` |
| `CONF_CASHPAYMENT_VAR` | `2` |
| `CONF_CASHPAYMENT_FIXED_FOREIGN` | `0.2` |
| `CONF_CASHPAYMENT_VAR_FOREIGN` | `2` |

**Assessment:** the version string `2.6.8` is *identical* to the bundled `cheque` module
(`qlo_module` row `(11, 'cheque', 1, '2.6.8')`; `modules/cheque/cheque.php:43` declares
`$this->version = '2.6.8'`, `$this->author = 'PrestaShop'`). The controller naming
(`module-<name>-payment`, `module-<name>-validation`) matches `cheque`'s pattern exactly. So
`cashpayment` is almost certainly a **copy of the 27-file `cheque` module, renamed and extended
with a fixed-plus-variable surcharge**, including separate rates for foreign transactions.
That is a hypothesis about provenance, **not** a verified fact — the source files are missing
(§2), so I cannot read them.

**Why this matters:** cash is ~49% of recorded payments and mobile money a further ~42%. Any
deployment that replaces the `modules/` directory without carrying `cashpayment` across will
**break the hotel's two main payment methods**. And because the file backup is missing, we
cannot simply copy it. Recovery options, in order of preference:
1. Retrieve `modules/cashpayment/` from the live server via cPanel File Manager (best — it is
   the real thing, including any local edits).
2. Reconstruct from `modules/cheque/` plus the four `CONF_CASHPAYMENT_*` settings, then have the
   client verify behaviour against the live site.
3. Replace with a purpose-built Module 5 payment component that supersedes it.

### 6.3 Configuration-level customisation
459 `qlo_configuration` rows and 56 enabled modules. A full comparison against 1.7.0 defaults is
possible but only meaningful alongside the file backup; **deferred**.

---

## 7. Functionality that must be retained

Ranked by what breaks the business if lost:

1. **1,934 reservations** and their 2,350 booking-detail lines, 2,104 order lines and 2,058
   payment records — with referential integrity across `qlo_orders` → `qlo_order_detail` →
   `qlo_order_payment` → `qlo_order_invoice` → `qlo_htl_booking_detail`.
2. **8 room types / 38 rooms**, their pricing, availability, disable-dates and refund rules.
3. **Cash and mobile-money payment recording** (`cashpayment` module + `payment_method` values).
4. **1,257 customer accounts** and 22,725 guest records.
5. **15 back-office employee accounts** and their profile/permission rows — though all 15
   passwords must be rotated for the reason in §8.
6. **42 hotel features** and their 7 hotel-level assignments — these are the amenities shown to
   guests; they must land in the new `htl_amenity` tables, not be dropped.
7. Hotel configuration: single property (`qlo_htl_branch_info`), refund rules, advance-payment
   rules, navigation links, testimonials and footer blocks.
8. **Bed-type data (9 types, 8 room assignments)** — flagged separately, because 1.8.0 has no
   place for it. A decision is required: keep the tables as orphans, re-model bed types as
   amenities/features, or accept the loss. **This needs your approval; I will not choose for you.**

---

## 8. Outstanding security items carried into this project

These are documented in full in `../audit/lush-view-hotel-audit.md` §2.2, §4.1 and §4.4 and are
repeated here only because they gate the work:

- The live 1.7.0 site is missing **all four** recent upstream security fix commits
  (`123c97c`, `153ec1c`, `1d06fd3`, `7ed467d`) — SQL injection via `bo_query` and three
  reflected-XSS classes.
- `qlohotelreview` is `active=1` and carries **CVE-2025-67325** (unauthenticated file upload →
  RCE). Zero reviews exist, so disabling it is free.
- A production database export was committed to this public repository and, although it has
  been purged from branch history, **is still publicly retrievable by SHA until GitHub
  garbage-collects it**. All 15 employee and 1,257 customer password hashes must be treated as
  compromised. Making the repository private was attempted programmatically and **failed with
  HTTP 403** (the automation token lacks the GitHub App *Administration* permission), so it
  needs doing by hand: **Settings → Danger Zone → Change repository visibility → Private**.

---

## 9. Assessment summary

| Question | Answer |
|---|---|
| Is the latest source compatible with the live database? | **No.** 36 tables and 23 columns must be created/altered first. Copying files over the live install would break it |
| Is there an automated 1.7.0→1.8.0 migration in the tree? | **No.** `install-dev/upgrade/sql/` stops at `1.6.1.0`; `modules/hotelreservationsystem/upgrade/` stops at `upgrade-1.6.0.php`; the core upgrader gates on `_PS_INSTALL_VERSION_` (1.6.1.23), not `_QLO_INSTALL_VERSION_` (1.8.0.0) |
| Will it run on GreenGeeks shared hosting? | **Very likely yes** — no Composer build, no Node build, no root, no daemons. Must verify `soap`, `allow_url_fopen`, `memory_limit ≥128M`, `max_execution_time ≥500`, `upload_max_filesize ≥16M` |
| Is the PHP version a blocker? | **No.** Host is PHP 8.4.27; 1.8.0 needs 8.2–8.5 |
| Are there customisations to preserve? | **One module** (`cashpayment`) and **four config values**. No custom tables. Its source is missing |
| Can we diff modified core files? | **No — blocked** until the file backup is re-uploaded |
| Is data at risk? | **Two groups:** hotel features (migratable to amenities) and bed types (**no target in 1.8.0**) |

**Blocking items before Phase 2 can be finalised:**
1. Re-upload the complete old-system file backup (§2).
2. Decide the bed-type question (§7 item 8).
3. Decide `cashpayment` recovery route (§6.2).
4. Make the repository private and file the GitHub Support purge request (§8).
