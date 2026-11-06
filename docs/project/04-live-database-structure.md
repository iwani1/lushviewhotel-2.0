# 04 — Live Database Structure Reference

**Subject:** the production QloApps 1.7.0 database behind `lushviewhotel.net`
**Purpose:** a complete, durable description of the live schema so the database dump can be
deleted without losing anything we need. This is Deliverable 2 of the project brief
("database assessment") and the structural half of Deliverable 3 ("old-vs-new comparison").
**Companion artefact:** [`live-db-structure-1.7.0.sql`](./live-db-structure-1.7.0.sql) — the
complete DDL for all 300 tables, machine-extracted and verified to contain **no row data**.

> **Privacy note.** This document and its companion SQL file describe *structure only*:
> table names, column names, data types, keys and row counts. They contain **no guest names,
> email addresses, telephone numbers, passwords, payment values or booking contents.**
> The only values quoted anywhere are payment-method *labels* (e.g. `Cash Payment`) and
> configuration keys, neither of which identifies a person.

---

## 1. Provenance — exactly what was analysed

| Property | Value |
| --- | --- |
| Export tool | phpMyAdmin **5.2.3** |
| Source host | `localhost:3306` (GreenGeeks cPanel, shared) |
| Server version | **MariaDB 11.4.13**-MariaDB |
| PHP version on host | **8.4.27** |
| Generation time | **Nov 06, 2026 at 10:04 AM** |
| Database name | `lushview_qloapp` |
| Table prefix | `qlo_` (not the PrestaShop default `ps_`) |
| Application version recorded in DB | `QLO_VERSION_DB` = **1.7.0.0** |
| Underlying PrestaShop schema version | **1.6.1.23** |
| Dump size | **16,140,519 bytes** (137,178 lines) |
| SHA-256 | `5fc7f3ffaf53fe2682f17de52c287047acc3f37e7c568ab5e0dff1f36e299edd` |

The SHA-256 is recorded so that any future copy can be proven to be the same file. It has
been re-verified after each recovery of the dump during this project.

### 1.1 How to reproduce this analysis

The dump is **not** stored in the repository (see §12). Given a copy at `/tmp/live_db.sql`:

```bash
# 1. Verify you have the same file we analysed
sha256sum /tmp/live_db.sql
# expect 5fc7f3ffaf53fe2682f17de52c287047acc3f37e7c568ab5e0dff1f36e299edd

# 2. Count tables (expect 300)
grep -c '^CREATE TABLE' /tmp/live_db.sql

# 3. Count DDL statements (expect 771 = 300 CREATE TABLE + 471 ALTER TABLE)
grep -c '^CREATE TABLE\|^ALTER TABLE' /tmp/live_db.sql

# 4. Confirm the dump declares no foreign keys at all (expect 0)
grep -c 'ADD CONSTRAINT' /tmp/live_db.sql
```

---

## 2. Headline structure

| Measure | Value |
| --- | --- |
| Tables | **300** |
| Columns (total, all tables) | **1,966** |
| Rows (total, all tables) | **128,113** |
| DDL statements | **771** (300 `CREATE TABLE` + 471 `ALTER TABLE`) |
| Storage engine | **InnoDB for all 300 tables** — no MyISAM anywhere |
| Character set | **utf8mb3** for 299 tables, **latin1** for 1 (see §5.4) |
| Collation | `utf8mb3_general_ci` ×299, `latin1_swedish_ci` ×1 |
| Primary keys | **295** tables have one; **5 do not** (see §5.5) |
| Unique keys | **27** |
| Non-unique indexes | **267** |
| Auto-increment counters | **101** |
| **Foreign key constraints** | **0 — none whatsoever** |
| Empty tables (0 rows) | **141** |

### 2.1 Column type census

| Type | Count | | Type | Count |
| --- | ---: | --- | --- | ---: |
| `int` | 886 | | `date` | 17 |
| `varchar` | 328 | | `enum` | 13 |
| `tinyint` | 274 | | `char` | 7 |
| `decimal` | 179 | | `bigint` | 4 |
| `datetime` | 165 | | `timestamp` | 4 |
| `text` | 59 | | `mediumint` | 3 |
| `smallint` | 19 | | `longtext` | 2 |

**No floating-point money anywhere** — every monetary column is `decimal`, never `float` or
`double`. That is correct and rules out a whole class of rounding bug.

But precision is **not** uniform. There are **18 distinct `decimal(p,s)` variants** across the
179 decimal columns:

| Precision | Columns | Where it matters |
| --- | ---: | --- |
| `decimal(20,6)` | 130 | the dominant form — order lines, prices, tax amounts |
| `decimal(17,2)` | 7 | `qlo_attribute_impact.price`, cart-rule amounts |
| `decimal(16,6)` | 6 | booking-demand and legacy tax amounts |
| **`decimal(10,2)`** | **6** | **`qlo_order_payment.amount`, `qlo_order_payment_detail.amount`, `qlo_order_slip.amount`** |
| `decimal(13,6)` | 5 | currency conversion rates |
| others (13 variants) | 25 | percentages, rates, latitude/longitude |

**This is a live reconciliation trap, and it affects Module 5 and Module 8 directly.**
Money *owed* is recorded at six decimal places (`qlo_order_detail.total_price_tax_incl` is
`decimal(20,6)`) while money *paid* is recorded at two (`qlo_order_payment.amount` is
`decimal(10,2)`). A report that sums order lines and compares the result with summed payments
can therefore disagree by fractions of a pesewa on individual bookings even when the books are
correct. Any daily reconciliation must round both sides to 2 decimal places *before* comparing,
and the rounding rule must be written down once and applied everywhere.

Also note the coordinate columns: `qlo_htl_branch_info.latitude` is `decimal(10,8)` and
`longitude` `decimal(11,8)` — adequate precision, but both currently hold `0.00000000`, so the
hotel has no usable map position.

### 2.2 The ten largest tables

| Table | Rows | Columns | What it is |
| --- | ---: | ---: | --- |
| `qlo_log` | 25,891 | 9 | back-office activity log |
| `qlo_guest` | 22,725 | 16 | visitor sessions (incl. anonymous) |
| `qlo_connections` | 20,992 | 8 | page-view/connection analytics |
| `qlo_pagenotfound` | 11,027 | 6 | 404 tracking |
| `qlo_mail` | 11,021 | 6 | outgoing email queue/log |
| `qlo_htl_cart_booking_data` | 2,570 | 22 | dates + occupancy per cart line |
| `qlo_htl_booking_detail` | 2,350 | 38 | **one row per booked room-night** |
| `qlo_order_history` | 2,339 | 5 | order status transitions |
| `qlo_cart_product` | 2,250 | 7 | cart lines |
| `qlo_cart` | 2,234 | 20 | carts |

Then the business records proper: `qlo_order_detail` **2,104**, `qlo_order_payment` **2,058**,
`qlo_order_payment_detail` **2,058**, `qlo_order_invoice_payment` **2,054**, `qlo_message`
**1,948**, `qlo_orders` **1,934**, `qlo_order_invoice` **1,916**, `qlo_customer` **1,257**,
`qlo_access` **1,077**, `qlo_configuration` **459**, `qlo_htl_room_information` **38**,
`qlo_htl_room_type` **8**, `qlo_htl_branch_info` **1**, `qlo_employee` **15**.

Note the ratio: **five of the ten largest tables are logs and analytics**, not business data.
They account for ~91,000 of the 128,113 rows. This matters for migration planning — the
records that are genuinely irreplaceable are a comparatively small, well-defined set.

---

## 3. A trap worth recording: phpMyAdmin splits a table across three places

This cost a wrong answer before it was caught, so it is written down.

A phpMyAdmin export does **not** put a table's keys inside its `CREATE TABLE` statement. It
writes the definition in three separate places:

```sql
-- (1) near the top, interleaved with the data: columns ONLY
CREATE TABLE `qlo_address` (
  `id_address` int(10) UNSIGNED NOT NULL,
  ...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- (2) from line ~134,057, in an "Indexes for dumped tables" section:
ALTER TABLE `qlo_address`
  ADD PRIMARY KEY (`id_address`),
  ADD KEY `address_customer` (`id_customer`);

-- (3) at the very end, in an "AUTO_INCREMENT for dumped tables" section:
ALTER TABLE `qlo_address`
  MODIFY `id_address` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=412;
```

An extractor that reads only section (1) — the obvious thing to do — reports
**0 primary keys, 0 indexes and 0 auto-increment counters for the entire database.**
That is what the first pass of this analysis produced, and it was wrong.

Everything in this document was produced by a script that merges all three sections.
The companion SQL file preserves sections (2) and (3) **verbatim**, including index names
and prefix lengths such as ``ADD KEY `recipient` (`recipient`(10))``, because those are part
of the schema and re-serialising them silently loses information.

---

## 4. The single most important fact: there are no foreign keys

`ADD CONSTRAINT` appears **0 times** in 771 DDL statements. Not one of the 300 tables
declares a foreign key. Every relationship in this database is enforced in PHP — by
`ObjectModel`, `Db::insert()` and the module classes — never by the database.

This is normal PrestaShop/QloApps design, not a defect introduced by the client. But it has
four consequences we must design around:

1. **Orphan rows are possible and silent.** Nothing stops a delete from leaving children
   behind. §6 shows this has already happened.
2. **Deletes never cascade.** Application code must remove related rows explicitly, and
   historically has not always done so.
3. **We cannot rely on the database to validate a migration.** Referential integrity has to
   be checked by a script before and after every data move — the database will happily accept
   an inconsistent import.
4. **Join order matters for performance.** With 267 hand-declared indexes and no FK-derived
   ones, an unindexed join column is invisible until a query is slow.

The upside: because there are no constraints, tables can be copied, renamed and re-populated
in any order during migration without fighting the engine. That is a real convenience.

### 4.1 How tables actually relate

Full detail in **Appendix B**; every join listed there was asserted against the real column
list by the generating script. Four conventions are worth learning early because they are
*not* what you would guess:

| Convention | Reality |
| --- | --- |
| **A room type is a product.** | `qlo_htl_room_type.id_product` → `qlo_product.id_product`. There is no separate "room type" entity at the catalogue level. |
| **Rooms join to types through the product, not through the room type.** | `qlo_htl_room_information` has **no `id_room_type` column at all**. It has `id_product`, `id_hotel`, `room_num`, `id_status`, `floor`, `comment`. To list the rooms of a room type you must go via `id_product`. Assuming `id_room_type` exists is the single easiest mistake to make here. |
| **Payments join to orders by a string.** | `qlo_order_payment.order_reference` → `qlo_orders.reference` (`varchar(9)`, e.g. `ADDPELBUV`). Not an id. String joins are slower and are the reason §6 finds payment orphans. |
| **One column breaks the `id_` naming rule.** | `qlo_htl_branch_features` links to `qlo_htl_features` via **`feature_id`**, not `id_feature`. |

Multilingual data follows the PrestaShop convention throughout: a base table plus a `_lang`
table keyed on `(id_<entity>, id_lang)`. **48 `_lang` tables** exist, and **29 `_shop` tables**
carry multistore scoping even though the live installation runs a single shop. Hotel identity
is split this way too — `qlo_htl_branch_info` holds `check_in`, `check_out`, `email`, `rating`,
`latitude`, `longitude` and `fax`, but the **hotel name, short description, description and
policies are in `qlo_htl_branch_info_lang`** (`hotel_name varchar(255)`, `short_description`,
`description`, `policies`). Anyone rebranding the hotel must edit the `_lang` row, not the base
row — editing only `qlo_htl_branch_info` would change nothing visible.

---

## 5. Structural findings

### 5.1 Booking and room lifecycles already exist in the schema

We do **not** need to invent status columns for Module 3 or Module 6 — the vocabulary is
already there as PHP constants:

| Concept | Constants | Location |
| --- | --- | --- |
| Booking lifecycle | `STATUS_ASSIGNED = 1`, `STATUS_CHECKED_IN = 2`, `STATUS_CHECKED_OUT = 3`, `STATUS_NO_SHOW = 4`, `STATUS_CANCELLED = 5` | `modules/hotelreservationsystem/classes/HotelBookingDetail.php:153-157` |
| Room status | `STATUS_ACTIVE = 1`, `STATUS_INACTIVE = 2`, `STATUS_SEARCH_LOS_UNSATISFIED = 4`, `STATUS_SEARCH_LOS_...` , `STATUS_SEARCH_OCCUPANCY_UNSATISFIED = 5` | `modules/hotelreservationsystem/classes/HotelRoomInformation.php:36-40` |

`qlo_htl_booking_detail.id_status` carries the booking lifecycle value and is consumed at
`HotelBookingDetail.php:257-258, 772`. All 38 live rooms have `id_status = 1`
(`STATUS_ACTIVE`).

**Design consequence for Module 6 (Housekeeping):** room status is a *hardcoded PHP constant*,
not a database lookup. There is no extensible status vocabulary to add "dirty", "being
cleaned" or "under inspection" to. The housekeeping status must live in a **new table owned by
our module**, leaving `htl_room_information.id_status` alone so the existing booking engine
keeps working.

### 5.2 `qlo_htl_room_status` is vestigial

It is created at `modules/hotelreservationsystem/classes/HotelReservationSystemDb.php:276` and
dropped at `:605`, but **nothing ever inserts into it and nothing reads from it**. It has
2 columns (`id`, `status`) and **0 rows** on live, while all 38 rooms point at `id_status = 1`.

This looks like a referential-integrity failure and is not one: `id_status` is a constant, not
a foreign key. Recorded here so nobody "fixes" it by seeding the table.

### 5.3 Three hotel capabilities already exist and should be extended, not rebuilt

| Table | Rows | What it gives us | Brief mapping |
| --- | ---: | --- | --- |
| `qlo_htl_access` | 11 | per-hotel staff permissions, PK `(id_profile, id_hotel)` | Module 7 — RBAC scoped to a property already exists |
| `qlo_htl_advance_payment` | 4 | deposit rules per room type (`payment_type`, `value`, `tax_include`, `calculate_from`) | Module 5 — partial payments / deposits |
| `qlo_htl_room_disable_dates` | 0 | date-banded room blocking with a `reason` column, keyed on `id_room_type` + `id_room` | Module 2 + Module 6 — maintenance / out-of-order |
| `qlo_service_product_order_detail` | 4 | extras sold against a booking, with full tax breakdown | Module 3 — ancillary revenue already modelled |

`qlo_htl_room_disable_dates` having 0 rows is useful: the mechanism exists and is unused, so
maintenance blocking can be adopted without displacing any data.

One caution on `qlo_htl_booking_document` (0 rows): its `file_name` column is
**`varchar(8)`** — eight characters for a filename. That is upstream's own definition
(`HotelReservationSystemDb.php`, `htl_booking_document` block), not local damage. Any feature
that stores guest documents must not rely on this table as-is; the column needs widening first.

### 5.4 One table is latin1, and it is an upstream bug

`qlo_htl_custom_navigation_link` (12 rows) is `CHARSET=latin1 COLLATE=latin1_swedish_ci`
while the other 299 tables are utf8mb3. The cause is in the module that creates it:

- `modules/blocknavigationmenu/classes/WkBlockNavigationMenuDb.php:41` — ends
  `ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;`
- `modules/blocknavigationmenu/classes/WkBlockNavigationMenuDb.php:47` — the sibling
  `_lang` table in the *same array* ends `DEFAULT CHARSET=utf8 ;`

Two tables created by the same statement array, two different charsets. Practical impact today
is low: the affected column is `link text`, and all 12 live values are ASCII URLs
(`index`, `https://lushviewhotel.net/our-properties`, `/#hotelInteriorBlock`, …). The
human-visible label lives in the utf8 `_lang` table. But any future non-ASCII URL would be
stored single-byte and render mangled.

**Recommendation:** convert this table to `utf8mb4` as part of the schema migration. Low risk
(12 rows, ASCII today), and it removes an inconsistency that will otherwise confuse whoever
maintains this next.

### 5.5 Five tables have no primary key — all benign

| Table | Cols | Rows | Indexes | Verdict |
| --- | ---: | ---: | ---: | --- |
| `qlo_accessory` | 2 | 0 | 1 | **Upstream is identical** — PrestaShop defines this link table with an index and no PK. Not drift. |
| `qlo_customer_message_sync_imap` | 1 | 0 | 1 | Same — upstream defines no PK. |
| `qlo_order_detail_tax` | 4 | 0 | 2 | Legacy PrestaShop tax table. **Absent from 1.8.0's `db_structure.sql` entirely.** |
| `qlo_order_invoice_tax` | 4 | 0 | 1 | Same. |
| `qlo_order_slip_detail_tax` | 4 | 0 | 2 | Same. |

All five are empty. Two match upstream exactly; three are legacy tables that QloApps 1.8.0 no
longer creates at all. **No action required** — though the three tax tables are candidates for
archival cleanup once 1.8.0 is running, since `order_detail_tax` is already dead code
(`Order::updateOrderDetailTax()` at `classes/Order.php:2792` has zero live callers).

A missing primary key on a populated table would be a genuine problem for replication and for
any `ALTER` on shared hosting. None of these are populated.

### 5.6 Nothing in the database can store an emoji

All 299 utf8 tables use **utf8mb3**, MariaDB's 3-byte UTF-8. Four-byte characters — emoji,
some CJK, and a handful of rare symbols — cannot be stored. The blast radius:

- **328 `varchar` + 59 `text` + 7 `char` + 2 `longtext` + 13 `enum` = 409 character columns**
- **106 of them are user-entered free text** (guest names, addresses, booking comments,
  reviews, hotel descriptions), spread across **85 tables**

Affected columns include `qlo_address.lastname/firstname/address1/city`,
`qlo_htl_booking_detail.comment`, `qlo_customer` name fields, and every `*_lang.name`.

**This is not fixed by upgrading.** QloApps 1.8.0's own `install-dev/data/db_structure.sql`
declares `DEFAULT CHARSET=utf8` for all **249** tables and contains **zero** occurrences of
`utf8mb4`. So a modern guest pasting "👍" into a booking comment hits a limitation that the
upgrade will not remove.

**Recommendation:** convert to `utf8mb4` as a deliberate, separately-tested migration step.
This is a real change with real risk (index length limits under `utf8mb4` mean
`varchar(255)` indexes may exceed the 767-byte InnoDB limit and need `ROW_FORMAT=DYNAMIC` or
shorter prefixes), so it must not be bundled into the version upgrade. It should be its own
change, on staging, with a rollback.

---

## 6. Referential integrity probe — what is actually broken

Because the database enforces nothing (§4), integrity was tested by reading the real row
values and checking each implied relationship. 16 checks were run; **12 are clean and 4 have
orphans.**

| Child column | Parent column | Rows checked | Orphans | Verdict |
| --- | --- | ---: | ---: | --- |
| `htl_room_information.id_product` | `product.id_product` | 38 | 0 | clean |
| `htl_room_information.id_hotel` | `htl_branch_info.id` | 38 | 0 | clean |
| `htl_room_information.id_status` | `htl_room_status.id` | 38 | 38 | **benign** — §5.2 |
| `htl_room_type.id_product` | `product.id_product` | 8 | 0 | clean |
| `htl_room_type.id_hotel` | `htl_branch_info.id` | 8 | 0 | clean |
| `htl_branch_features.feature_id` | `htl_features.id` | 7 | 0 | clean |
| `htl_room_type_bed_type.id_bed_type` | `htl_bed_type.id_bed_type` | 8 | 0 | clean |
| `htl_room_type_bed_type.id_product` | `product.id_product` | 8 | 0 | clean |
| `order_detail.id_order` | `orders.id_order` | 2,104 | **25** | **real orphans** |
| `orders.id_customer` | `customer.id_customer` | 1,934 | 0 | clean |
| `htl_booking_detail.id_order_detail` | `order_detail.id_order_detail` | 2,350 | 0 | clean |
| `order_payment.order_reference` | `orders.reference` | 2,058 | **16** | **real orphans** |
| `order_invoice.id_order` | `orders.id_order` | 1,916 | **14** | **real orphans** |
| `service_product_order_detail.id_htl_booking_detail` | `htl_booking_detail.id` | 4 | 0 | clean |
| `htl_advance_payment.id_product` | `product.id_product` | 4 | 0 | clean |
| `htl_access.id_hotel` | `htl_branch_info.id` | 11 | 0 | clean |

### 6.1 The real problem: orders were deleted, their children were not

**20 order ids no longer exist** but are still referenced:

```
1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 24, 25, 31, 32, 33, 35, 47, 51, 56
```

Note the pattern — **the first eleven orders ever created are gone.** These are the earliest
transactions in the hotel's history, plus a scatter of other low ids.

Dangling children left behind:

| Table | Rows orphaned | Distinct parents missing |
| --- | ---: | ---: |
| `qlo_order_detail` | 25 | 20 order ids |
| `qlo_htl_booking_detail` | 24 | (via `id_order`) |
| `qlo_order_payment` | 16 | 14 reference strings |
| `qlo_order_invoice` | 14 | 14 order ids |

The 14 orphaned payment references are listed so the migration can locate exactly which rows to
carry across:

`ADDPELBUV, BFCYZRTXB, BPPEPGKNB, CZHLHHZNE, HTKCXYQXO, LCREACDJL, OGRBHESKW, OMCGBYFLO,
QBKFQRDIW, TMGWCPTNG, TWIYIXQJU, UQHQRDMQJ, WJXLTRFWS, YYADQLFTP`

These are PrestaShop's randomly-generated `varchar(9)` order references, not personal data, and
they identify orders that **no longer exist** — so they cannot be used to retrieve anything from
the live site. They are recorded here purely as the primary keys of the dangling rows.

`qlo_htl_booking_detail.id_order_detail` is clean (0 orphans), which tells us the order *lines*
survived even though their parent *orders* were removed — the deletion was partial, not
cascading, exactly as §4 predicts.

**One reassuring result:** `orders.id_customer` has **0 orphans**. The 17 customer ids that are
missing (§7) left no orphaned orders behind, so guest accounts were removed cleanly.

### 6.2 What to do about it

The brief (§6) requires preserving reservations, guests, payments and historical records.
These orphans *are* historical records — evidence of transactions that happened.

- **Do not delete them.** Removing them to make reports "clean" would destroy the last trace
  of 20 real bookings and 16 real payments.
- **Do not let them silently vanish from reporting.** Any inner join
  `orders → order_detail` drops those 25 lines and understates early revenue.
- **Use `LEFT JOIN` from the child side** in the new reporting layer, and surface a
  "records with missing parent order" figure so the discrepancy is visible rather than hidden.
- **Carry them across the migration unchanged**, and record the 20 missing ids in the
  migration log so a future auditor knows the gap predates our work and was not caused by it.

---

## 7. Evidence of historical deletions, from the auto-increment counters

An `AUTO_INCREMENT` counter only ever moves forward. Comparing it with the live row count
shows how many ids were consumed and then removed. **41 tables show gaps, totalling 6,023 ids
that were issued but no longer exist.**

| Table | Rows now | Next id | Ids consumed but absent | Reading |
| --- | ---: | ---: | ---: | --- |
| `qlo_search_word` | 306 | 5,130 | 4,823 | search index rebuilt repeatedly — **normal** |
| `qlo_htl_cart_booking_data` | 2,570 | 2,974 | 403 | abandoned carts pruned — **normal** |
| `qlo_htl_branch_features` | 7 | 176 | 168 | hotel features re-assigned many times |
| `qlo_specific_price_priority` | 20 | 126 | 105 | pricing rules churned |
| `qlo_htl_room_information` | 38 | 110 | **71** | **rooms were created and deleted** |
| `qlo_htl_features` | 42 | 111 | 68 | feature list edited heavily |
| `qlo_cart` | 2,234 | 2,299 | 64 | normal cart expiry |
| `qlo_image` | 24 | 70 | 45 | images replaced |
| `qlo_htl_booking_detail` | 2,350 | 2,387 | **36** | **booking records removed** |
| `qlo_order_detail` | 2,104 | 2,129 | 24 | matches §6 |
| `qlo_htl_room_type_service_product` | 12 | 37 | 24 | extras reconfigured |
| `qlo_orders` | 1,934 | 1,955 | **20** | **20 orders deleted** — matches §6 exactly |
| `qlo_customer` | 1,257 | 1,275 | **17** | **17 guest accounts deleted** |
| `qlo_htl_room_type_bed_type` | 8 | 23 | 14 | bed-type assignments churned |
| `qlo_profile` | 6 | 19 | 12 | staff roles created and removed |
| `qlo_product` | 13 | 25 | 11 | products removed |

Two independent methods agree on the order count — 20 missing order ids (§6.1) and a gap of 20
between `qlo_orders`' 1,934 rows and its next id of 1,955. That cross-check is why these
numbers can be trusted.

**Why this matters for the project:** the brief's requirement to "preserve historical records"
has *already* been partly compromised by ordinary back-office use, before we touched anything.
71 room ids and 17 guest accounts are gone. This is not something our migration caused, and it
cannot be undone — but it should be stated plainly in the migration plan so expectations are
correct, and so the new platform's audit-log module (Module 7) prevents it recurring.

The pure-append tables show no gaps at all (`qlo_log` 25,891 → next 25,892; `qlo_guest`,
`qlo_connections`, `qlo_mail`, `qlo_pagenotfound` likewise), which confirms the counters are
being read correctly.

---

## 8. The payment-method problem, in structural terms

`qlo_order_payment.payment_method` is a **free-text `varchar`** with no lookup table, no
`enum` and no foreign key. 2,058 rows have accumulated **23 distinct spellings** for what are
really about five tenders:

| Canonical tender | Spellings found | Rows |
| --- | --- | ---: |
| Cash | `Cash Payment` (993), `cash` (11) | **1,004** |
| Mobile Money | `Momo` (629), `momo` (115), `MOMO` (104), `Momo payment` (12) | **860** |
| Card / terminal | `P.O.S` (49), `POS` (24) | **73** |
| Internal | `Back office order` (80), `Free order` (8), `Approved by Management` (6), `awaiting payment` (8) | **102** |
| Other | `Bank wire`, `Cheque`, partial-payment notes, and one row whose value is an 11-digit numeric string typed into the tender field (redacted here — it may be a transaction reference or a mis-keyed phone number) | remainder |

Three consequences:

1. **Reports cannot be trusted as-is.** Any "revenue by payment method" query today returns 23
   rows and splits mobile money across four case variants. Module 8 must normalise before
   aggregating.
2. **Mobile money is already 42% of tendered payments** (860 of 2,058) — recorded by hand, with
   no gateway. This is the strongest available evidence for the Module 5 mobile-money
   requirement, and it also explains why `transaction_id` is populated on 937 rows: staff are
   manually copying MoMo reference numbers.
3. **The system has never stored cardholder data.** Exactly four card columns exist in the
   entire 300-table database, all on `qlo_order_payment` — `card_number varchar(254)`,
   `card_brand varchar(254)`, `card_expiration char(7)`, `card_holder varchar(254)` — and
   **all four are empty in all 2,058 payment rows.** A scan of every column in every table
   found no other card-, PAN- or CVV-like field anywhere. This is therefore **not** a PCI-DSS
   cardholder-data environment, which is a significant and favourable finding for scope and
   compliance. `transaction_id varchar(254)` is populated on 937 rows and is where staff
   manually copy mobile-money reference numbers.

**Recommendation:** introduce a proper tender lookup (id + canonical name) owned by our
payments module, back-fill it from a documented mapping of these 23 strings, keep the original
free-text value in an `original_label` column so nothing is lost, and make the mapping table
part of the migration so it is reviewable and reversible.

---

## 9. What this means for the migration

Ordered by risk. Full planning lives in
[`03-migration-risks-and-plan.md`](./03-migration-risks-and-plan.md); these are the items the
*structure* dictates.

1. **Carry the orphaned records, do not clean them.** §6.2. Deleting them would breach the
   brief's "preserve historical records" requirement.
2. **The features → amenities migration has no upstream script.** `qlo_htl_features` (42 rows),
   `qlo_htl_features_lang` (42) and `qlo_htl_branch_features` (7) hold live data, but 1.8.0
   replaces them with `htl_amenity` / `htl_branch_amenity` / `htl_room_type_amenity` and
   **ships no migration** — `modules/hotelreservationsystem/upgrade/` stops at
   `upgrade-1.6.0.php`. We must write it. Note the irregular `feature_id` column (§4.1).
3. **Bed types have no destination at all.** `qlo_htl_bed_type` (9 rows), `htl_bed_type_lang`
   (9) and `qlo_htl_room_type_bed_type` (8) are not created by 1.8.0, and the only references
   to them in the 1.8.0 source are the three `DROP TABLE` lines at
   `HotelReservationSystemDb.php:623-625`. **Keeping these tables plus a small custom module is
   the recommended path; this needs a client decision.**
4. **23 columns are missing across 17 shared tables**, and **36 tables present in 1.8.0 do not
   exist on live** — itemised in
   [`01-phase1-technical-assessment.md`](./01-phase1-technical-assessment.md) §5.
5. **Normalise payment tenders** with a reversible, documented mapping (§8).
6. **Convert `qlo_htl_custom_navigation_link` off latin1** (§5.4) — cheap and safe.
7. **Plan `utf8mb4` as a separate, later change** (§5.6) — valuable but genuinely risky; never
   bundle it with the version upgrade.
8. **Build the housekeeping status vocabulary new** (§5.1) — do not try to extend
   `htl_room_information.id_status`, which is a hardcoded constant consumed by the booking
   engine.
9. **Write an integrity-check script and run it before and after every migration** (§4). The
   database will not catch a bad import; only our script will. The 16-check probe used for §6
   is the starting point and should become a permanent pre-deployment gate, as the brief's §9
   requires.
10. **Widen `htl_booking_document.file_name`** from `varchar(8)` before any document-storage
    feature is built on it (§5.3).

---

## 10. What is *not* in this database

Confirmed absent, so nobody searches for it later:

- **No cardholder data.** The only four card columns in the database are on
  `qlo_order_payment` and all are empty across all 2,058 payments (§8).
- **No stored API credentials.** All three webservice tables — `qlo_webservice_account`,
  `qlo_webservice_account_shop` and `qlo_webservice_permission` — have **0 rows**, so no API
  key has ever been issued from this installation.
- **No cookie salt or encryption keys.** `_COOKIE_KEY_` appears **0 times** in the dump —
  those live in `config/settings.inc.php`, which is absent from the repository and was never
  exported. Password hashes are `md5(_COOKIE_KEY_ . password)`, so they cannot be verified
  without that file.
- **No guest reviews.** `qlo_qhr_hotel_review` has **0 rows**, which is why disabling the
  `qlohotelreview` module (the vector for CVE-2025-67325) costs the business nothing.
- **No mobile-money gateway tables.** MoMo is recorded by hand as free text (§8).
- **No second hotel.** `qlo_htl_branch_info` has exactly **1 row** — the schema supports
  multiple properties (`qlo_htl_access` is keyed per hotel) but only one is configured.

---

## 11. Appendices

The appendices are generated mechanically from the dump, not typed. Each was validated by the
script that produced it: Appendix A asserts that all 300 tables are classified exactly once;
Appendix B asserts that every claimed join column really exists on the child table.

- **Appendix A** — all 300 tables grouped into 20 functional domains, each with row count,
  column count, primary key, index count and next auto-increment value.
- **Appendix B** — the verified relationship map (22 joins) plus fan-in analysis.
- **Appendix C** — complete column-by-column listings for the 16 tables the nine custom
  modules will touch.

And alongside this document:

- **[`live-db-structure-1.7.0.sql`](./live-db-structure-1.7.0.sql)** — all 771 DDL statements,
  byte-normalised against the original dump (verified 771 of 771 match, 0 missing, 0 extra),
  with **0 `INSERT` statements** and no row data of any kind.

---

## 12. Status of the dump file itself

The 16 MB dump **is not and must not be committed to this repository.** Root `.gitignore` line
260 ignores `*.sql`; the companion schema file is exempted by an explicit negation because it
is provably data-free.

The dump contains 1,257 guest records, 15 employee password hashes and 2,058 payment rows.
It was accidentally committed earlier in this project's history, that history was rewritten to
remove it, and the exposure is documented in
[`../audit/full-application-audit.md`](../audit/full-application-audit.md) §4.4 along with the
outstanding owner actions. **Everything of structural value has now been extracted into this
document and its companion SQL file**, so the dump itself can be deleted from any working
location without losing information the project needs.

What cannot be recovered from the structure alone is the *data*: the 128,113 rows. Those remain
authoritatively on the live server and in the client's own backups, which is where they belong.

## Appendix A - all 300 tables, grouped by functional domain

Produced mechanically from the dump; each table appears exactly once. "Idx" counts
unique plus non-unique keys (the primary key is listed separately).

| Domain | Tables | Rows | Comment |
| --- | ---: | ---: | --- |
| Hotel LEGACY: features & bed types (1.8.0 does not create these) | 10 | 119 | **holds live data - needs migration** |
| Hotel LEGACY: orphaned PrestaShop tax tables | 3 | 0 | all empty - safe to leave orphaned |
| Hotel reservations & bookings | 5 | 4,920 | core booking records - must be preserved |
| Hotel service products (extras sold with a room) | 4 | 34 |  |
| Hotel property, rooms & availability | 20 | 65 | core - must be preserved |
| Hotel front-end content blocks | 11 | 68 |  |
| Guest reviews (qlohotelreview module) | 7 | 4 | 0 reviews - module can be disabled |
| Channel manager & third-party connectors | 1 | 0 |  |
| Orders, invoicing & payments | 20 | 14,509 | core financial records - must be preserved |
| Discounts / cart rules | 10 | 0 |  |
| Cart & checkout | 4 | 4,484 |  |
| Customers, guests & addresses | 18 | 47,437 |  |
| Catalogue: products, images, stock (rooms are products) | 72 | 335 |  |
| Tax, currency & geography | 15 | 1,322 |  |
| Shipping & carriers | 7 | 0 |  |
| CMS pages, SEO & search | 16 | 12,161 |  |
| Employees, permissions & audit | 22 | 28,074 |  |
| Configuration, localisation & messaging | 34 | 14,479 |  |
| PrestaShop commerce remnants unused by a hotel | 17 | 102 | vestigial - cleanup candidates |
| Caching & runtime infrastructure | 4 | 0 | rebuilt automatically - never migrate |
| **Total** | **300** | **128,113** |  |

### A.1 Hotel LEGACY: features & bed types (1.8.0 does not create these)

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_htl_bed_type` | 9 | 3 | `id_bed_type` | 0 | 10 |
| `qlo_htl_bed_type_lang` | 9 | 3 | `id_bed_type, id_lang` | 0 | - |
| `qlo_htl_branch_features` | 7 | 5 | `id` | 0 | 176 |
| `qlo_htl_features` | 42 | 6 | `id` | 0 | 111 |
| `qlo_htl_features_lang` | 42 | 3 | `id, id_lang` | 0 | - |
| `qlo_htl_room_type_bed_type` | 8 | 3 | `id_room_type_bed_type` | 0 | 23 |
| `qlo_htl_room_type_global_demand` | 1 | 6 | `id_global_demand` | 0 | 2 |
| `qlo_htl_room_type_global_demand_advance_option` | 0 | 5 | `id_option` | 0 | - |
| `qlo_htl_room_type_global_demand_advance_option_lang` | 0 | 3 | `id_option, id_lang` | 0 | - |
| `qlo_htl_room_type_global_demand_lang` | 1 | 3 | `id_global_demand, id_lang` | 0 | - |

### A.2 Hotel LEGACY: orphaned PrestaShop tax tables

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_order_detail_tax` | 0 | 4 | `**NONE**` | 2 | - |
| `qlo_order_invoice_tax` | 0 | 4 | `**NONE**` | 1 | - |
| `qlo_order_slip_detail_tax` | 0 | 4 | `**NONE**` | 2 | - |

### A.3 Hotel reservations & bookings

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_htl_booking_demands` | 0 | 12 | `id_booking_demand` | 0 | - |
| `qlo_htl_booking_demands_tax` | 0 | 4 | `id_booking_demand, id_tax` | 0 | - |
| `qlo_htl_booking_detail` | 2,350 | 38 | `id` | 0 | 2,387 |
| `qlo_htl_booking_document` | 0 | 6 | `id_htl_booking_document` | 0 | - |
| `qlo_htl_cart_booking_data` | 2,570 | 22 | `id` | 0 | 2,974 |

### A.4 Hotel service products (extras sold with a room)

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_htl_room_type_service_product` | 12 | 5 | `id_room_type_service_product` | 1 | 37 |
| `qlo_htl_room_type_service_product_price` | 14 | 6 | `id_room_type_service_product_price` | 1 | 36 |
| `qlo_service_product_cart_detail` | 4 | 7 | `id_service_product_cart_detail` | 1 | 9 |
| `qlo_service_product_order_detail` | 4 | 23 | `id_service_product_order_detail` | 0 | 5 |

### A.5 Hotel property, rooms & availability

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_htl_access` | 11 | 3 | `id_profile, id_hotel` | 0 | - |
| `qlo_htl_advance_payment` | 4 | 10 | `id` | 0 | 8 |
| `qlo_htl_branch_info` | 1 | 15 | `id` | 0 | 2 |
| `qlo_htl_branch_info_lang` | 1 | 6 | `id, id_lang` | 0 | - |
| `qlo_htl_branch_refund_rules` | 0 | 6 | `id_hotel_refund_rule` | 0 | - |
| `qlo_htl_image` | 1 | 3 | `id` | 0 | 3 |
| `qlo_htl_order_refund_rules` | 0 | 7 | `id_refund_rule` | 0 | - |
| `qlo_htl_order_refund_rules_lang` | 0 | 4 | `id_refund_rule, id_lang` | 0 | - |
| `qlo_htl_order_restrict_date` | 1 | 8 | `id` | 0 | 2 |
| `qlo_htl_room_disable_dates` | 0 | 8 | `id` | 0 | - |
| `qlo_htl_room_information` | 38 | 9 | `id` | 0 | 110 |
| `qlo_htl_room_status` | 0 | 2 | `id` | 0 | - |
| `qlo_htl_room_type` | 8 | 12 | `id` | 0 | 16 |
| `qlo_htl_room_type_demand` | 0 | 5 | `id_room_type_demand` | 0 | - |
| `qlo_htl_room_type_demand_price` | 0 | 7 | `id_room_type_demand_price` | 0 | - |
| `qlo_htl_room_type_feature_pricing` | 0 | 11 | `id_feature_price` | 0 | 144 |
| `qlo_htl_room_type_feature_pricing_group` | 0 | 2 | `id_feature_price, id_group` | 0 | - |
| `qlo_htl_room_type_feature_pricing_lang` | 0 | 3 | `id_feature_price, id_lang` | 0 | - |
| `qlo_htl_room_type_feature_pricing_restriction` | 0 | 9 | `id_feature_price_restriction` | 0 | 144 |
| `qlo_htl_room_type_restriction_date_range` | 0 | 8 | `id_rt_restriction` | 0 | - |

### A.6 Hotel front-end content blocks

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_htl_custom_navigation_link` | 12 | 10 | `id_navigation_link` | 0 | 13 |
| `qlo_htl_custom_navigation_link_lang` | 12 | 3 | `id_navigation_link, id_lang` | 0 | - |
| `qlo_htl_features_block_data` | 3 | 5 | `id_features_block` | 0 | 9 |
| `qlo_htl_features_block_data_lang` | 3 | 4 | `id_features_block, id_lang` | 0 | - |
| `qlo_htl_footer_payment_block_info` | 5 | 6 | `id_payment_block` | 0 | 6 |
| `qlo_htl_interior_image` | 12 | 7 | `id_interior_image` | 0 | 13 |
| `qlo_htl_room_block_data` | 5 | 6 | `id_room_block` | 0 | 7 |
| `qlo_htl_settings_link` | 5 | 9 | `id_settings_link` | 0 | 6 |
| `qlo_htl_settings_link_lang` | 5 | 4 | `id_settings_link, id_lang` | 0 | - |
| `qlo_htl_testimonials_block_data` | 3 | 7 | `id_testimonial_block` | 0 | 7 |
| `qlo_htl_testimonials_block_data_lang` | 3 | 3 | `id_testimonial_block, id_lang` | 0 | - |

### A.7 Guest reviews (qlohotelreview module)

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_qhr_category` | 2 | 4 | `id_category` | 0 | 3 |
| `qlo_qhr_category_lang` | 2 | 3 | `id_category, id_lang` | 0 | - |
| `qlo_qhr_hotel_review` | 0 | 10 | `id_hotel_review` | 0 | - |
| `qlo_qhr_review_category_rating` | 0 | 3 | `id_hotel_review, id_category` | 0 | - |
| `qlo_qhr_review_reply` | 0 | 5 | `id_review_reply` | 0 | - |
| `qlo_qhr_review_report` | 0 | 2 | `id_hotel_review, id_customer` | 0 | - |
| `qlo_qhr_review_usefulness` | 0 | 2 | `id_hotel_review, id_customer` | 0 | - |

### A.8 Channel manager & third-party connectors

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_qcmc_channel_manager_booking` | 0 | 3 | `id_channel_manager_booking` | 0 | - |

### A.9 Orders, invoicing & payments

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_order_carrier` | 0 | 9 | `id_order_carrier` | 3 | - |
| `qlo_order_cart_rule` | 0 | 8 | `id_order_cart_rule` | 2 | - |
| `qlo_order_customer_guest_detail` | 0 | 9 | `id_order_customer_guest_detail` | 0 | - |
| `qlo_order_detail` | 2,104 | 50 | `id_order_detail` | 5 | 2,129 |
| `qlo_order_history` | 2,339 | 5 | `id_order_history` | 3 | 2,342 |
| `qlo_order_invoice` | 1,916 | 21 | `id_order_invoice` | 1 | 1,922 |
| `qlo_order_invoice_payment` | 2,054 | 4 | `id_order_invoice, id_order_payment` | 2 | - |
| `qlo_order_message` | 1 | 2 | `id_order_message` | 0 | 2 |
| `qlo_order_message_lang` | 1 | 4 | `id_order_message, id_lang` | 0 | - |
| `qlo_order_payment` | 2,058 | 13 | `id_order_payment` | 1 | 2,068 |
| `qlo_order_payment_detail` | 2,058 | 5 | `id_order_payment_detail` | 0 | 2,068 |
| `qlo_order_return` | 7 | 13 | `id_order_return` | 2 | 8 |
| `qlo_order_return_detail` | 7 | 8 | `id_order_return_detail` | 2 | 8 |
| `qlo_order_return_state` | 3 | 9 | `id_order_return_state` | 0 | 4 |
| `qlo_order_return_state_lang` | 3 | 5 | `id_order_return_state, id_lang` | 0 | - |
| `qlo_order_slip` | 0 | 17 | `id_order_slip` | 2 | - |
| `qlo_order_slip_detail` | 0 | 11 | `id_order_slip, id_htl_booking, id_service_product_order_detail` | 0 | - |
| `qlo_order_state` | 12 | 14 | `id_order_state` | 1 | 13 |
| `qlo_order_state_lang` | 12 | 4 | `id_order_state, id_lang` | 0 | - |
| `qlo_orders` | 1,934 | 52 | `id_order` | 13 | 1,955 |

### A.10 Discounts / cart rules

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_cart_rule` | 0 | 32 | `id_cart_rule` | 4 | - |
| `qlo_cart_rule_carrier` | 0 | 2 | `id_cart_rule, id_carrier` | 0 | - |
| `qlo_cart_rule_combination` | 0 | 2 | `id_cart_rule_1, id_cart_rule_2` | 2 | - |
| `qlo_cart_rule_country` | 0 | 2 | `id_cart_rule, id_country` | 0 | - |
| `qlo_cart_rule_group` | 0 | 2 | `id_cart_rule, id_group` | 0 | - |
| `qlo_cart_rule_lang` | 0 | 3 | `id_cart_rule, id_lang` | 0 | - |
| `qlo_cart_rule_product_rule` | 0 | 3 | `id_product_rule` | 0 | - |
| `qlo_cart_rule_product_rule_group` | 0 | 3 | `id_product_rule_group` | 0 | - |
| `qlo_cart_rule_product_rule_value` | 0 | 2 | `id_product_rule, id_item` | 0 | - |
| `qlo_cart_rule_shop` | 0 | 2 | `id_cart_rule, id_shop` | 0 | - |

### A.11 Cart & checkout

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_cart` | 2,234 | 20 | `id_cart` | 10 | 2,299 |
| `qlo_cart_cart_rule` | 0 | 2 | `id_cart, id_cart_rule` | 1 | - |
| `qlo_cart_customer_guest` | 0 | 2 | `id_customer_guest_detail, id_cart` | 0 | - |
| `qlo_cart_product` | 2,250 | 7 | `id_cart, id_product, id_product_attribute, id_address_delivery` | 2 | - |

### A.12 Customers, guests & addresses

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_address` | 22 | 26 | `id_address` | 6 | 24 |
| `qlo_address_format` | 244 | 2 | `id_country` | 0 | - |
| `qlo_connections` | 20,992 | 8 | `id_connections` | 3 | 20,993 |
| `qlo_connections_page` | 0 | 4 | `id_connections, id_page, time_start` | 0 | - |
| `qlo_connections_source` | 812 | 6 | `id_connections_source` | 4 | 813 |
| `qlo_customer` | 1,257 | 32 | `id_customer` | 6 | 1,275 |
| `qlo_customer_group` | 1,259 | 2 | `id_customer, id_group` | 2 | - |
| `qlo_customer_guest_detail` | 0 | 9 | `id_customer_guest_detail` | 0 | - |
| `qlo_customer_message` | 58 | 12 | `id_customer_message` | 2 | 66 |
| `qlo_customer_message_sync_imap` | 0 | 1 | `**NONE**` | 1 | - |
| `qlo_customer_thread` | 55 | 15 | `id_customer_thread` | 5 | 59 |
| `qlo_gender` | 2 | 2 | `id_gender` | 0 | 3 |
| `qlo_gender_lang` | 2 | 3 | `id_gender, id_lang` | 1 | - |
| `qlo_group` | 3 | 6 | `id_group` | 0 | 4 |
| `qlo_group_lang` | 3 | 3 | `id_group, id_lang` | 0 | - |
| `qlo_group_reduction` | 0 | 4 | `id_group_reduction` | 1 | - |
| `qlo_group_shop` | 3 | 2 | `id_group, id_shop` | 1 | - |
| `qlo_guest` | 22,725 | 16 | `id_guest` | 3 | 22,726 |

### A.13 Catalogue: products, images, stock (rooms are products)

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_accessory` | 0 | 2 | `**NONE**` | 1 | - |
| `qlo_attribute` | 0 | 4 | `id_attribute` | 1 | - |
| `qlo_attribute_group` | 0 | 4 | `id_attribute_group` | 0 | - |
| `qlo_attribute_group_lang` | 0 | 4 | `id_attribute_group, id_lang` | 0 | - |
| `qlo_attribute_group_shop` | 0 | 2 | `id_attribute_group, id_shop` | 1 | - |
| `qlo_attribute_impact` | 0 | 5 | `id_attribute_impact` | 1 | - |
| `qlo_attribute_lang` | 0 | 3 | `id_attribute, id_lang` | 1 | - |
| `qlo_attribute_shop` | 0 | 2 | `id_attribute, id_shop` | 1 | - |
| `qlo_category` | 12 | 11 | `id_category` | 6 | 17 |
| `qlo_category_group` | 33 | 2 | `id_category, id_group` | 2 | - |
| `qlo_category_lang` | 12 | 9 | `id_category, id_shop, id_lang` | 1 | - |
| `qlo_category_product` | 53 | 3 | `id_category, id_product` | 2 | - |
| `qlo_category_shop` | 12 | 3 | `id_category, id_shop` | 0 | - |
| `qlo_customization` | 0 | 9 | `id_customization, id_cart, id_product, id_address_delivery` | 2 | - |
| `qlo_customization_field` | 0 | 4 | `id_customization_field` | 1 | - |
| `qlo_customization_field_lang` | 0 | 4 | `id_customization_field, id_lang, id_shop` | 0 | - |
| `qlo_customized_data` | 0 | 4 | `id_customization, type, index` | 0 | - |
| `qlo_feature` | 4 | 2 | `id_feature` | 0 | 8 |
| `qlo_feature_lang` | 4 | 3 | `id_feature, id_lang` | 1 | - |
| `qlo_feature_product` | 20 | 3 | `id_feature, id_product` | 2 | - |
| `qlo_feature_shop` | 4 | 2 | `id_feature, id_shop` | 1 | - |
| `qlo_feature_value` | 4 | 3 | `id_feature_value` | 1 | 8 |
| `qlo_feature_value_lang` | 4 | 3 | `id_feature_value, id_lang` | 0 | - |
| `qlo_image` | 24 | 4 | `id_image` | 3 | 70 |
| `qlo_image_lang` | 24 | 3 | `id_image, id_lang` | 1 | - |
| `qlo_image_shop` | 24 | 4 | `id_image, id_shop` | 2 | - |
| `qlo_image_type` | 7 | 11 | `id_image_type` | 1 | 8 |
| `qlo_manufacturer` | 0 | 5 | `id_manufacturer` | 0 | - |
| `qlo_manufacturer_lang` | 0 | 7 | `id_manufacturer, id_lang` | 0 | - |
| `qlo_manufacturer_shop` | 0 | 2 | `id_manufacturer, id_shop` | 1 | - |
| `qlo_pack` | 0 | 4 | `id_product_pack, id_product_item, id_product_attribute_item` | 1 | - |
| `qlo_product` | 13 | 56 | `id_product` | 5 | 25 |
| `qlo_product_attachment` | 0 | 2 | `id_product, id_attachment` | 0 | - |
| `qlo_product_attribute` | 0 | 16 | `id_product_attribute` | 5 | - |
| `qlo_product_attribute_combination` | 0 | 2 | `id_attribute, id_product_attribute` | 1 | - |
| `qlo_product_attribute_image` | 0 | 2 | `id_product_attribute, id_image` | 1 | - |
| `qlo_product_attribute_shop` | 0 | 11 | `id_product_attribute, id_shop` | 1 | - |
| `qlo_product_carrier` | 0 | 3 | `id_product, id_carrier_reference, id_shop` | 0 | - |
| `qlo_product_country_tax` | 0 | 3 | `id_product, id_country` | 0 | - |
| `qlo_product_download` | 0 | 10 | `id_product_download` | 2 | - |
| `qlo_product_group_reduction_cache` | 0 | 3 | `id_product, id_group` | 0 | - |
| `qlo_product_lang` | 13 | 12 | `id_product, id_shop, id_lang` | 2 | - |
| `qlo_product_option` | 0 | 5 | `id_product_option` | 0 | - |
| `qlo_product_option_lang` | 0 | 3 | `id_product_option, id_lang` | 0 | - |
| `qlo_product_sale` | 6 | 4 | `id_product` | 1 | - |
| `qlo_product_shop` | 13 | 37 | `id_product, id_shop` | 3 | - |
| `qlo_product_supplier` | 0 | 7 | `id_product_supplier` | 2 | - |
| `qlo_product_tag` | 0 | 3 | `id_product, id_tag` | 2 | - |
| `qlo_scene` | 0 | 2 | `id_scene` | 0 | - |
| `qlo_scene_category` | 0 | 2 | `id_scene, id_category` | 0 | - |
| `qlo_scene_lang` | 0 | 3 | `id_scene, id_lang` | 0 | - |
| `qlo_scene_products` | 0 | 6 | `id_scene, id_product, x_axis, y_axis` | 0 | - |
| `qlo_scene_shop` | 0 | 2 | `id_scene, id_shop` | 1 | - |
| `qlo_specific_price` | 0 | 19 | `id_specific_price` | 10 | - |
| `qlo_specific_price_priority` | 20 | 3 | `id_specific_price_priority, id_product` | 1 | 126 |
| `qlo_specific_price_rule` | 0 | 13 | `id_specific_price_rule` | 1 | - |
| `qlo_specific_price_rule_condition` | 0 | 4 | `id_specific_price_rule_condition` | 1 | - |
| `qlo_specific_price_rule_condition_group` | 0 | 2 | `id_specific_price_rule_condition_group, id_specific_price_rule` | 0 | - |
| `qlo_stock` | 0 | 10 | `id_stock` | 3 | - |
| `qlo_stock_available` | 13 | 8 | `id_stock_available` | 5 | 25 |
| `qlo_stock_mvt` | 0 | 15 | `id_stock_mvt` | 2 | - |
| `qlo_stock_mvt_reason` | 8 | 5 | `id_stock_mvt_reason` | 0 | 9 |
| `qlo_stock_mvt_reason_lang` | 8 | 3 | `id_stock_mvt_reason, id_lang` | 0 | - |
| `qlo_supplier` | 0 | 5 | `id_supplier` | 0 | - |
| `qlo_supplier_lang` | 0 | 6 | `id_supplier, id_lang` | 0 | - |
| `qlo_supplier_shop` | 0 | 2 | `id_supplier, id_shop` | 1 | - |
| `qlo_tag` | 0 | 3 | `id_tag` | 2 | - |
| `qlo_tag_count` | 0 | 5 | `id_group, id_tag` | 1 | - |
| `qlo_warehouse` | 0 | 8 | `id_warehouse` | 0 | - |
| `qlo_warehouse_carrier` | 0 | 2 | `id_warehouse, id_carrier` | 2 | - |
| `qlo_warehouse_product_location` | 0 | 5 | `id_warehouse_product_location` | 1 | - |
| `qlo_warehouse_shop` | 0 | 2 | `id_warehouse, id_shop` | 2 | - |

### A.14 Tax, currency & geography

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_country` | 244 | 11 | `id_country` | 2 | 245 |
| `qlo_country_lang` | 244 | 3 | `id_country, id_lang` | 0 | - |
| `qlo_country_shop` | 244 | 2 | `id_country, id_shop` | 1 | - |
| `qlo_currency` | 1 | 11 | `id_currency` | 0 | 2 |
| `qlo_currency_shop` | 1 | 3 | `id_currency, id_shop` | 1 | - |
| `qlo_range_price` | 0 | 4 | `id_range_price` | 1 | - |
| `qlo_range_weight` | 0 | 4 | `id_range_weight` | 1 | - |
| `qlo_state` | 312 | 7 | `id_state` | 3 | 313 |
| `qlo_tax` | 52 | 4 | `id_tax` | 0 | 53 |
| `qlo_tax_lang` | 52 | 3 | `id_tax, id_lang` | 0 | - |
| `qlo_tax_rule` | 52 | 9 | `id_tax_rule` | 3 | 53 |
| `qlo_tax_rules_group` | 52 | 6 | `id_tax_rules_group` | 0 | 53 |
| `qlo_tax_rules_group_shop` | 52 | 2 | `id_tax_rules_group, id_shop` | 1 | - |
| `qlo_zone` | 8 | 3 | `id_zone` | 0 | 9 |
| `qlo_zone_shop` | 8 | 2 | `id_zone, id_shop` | 1 | - |

### A.15 Shipping & carriers

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_carrier` | 0 | 21 | `id_carrier` | 3 | - |
| `qlo_carrier_group` | 0 | 2 | `id_carrier, id_group` | 0 | - |
| `qlo_carrier_lang` | 0 | 4 | `id_lang, id_shop, id_carrier` | 0 | - |
| `qlo_carrier_shop` | 0 | 2 | `id_carrier, id_shop` | 1 | - |
| `qlo_carrier_tax_rules_group_shop` | 0 | 3 | `id_carrier, id_tax_rules_group, id_shop` | 0 | - |
| `qlo_carrier_zone` | 0 | 2 | `id_carrier, id_zone` | 0 | - |
| `qlo_delivery` | 0 | 8 | `id_delivery` | 4 | - |

### A.16 CMS pages, SEO & search

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_alias` | 2 | 4 | `id_alias` | 1 | 3 |
| `qlo_cms` | 5 | 5 | `id_cms` | 0 | 6 |
| `qlo_cms_category` | 1 | 7 | `id_cms_category` | 1 | 2 |
| `qlo_cms_category_lang` | 1 | 9 | `id_cms_category, id_shop, id_lang` | 1 | - |
| `qlo_cms_category_shop` | 1 | 2 | `id_cms_category, id_shop` | 1 | 2 |
| `qlo_cms_lang` | 5 | 8 | `id_cms, id_shop, id_lang` | 0 | - |
| `qlo_cms_role` | 0 | 3 | `id_cms_role, id_cms` | 1 | - |
| `qlo_cms_role_lang` | 0 | 4 | `id_cms_role, id_lang, id_shop` | 0 | - |
| `qlo_cms_shop` | 5 | 2 | `id_cms, id_shop` | 1 | - |
| `qlo_meta` | 40 | 3 | `id_meta` | 1 | 41 |
| `qlo_meta_lang` | 32 | 7 | `id_meta, id_shop, id_lang` | 2 | - |
| `qlo_pagenotfound` | 11,027 | 6 | `id_pagenotfound` | 1 | 11,028 |
| `qlo_search_engine` | 38 | 3 | `id_search_engine` | 0 | 39 |
| `qlo_search_index` | 674 | 3 | `id_word, id_product` | 1 | - |
| `qlo_search_word` | 306 | 4 | `id_word` | 1 | 5,130 |
| `qlo_sekeyword` | 24 | 5 | `id_sekeyword` | 0 | 25 |

### A.17 Employees, permissions & audit

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_access` | 1,077 | 6 | `id_profile, id_tab` | 0 | - |
| `qlo_employee` | 15 | 26 | `id_employee` | 3 | 18 |
| `qlo_employee_shop` | 15 | 2 | `id_employee, id_shop` | 1 | - |
| `qlo_log` | 25,891 | 9 | `id_log` | 0 | 25,892 |
| `qlo_maintenance_access` | 0 | 4 | `id_maintenance_access` | 0 | - |
| `qlo_module` | 56 | 4 | `id_module` | 1 | 58 |
| `qlo_module_access` | 560 | 5 | `id_profile, id_module` | 0 | - |
| `qlo_module_country` | 4 | 3 | `id_module, id_shop, id_country` | 0 | - |
| `qlo_module_currency` | 3 | 3 | `id_module, id_shop, id_currency` | 1 | - |
| `qlo_module_group` | 168 | 3 | `id_module, id_shop, id_group` | 0 | - |
| `qlo_module_preference` | 1 | 5 | `id_module_preference` | 1 | 2 |
| `qlo_module_shop` | 56 | 3 | `id_module, id_shop` | 1 | - |
| `qlo_modules_perfs` | 0 | 8 | `id_modules_perfs` | 1 | - |
| `qlo_profile` | 6 | 1 | `id_profile` | 0 | 19 |
| `qlo_profile_lang` | 6 | 3 | `id_profile, id_lang` | 0 | - |
| `qlo_quick_access` | 0 | 3 | `id_quick_access` | 0 | 3 |
| `qlo_quick_access_lang` | 0 | 3 | `id_quick_access, id_lang` | 0 | - |
| `qlo_request_sql` | 0 | 3 | `id_request_sql` | 0 | - |
| `qlo_required_field` | 0 | 3 | `id_required_field` | 1 | - |
| `qlo_tab` | 108 | 7 | `id_tab` | 2 | 110 |
| `qlo_tab_lang` | 108 | 3 | `id_tab, id_lang` | 0 | - |
| `qlo_tab_module_preference` | 0 | 4 | `id_tab_module_preference` | 1 | - |

### A.18 Configuration, localisation & messaging

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_attachment` | 0 | 5 | `id_attachment` | 0 | - |
| `qlo_attachment_lang` | 0 | 4 | `id_attachment, id_lang` | 0 | - |
| `qlo_compare` | 0 | 2 | `id_compare` | 0 | - |
| `qlo_compare_product` | 0 | 4 | `id_compare, id_product` | 0 | - |
| `qlo_configuration` | 459 | 7 | `id_configuration` | 3 | 460 |
| `qlo_configuration_kpi` | 38 | 7 | `id_configuration_kpi` | 3 | 39 |
| `qlo_configuration_kpi_lang` | 0 | 4 | `id_configuration_kpi, id_lang` | 0 | - |
| `qlo_configuration_lang` | 18 | 4 | `id_configuration, id_lang` | 0 | - |
| `qlo_contact` | 2 | 4 | `id_contact` | 0 | 3 |
| `qlo_contact_lang` | 2 | 4 | `id_contact, id_lang` | 0 | - |
| `qlo_contact_shop` | 2 | 2 | `id_contact, id_shop` | 1 | - |
| `qlo_date_range` | 0 | 3 | `id_date_range` | 0 | - |
| `qlo_hook` | 160 | 6 | `id_hook` | 1 | 162 |
| `qlo_hook_alias` | 86 | 3 | `id_hook_alias` | 1 | 87 |
| `qlo_hook_module` | 169 | 4 | `id_module, id_hook, id_shop` | 3 | - |
| `qlo_hook_module_exceptions` | 1 | 5 | `id_hook_module_exceptions` | 2 | 2 |
| `qlo_lang` | 1 | 8 | `id_lang` | 1 | 2 |
| `qlo_lang_shop` | 1 | 2 | `id_lang, id_shop` | 1 | - |
| `qlo_mail` | 11,021 | 6 | `id_mail` | 1 | 11,022 |
| `qlo_message` | 1,948 | 8 | `id_message` | 4 | 1,959 |
| `qlo_message_readed` | 0 | 3 | `id_message, id_employee` | 0 | - |
| `qlo_newsletter` | 0 | 8 | `id` | 0 | - |
| `qlo_referrer` | 0 | 15 | `id_referrer` | 0 | - |
| `qlo_referrer_cache` | 0 | 2 | `id_connections_source, id_referrer` | 0 | - |
| `qlo_referrer_shop` | 0 | 10 | `id_referrer, id_shop` | 0 | - |
| `qlo_risk` | 4 | 3 | `id_risk` | 0 | 5 |
| `qlo_risk_lang` | 4 | 3 | `id_risk, id_lang` | 1 | - |
| `qlo_shop` | 1 | 7 | `id_shop` | 3 | 2 |
| `qlo_shop_group` | 1 | 7 | `id_shop_group` | 1 | 2 |
| `qlo_shop_url` | 1 | 8 | `id_shop_url` | 3 | 2 |
| `qlo_timezone` | 560 | 2 | `id_timezone` | 0 | 561 |
| `qlo_webservice_account` | 0 | 7 | `id_webservice_account` | 1 | - |
| `qlo_webservice_account_shop` | 0 | 2 | `id_webservice_account, id_shop` | 1 | - |
| `qlo_webservice_permission` | 0 | 4 | `id_webservice_permission` | 4 | - |

### A.19 PrestaShop commerce remnants unused by a hotel

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_import_match` | 0 | 4 | `id_import_match` | 0 | - |
| `qlo_operating_system` | 7 | 2 | `id_operating_system` | 0 | 8 |
| `qlo_page` | 19 | 3 | `id_page` | 2 | 20 |
| `qlo_page_type` | 14 | 2 | `id_page_type` | 1 | 15 |
| `qlo_page_viewed` | 0 | 5 | `id_page, id_date_range, id_shop` | 0 | - |
| `qlo_store` | 0 | 18 | `id_store` | 0 | - |
| `qlo_store_shop` | 0 | 2 | `id_store, id_shop` | 1 | - |
| `qlo_supply_order` | 0 | 19 | `id_supply_order` | 3 | - |
| `qlo_supply_order_detail` | 0 | 23 | `id_supply_order_detail` | 3 | - |
| `qlo_supply_order_history` | 0 | 7 | `id_supply_order_history` | 3 | - |
| `qlo_supply_order_receipt_history` | 0 | 8 | `id_supply_order_receipt_history` | 2 | - |
| `qlo_supply_order_state` | 6 | 7 | `id_supply_order_state` | 0 | 7 |
| `qlo_supply_order_state_lang` | 6 | 3 | `id_supply_order_state, id_lang` | 0 | - |
| `qlo_theme` | 1 | 7 | `id_theme` | 0 | 2 |
| `qlo_theme_meta` | 38 | 5 | `id_theme_meta` | 3 | 40 |
| `qlo_theme_specific` | 0 | 4 | `id_theme, id_shop, entity, id_object` | 0 | - |
| `qlo_web_browser` | 11 | 2 | `id_web_browser` | 0 | 12 |

### A.20 Caching & runtime infrastructure

| Table | Rows | Cols | Primary key | Idx | Next AUTO_INCREMENT |
| --- | ---: | ---: | --- | ---: | ---: |
| `qlo_memcached_servers` | 0 | 4 | `id_memcached_server` | 0 | - |
| `qlo_smarty_cache` | 0 | 5 | `id_smarty_cache` | 3 | - |
| `qlo_smarty_last_flush` | 0 | 2 | `type` | 0 | - |
| `qlo_smarty_lazy_cache` | 0 | 5 | `template_hash, cache_id, compile_id` | 0 | - |

## Appendix B - verified relationship map

The dump contains **0 `ADD CONSTRAINT` statements** - the database declares no foreign
keys at all. Every relationship is enforced in PHP. Each join below was checked against
the real column lists; the generating script aborts if a claimed column does not exist.

| Parent | Child | Join column | Meaning |
| --- | --- | --- | --- |
| `qlo_customer` | `qlo_orders` | `id_customer` | 1 guest account : N orders |
| `qlo_cart` | `qlo_orders` | `id_cart` | the cart that became the order |
| `qlo_orders` | `qlo_order_detail` | `id_order` | 1 order : N room-nights |
| `qlo_orders` | `qlo_order_invoice` | `id_order` | 1 order : N invoices |
| `qlo_orders` | `qlo_order_history` | `id_order` | 1 order : N status transitions |
| `qlo_orders` | `qlo_order_payment` | `order_reference` | **joined by reference STRING, not by id** |
| `qlo_order_payment` | `qlo_order_payment_detail` | `id_order_payment` | 1 payment : N tender rows |
| `qlo_order_invoice` | `qlo_order_invoice_payment` | `id_order_invoice` | allocation of payments to invoices |
| `qlo_order_detail` | `qlo_htl_booking_detail` | `id_order_detail` | hotel layer on top of the order line |
| `qlo_cart` | `qlo_cart_product` | `id_cart` | cart lines |
| `qlo_cart_product` | `qlo_htl_cart_booking_data` | `id_cart` | dates + occupancy per cart line |
| `qlo_htl_branch_info` | `qlo_htl_room_type` | `id_hotel` | 1 hotel : N room types |
| `qlo_htl_branch_info` | `qlo_htl_room_information` | `id_hotel` | 1 hotel : N physical rooms |
| `qlo_htl_branch_info` | `qlo_htl_access` | `id_hotel` | per-hotel staff permissions (already exists) |
| `qlo_product` | `qlo_htl_room_type` | `id_product` | a room type IS a PrestaShop product |
| `qlo_product` | `qlo_htl_room_information` | `id_product` | **rooms join to types via id_product, NOT id_room_type** |
| `qlo_product` | `qlo_htl_room_type_bed_type` | `id_product` | legacy bed-type assignment, also via id_product |
| `qlo_product` | `qlo_htl_advance_payment` | `id_product` | deposit rules per room type |
| `qlo_htl_features` | `qlo_htl_branch_features` | `feature_id` | **irregular column name: feature_id, not id_feature** |
| `qlo_htl_bed_type` | `qlo_htl_room_type_bed_type` | `id_bed_type` | legacy bed types (9 defined, 8 assigned) |
| `qlo_htl_booking_detail` | `qlo_service_product_order_detail` | `id_htl_booking_detail` | extras attached to a booking |
| `qlo_htl_room_information` | `qlo_htl_room_disable_dates` | `id_room` | availability / maintenance blocks (0 rows) |

### B.1 Fan-in: the most referenced tables

| Table | Referenced by N tables (by column name) |
| --- | ---: |
| `qlo_shop` | 63 |
| `qlo_lang` | 59 |
| `qlo_product_sale` | 46 |
| `qlo_orders` | 18 |
| `qlo_customer` | 17 |
| `qlo_shop_group` | 14 |
| `qlo_currency` | 14 |
| `qlo_employee` | 14 |
| `qlo_newsletter` | 14 |
| `qlo_carrier` | 13 |
| `qlo_group` | 13 |
| `qlo_country` | 12 |

## Appendix C - full column listings for the tables the nine modules touch

Reproduced from the live dump. `PK` marks a primary-key column, `U` a column covered by a
unique key, `AI` an auto-increment column. Nothing here is invented: every line comes from
the extracted DDL.

### C.1 `qlo_orders`

1934 rows | 52 columns | PK: `id_order` | 13 index(es) | Module 3 Reservation Mgmt, Module 8 Reports

| Column | Type / definition | Flags |
| --- | --- | --- |
| `id_order` | `int(10) UNSIGNED NOT NULL` | PK AI |
| `reference` | `varchar(9) DEFAULT NULL` |  |
| `id_shop_group` | `int(11) UNSIGNED NOT NULL DEFAULT 1` |  |
| `id_shop` | `int(11) UNSIGNED NOT NULL DEFAULT 1` |  |
| `id_carrier` | `int(10) UNSIGNED NOT NULL` |  |
| `id_lang` | `int(10) UNSIGNED NOT NULL` |  |
| `id_customer` | `int(10) UNSIGNED NOT NULL` |  |
| `id_cart` | `int(10) UNSIGNED NOT NULL` |  |
| `id_currency` | `int(10) UNSIGNED NOT NULL` |  |
| `id_address_delivery` | `int(10) UNSIGNED NOT NULL` |  |
| `id_address_invoice` | `int(10) UNSIGNED NOT NULL` |  |
| `id_address_tax` | `int(10) UNSIGNED NOT NULL` |  |
| `current_state` | `int(10) UNSIGNED NOT NULL` |  |
| `secure_key` | `varchar(32) NOT NULL DEFAULT '-1'` |  |
| `payment` | `varchar(255) NOT NULL` |  |
| `payment_type` | `tinyint(10) UNSIGNED NOT NULL` |  |
| `conversion_rate` | `decimal(13,6) NOT NULL DEFAULT 1.000000` |  |
| `module` | `varchar(255) DEFAULT NULL` |  |
| `recyclable` | `tinyint(1) UNSIGNED NOT NULL DEFAULT 0` |  |
| `gift` | `tinyint(1) UNSIGNED NOT NULL DEFAULT 0` |  |
| `gift_message` | `text DEFAULT NULL` |  |
| `mobile_theme` | `tinyint(1) NOT NULL DEFAULT 0` |  |
| `shipping_number` | `varchar(64) DEFAULT NULL` |  |
| `total_discounts` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `total_discounts_tax_incl` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `total_discounts_tax_excl` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `total_paid` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `total_paid_tax_incl` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `total_paid_tax_excl` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `total_paid_real` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `total_products` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `total_products_wt` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `total_shipping` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `total_shipping_tax_incl` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `total_shipping_tax_excl` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `carrier_tax_rate` | `decimal(10,3) NOT NULL DEFAULT 0.000` |  |
| `total_wrapping` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `total_wrapping_tax_incl` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `total_wrapping_tax_excl` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `round_mode` | `tinyint(1) NOT NULL DEFAULT 2` |  |
| `round_type` | `tinyint(1) NOT NULL DEFAULT 1` |  |
| `invoice_number` | `int(10) UNSIGNED NOT NULL DEFAULT 0` |  |
| `delivery_number` | `int(10) UNSIGNED NOT NULL DEFAULT 0` |  |
| `invoice_date` | `datetime NOT NULL` |  |
| `delivery_date` | `datetime NOT NULL` |  |
| `source` | `varchar(255) DEFAULT NULL` |  |
| `valid` | `int(1) UNSIGNED NOT NULL DEFAULT 0` |  |
| `is_advance_payment` | `tinyint(1) NOT NULL DEFAULT 0` |  |
| `advance_paid_amount` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `with_occupancy` | `tinyint(1) NOT NULL DEFAULT 0` |  |
| `date_add` | `datetime NOT NULL` |  |
| `date_upd` | `datetime NOT NULL` |  |

### C.2 `qlo_order_detail`

2104 rows | 50 columns | PK: `id_order_detail` | 5 index(es) | Module 3, Module 8 - one row per room-night

| Column | Type / definition | Flags |
| --- | --- | --- |
| `id_order_detail` | `int(10) UNSIGNED NOT NULL` | PK AI |
| `id_order` | `int(10) UNSIGNED NOT NULL` |  |
| `id_order_invoice` | `int(11) DEFAULT NULL` |  |
| `id_warehouse` | `int(10) UNSIGNED DEFAULT 0` |  |
| `id_shop` | `int(11) UNSIGNED NOT NULL` |  |
| `product_id` | `int(10) UNSIGNED NOT NULL` |  |
| `product_attribute_id` | `int(10) UNSIGNED DEFAULT NULL` |  |
| `product_name` | `varchar(255) NOT NULL` |  |
| `product_quantity` | `int(10) UNSIGNED NOT NULL DEFAULT 0` |  |
| `product_quantity_in_stock` | `int(10) NOT NULL DEFAULT 0` |  |
| `product_quantity_refunded` | `int(10) UNSIGNED NOT NULL DEFAULT 0` |  |
| `product_quantity_return` | `int(10) UNSIGNED NOT NULL DEFAULT 0` |  |
| `product_quantity_reinjected` | `int(10) UNSIGNED NOT NULL DEFAULT 0` |  |
| `product_price` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `reduction_percent` | `decimal(10,2) NOT NULL DEFAULT 0.00` |  |
| `reduction_amount` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `reduction_amount_tax_incl` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `reduction_amount_tax_excl` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `group_reduction` | `decimal(10,2) NOT NULL DEFAULT 0.00` |  |
| `product_quantity_discount` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `is_booking_product` | `tinyint(1) NOT NULL DEFAULT 0` |  |
| `selling_preference_type` | `tinyint(1) NOT NULL DEFAULT 1` |  |
| `product_auto_add` | `tinyint(1) NOT NULL DEFAULT 0` |  |
| `product_price_addition_type` | `tinyint(1) NOT NULL DEFAULT 0` |  |
| `product_allow_multiple_quantity` | `tinyint(1) UNSIGNED NOT NULL DEFAULT 1` |  |
| `product_price_calculation_method` | `tinyint(1) UNSIGNED NOT NULL DEFAULT 1` |  |
| `product_ean13` | `varchar(13) DEFAULT NULL` |  |
| `product_upc` | `varchar(12) DEFAULT NULL` |  |
| `product_reference` | `varchar(32) DEFAULT NULL` |  |
| `product_supplier_reference` | `varchar(32) DEFAULT NULL` |  |
| `product_weight` | `decimal(20,6) NOT NULL` |  |
| `id_tax_rules_group` | `int(11) UNSIGNED DEFAULT 0` |  |
| `tax_computation_method` | `tinyint(1) UNSIGNED NOT NULL DEFAULT 0` |  |
| `tax_name` | `varchar(16) NOT NULL` |  |
| `tax_rate` | `decimal(10,3) NOT NULL DEFAULT 0.000` |  |
| `ecotax` | `decimal(21,6) NOT NULL DEFAULT 0.000000` |  |
| `ecotax_tax_rate` | `decimal(5,3) NOT NULL DEFAULT 0.000` |  |
| `discount_quantity_applied` | `tinyint(1) NOT NULL DEFAULT 0` |  |
| `download_hash` | `varchar(255) DEFAULT NULL` |  |
| `download_nb` | `int(10) UNSIGNED DEFAULT 0` |  |
| `download_deadline` | `datetime DEFAULT NULL` |  |
| `total_price_tax_incl` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `total_price_tax_excl` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `unit_price_tax_incl` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `unit_price_tax_excl` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `total_shipping_price_tax_incl` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `total_shipping_price_tax_excl` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `purchase_supplier_price` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `original_product_price` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `original_wholesale_price` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |

### C.3 `qlo_htl_booking_detail`

2350 rows | 38 columns | PK: `id` | 0 index(es) | Module 3 - the hotel layer; holds check-in/out state

| Column | Type / definition | Flags |
| --- | --- | --- |
| `id` | `int(11) NOT NULL` | PK AI |
| `id_product` | `int(11) NOT NULL` |  |
| `id_order` | `int(11) NOT NULL` |  |
| `id_order_detail` | `int(11) NOT NULL` |  |
| `id_cart` | `int(11) NOT NULL` |  |
| `id_room` | `int(11) NOT NULL` |  |
| `id_hotel` | `int(11) NOT NULL` |  |
| `id_customer` | `int(11) NOT NULL` |  |
| `booking_type` | `tinyint(4) NOT NULL` |  |
| `id_status` | `int(11) NOT NULL` |  |
| `comment` | `text NOT NULL` |  |
| `check_in` | `datetime NOT NULL` |  |
| `check_out` | `datetime NOT NULL` |  |
| `planned_check_out` | `datetime NOT NULL` |  |
| `date_from` | `datetime NOT NULL` |  |
| `date_to` | `datetime NOT NULL` |  |
| `total_price_tax_excl` | `decimal(20,6) NOT NULL` |  |
| `total_price_tax_incl` | `decimal(20,6) NOT NULL` |  |
| `total_paid_amount` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `is_back_order` | `tinyint(4) NOT NULL` |  |
| `hotel_name` | `varchar(255) DEFAULT NULL` |  |
| `room_type_name` | `varchar(255) DEFAULT NULL` |  |
| `city` | `varchar(255) NOT NULL` |  |
| `state` | `varchar(255) DEFAULT NULL` |  |
| `country` | `varchar(255) DEFAULT NULL` |  |
| `zipcode` | `varchar(12) DEFAULT NULL` |  |
| `phone` | `varchar(32) DEFAULT NULL` |  |
| `email` | `varchar(128) DEFAULT NULL` |  |
| `check_in_time` | `varchar(32) DEFAULT NULL` |  |
| `check_out_time` | `varchar(32) DEFAULT NULL` |  |
| `room_num` | `varchar(225) DEFAULT NULL` |  |
| `adults` | `smallint(6) NOT NULL DEFAULT 0` |  |
| `children` | `smallint(6) NOT NULL DEFAULT 0` |  |
| `child_ages` | `text NOT NULL` |  |
| `is_refunded` | `tinyint(1) NOT NULL DEFAULT 0` |  |
| `is_cancelled` | `tinyint(1) NOT NULL DEFAULT 0` |  |
| `date_add` | `datetime NOT NULL` |  |
| `date_upd` | `datetime NOT NULL` |  |

### C.4 `qlo_order_payment`

2058 rows | 13 columns | PK: `id_order_payment` | 1 index(es) | Module 5 Payment Mgmt - free-text tender, needs normalisation

| Column | Type / definition | Flags |
| --- | --- | --- |
| `id_order_payment` | `int(11) NOT NULL` | PK AI |
| `order_reference` | `varchar(9) DEFAULT NULL` |  |
| `id_currency` | `int(10) UNSIGNED NOT NULL` |  |
| `amount` | `decimal(10,2) NOT NULL` |  |
| `payment_method` | `varchar(255) NOT NULL` |  |
| `payment_type` | `int(10) UNSIGNED NOT NULL` |  |
| `conversion_rate` | `decimal(13,6) NOT NULL DEFAULT 1.000000` |  |
| `transaction_id` | `varchar(254) DEFAULT NULL` |  |
| `card_number` | `varchar(254) DEFAULT NULL` |  |
| `card_brand` | `varchar(254) DEFAULT NULL` |  |
| `card_expiration` | `char(7) DEFAULT NULL` |  |
| `card_holder` | `varchar(254) DEFAULT NULL` |  |
| `date_add` | `datetime NOT NULL` |  |

### C.5 `qlo_order_payment_detail`

2058 rows | 5 columns | PK: `id_order_payment_detail` | 0 index(es) | Module 5 - per-tender breakdown

| Column | Type / definition | Flags |
| --- | --- | --- |
| `id_order_payment_detail` | `int(10) UNSIGNED NOT NULL` | PK AI |
| `id_order_payment` | `int(10) UNSIGNED NOT NULL` |  |
| `id_order` | `int(10) UNSIGNED NOT NULL` |  |
| `amount` | `decimal(10,2) NOT NULL` |  |
| `date_add` | `datetime NOT NULL` |  |

### C.6 `qlo_customer`

1257 rows | 32 columns | PK: `id_customer` | 6 index(es) | Module 4 Guest Mgmt

| Column | Type / definition | Flags |
| --- | --- | --- |
| `id_customer` | `int(10) UNSIGNED NOT NULL` | PK AI |
| `id_shop_group` | `int(11) UNSIGNED NOT NULL DEFAULT 1` |  |
| `id_shop` | `int(11) UNSIGNED NOT NULL DEFAULT 1` |  |
| `id_gender` | `int(10) UNSIGNED NOT NULL` |  |
| `id_default_group` | `int(10) UNSIGNED NOT NULL DEFAULT 1` |  |
| `id_lang` | `int(10) UNSIGNED DEFAULT NULL` |  |
| `id_risk` | `int(10) UNSIGNED NOT NULL DEFAULT 1` |  |
| `company` | `varchar(64) DEFAULT NULL` |  |
| `siret` | `varchar(14) DEFAULT NULL` |  |
| `ape` | `varchar(5) DEFAULT NULL` |  |
| `firstname` | `varchar(32) NOT NULL` |  |
| `lastname` | `varchar(32) NOT NULL` |  |
| `email` | `varchar(128) NOT NULL` |  |
| `passwd` | `varchar(32) NOT NULL` |  |
| `last_passwd_gen` | `timestamp NOT NULL DEFAULT current_timestamp()` |  |
| `birthday` | `date DEFAULT NULL` |  |
| `newsletter` | `tinyint(1) UNSIGNED NOT NULL DEFAULT 0` |  |
| `ip_registration_newsletter` | `varchar(15) DEFAULT NULL` |  |
| `newsletter_date_add` | `datetime DEFAULT NULL` |  |
| `optin` | `tinyint(1) UNSIGNED NOT NULL DEFAULT 0` |  |
| `website` | `varchar(128) DEFAULT NULL` |  |
| `outstanding_allow_amount` | `decimal(20,6) NOT NULL DEFAULT 0.000000` |  |
| `show_public_prices` | `tinyint(1) UNSIGNED NOT NULL DEFAULT 0` |  |
| `max_payment_days` | `int(10) UNSIGNED NOT NULL DEFAULT 60` |  |
| `secure_key` | `varchar(32) NOT NULL DEFAULT '-1'` |  |
| `note` | `text DEFAULT NULL` |  |
| `phone` | `varchar(32) DEFAULT NULL` |  |
| `active` | `tinyint(1) UNSIGNED NOT NULL DEFAULT 0` |  |
| `is_guest` | `tinyint(1) NOT NULL DEFAULT 0` |  |
| `deleted` | `tinyint(1) NOT NULL DEFAULT 0` |  |
| `date_add` | `datetime NOT NULL` |  |
| `date_upd` | `datetime NOT NULL` |  |

### C.7 `qlo_guest`

22725 rows | 16 columns | PK: `id_guest` | 3 index(es) | Module 4 - anonymous/visitor sessions

| Column | Type / definition | Flags |
| --- | --- | --- |
| `id_guest` | `int(10) UNSIGNED NOT NULL` | PK AI |
| `id_operating_system` | `int(10) UNSIGNED DEFAULT NULL` |  |
| `id_web_browser` | `int(10) UNSIGNED DEFAULT NULL` |  |
| `id_customer` | `int(10) UNSIGNED DEFAULT NULL` |  |
| `javascript` | `tinyint(1) DEFAULT 0` |  |
| `screen_resolution_x` | `smallint(5) UNSIGNED DEFAULT NULL` |  |
| `screen_resolution_y` | `smallint(5) UNSIGNED DEFAULT NULL` |  |
| `screen_color` | `tinyint(3) UNSIGNED DEFAULT NULL` |  |
| `sun_java` | `tinyint(1) DEFAULT NULL` |  |
| `adobe_flash` | `tinyint(1) DEFAULT NULL` |  |
| `adobe_director` | `tinyint(1) DEFAULT NULL` |  |
| `apple_quicktime` | `tinyint(1) DEFAULT NULL` |  |
| `real_player` | `tinyint(1) DEFAULT NULL` |  |
| `windows_media` | `tinyint(1) DEFAULT NULL` |  |
| `accept_language` | `varchar(8) DEFAULT NULL` |  |
| `mobile_theme` | `tinyint(1) NOT NULL DEFAULT 0` |  |

### C.8 `qlo_htl_room_type`

8 rows | 12 columns | PK: `id` | 0 index(es) | Module 2 Room Mgmt - 1 row per sellable room type

| Column | Type / definition | Flags |
| --- | --- | --- |
| `id` | `int(11) NOT NULL` | PK AI |
| `id_product` | `int(11) NOT NULL` |  |
| `id_hotel` | `int(11) NOT NULL` |  |
| `adults` | `smallint(6) NOT NULL DEFAULT 2` |  |
| `children` | `smallint(6) NOT NULL DEFAULT 0` |  |
| `max_adults` | `smallint(6) NOT NULL DEFAULT 2` |  |
| `max_children` | `smallint(6) NOT NULL DEFAULT 0` |  |
| `max_guests` | `smallint(6) NOT NULL DEFAULT 2` |  |
| `min_los` | `smallint(6) NOT NULL DEFAULT 1` |  |
| `max_los` | `smallint(6) NOT NULL DEFAULT 0` |  |
| `date_add` | `datetime NOT NULL` |  |
| `date_upd` | `datetime NOT NULL` |  |

### C.9 `qlo_htl_room_information`

38 rows | 9 columns | PK: `id` | 0 index(es) | Module 2 + Module 6 Housekeeping - 1 row per physical room

| Column | Type / definition | Flags |
| --- | --- | --- |
| `id` | `int(11) NOT NULL` | PK AI |
| `id_product` | `int(11) NOT NULL` |  |
| `id_hotel` | `int(11) NOT NULL` |  |
| `room_num` | `varchar(225) NOT NULL` |  |
| `id_status` | `int(11) NOT NULL` |  |
| `floor` | `text NOT NULL` |  |
| `comment` | `text NOT NULL` |  |
| `date_add` | `datetime NOT NULL` |  |
| `date_upd` | `datetime NOT NULL` |  |

### C.10 `qlo_htl_room_disable_dates`

0 rows | 8 columns | PK: `id` | 0 index(es) | Module 2 + Module 6 - availability/maintenance blocks

| Column | Type / definition | Flags |
| --- | --- | --- |
| `id` | `int(11) NOT NULL` | PK AI |
| `id_room_type` | `int(11) NOT NULL` |  |
| `id_room` | `int(11) NOT NULL` |  |
| `date_from` | `date NOT NULL` |  |
| `date_to` | `date NOT NULL` |  |
| `reason` | `text DEFAULT NULL` |  |
| `date_add` | `datetime NOT NULL` |  |
| `date_upd` | `datetime NOT NULL` |  |

### C.11 `qlo_htl_branch_info`

1 rows | 15 columns | PK: `id` | 0 index(es) | Module 9 Hotel Settings - hotel identity

| Column | Type / definition | Flags |
| --- | --- | --- |
| `id` | `int(10) UNSIGNED NOT NULL` | PK AI |
| `id_category` | `int(10) UNSIGNED NOT NULL` |  |
| `email` | `varchar(128) NOT NULL` |  |
| `check_in` | `varchar(255) DEFAULT NULL` |  |
| `check_out` | `varchar(255) DEFAULT NULL` |  |
| `rating` | `int(2) UNSIGNED NOT NULL` |  |
| `active` | `tinyint(1) UNSIGNED NOT NULL DEFAULT 0` |  |
| `latitude` | `decimal(10,8) NOT NULL` |  |
| `longitude` | `decimal(11,8) NOT NULL` |  |
| `map_formated_address` | `text NOT NULL` |  |
| `map_input_text` | `text NOT NULL` |  |
| `active_refund` | `tinyint(1) UNSIGNED NOT NULL DEFAULT 0` |  |
| `fax` | `varchar(255) DEFAULT NULL` |  |
| `date_add` | `datetime NOT NULL` |  |
| `date_upd` | `datetime NOT NULL` |  |

### C.12 `qlo_htl_branch_info_lang`

1 rows | 6 columns | PK: `id, id_lang` | 0 index(es) | Module 9 - hotel name/description/policies

| Column | Type / definition | Flags |
| --- | --- | --- |
| `id` | `int(10) UNSIGNED NOT NULL` | PK |
| `id_lang` | `int(10) UNSIGNED NOT NULL` | PK |
| `hotel_name` | `varchar(255) DEFAULT NULL` |  |
| `short_description` | `text DEFAULT NULL` |  |
| `description` | `text DEFAULT NULL` |  |
| `policies` | `text DEFAULT NULL` |  |

### C.13 `qlo_employee`

15 rows | 26 columns | PK: `id_employee` | 3 index(es) | Module 7 Staff & Permissions

| Column | Type / definition | Flags |
| --- | --- | --- |
| `id_employee` | `int(10) UNSIGNED NOT NULL` | PK AI |
| `id_profile` | `int(10) UNSIGNED NOT NULL` |  |
| `id_lang` | `int(10) UNSIGNED NOT NULL DEFAULT 0` |  |
| `lastname` | `varchar(32) NOT NULL` |  |
| `firstname` | `varchar(32) NOT NULL` |  |
| `email` | `varchar(128) NOT NULL` |  |
| `passwd` | `varchar(32) NOT NULL` |  |
| `last_passwd_gen` | `timestamp NOT NULL DEFAULT current_timestamp()` |  |
| `stats_date_from` | `date DEFAULT NULL` |  |
| `stats_date_to` | `date DEFAULT NULL` |  |
| `stats_compare_from` | `date DEFAULT NULL` |  |
| `stats_compare_to` | `date DEFAULT NULL` |  |
| `stats_compare_option` | `int(1) UNSIGNED NOT NULL DEFAULT 1` |  |
| `preselect_date_range` | `varchar(32) DEFAULT NULL` |  |
| `bo_color` | `varchar(32) DEFAULT NULL` |  |
| `bo_theme` | `varchar(32) DEFAULT NULL` |  |
| `bo_css` | `varchar(64) DEFAULT NULL` |  |
| `default_tab` | `int(10) UNSIGNED NOT NULL DEFAULT 0` |  |
| `bo_width` | `int(10) UNSIGNED NOT NULL DEFAULT 0` |  |
| `bo_menu` | `tinyint(1) NOT NULL DEFAULT 1` |  |
| `active` | `tinyint(1) UNSIGNED NOT NULL DEFAULT 0` |  |
| `optin` | `tinyint(1) UNSIGNED NOT NULL DEFAULT 1` |  |
| `id_last_order` | `int(10) UNSIGNED NOT NULL DEFAULT 0` |  |
| `id_last_customer_message` | `int(10) UNSIGNED NOT NULL DEFAULT 0` |  |
| `id_last_customer` | `int(10) UNSIGNED NOT NULL DEFAULT 0` |  |
| `last_connection_date` | `date DEFAULT '0000-00-00'` |  |

### C.14 `qlo_access`

1077 rows | 6 columns | PK: `id_profile, id_tab` | 0 index(es) | Module 7 - profile x tab permissions

| Column | Type / definition | Flags |
| --- | --- | --- |
| `id_profile` | `int(10) UNSIGNED NOT NULL` | PK |
| `id_tab` | `int(10) UNSIGNED NOT NULL` | PK |
| `view` | `int(11) NOT NULL` |  |
| `add` | `int(11) NOT NULL` |  |
| `edit` | `int(11) NOT NULL` |  |
| `delete` | `int(11) NOT NULL` |  |

### C.15 `qlo_htl_access`

11 rows | 3 columns | PK: `id_profile, id_hotel` | 0 index(es) | Module 7 - per-hotel permissions (already exists)

| Column | Type / definition | Flags |
| --- | --- | --- |
| `id_profile` | `int(10) UNSIGNED NOT NULL` | PK |
| `id_hotel` | `int(10) UNSIGNED NOT NULL` | PK |
| `access` | `int(11) NOT NULL` |  |

### C.16 `qlo_configuration`

459 rows | 7 columns | PK: `id_configuration` | 3 index(es) | Module 9 - all settings, incl. check-in/out, timezone

| Column | Type / definition | Flags |
| --- | --- | --- |
| `id_configuration` | `int(10) UNSIGNED NOT NULL` | PK AI |
| `id_shop_group` | `int(11) UNSIGNED DEFAULT NULL` |  |
| `id_shop` | `int(11) UNSIGNED DEFAULT NULL` |  |
| `name` | `varchar(254) NOT NULL` |  |
| `value` | `text DEFAULT NULL` |  |
| `date_add` | `datetime NOT NULL` |  |
| `date_upd` | `datetime NOT NULL` |  |
