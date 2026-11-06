# Lush View Hotel — QloApps Application Audit

**Repository:** `iwani1/lushviewhotel-2.0`
**Audited tree:** QloApps `develop` snapshot @ `e502b63f`, imported 2026-11-06
**Audit date:** 2026-11-06
**Method:** static code + configuration analysis. No PHP runtime is available in the audit
sandbox, so the installer was **not** booted and no dynamic/lint pass was run. Findings
below are from source inspection, dependency metadata, and upstream CVE correlation.

---

## 1. Executive summary

The imported tree is **healthy, current, and patched**. It is not a moving development
branch: GitHub reports `v1.8.0...develop` as `identical` (0 commits ahead, 0 behind, 0 files
changed), so this snapshot *is* the stable **QloApps 1.8.0** release, published 2026-11-06
08:53 UTC — the same day as the import. Every QloApps CVE published in the last 12 months
that I could locate is fixed in this tree, including the RCE-class file-upload flaw.

The work required for Lush View Hotel is therefore **not remediation, it is localisation**.
Three things stand between this codebase and a production Ghanaian hotel booking engine:

1. **No mobile-money payment path.** Zero African gateways are bundled. For a hotel in
   Accra this is the single largest functional gap — guests will predominantly pay by
   MTN MoMo / Telecel Cash / AT Money.
2. **No Ghana localisation pack, and no GHS currency anywhere in the codebase.**
   The country row exists; the currency, region data, and tax wiring do not.
3. **One hard install blocker:** `composer.json` requires `ext-mcrypt`, an extension
   removed from PHP core in 7.2 and absent on every PHP version this release supports.

Branding is far cheaper than the raw string count suggests (see §6) — **do not** bulk-rename.

---

## 2. Identity and provenance

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

## 3. Security audit

### 3.1 Published CVEs — all verified patched in this snapshot

| CVE | Class | CVSS | Status here | Evidence |
|---|---|---|---|---|
| CVE-2026-75498 | SQLi, `bo_query` in `Address.php` | 7.2 / 8.6 (v4) | **Patched** | Fix `123c97c` is an ancestor of our commit (`ahead_by=1091`). `bo_query` is sanitised via `Tools::safeOutput()` at `classes/controller/AdminController.php:2267` |
| CVE-2026-75497 | SQLi, `bo_query` in `CustomerMessage.php` | 7.2 | **Patched** | Same fix commit, same sanitisation path |
| CVE-2026-89268 | Reflected XSS, list filter POST params | 5.4 | **Patched** | Fix `153ec1c` is an ancestor. `\|escape:'html':'UTF-8'` confirmed at `list_header.tpl:254, 282, 303, 304` |
| CVE-2026-103589 | Reflected XSS, room type editor (`room_num`, `floor`, `comment`) | 5.4 | **Patched** | Escaping confirmed at `products/configuration.tpl:98, 101, 111, 123`. (Upstream fix commits `7ed467d`/`1d06fd3` read as `diverged` — they were squash-merged, so SHA ancestry is inconclusive; **the code itself is correct**) |
| CVE-2026-103588 | Reflected XSS, `exceptions` field, Transplant-a-module | 5.4 | **Patched** | `AdminModulesPositionsController.php:94-106, 133-149` validates each value and rejects invalid input before persistence |
| CVE-2025-67325 | **Unrestricted file upload → RCE**, hotel review (≤1.7.0) | High | **Patched** | `QhrHotelReview::saveReviewImages()` applies an explicit `['jpg','png','jpeg']` allowlist with strict `in_array(..., true)`, and writes through `ImageManager::resize()` — which re-encodes the image and strips any embedded payload rather than moving raw bytes |

**Conclusion: no known-vulnerable code in this tree.** The security work is deployment
hygiene (§3.3), not patching.

### 3.2 Residual code-level observations

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

### 3.3 Deployment hardening checklist (pre-launch, blocking)

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

## 4. Install blocker: `ext-mcrypt`

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

## 5. Ghana / Lush View Hotel localisation readiness

This is where the real project work lives.

### 5.1 What already works

| Item | Status | Detail |
|---|---|---|
| Ghana as a country | ✅ Present | `<country id="GH" id_zone="Africa" iso_code="GH" call_prefix="233" active="1" contains_states="0" need_identification_number="0" need_zip_code="1" zip_code_format="" display_tax_label="1"/>` — one of 244 seeded countries; the Africa zone carries 56 |
| Timezone | ✅ Present | `Africa/Accra` is in `install-dev/data/xml/timezone.xml` (560 entries, 52 African) |
| Tourism tax | ✅ Available | v1.8.0 ships a per-hotel configurable tourism-tax system with rate tiers, child pricing and exemptions (CHANGELOG #1760). Maps well onto Ghana's tourism levy |
| Channel distribution | ✅ Available | `qlocmconnector` (Channel Manager & API Connector, replaces the older `qlochannelmanagerconnector`) and `qlogooglehotelconnector` for Google Hotels |
| Offline payment | ✅ Available | `bankwire` 1.1.7, `cheque` 2.6.8 |

### 5.2 What is missing

| Gap | Severity | Detail |
|---|---|---|
| **No mobile-money gateway** | **Critical** | Bundled payment modules are bankwire, cheque, `qloduitkupayment` 4.0.0 (**Indonesia**), `qlopaypalcommerce` 1.0.6. A full-tree grep for `paystack`, `flutterwave`, `hubtel`, `mobile money`, `momo`, `mpesa` returns **zero hits**. In Ghana, MoMo is the dominant rail; card penetration is low. Without it, the booking engine cannot take the payments most guests will offer |
| **No `localization/gh.xml`** | High | 78 country packs are shipped — including Nigeria (`ng.xml`), Kenya (`ke.xml`), Egypt (`eg.xml`) — but **not Ghana** |
| **GHS currency absent** | High | `GHS` occurs **0 times** in `localization/` and install data. There is no `currency.xml` seed file at all; currencies only enter the system via a `LocalizationPack` import (`classes/LocalizationPack.php:286-309`) or manual creation in the back office. So GHS must be created by hand or by building a `gh.xml` pack |
| **Ghana regions not seeded** | Medium | `state.xml` contains 312 states, **none** for Ghana. Compounding this, the GH country row is flagged `contains_states="0"` — so the 16 Ghanaian regions will not even be collected as states unless that flag is flipped and rows added |
| **Empty `zip_code_format`** | Medium | Ghana's GH row has `zip_code_format=""` while `need_zip_code="1"`. Ghana uses GhanaPostGPS digital addresses (e.g. `GA-183-6412`), not classic postal codes. Needs a deliberate decision: either supply a `LNN-NNN-NNNN`-style format or set `need_zip_code="0"` to avoid rejecting guests at checkout |
| **No Ghana address format** | Low | `address_format.xml` has no GH entry; the default will be used. Verify it renders sensibly for Ghanaian addresses |

### 5.3 Recommended localisation sequence

1. Build `localization/gh.xml` declaring **GHS** (ISO 4217 numeric `936`, symbol `GH₵`,
   2 decimals), Ghana tax rules, and the address format.
2. Flip GH to `contains_states="1"` and seed the 16 regions into `state.xml` **only if** you
   actually want region-level selection — otherwise leave it off and treat region as free
   text. Don't add UI friction for no benefit.
3. Decide the postal-code policy explicitly (§5.2, row 5).
4. **Integrate a mobile-money gateway.** Paystack and Flutterwave both support Ghana MoMo and
   both have PrestaShop-lineage module patterns to crib from. QloApps'
   `AGENTS.md` references reusable **payment-module-development** skills
   (`npx skills add Qloapps/agent-skills`) — use those rather than hand-rolling the hook
   wiring. Budget this as the largest single work item.
5. Configure the tourism-tax system for Ghana's levy rates at hotel level.
6. Set `Africa/Accra` timezone and Ghana as the default country during install.

---

## 6. Branding surface area — smaller than the raw numbers suggest

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

## 7. Quality, testing, and CI gaps

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

## 8. Prioritised action plan

### P0 — blocks installation or revenue

1. **Remove `ext-mcrypt` from `composer.json`** (§4). One-line fix, unblocks `composer install`.
2. **Integrate a mobile-money gateway** — Paystack or Flutterwave (§5.2). Largest work item;
   without it the engine cannot collect most guest payments.
3. **Create GHS currency + `localization/gh.xml`** (§5.2). Cannot transact in Ghana without it.

### P1 — required before going live

4. Rename `admin-dev/`; delete `install-dev/`; verify generated `.htaccess` (§3.3).
5. Decide the Ghana postal-code policy and set `zip_code_format` / `need_zip_code` (§5.2).
6. Configure tourism tax for Ghana's levy (§5.1).
7. Set timezone `Africa/Accra`, country Ghana, shop name/email (§6).
8. Swap the 6 logo assets; edit the 2 guest-facing template lines (§6).

### P2 — durability

9. Add `|escape` to `search_form.tpl:85` for defence in depth (§3.2).
10. Stand up GitHub Actions CI with a syntax-lint + install smoke test (§7).
11. Write tests around the booking and payment flows before customising (§7).
12. Decide on the Ghana regions question (`contains_states` flag) (§5.2).
13. Legal read on OSL-3.0 obligations for the attribution footer (§2, §6).

---

## 9. Suggested customisation strategy

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
