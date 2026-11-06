# Lush View Hotel — QloApps Application Audit

**Repository:** `iwani1/lushviewhotel-2.0`
**Audited tree:** QloApps **1.8.0** — `develop` snapshot @ `e502b63f`, imported 2026-11-06;
verified byte-identical to the stable `v1.8.0` tag
**Client production site:** `lushviewhotel.net` — QloApps **1.7.0** (see §2)
**Audit date:** 2026-11-06

**Method and scope.** Two parts:

1. **Static analysis of the imported tree** — source inspection, dependency metadata, and
   correlation against published QloApps CVEs. Where a CVE was relevant I read the patched
   code directly rather than trusting version numbers, because two upstream fixes were
   squash-merged and therefore invisible to commit-ancestry checks.
2. **Read-only reconnaissance of the live production site** — fetching publicly served pages
   and files to fingerprint the running version and check deployment hygiene. Version
   detection used CHANGELOG blob fingerprinting cross-checked against `composer.json`
   (§2.1). Probe paths were existence checks only; **no exploit was attempted and no
   authenticated area was touched.**

**Limitations.** No PHP runtime exists in the audit sandbox, so the installer was **not**
booted and no dynamic or lint pass was run — nothing here is verified by execution. Live-site
findings are limited to what is publicly served; I could not enumerate room inventory,
confirm which modules are enabled, or read the host's actual PHP version. Those are flagged
as open questions in §2.6.

---

## 1. Executive summary

**This is an upgrade project, not a greenfield install.** The client's production site,
`lushviewhotel.net`, is **already running QloApps — version 1.7.0** (§2). The tree in this
repository is **QloApps 1.8.0**, released 2026-11-06 08:53 UTC, and GitHub reports
`v1.8.0...develop` as `identical` (0 commits ahead, 0 behind, 0 files changed) — so the
import is the stable release, not a moving dev branch. The repository name
`lushviewhotel-2.0` is therefore literally the 1.7.0 → 1.8.0 migration.

Three findings dominate everything else.

**0. A production database export was committed to this public repository.** During this
engagement a ~15 MB dump of the live hotel database — 300 tables including 1,257 customer
records, 22,725 guest rows, 1,934 orders, 2,350 booking details and 15 active back-office
employee accounts — was pushed to this branch in a repository whose visibility is **public**.
It has been **purged from branch history and `.gitignore` now blocks a repeat**, but the
exposure window cannot be undone by git alone. Employee and customer passwords must be
rotated, GitHub Support asked to purge the dangling object, and breach-notification duties
under Ghana's Data Protection Act 2012 (Act 843) assessed. Two facts limit the damage: **no
card data** (the `card_number`, `card_holder`, `card_expiration` and `card_brand` columns are
empty in all 2,058 payment rows) and **no API keys or cookie salt** leaked, so the password
hashes — MD5-era but salted with the install's `_COOKIE_KEY_`, which was not included — are
not trivially crackable. Full detail and the outstanding checklist in **§4.4**; this is P0
item 7 in §9.

**1. The live production site is exposed.** Every recent QloApps security fix is **absent
from the v1.7.0 tag** that production runs — verified by commit ancestry, all four fix
commits return `behind`. That includes **CVE-2025-67325, an unauthenticated unrestricted
file upload leading to remote code execution**, and `qlohotelreview` (the module carrying
it) ships in 1.7.0. All are patched in the 1.8.0 tree held here. Compounding it,
`CHANGELOG.txt` and `composer.json` are publicly readable on the live site, handing an
attacker the exact version in one request (§2.2, §2.3).

**2. This repository cannot upgrade the live site by itself.** The 1.8.0 tree contains **no
1.7.0 → 1.8.0 database migration**. At least eight tables the new code depends on —
`tax_configuration`, `tax_validity_range`, `tax_price_tier`, `tax_child_range`,
`order_tax_detail`, `order_tax_exemption`, `business_source`, `source` — are absent from the
1.7.0 schema and nothing in this tree creates them for an existing install. The bundled
`install-dev/upgrade/` machinery is PrestaShop-era and keyed to `_PS_INSTALL_VERSION_` =
`1.6.1.23`; `hotelreservationsystem` declares version `1.8.0` but ships no `upgrade-1.7.0.php`
or `upgrade-1.8.0.php`, so the module version gets bumped in the database **with no migration
run and no error raised**. The supported vehicle, the QloApps **1-Click Upgrade addon**
(`qloautoupgrade`), is a stub in 1.7.0 and **removed entirely from 1.8.0** — it is fetched
from QloApps' servers, not vendored here. **Copying these files over the live install would
silently corrupt it** (§2.9).

The imported 1.8.0 tree is otherwise clean: every QloApps CVE published in the last 12 months
that I could locate is fixed in it, verified by reading the patched code rather than trusting
version numbers.

**The remaining work is migration strategy, then localisation and content:**

1. **Choose the migration path** — in-place 1-Click Upgrade from the live 1.7.0 back office,
   or a fresh 1.8.0 install plus data migration. Do **not** file-copy (§2.9).
2. ~~**Confirm the host PHP version**~~ — **RESOLVED.** The live manifest declares
   `>=5.4 <8.0`, but that file is stale: the production database records the host runtime as
   **PHP 8.4.27** on **MariaDB 11.4.13**, inside 1.8.0's supported `>=8.2 <8.6` range. **No
   PHP upgrade is needed**, which removes what looked like the biggest schedule risk (§2.1,
   §2.7). The same export confirms `QLO_VERSION_DB` = `1.7.0.0`, so the target version is no
   longer inferred from a CHANGELOG fingerprint but read from the database itself.
3. **Account for the `install/` → `install-dev/` and `admin/` → `admin-dev/` directory
   rename** in any deploy script, `.htaccess` rule or cron entry (§2.9).
4. **Ghana localisation** — now added to this branch as `localization/gh.xml` (GHS + Act 1151
   tax rules), guarded by CI. Still open: region data and the `need_zip_code` decision (§6).
5. **Install blocker fixed on this branch:** `ext-mcrypt` removed from `composer.json` (§5).
6. **Content defects on the live site** — page-title typo `Lush VIew`, `Dummy Image 1–12` alt
   text across the gallery, duplicated placeholder blocks. Cheap, high-visibility (§2.5).
7. **Payment gateway — DEFERRED** at the client's direction. The gap is real and documented
   (§6.2) but off the critical path (§2.8).

Branding is far cheaper than the raw string count suggests (see §7) — **do not** bulk-rename.

---

## 2. Live site: lushviewhotel.net

The client's production site is **already a QloApps installation, running version 1.7.0**.
This reframes the project: `lushviewhotel-2.0` is a **1.7.0 → 1.8.0 upgrade**, and the tree
in this repository is precisely the release that fixes what the live site is missing.

### 2.1 Platform and version confirmation

**Platform — confirmed QloApps.** Live asset paths map exactly onto modules and the theme in
our tree:

- `lushviewhotel.net/modules/wkabouthotelblock/views/img/hotel_interior/*.jpg` → present in
  our tree at the same path (`wkabouthotelblock` 1.2.1)
- `lushviewhotel.net/modules/wktestimonialblock/views/img/hotels_testimonials_img/*.jpg` →
  `wktestimonialblock` 1.1.9
- `lushviewhotel.net/themes/hotel-reservation-theme/img/404.png` → same theme as ours

**Version — 1.7.0, established by CHANGELOG blob fingerprinting.** `CHANGELOG.txt` is
publicly readable on the live site; its top entry is `V1.7.0` and it contains the 1.7.0
release notes (e.g. `#1464: Upgrade for PHP version 8.1 to 8.4`). Each upstream release has
a distinct CHANGELOG blob, and no `V1.8.0` entry is present:

| Tag | CHANGELOG blob | Bytes | Top entry |
|---|---|---|---|
| v1.5.2 | `fae99866` | 42,119 | 1.5.2 |
| v1.6.0 | `87434941` | 63,243 | 1.6.0 |
| v1.6.1 | `927014a0` | 101,563 | 1.6.1 |
| **v1.7.0** | `9e5053a6` | 114,514 | **1.7.0 ← live site** |
| v1.8.0 | `41505844` | 139,170 | 1.8.0 ← this repo |

Corroborating signal: the live `composer.json` matches the *single identical blob* shared by
v1.5.2–v1.7.0 (`webkul/qloapps`, `php >=5.4 <8.0`, `minimum-stability: dev`). Note that file
**cannot discriminate** between those three releases — only v1.8.0 changed it
(`qloapps/qloapps`, `php >=8.2 <8.6`, `stable`). So composer.json bounds the live site at
"≤1.7.0" and the CHANGELOG pins it to exactly 1.7.0.

**Version — now confirmed directly from the database, not inferred.** A production database
export taken from the live server during this audit carries the version rows QloApps writes
itself (added by CHANGELOG #1481, *"Added current installed QloApps version to the DB on
install"*):

| Configuration key | Value | Meaning |
|---|---|---|
| `PS_VERSION_DB` | `1.6.1.23` | Underlying PrestaShop schema version |
| `QLO_VERSION_DB` | `1.7.0.0` | **Installed QloApps version — the live site is 1.7.0** |

Both values match `install-dev/install_version.php` in the v1.7.0 tree exactly
(`_PS_INSTALL_VERSION_` = 1.6.1.23). This removes the last doubt from the CHANGELOG
fingerprint: **the upgrade is 1.7.0 → 1.8.0**, and this repo is the 1.8.0 side of it.

The same export also records the host runtime, which closes the PHP question in §2.7:

- **PHP 8.4.27** — inside 1.8.0's supported `>=8.2 <8.6` range.
- **MariaDB 11.4.13**, exported via phpMyAdmin 5.2.3.
- Table prefix **`qlo_`** (not the PrestaShop default `ps_`) — relevant to any migration
  script written against this database.

### 2.2 URGENT — the live site is missing every recent security fix

I verified by commit ancestry that **all four upstream fix commits are absent from the
v1.7.0 tag** (compare status `behind`, `ahead_by=0` for each):

| Fix commit | CVEs | Class | In v1.7.0 (live)? |
|---|---|---|---|
| `123c97c` | CVE-2026-75497, CVE-2026-75498 | SQL injection via `bo_query` | **No** |
| `153ec1c` | CVE-2026-89268 | Reflected XSS, list filter params | **No** |
| `1d06fd3` | CVE-2026-103587 | Reflected XSS, Book Now search | **No** |
| `7ed467d` | CVE-2026-103588, CVE-2026-103589 | Reflected XSS, module transplant + room type editor | **No** |

Most seriously, **`qlohotelreview` is bundled in v1.7.0** (59 modules in that tag). That is
the module carrying **CVE-2025-67325 — unauthenticated unrestricted file upload leading to
remote code execution**, scoped to versions ≤1.7.0. §4.1 documents that our 1.8.0 tree
patches it with a strict `jpg/png/jpeg` allowlist and `ImageManager::resize()` re-encoding.

**The module is enabled on the live site.** The production database export lists
`qlohotelreview` with `active=1` in `qlo_module` (§2.1). So the unpatched upload path is not
merely present in the release the hotel runs — it is switched on. The review table
`qlo_qhr_hotel_review` also exists in that schema, confirming the module has been installed
and used. **This is the single highest-priority item in this audit** and it is a back-office
toggle, not a code change (§9, P0 item 2).

> **Honest scoping of this finding.** I confirmed (a) the fixes are absent from the v1.7.0
> tag, (b) the CVEs are published as affecting ≤1.7.0, and (c) `qlohotelreview` is
> `active=1` in the live database. What I have **not** confirmed is whether the operator
> back-ported a hotfix to the module's files on disk — the database records the enabled flag
> and version string, not the code. Treat this as **confirmed-exposed, verify-then-remediate**:
> disable the module now regardless, since the toggle costs nothing and is reversible.

**If the upgrade cannot happen immediately**, apply these stop-gaps in order:

1. **Disable the `qlohotelreview` module** in the back office — removes the RCE-class upload
   path outright. Re-enable after the upgrade.
2. **Block public access** to `CHANGELOG.txt`, `composer.json`, `README.md`, `AGENTS.md`,
   `LICENSE.md` (see §2.3).
3. Add a WAF rule on `bo_query` rejecting SQL metacharacters.
4. Drop `FILE`, `DROP` and cross-database rights from the MySQL user.

### 2.3 Live-site deployment hygiene

Mostly good, with two information-disclosure gaps:

| Path probed | Result | Verdict |
|---|---|---|
| `/install-dev/` | QloApps 404 page | ✅ Installer correctly removed |
| `/admin-dev/` | QloApps 404 page | ✅ Back office not on the default path |
| `/sitemap.xml` | 404 | ✅ Not exposed |
| `/CHANGELOG.txt` | **Full release notes served** | ❌ Discloses exact version |
| `/composer.json` | **Full manifest served** | ❌ Discloses stack + PHP range |

Version disclosure is what converts a public CVE list into a targeted attack: an attacker
reading `/CHANGELOG.txt` learns "1.7.0" in one request and immediately knows which published
exploits apply. Blocking those two files is a five-minute `.htaccess`/nginx change and should
be done regardless of upgrade timing.

### 2.4 Content inventory for migration

| Field | Value on live site |
|---|---|
| Hotel name | Lush View Hotel |
| Address (contact page, "Main Branch") | `lushviewhotel.net, H71 Afariwa Rd, Tema, Tema, Ghana` |
| Phone | `030 298 5459` (intl. `+233 30 298 5459`) |
| Email | `info@lushviewhotel.net` |
| Footer | `© 2010-2026 Lush View Hotel. All rights reserved.` |
| Amenities copy | fitness center, spa, outdoor pool, dining |
| Room types referenced | Executive Room, View Room (from testimonials) |
| Testimonials | Paul Kumi (CEO), Samuel Adams (Business Man), Felix Abbey (Banker) |
| Third-party listing | Google: H71 Afariwa Rd, Tema · +233 30 298 5459 · 4.2 "Very good" · 123 reviews |

The 123 Google reviews are a real asset — worth wiring the `qlohotelreview` module
(post-upgrade) or a review-platform embed so on-site social proof matches Google's.

### 2.5 Content defects on the live site (cheap, high-visibility wins)

- **Page title typo:** `Lush VIew Hotel - lushviewhotel.net` — capital `I` in "VIew". This is
  the browser-tab and SERP title, so it is the most visible error on the site.
- **Placeholder alt text:** all twelve hotel-interior gallery images carry alt text
  `Dummy Image 1` … `Dummy Image 12`. The images are real uploads; the alt text is the
  module default, never replaced. Hurts accessibility and image SEO.
- **Duplicated homepage blocks:** "Luxurious Rooms" ×3, "Restaurants" ×3 and
  "Swimming Pool" ×3, each trio with byte-identical copy. This reads as demo/fixture content
  left in place rather than three genuine rooms, restaurants and pools.
- **Unfinished copy:** "crafted from the finest local ingredient" (truncated), "featuring
  premium amenities ." (stray space before the period), "Swimming Pool available"
  (placeholder sentence).
- **Domain in the address field:** the contact address begins with `lushviewhotel.net,` — the
  website URL has been typed into a street-address field.
- **Copyright range starts 2010:** almost certainly boilerplate.

### 2.6 Open questions for the client (blocking)

1. **`lushviewhotel.com` also exists** and appears to be the same hotel carrying *different*
   data: address "Nii Okoi Olaaye, Tema C22, Ghana", emails `info@lushviewhotel.com` and
   `lushview6@gmail.com`, nav HOME / BOOKING / ROOMS / ABOUT / NEWS / CONTACT, and room URLs
   like `/rooms/ds-r001/?room_type=presidential`. Is `.com` the client's too? Is it the old
   site, a second property, or a third-party listing? **Which domain is canonical for 2.0?**
2. A third-party listing places a "Lush View Hotel" in **Ashaiman** (H417, Nii Teeth Kple
   Street, `GB-033-4416`, 25 bedrooms) — a different address from Tema. Same property, second
   property, or an unrelated hotel sharing the name?
3. ~~**Room inventory could not be enumerated**~~ — **ANSWERED** from the production database
   export: **8 room types**, **38 individual rooms**, **56 enabled modules**, 459
   configuration rows. Still to confirm with the client: whether all 38 rooms are sellable
   and whether the room-type set matches what the public site advertises (§2.5 notes the
   homepage triplicates three room blocks).
4. **In-place upgrade or fresh build?** Running QloApps' own 1.7.0→1.8.0 upgrade path against
   the live database preserves bookings and customer history. A fresh 1.8.0 install needs an
   explicit export/import plan. These are very different projects with very different risk.

### 2.7 Migration prerequisites and risks

- **PHP version — RESOLVED, no longer a schedule risk.** 1.8.0 requires **PHP >=8.2 <8.6**.
  The live `composer.json` still declares `>=5.4 <8.0`, but that manifest is stale; the
  production database export records the host's actual runtime as **PHP 8.4.27** (§2.1),
  which sits inside the supported range. **No PHP upgrade is needed before 1.8.0 can boot.**
  What still needs checking is the *other* extensions 1.8.0 requires (`intl`, `gd`,
  `openssl`, `mbstring`, `zip`, `curl`) — see §5, where `ext-mcrypt` is deliberately **not**
  among them.
- **Channel manager is a breaking swap.** v1.8.0 removed the default
  `qlochannelmanagerconnector` and replaced it with the combined `qlocmconnector`
  (CHANGELOG #1952). **This now applies: the production database shows
  `qlochannelmanagerconnector` is installed and `active=1` on the live site**, so the swap
  needs configuration and data migration, not just a file copy. Confirm with the client which
  channel manager / OTA connections are actually in use before the upgrade.
- **Module count moved 57 → 61 bundled.** Removed: `qloautoupgrade`, `qlochannelmanagerconnector`. Added: `qlocmconnector`, `qlocrontaskmanager`, `qloduitkupayment`, `qlogooglehotelconnector`, `qlohotelreports`, `qlositemap`. Diff the *enabled* module list in the live database before and after, not just the bundled set.
- **Back up the database and all uploaded media** (`img/`, `modules/*/views/img/`,
  `upload/`, `download/`) before any upgrade attempt. The gallery and testimonial images
  above live under module paths that an upgrade may overwrite.

### 2.8 Deferred: payment gateway work

Payment gateway integration is **on hold** at the client's direction. The §6.2 finding stands
(no mobile-money gateway is bundled; zero hits for Paystack, Flutterwave, Hubtel, MoMo or
M-Pesa) and remains the largest functional gap for a Ghanaian property — but it is not on the
critical path for the 1.7.0 → 1.8.0 upgrade and is excluded from the action plan below until
reinstated.

### 2.9 Migration path — a file-copy upgrade will break the database

**This is the single highest-risk finding in the audit.** The 1.8.0 tree in this repository
**contains no 1.7.0 → 1.8.0 database migration**. Copying these files over the live
installation would leave the database missing tables the new code requires, and the site
would fail at runtime.

**Evidence 1 — the schema moved, and nothing in the tree bridges it.**
Comparing `install/data/db_structure.sql` at tag v1.7.0 against the 1.8.0 tree, at least
eight tables that 1.8.0 code depends on are **absent from the 1.7.0 schema**:

| New in 1.8.0 | Referenced by |
|---|---|
| `tax_configuration` | `classes/tax/TaxConfiguration.php`, `TaxCalculator.php` |
| `tax_validity_range` | `classes/tax/TaxValidityRange.php` |
| `tax_price_tier` | tourism tax rate tiers (#1760) |
| `tax_child_range` | tourism tax child pricing (#1760) |
| `order_tax_detail` | `classes/order/OrderTaxDetail.php`, `Cart.php`, `PaymentModule.php` |
| `order_tax_exemption` | tourism tax exemptions (#1760) |
| `business_source` / `business_source_lang` | Source & Business Source Management (#1782) |
| `source` / `source_lang` | Source & Business Source Management (#1782) |

These are exactly the tables behind 1.8.0's two headline features — the tourism tax system
and source tracking. `TaxCalculator::getTaxesAmount()` calls
`TaxConfiguration::getByTaxId()` and reads `Configuration::get('QLO_USE_TOURISM_TAX')`, so
the code paths are live, not dormant. A 1.7.0 database has none of them.

**Evidence 2 — the core upgrade machinery is PrestaShop-era and inert for this bump.**
`install-dev/upgrade/sql/` contains only `1.4.1.sql`, `1.5.0.sql`, `1.5.1.0.sql`,
`1.5.2.0.sql`, `1.6.0.0.sql`, `1.6.1.0.sql`. `upgrade.php:137` compares against
`_PS_INSTALL_VERSION_` — hardcoded to **`1.6.1.23`** — not `_QLO_INSTALL_VERSION_`
(`1.8.0.0`). So the built-in upgrader governs PrestaShop-lineage versions and has nothing to
say about a QloApps 1.7.0 → 1.8.0 transition.

**Evidence 3 — the module-level migration for the core hotel module is missing.**
`hotelreservationsystem` declares `version = '1.8.0'` (was `1.7.0`), but its `upgrade/`
directory tops out at `upgrade-1.6.0.php` — identical in both tags. There is no
`upgrade-1.7.0.php` or `upgrade-1.8.0.php`. Per `classes/module/Module.php:626-663`,
`loadUpgradeVersionList()` finds no applicable file and then simply calls
`Module::upgradeModuleVersion()` and records `success = true`. **The module version number is
bumped in the database with no schema migration executed** — the failure is silent, which is
worse than an error.

**Evidence 4 — the 1-Click Upgrade module is not in this tree.**
`qloautoupgrade` is a two-file stub in v1.7.0 (`CHANGELOG.txt`, `qloautoupgrade.php`) and is
**absent entirely from v1.8.0**. The 1.8.0 CHANGELOG records `#1345: Removed Autoupgrade
module from the QloApps core` (under V1.7.0) and `#370: Added Autoupgrade addon`. Core code
still probes for it — `classes/Tools.php:3456` reports
`Module::isInstalled('qloautoupgrade') && Module::isEnabled('qloautoupgrade')` to the
version-check feed, and `classes/Upgrader.php:128-131` reads `autoupgrade_module_link` from
QloApps' RSS feed.

**Conclusion:** the supported upgrade vehicle is the **separately-distributed QloApps 1-Click
Upgrade addon**, fetched from QloApps' servers and run from the *existing* 1.7.0 back office.
It is not in this repository and I could not inspect it, so **its exact behaviour is
unverified**. Two viable paths:

- **(A) In-place 1-Click Upgrade.** Install/enable the autoupgrade addon on the live 1.7.0
  site and let it pull 1.8.0 and run the real migrations. Preserves bookings, customers and
  history. Requires the addon and a full backup first.
- **(B) Fresh 1.8.0 install + data migration.** Install this tree clean (it will create the
  full 1.8.0 schema), then export/import hotels, room types, customers and bookings. More
  control, more work, and the export/import surface must be validated — 1.7.0 CSV samples
  keyed on product id were changed to room type id in 1.7.0 (#1344), so format compatibility
  needs checking.

Also note the **directory rename between versions**: v1.7.0 uses `install/` and `admin/`;
1.8.0 uses `install-dev/` and `admin-dev/`. Any deployment script, CI job, `.htaccess` rule,
cron entry or `settings.inc.php` path referencing the old names must be updated. This alone
breaks naive rsync-based deploys.

Scale of the delta: **1,620 commits** between v1.7.0 and v1.8.0. The compare API reports 300
changed files, which is its response cap — the true figure is higher.

---

## 3. Identity and provenance

| Attribute | Value |
|---|---|
| Application | QloApps (hotel reservation / PMS / booking engine) |
| Version | **1.8.0.0** (`_QLO_INSTALL_VERSION_`, `install-dev/install_version.php`) |
| Lineage | PrestaShop fork, base `_PS_INSTALL_VERSION_` = **1.6.1.23** |
| Upstream commit | `e502b63f83a3b1d9d40caea4993a5fe817b96396` (2026-11-06 08:17 UTC) |
| Release equivalence | **`develop` ≡ tag `v1.8.0`** — verified identical via GitHub compare API |
| License | **OSL-3.0** (core), **AFL-3.0** (modules) |
| PHP | `>=8.2 <8.6` |
| Database | MySQL 5.7 / 8.0+; MariaDB 10.5, 10.6, 10.11, 11.0, 11.2, 11.4 |
| Template engine | Smarty 3.x, bundled in `tools/smarty` |
| Tracked files | 8,314 (49 ignored by upstream `.gitignore`) |
| Bundled modules | 61 |
| Install state | **Not installed** — `config/settings.inc.php` absent, root `.htaccess` absent |

> **Licence note for a commercial hotel:** OSL-3.0 is a *copyleft* licence. Running it as a
> service for Lush View Hotel is fine, but if you distribute modified source you must
> disclose it under the same terms. Custom modules you write are AFL-3.0 territory. Worth a
> brief legal read before any client-facing redistribution.

---

## 4. Security audit

### 4.1 Published CVEs — all verified patched in this snapshot

| CVE | Class | CVSS | Status here | Evidence |
|---|---|---|---|---|
| CVE-2026-75498 | SQLi, `bo_query` in `Address.php` | 7.2 / 8.6 (v4) | **Patched** | Fix `123c97c` is an ancestor of our commit (`ahead_by=1091`). `bo_query` is sanitised via `Tools::safeOutput()` at `classes/controller/AdminController.php:2267` |
| CVE-2026-75497 | SQLi, `bo_query` in `CustomerMessage.php` | 7.2 | **Patched** | Same fix commit, same sanitisation path |
| CVE-2026-89268 | Reflected XSS, list filter POST params | 5.4 | **Patched** | Fix `153ec1c` is an ancestor. `\|escape:'html':'UTF-8'` confirmed at `list_header.tpl:254, 282, 303, 304` |
| CVE-2026-103589 | Reflected XSS, room type editor (`room_num`, `floor`, `comment`) | 5.4 | **Patched** | Escaping confirmed at `products/configuration.tpl:98, 101, 111, 123`. (Upstream fix commits `7ed467d`/`1d06fd3` read as `diverged` — they were squash-merged, so SHA ancestry is inconclusive; **the code itself is correct**) |
| CVE-2026-103588 | Reflected XSS, `exceptions` field, Transplant-a-module | 5.4 | **Patched** | `AdminModulesPositionsController.php:94-106, 133-149` validates each value and rejects invalid input before persistence |
| CVE-2025-67325 | **Unrestricted file upload → RCE**, hotel review (≤1.7.0) | High | **Patched** | `QhrHotelReview::saveReviewImages()` applies an explicit `['jpg','png','jpeg']` allowlist with strict `in_array(..., true)`, and writes through `ImageManager::resize()` — which re-encodes the image and strips any embedded payload rather than moving raw bytes |

**Conclusion: no known-vulnerable code in this tree.** The security work is deployment
hygiene (§4.3), not patching.

### 4.2 Residual code-level observations

- **`search_form.tpl:85`** renders `value="{$bo_query}"` with **no template-level escape**.
  It is safe *today* only because the value is pre-sanitised by `Tools::safeOutput()` at
  `AdminController.php:2267`. That is a single point of failure — if any code path ever
  assigns `bo_query` directly, it becomes reflected XSS in the back office.
  **Recommendation:** add `|escape:'html':'UTF-8'` for defence in depth. Low effort, closes
  a latent regression risk.
- Encryption is OpenSSL-first. `classes/PhpEncryptionEngine.php:131` uses
  `openssl_random_pseudo_bytes()`, with `mcrypt_create_iv()` at line 142 only as a fallback.
  `PhpEncryptionLegacyEngine.php` is mcrypt-only but is gated behind `PS_CIPHER_ALGORITHM=1`
  and self-documents as deprecated. Correct posture for modern PHP.

### 4.3 Deployment hardening checklist (pre-launch, blocking)

- [ ] Rename `admin-dev/` to an unguessable path — it is the back-office entry point and a
      well-known scanner target.
- [ ] **Delete `install-dev/` entirely** after installation. A live installer is a
      full-reinstall vector.
- [ ] Verify the generated root `.htaccess` actually landed (it is absent now by design —
      the installer writes it). On nginx, port those rules manually; Apache directives will
      be silently ignored.
- [ ] Confirm `config/settings.inc.php` is written with correct permissions and stays
      gitignored (it holds DB credentials). Currently correctly ignored upstream.
- [ ] Keep `_PS_MODE_DEV_` **false** (default is false — good). Never enable on production;
      it flips `display_errors` on and enables `_PS_DEBUG_SQL_`.
- [ ] Confirm `config/.htaccess` deny rules are honoured (present and correct for both
      Apache 2.2 and 2.4).
- [ ] Least-privilege the MySQL user — drop `FILE`, `DROP`, and cross-database rights. This
      is also the documented mitigation for the `bo_query` SQLi class.
- [ ] Put a WAF rule on `bo_query` for SQL metacharacters as belt-and-braces.

---

### 4.4 Incident — a production database export was committed to this public repository

**What happened.** During this engagement a full production database export from the live
hotel server (~15 MB, 300 tables, `qlo_` prefix) was committed to branch
`arena/07df51c3-lushviewhotel-2-0` via the GitHub web UI. The repository
`iwani1/lushviewhotel-2.0` is **public** (`isPrivate=false`), so the file was retrievable by
anyone from the moment it was pushed. It was never merged to `main`.

**What was in it.** Personal and operational data, not just schema:

| Table | Rows | Sensitive content |
|---|---|---|
| `qlo_guest` | 22,725 | Guest records |
| `qlo_connections` | 20,992 | Session/connection logs |
| `qlo_mail` | 11,021 | Queued mail |
| `qlo_htl_booking_detail` | 2,350 | City, phone, email per booking |
| `qlo_order_detail` | 2,104 | Order line items |
| `qlo_orders` | 1,934 | Order headers |
| `qlo_customer` | 1,257 | First/last name, email, birthday, phone |
| `qlo_customer_message` / `_thread` | 58 / 55 | Guest correspondence |
| `qlo_address` | 22 | Includes `dni`, `vat_number`, `phone_mobile` |
| `qlo_employee` | 15 | **All `active=1`** — back-office accounts |

**Two things that materially limit the damage:**

1. **No payment card data.** `qlo_order_payment` (2,058 rows) has `card_number`,
   `card_holder`, `card_expiration` and `card_brand` columns, and **all four are empty in
   every row** — the hotel does not store card data, which is consistent with the payment mix
   being overwhelmingly cash and mobile money. So this is **not** a PCI-DSS cardholder-data
   incident.
2. **No API keys and no cookie salt.** `qlo_webservice_account` and `qlo_webservice_permission`
   exist but contain **zero rows**, and the `_COOKIE_KEY_` / `_COOKIE_IV_` values appear
   **nowhere** in the export (they live in `config/settings.inc.php`, which was not included).

**The password hashes are weak but not trivially crackable.** All 1,257 `qlo_customer.passwd`
values and 14 of 15 `qlo_employee.passwd` values are 32-character hex — the MD5-era format.
QloApps computes these as `md5(_COOKIE_KEY_ . $password)`, so they are salted with a
per-install secret that did **not** leak. They are therefore not bare MD5 and cannot be
looked up in a rainbow table. They are still MD5, still fast to brute-force if the salt is
ever recovered, and must be treated as compromised.

**Remediation — repository (executed and verified on this branch):**

- [x] The export commit was **removed from branch history**. The branch was rebuilt from the
      last clean commit and force-pushed, so no commit in the branch range contains the file.
      Verified three ways: `git log HEAD -- <file>` returns nothing; a per-commit
      `git ls-tree -r` scan of every tree in the rewritten range finds no root-level `.sql`
      outside `install-dev/`; and the contents API returns **404** for the file at the branch
      tip. PR #1's head moved to the rewritten commit and remains `OPEN` / `MERGEABLE`, so
      merging will no longer carry the export onto `main`.
- [x] The commit message that documented the `.gitignore` change was **reworded during the
      rewrite** so it no longer names the purged commit. Citing that SHA in a public commit
      message would have handed a reader the exact dangling object to request.
- [x] `.gitignore` now excludes `*.sql` (plus `*.sql.gz`, `*.sql.bz2`, `*.sql.zip`,
      `*.sqlite`, `*.sqlite3`, `*.db`) with negations preserving the schema and upgrade SQL
      that QloApps itself ships under `install-dev/`. A future `git add -A` cannot repeat
      this. Verified against untracked probe files: a dump at the repo root is ignored,
      `install-dev/data/` and `install-dev/upgrade/sql/` are not, and no already-tracked file
      is affected.

**Remediation — still required, and outside what git can fix:**

- [ ] **CRITICAL — the blob is still publicly downloadable.** Rewriting history makes the
      commit unreachable *from the branch*, but GitHub retains unreachable objects until it
      garbage-collects, and it still serves them by SHA. Confirmed with **unauthenticated**
      requests after the force-push — all three returned HTTP **200**:

      | Unauthenticated request | Result |
      |---|---|
      | `GET /repos/iwani1/lushviewhotel-2.0` | 200 |
      | `GET /repos/…/git/blobs/<blob-sha>` | 200 — 16,140,519 bytes served |
      | `GET /repos/…/commits/<purged-commit-sha>` | 200 — commit still resolves |

      So **the data must be treated as disclosed**, and the exposure window is not closed by
      the force-push. Two things are needed:
      1. **Contact GitHub Support** (<https://support.github.com/contact>) to request removal
         of cached views and a purge of the dangling commit and blob. Supply the repository
         name, the purged commit SHA, the blob SHA and the file path — Support needs the exact
         objects, and only the repository owner can file this.
      2. **Make the repository private** (next item). This is the only mitigation that takes
         effect immediately and does not depend on Support.
- [ ] **Make the repository private — owner action required.** The repository is still
      `isPrivate=false`. This was attempted programmatically and **failed with HTTP 403
      "Resource not accessible by integration"**: the automation runs under a GitHub App
      installation token whose `x-oauth-scopes` header is empty, and changing repository
      visibility requires the app's **Administration** permission. Note that the token reports
      `permissions.admin=true` on the repo — that is *not* sufficient; the block is at the
      installation-policy level. It must be done by hand:
      **GitHub → `iwani1/lushviewhotel-2.0` → Settings → Danger Zone → Change repository
      visibility → Private.** The repository has **0 forks, 0 watchers, 0 stars**, so making
      it private detaches nothing and costs nothing. PR #1 and CI are unaffected.
- [ ] Anyone who fetched, cloned, forked or cached the commit during the window may hold a
      copy. The zero fork/watcher/star counts make wide secondary spread unlikely, but
      automated secret-scraping bots do harvest public GitHub repositories, so retrieval
      during the window cannot be ruled out.
- [ ] **Rotate all 15 back-office employee passwords immediately.** All 15 accounts are
      `active=1`. This is the highest-value credential set in the export — it grants access to
      guest PII and the ability to alter bookings.
- [ ] **Force a password reset for the 1,257 customer accounts.**
- [ ] **Assess breach-notification obligations.** Ghana's **Data Protection Act, 2012
      (Act 843)** requires a data controller to notify the Data Protection Commission and
      affected individuals of a security compromise of personal data. The customer base
      includes corporate and international domains, so **GDPR** may also apply if any data
      subjects are in the EU/EEA — in which case the 72-hour supervisory-authority clock
      starts from awareness. **This is a legal question for the client and their counsel, not
      an engineering one**; flag it now rather than after the upgrade.
- [ ] **Check access logs** for the repository and for any GitHub traffic API signals
      available to the client, to gauge whether the file was actually retrieved.

**Facts worth keeping from the export.** Before it was purged, the following was extracted and
is recorded elsewhere in this audit, because it is operationally useful and contains no
personal data: the host runs **PHP 8.4.27** and **MariaDB 11.4.13** (§2.1, §2.7); the
installed versions are **`PS_VERSION_DB` 1.6.1.23 / `QLO_VERSION_DB` 1.7.0.0** (§2.1); the
inventory is **8 room types / 38 rooms / 56 enabled modules** (§2.6); and
**`qlohotelreview` is `active=1`** (§2.2). The payment mix recorded in `qlo_order_payment`
is dominated by **cash and mobile money**, with card fields empty — relevant context if the
deferred payment-gateway work (§2.8) is ever reinstated.

---

## 5. Install blocker: `ext-mcrypt`

**`composer.json` declares `"ext-mcrypt": "*"`.** mcrypt was removed from PHP core in 7.2
and is not present on any PHP 8.2–8.5 build. On a normal host, `composer install` — the very
first step `AGENTS.md` instructs — **fails** with *"requires ext-mcrypt \* → it is missing
from your system"*.

This requirement is spurious. Evidence:

- Only **13** `mcrypt_` call sites exist in the entire tree, across 5 files
  (`PhpEncryptionEngine.php`, `PhpEncryptionLegacyEngine.php`, `install-dev/models/install.php`,
  and two TCPDF files).
- All are guarded or fallback-only. `install-dev/models/install.php:506` explicitly does
  `function_exists('mcrypt_encrypt') ? 1 : 0` — the app is *designed* to run without it.
- OpenSSL is used on the primary path (11 `openssl_encrypt`/`openssl_decrypt` sites).

**Fix: remove `ext-mcrypt` from `composer.json`.** Until then, `composer install` needs
`--ignore-platform-req=ext-mcrypt`.

**Also note:** `composer.json` declares **zero package dependencies** — every library is
vendored under `tools/` (smarty, tcpdf, htmlpurifier, defuse, random_compat, geoip, mailer,
mobile_detect, parser_sql, tar, js_minify, minify_html, pear, profiling). Composer is
therefore only generating an autoloader here, not resolving a dependency graph. There is no
`composer.lock` (gitignored), which is consistent with that. Practical upshot: **the
`ext-mcrypt` constraint buys you nothing and costs you a broken first-run experience.**

---

## 6. Ghana / Lush View Hotel localisation readiness

This is where the real project work lives.

### 6.1 What already works

| Item | Status | Detail |
|---|---|---|
| Ghana as a country | ✅ Present | `<country id="GH" id_zone="Africa" iso_code="GH" call_prefix="233" active="1" contains_states="0" need_identification_number="0" need_zip_code="1" zip_code_format="" display_tax_label="1"/>` — one of 244 seeded countries; the Africa zone carries 56 |
| Timezone | ✅ Present | `Africa/Accra` is in `install-dev/data/xml/timezone.xml` (560 entries, 52 African) |
| Tourism tax | ✅ Available | v1.8.0 ships a per-hotel configurable tourism-tax system with rate tiers, child pricing and exemptions (CHANGELOG #1760). Maps well onto Ghana's tourism levy |
| Channel distribution | ✅ Available | `qlocmconnector` (Channel Manager & API Connector, replaces the older `qlochannelmanagerconnector`) and `qlogooglehotelconnector` for Google Hotels |
| Offline payment | ✅ Available | `bankwire` 1.1.7, `cheque` 2.6.8 |

### 6.2 What is missing

| Gap | Severity | Detail |
|---|---|---|
| **No mobile-money gateway** | **Critical** | Bundled payment modules are bankwire, cheque, `qloduitkupayment` 4.0.0 (**Indonesia**), `qlopaypalcommerce` 1.0.6. A full-tree grep for `paystack`, `flutterwave`, `hubtel`, `mobile money`, `momo`, `mpesa` returns **zero hits**. In Ghana, MoMo is the dominant rail; card penetration is low. Without it, the booking engine cannot take the payments most guests will offer |
| **No `localization/gh.xml`** | **DONE** | Upstream v1.8.0 ships 82 country packs (plus `default.xml`) — including Nigeria (`ng.xml`), Kenya (`ke.xml`) and Egypt (`eg.xml`) — but **not Ghana**. **Added on this branch** (commit `c25596c`), bringing the total to 83. See §6.3 |
| **GHS currency absent** | **DONE** | `GHS` occurred **0 times** in `localization/` and install data, and there is no `currency.xml` seed file at all — currencies only enter via a `LocalizationPack` import (`classes/LocalizationPack.php:282-317`) or manual back-office creation. **Resolved** by the `gh.xml` pack, which declares GHS (ISO 4217 numeric 936, sign `₵` U+20B5, 2 decimals, `format="1"` + `blank="1"` → `₵ 1,234.56`) |
| **Ghana regions not seeded** | Medium | `state.xml` contains 312 states, **none** for Ghana. Compounding this, the GH country row is flagged `contains_states="0"` — so the 16 Ghanaian regions will not even be collected as states unless that flag is flipped and rows added |
| **Mandatory postcode with no format** | Medium | Ghana's GH row has `zip_code_format=""` **and** `need_zip_code="1"`. Verified in code, this does **not** reject Ghanaian addresses — but it does make the postcode field **mandatory**. See the corrected analysis below |
| **No Ghana address format** | Low | `address_format.xml` has no GH entry; the default will be used. Verify it renders sensibly for Ghanaian addresses |

#### Corrected: what `zip_code_format=""` + `need_zip_code="1"` actually does

An earlier draft of this audit claimed Ghana's empty `zip_code_format` combined with
`need_zip_code="1"` would **reject guests at checkout**. Reading the validation path shows
that is **wrong**, and the real behaviour is the opposite of a format rejection:

- `Country::checkZipCode()` (`classes/Country.php:376-378`) returns **`true` immediately**
  when `zip_code_format` is empty — no format is enforced at all.
- `Address.php:573` guards the check with `$objCountry->zip_code_format && ...`, so with an
  empty format the branch is skipped entirely.
- `Validate::isPostCode()` (`classes/Validate.php:644-647`) accepts `/^[a-zA-Z 0-9-]+$/`,
  which comfortably matches a GhanaPostGPS code such as `GA-183-6412` (verified) and legacy
  numeric forms like `00233`.

So **Ghanaian addresses are accepted.** The genuine consequence is narrower: `Address.php:575`
rejects an **empty** postcode because `need_zip_code="1"`, so the field is **mandatory** —
guests who do not know their GhanaPostGPS code are forced to enter something. That is a
conversion risk at checkout, not a validation failure.

Two related findings worth knowing:

- **Silent placeholder generation.** `Tools::generateRandomZipcode()` (`classes/Tools.php:84-114`)
  falls back to **five random digits** when `need_zip_code` is set but `zip_code_format` is
  empty. It is called by `HotelHelper.php:2020` for hotel addresses and by
  `qlocmconnector`'s `WebserviceSpecificManagementCmApi.php:4749,4752` for guest details. So
  channel-manager bookings and hotel records can acquire **fabricated postcodes** that look
  legitimate. If postcode data matters for reporting or tax jurisdiction, this needs
  attention.
- **Adding a format would make things worse.** Setting `zip_code_format="LNN-NNN-NNNN"` to
  match GhanaPostGPS would start *rejecting* the many guests who enter a legacy code, a
  street name, or nothing meaningful. The current empty format is more permissive and is
  probably the right default.

**Recommendation (revised):** leave `zip_code_format` empty. Decide `need_zip_code` on
conversion grounds — set it to `0` if forcing a postcode is costing bookings, otherwise leave
it at `1`. Either way, do not add a GhanaPostGPS format mask.

### 6.3 Recommended localisation sequence

1. Build `localization/gh.xml` declaring **GHS** (ISO 4217 numeric `936`, symbol `GH₵`,
   2 decimals), Ghana tax rules, and the address format.
2. Flip GH to `contains_states="1"` and seed the 16 regions into `state.xml` **only if** you
   actually want region-level selection — otherwise leave it off and treat region as free
   text. Don't add UI friction for no benefit.
3. Decide `need_zip_code` on conversion grounds and **leave `zip_code_format` empty**
   (§6.2, corrected analysis). Do not add a GhanaPostGPS format mask — it would start
   rejecting guests the current permissive behaviour accepts.
4. **Integrate a mobile-money gateway.** Paystack and Flutterwave both support Ghana MoMo and
   both have PrestaShop-lineage module patterns to crib from. QloApps'
   `AGENTS.md` references reusable **payment-module-development** skills
   (`npx skills add Qloapps/agent-skills`) — use those rather than hand-rolling the hook
   wiring. Budget this as the largest single work item.
5. Configure the tourism-tax system for Ghana's levy rates at hotel level.
6. Set `Africa/Accra` timezone and Ghana as the default country during install.

### 6.4 Upstream defects surfaced by the new CI check

The localization validator added in `.github/workflows/ci.yml` found **37 genuine defects in
QloApps' own shipped packs**. None affect Ghana, and all are baselined so CI is green, but
they are worth reporting upstream because each one causes **silent data loss on import** —
`classes/LocalizationPack.php` hits `continue` and the pack imports "successfully" while
quietly dropping rows.

**`localization/cz.xml` — 29 dropped tax rules.** The group `CZ Snížená sazba (12%)` points
all 29 of its `taxRule` entries at `id_tax="32"`, but the pack only declares ids **1–30**.
`id=2` is `DPH CZ 12%` — the only 12% Czech rate — so `32` looks like a renumbering slip.
Effect at `LocalizationPack.php:238-240`: the group imports **completely empty**, so a Czech
merchant selecting the reduced rate gets **no tax applied at all**.

**`localization/hr.xml` and `localization/se.xml` — 8 dropped tax rules.** Both target
`iso_code_country="uk"`, but `install-dev/data/xml/country.xml` seeds the United Kingdom as
**`GB`** (correct ISO 3166-1 alpha-2). `Country::getByIso('UK')` returns 0, so
`LocalizationPack.php:234-235` drops the rules. No other pack uses `"uk"`, which suggests
these two were missed when the seed data standardised on `GB` — most likely a post-Brexit
cleanup that didn't reach every file.

Neither is fixed here: both are out of scope for Lush View Hotel, and editing unrelated
country packs would create merge friction on the next upstream sync. They are recorded in the
workflow's `KNOWN_UPSTREAM` baseline, which fails CI if the list ever needs to grow.

**Relevance to this project:** the same two failure modes apply directly to `gh.xml`. A
typo'd `iso_code_country` or a dangling `id_tax` would silently produce a Ghana pack with no
working tax rules — and the site would appear to import fine. `gh.xml` was written against
this contract and is verified by CI: `iso_code_country="gh"` resolves against the seed data,
and all five tax ids resolve within the pack.

---

## 7. Branding surface area — smaller than the raw numbers suggest

A naive grep finds **"QloApps" 3,152 times across 1,202 files.** That number is misleading.
Breaking it down by location:

| Location | Occurrences | Guest-visible? |
|---|---|---|
| `.php` licence / docblock headers | 2,598 | No — legal boilerplate, **must not be touched** |
| `install-dev/` | 1,792 | No — deleted after install anyway |
| `modules/` | 1,045 | Mostly no — internal identifiers, class names, hooks |
| `admin-dev/` (back office) | 216 | No — staff-facing only |
| `tools/` (vendored libs) | 9 | No |
| **`themes/` (guest-facing)** | **42** | **Yes** |

Those 42 reduce to almost nothing:

- `themes/hotel-reservation-theme/header.tpl:40` — `<meta name="generator" content="QloApps {{ _QLO_VERSION_ }}" />`
- `themes/hotel-reservation-theme/header.tpl:80` — `<!-- Built with QloApps -->`
- The remainder are **JavaScript event namespaces**: `QloApps:updateRoomOccupancy`,
  `QloApps:updateRoomQuantity`, `QloApps:afterBookingFormInit` — bound in
  `js/product.js:1043,1047,1358` and triggered from `js/occupancy.js:225,332,346`.

> ⚠️ **Do not rename the JS event namespaces.** They are an internal contract between
> `occupancy.js` and `product.js`. A blind `sed s/QloApps/LushView/` across the theme
> silently breaks room-occupancy and booking-form initialisation — a bug that will not
> surface until a guest tries to change room counts.

Also clean already:

- **Mail templates: 0 hardcoded occurrences.** `mails/` is driven by shop-name variables.
- **PDF / invoices: clean.** `pdf/supply-order-header.tpl:40` and
  `pdf/supply-order.addresses-tab.tpl:28` use `{$shop_name}` — they will pick up your
  configured name automatically.

### Actual rebrand worklist

1. Set `PS_SHOP_NAME` / `PS_SHOP_EMAIL` at install or in the back office — this propagates
   to mails, PDFs, and invoices with no code change.
2. Replace **6 logo assets**: `img/logo.jpg`, `img/logo_invoice.jpg`, `img/logo_mail.jpg`,
   `img/qloapps@2x.png`, `img/qloapps-login@2x.png`, `img/qloapps-login-wink@2x.png`.
3. Edit **2 lines** in `themes/hotel-reservation-theme/header.tpl` (generator meta +
   comment) — or leave the attribution, which OSL-3.0 may in any case require you to retain.
   Check the licence before stripping it.
4. Upload hotel photography and set the header media (v1.8.0 added dynamic homepage
   header image-slider/video display, CHANGELOG #1764).

**Total guest-facing code edits: ~2 lines plus image swaps.** Everything else is
configuration.

---

## 8. Quality, testing, and CI gaps

| Finding | Impact |
|---|---|
| **No test suite.** `tests/` contains only `bootstrap.php`, `index.php`, and `phpunit.xml` — **zero test cases** despite the PHPUnit harness and a `tests/composer.json` | No automated safety net for any customisation. Regressions in the booking flow will only be caught manually. This is the biggest maintainability risk for a customised fork |
| **CI is dead.** `.travis.yml` targets PHP 8.2/8.3/8.4/8.5 but pins `dist: trusty` (Ubuntu 14.04, EOL 2019), and Travis CI `.org` has been retired | No CI signal at all. There is no GitHub Actions workflow to replace it |
| **Legacy architecture.** PrestaShop 1.6.1.23 lineage, Smarty 3, jQuery | Customisation follows QloApps conventions (overrides in `override/`, module hooks) rather than modern framework patterns. `AGENTS.md` documents these well — read it before contributing |
| **`vendor/` and `composer.lock` gitignored** | Reproducible builds depend on the bundled `tools/` libs, which *are* committed. Acceptable, but means dependency updates are manual vendor drops |

**Recommendation:** add a minimal GitHub Actions workflow (PHP 8.3 matrix, `php -l` syntax
lint across the tree, and a headless install smoke test) before starting customisation. It
is cheap now and expensive to retrofit after the fork has diverged.

---

## 9. Prioritised action plan

Revised after the live-site discovery (§2). Payment gateway work is **deferred** at the
client's direction (§2.8) and is listed separately at the end so it can be reinstated without
re-planning.

Items marked **DONE** are already committed on this branch.

### P0 — production is exposed; act before any feature work

1. **Do NOT file-copy this tree over the live install.** There is no 1.7.0 → 1.8.0 database
   migration in it, and the module version bumps silently with no schema change and no error
   (§2.9). This is the fastest way to take the booking engine down.
2. **Disable the `qlohotelreview` module on the live 1.7.0 site.** It carries
   CVE-2025-67325 (unauthenticated file upload → RCE) and the fix is absent from v1.7.0
   (§2.2). Re-enable after the upgrade.
3. **Block public access to `CHANGELOG.txt` and `composer.json`** on the live site (§2.3).
   Also `README.md`, `AGENTS.md`, `LICENSE.md`. Five-minute change; stops version disclosure.
4. **Add a WAF rule on `bo_query`** and drop `FILE`/`DROP`/cross-database rights from the
   MySQL user (§2.2) — mitigates CVE-2026-75497/75498 until the upgrade lands.
5. ~~**Confirm the host PHP version.**~~ **RESOLVED** — the production database records the
   host as **PHP 8.4.27** on **MariaDB 11.4.13**, inside 1.8.0's supported `>=8.2 <8.6` range
   (§2.1, §2.7). The stale live manifest (`>=5.4 <8.0`) was the only thing suggesting
   otherwise, so **no PHP upgrade is required before 1.8.0 can boot**. What remains is
   confirming the other required extensions (`intl`, `gd`, `openssl`, `mbstring`, `zip`,
   `curl`) — note that `ext-mcrypt` is deliberately **not** among them (§5).
6. **Back up the live database and all uploaded media** (`img/`, `modules/*/views/img/`,
   `upload/`, `download/`) before touching anything (§2.7). Media under module paths can be
   overwritten by an upgrade.
7. **Treat the database export committed to this branch as disclosed** (§4.4). The file has
   been purged from branch history and `.gitignore` now blocks a repeat, but that does not
   undo the exposure window. Remaining actions: ask GitHub Support to purge the dangling
   object, **rotate all 15 back-office employee passwords** (all `active=1`), force a reset
   for the 1,257 customer accounts, and get a legal read on breach notification under
   Ghana's Data Protection Act 2012 (Act 843) — plus GDPR if any EU data subjects are
   involved. No card data leaked (card columns are empty) and no API keys or cookie salt
   leaked, which limits the blast radius.

### P1 — the upgrade itself

8. **Choose the migration path** (§2.9). Either (A) run the QloApps **1-Click Upgrade addon**
   from the *existing* 1.7.0 back office — the only supported vehicle, and not vendored in
   this repo — or (B) fresh 1.8.0 install plus export/import of hotels, room types, customers
   and bookings. Blocking decision; everything below depends on it.
9. **Account for the directory rename** `install/` → `install-dev/` and `admin/` →
   `admin-dev/` between 1.7.0 and 1.8.0 (§2.9). Update deploy scripts, `.htaccess` rules,
   cron entries and any hardcoded `_PS_ADMIN_DIR_` paths.
10. **Plan the module delta, 57 → 61 bundled** (§2.9). Removed: `qloautoupgrade`,
   `qlochannelmanagerconnector`. Added: `qlocmconnector`, `qlocrontaskmanager`,
   `qloduitkupayment`, `qlogooglehotelconnector`, `qlohotelreports`, `qlositemap`. The
   channel-manager swap is breaking and needs config/data migration if in use (§2.7).
11. **DONE — remove `ext-mcrypt` from `composer.json`** (§5). Committed `f1f0749`; unblocks
    `composer install` on any supported PHP.
12. **DONE — create GHS currency and `localization/gh.xml`** (§6.2). Committed `c25596c` with
    Act 1151 tax rules (VAT 15% + NHIL 2.5% + GETFund 2.5% = 20%, `behavior="1"` combine, not
    compound). **Rates still need sign-off from the client's accountant.**
13. **Decide `need_zip_code` only** — leave `zip_code_format` empty (§6.2, corrected
    analysis). The earlier claim that an empty format rejects guests was wrong:
    `Country::checkZipCode()` returns `true` when the format is empty, and
    `Validate::isPostCode()` already accepts GhanaPostGPS codes like `GA-183-6412`. The real
    issue is that `need_zip_code="1"` makes the field **mandatory**, which is a conversion
    risk. Also note `Tools::generateRandomZipcode()` fabricates five random digits for Ghana
    in hotel and channel-manager records.
14. **Set timezone `Africa/Accra`, country Ghana**, shop name and shop email (§6, §7).
15. **Configure the tourism-tax system** for Ghana's 1% GTA Tourism Development Levy via
    `QLO_USE_TOURISM_TAX` + `TaxConfiguration` — new in 1.8.0 (#1760). Prefer this over the
    flat-rate group in `gh.xml` (§6.1, §6.3). Note these tables are among those missing from
    a 1.7.0 database (§2.9).

### P2 — launch hardening and content

16. Rename `admin-dev/`; delete `install-dev/`; verify the generated root `.htaccess` (§4.3).
17. Swap the **6 logo assets** and edit the **2 guest-facing template lines** (§7).
18. Fix the live-site content defects (§2.5): page-title typo `Lush VIew`, `Dummy Image 1–12`
    alt text, duplicated Luxurious Rooms / Restaurants / Swimming Pool blocks, truncated copy,
    domain name inside the address field, `2010` copyright start.
19. Resolve the **`.com` vs `.net` domain question** and the Tema/Ashaiman address conflict
    (§2.6) — canonical data must be settled before content is migrated.
20. Inventory room types, rates, availability and existing bookings from the back office or
    database; the public site does not expose them (§2.6).

### P3 — durability

21. **DONE — add `|escape` to `search_form.tpl:85`** for defence in depth (§4.2). Committed `3587135`; removes the single-point-of-failure dependency on `AdminController.php:2267` pre-sanitising the value.
22. Stand up GitHub Actions CI with a syntax-lint + install smoke test (§8).
23. Write tests around the booking and payment flows before customising (§8).
24. Decide the Ghana regions question (`contains_states` flag + `state.xml` rows) (§6.2).
25. Legal read on OSL-3.0 obligations, especially the attribution footer (§3, §7).

### Deferred — payment gateway (reinstated on request)

- **Integrate a mobile-money gateway** — Paystack or Flutterwave (§6.2). Currently bundled:
  bankwire 1.1.7, cheque 2.6.8, `qloduitkupayment` 4.0.0 (Indonesia), `qlopaypalcommerce`
  1.0.6. Zero hits tree-wide for Paystack, Flutterwave, Hubtel, MoMo or M-Pesa. This remains
  the largest functional gap for a Ghanaian property and should be reinstated before the site
  is expected to take direct online revenue.

---

## 10. Suggested customisation strategy

Stay on the **override + module** rails rather than editing core:

- Class/controller changes → `override/` (remember to clear `cache/class_index.php`
  afterwards, per `AGENTS.md`).
- New behaviour → a custom module. Write it under **AFL-3.0** so it is cleanly yours.
- Theme changes → child theme or careful `themes/hotel-reservation-theme` edits.
- Clear caches after template work: `rm -rf cache/smarty/compile/* cache/smarty/cache/*`.

Editing core files directly will make every future QloApps upgrade a manual three-way merge.
Since v1.8.0 shipped *today*, upstream is actively moving — preserving an upgrade path is
worth real effort.

**Upstream tracking:** record `e502b63f` as the baseline. Keep customisations in
`override/` and `modules/` so that pulling a future `v1.8.x` is a clean operation.
