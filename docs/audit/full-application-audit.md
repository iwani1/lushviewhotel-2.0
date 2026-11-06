# Full Application Audit — QloApps 1.8.0 tree + live 1.7.0 data layer

**Date:** 2026-11-06 · **Branch:** `arena/07df51c3-lushviewhotel-2-0` · **Commit:** `f4cc3e9`+
**Scope:** the entire application — 2,121 first-party PHP files, 713 Smarty templates, 61 modules,
1 theme, plus the live production data layer (300 tables).
**Method:** static analysis of every PHP and Smarty file (vendored `tools/` excluded), followed by
manual verification of **every** lead against the actual source. No finding below is reported from
a pattern match alone.

**Companion documents:**
- `lush-view-hotel-audit.md` — platform/version provenance, CVE status, Ghana localisation, branding surface, action plan
- `../project/01-phase1-technical-assessment.md` — schema and module comparison, live data inventory
- `../project/02-architecture-and-branding.md` — target architecture, branding design, licence constraints
- `../project/03-migration-risks-and-plan.md` — migration design, risk register, phased plan
- `../deploy/live-site-hardening.md` — deployable Apache/nginx/WAF/SQL hardening rules

---

## 1. Summary scorecard

| Area | Verdict |
|---|---|
| Known CVEs patched in this tree | ✅ **All verified patched** by reading the fixed code |
| Password storage design | ⚠️ **Strong design, blocked by schema** — bcrypt primary with transparent legacy rehash on login, but the live `passwd` columns are `varchar(32)` and bcrypt needs 60. The rehash silently fails (or truncates and locks the account out) until migration 001 widens them. See §5.1 |
| Debug/error exposure | ✅ **Safe** — `display_errors` correctly gated behind `_PS_MODE_DEV_` (default false) |
| Committed credentials | ✅ **None** — `config/settings.inc.php` absent and gitignored |
| Shared-hosting compatibility | ✅ **Good** — every `exec`/`shell_exec` path degrades gracefully |
| Template output escaping | ⚠️ **Systemic weakness** — auto-escape off, 5,323 bare output tags |
| Third-party upload surface | ⚠️ **Bundled file manager with a static session gate** |
| Token/randomness quality | ⚠️ **8 sites use `md5(uniqid(rand()))`** for security-relevant keys |
| Channel-manager customer creation | ⚠️ **Constant password + a real logic bug** (latent, not exercised) |
| Dead code on PHP 8 | ⚠️ **One unreachable file would fatal** (`mysql_*` removed in PHP 7) |
| Licence hygiene | ⚠️ **141 files carry no licence header** |
| Test coverage | ❌ **Zero test cases** — `tests/` holds bootstrap and config only |

**Overall:** the 1.8.0 codebase is a sound foundation. Nothing found here is an unpatched
exploit in the shipped tree; the genuine issues are a systemic escaping convention, one bundled
third-party component, weak token randomness, and one latent logic bug in a channel-manager path.
The most urgent risks in this project remain **operational** (the live 1.7.0 site and the data
exposure), not code defects in 1.8.0 — see §12.

---

## 2. Codebase inventory

| Metric | Value |
|---|---|
| Tracked files | 8,322 |
| PHP files (all / first-party) | 3,290 / **2,121** |
| Smarty templates | 713 |
| JavaScript files (non-minified) | 532 |
| Modules | 61 |
| Themes | 1 (`hotel-reservation-theme`) |
| Licence headers | OSL-3.0 **1,903** · AFL-3.0 **790** · none **141** |
| `TODO` / `FIXME` / `XXX` / `HACK` markers | 36 |
| Test cases | **0** |

---

## 3. Security findings

Severity reflects this deployment (a single-hotel public booking site on shared hosting), not a
generic CVSS score.

### G1 — Channel-manager customers get a constant, unhashed password · **Medium** (latent)

`modules/hotelreservationsystem/classes/HotelBookingDetail.php:3103-3128`

```php
$customeremail = $firstName.$lastName.'@'.$params['channel_name'].'.com';   // 3109 - synthetic, guessable
$objCustomer->passwd = 'qloChannelCustomer';                                // 3118 - constant literal
$objCustomer->save();                                                       // 3119
$context->customer = $objCustomer;                                          // 3120 - $context UNDEFINED
```

Two distinct defects in one method:

**(a) The password is a known constant and is stored without hashing.** `ObjectModel` only hashes
the `passwd` field inside `validateController()` (`classes/ObjectModel.php:1189-1193`), which reads
from `Tools::getValue()` — i.e. from a submitted form. Neither `Customer::add()`
(`classes/Customer.php:245-252`, which sets `secure_key` and `last_passwd_gen`) nor
`ObjectModel::add()`/`save()` hashes anything. So `save()` here persists the literal string.
Anyone who reads this source knows the password of every account it creates, and the email address
is synthesised from the guest's name plus the channel name.

**(b) `$context` is never defined in this method.** There is no `$context = Context::getContext();`
anywhere in lines 3103-3128. The assignment at 3120 therefore cannot affect the request's real
context singleton — the intended side effect silently does not happen, and under PHP 8.x it is at
best a write to an auto-vivified local `stdClass`. This is a correctness bug independent of the
password issue.

**Reachability.** The method is called from `HotelBookingDetail.php:3066`, and the live site runs
`qlochannelmanagerconnector` with `active=1`, so the path is live-capable.

**Was it exercised? No.** All **1,257** live customer rows were parsed across all 12 INSERT
statements: **1,257 are 32-hex MD5-era hashes, zero are plaintext**, and zero equal
`qloChannelCustomer`. So this is a **latent** defect on this installation, not an active breach.

**Recommendation.** Do not patch core. If the channel-manager path is used after the upgrade,
override the method (or hook the customer-creation event) to generate a random unusable password
via `Tools::passwdGen()` / `random_bytes()` and hash it with `PasswordHashing::passwordHash()`,
and drop the `$context` line or define `$context` properly. If no channel manager is used,
confirm that and leave it.

### G2 — Security-relevant tokens use non-cryptographic randomness · **Medium**

Eight sites build keys from `md5(uniqid(rand(), true))`. `uniqid()` is derived from microtime and
`rand()` is not a CSPRNG, so the output is guessable by an attacker who can approximate the
request time.

| Location | What it protects |
|---|---|
| `classes/Customer.php:251` | `$this->secure_key` — set on **every** customer creation |
| `classes/Cart.php:4497` | `$customized_value` for cart product personalisation |
| `classes/webservice/WebserviceSpecificManagementImages.php:1240` | uploaded image filename |
| `controllers/admin/AdminCartsController.php:555` | uploaded file name |

`secure_key` is the significant one: it propagates from `Customer` to `Cart`
(`classes/Cart.php:209-210`, `:4412`; `classes/Context.php:309`, `:320`) and is used to authorise
guest cart access, so a predictable value weakens guest checkout integrity. The field is defined
as 32 chars (`classes/Cart.php:125`).

**Recommendation.** Replace with `bin2hex(random_bytes(16))` via an `override/` of
`Customer::add()`. Note this is an upstream weakness, so it should also be reported upstream;
fixing it locally in an override keeps us upgrade-safe.

### G3 — Smarty auto-escaping is disabled; 5,323 unescaped output tags · **High as a work queue**

`$smarty->escape_html` is **read** once (`config/smarty.config.inc.php:80`) and **never set**
anywhere in first-party code, so it retains Smarty's default of *off*. Measured across all 713
templates:

| | |
|---|---|
| `{$…}` tags total | 9,501 |
| output tags with **no** escape modifier | **5,323** |
| template files affected | **553** |

Largest concentrations: `admin-dev/.../helpers/form/form.tpl` (258),
`helpers/list/list_header.tpl` (174), `helpers/options/options.tpl` (143),
`controllers/orders/helpers/view/view.tpl` (122), and on the guest side
`themes/hotel-reservation-theme/shopping-cart-detail.tpl` (85), `authentication.tpl` (81),
`product.tpl` (52), `order-detail.tpl` (52).

**Interpretation — read this before acting.** This is *not* 5,323 vulnerabilities. PrestaShop's
convention is manual escaping, most of these tags emit integers, configuration values or data
already sanitised in PHP, and this release's XSS fixes (CVE-2026-89268, CVE-2026-103587/88/89) are
precisely the cases where that convention was missed. But it *is* the systemic root cause of that
CVE class, and it is the reason our own module standard mandates escaping on every output.

**Do not flip `escape_html` to true globally.** The comment at `config/smarty.config.inc.php:67`
states the escape-modifier override exists because it "is required for invoice PDF generation";
enabling global escaping would double-escape and break PDFs and existing templates. Instead:
enforce `|escape:'html':'UTF-8'` in all new and modified templates, and add a CI rule that fails
when a *changed* template introduces a new bare output tag.

### G4 — Bundled Responsive Filemanager gated by a static session string · **Medium-High**

`admin-dev/filemanager/` ships a complete third-party file manager: `upload.php`, `execute.php`,
`force_download.php`, `ajax_calls.php`, `dialog.php`, plus `include/php_image_magician.php`,
`config/config.php` and 20+ language files — **32 of the 141 files with no licence header** (§6).

Its entire authorisation gate is `admin-dev/filemanager/upload.php:3`:

```php
if ($_SESSION['verify'] != 'RESPONSIVEfilemanager') { die('forbiden'); }
```

A **fixed, published string** rather than a per-session or per-employee token, and there is no
employee-identity check in the file itself. To its credit the upload path is not naive:
`upload.php:17-20` rejects `../` sequences and requires the path to begin with `$current_path`,
`:41-44` enforces an extension allowlist plus a `mime_content_type()` check when available, and
`:67` re-validates images with `getimagesize()`.

**Recommendation.** The audit already requires `admin-dev/` to be renamed to an unguessable path
and `install-dev/` deleted. Add: **remove `admin-dev/filemanager/` entirely** unless the client
confirms it is used — QloApps has its own `classes/FileUploader.php`
(`move_uploaded_file` at `:143`) for image handling. Deleting it removes 32 unlicensed files and a
large upload surface in one step.

### G5 — Dead code that would fatal on PHP 8 · **Low** (unreachable)

`classes/module/ImportModule.php:62,63,66,78` call `mysql_connect()`, `mysql_select_db()` and
`mysql_query()` — all **removed in PHP 7.0**. On the host's PHP 8.4.27 any invocation is a fatal
error.

Verified **unreachable**: a repository-wide grep for `ImportModule` outside its own file returns
**zero references**. So it cannot execute today. It is nonetheless a landmine — the class is
autoloadable, and any future code that instantiates it fails hard.

**Recommendation.** Delete the file, or port it to `Db::getInstance()`. Low urgency, zero risk.

### G6 — `unserialize()` on cache and session data · **Low-Medium**

| Location | Input |
|---|---|
| `classes/cache/CacheFs.php:40` | `file_get_contents($keys_filename)` from the filesystem cache |
| `classes/cache/CacheFs.php:70` | cached file contents |
| `classes/Tools.php:3327` | caller-supplied serialized string |
| `install-dev/classes/session.php:75` | installer session data |

PHP object injection requires an attacker who can write those files, so severity is bounded by
**write access to `cache/`**. That makes this a directory-protection issue as much as a code issue:
`cache/` has an `index.php` guard but **no `.htaccess`** (§9).

### G7 — `extract()` on hook parameters · **Low**

12 sites, the notable ones being
`modules/hotelreservationsystem/classes/HotelBookingDetail.php:356` and `:595`
(`extract($params)`), plus `classes/Tools.php:1561` (`extract($options)`). `extract()` lets array
keys create local variables, so crafted or colliding keys can overwrite locals. Parameters are
internally sourced (hook dispatch), which is why this is low — but it is a pattern our own modules
must not copy.

### G8 — `eval()` in the override/autoloader mechanism · **Low**, but security-relevant

12 sites, concentrated in the machinery that makes `override/` work:
`classes/ControllerFactory.php:48,50` and `classes/PrestaShopAutoload.php:95,114`. Class names come
from the generated `cache/class_index.php`, not from user input, so this is not an injection point
in normal operation.

The consequence worth recording: **the integrity of `cache/class_index.php` is a security
boundary.** An attacker who can write it obtains code execution through the autoloader. Our chosen
architecture depends on `override/`, so we accept this mechanism — but `cache/` must be protected
(§9) and the file must be regenerated (deleted) rather than hand-edited.

### G9 — 141 files carry no licence header · **Low**, but blocks the legal review

Grouped by directory:

| Directory | Files | Note |
|---|---|---|
| `admin-dev/filemanager` | 32 | Third-party Responsive Filemanager (see G4) |
| `install-dev/langs` | 31 | Installer translation files |
| `mails/en` | 26 | **Email templates — directly in scope for branding work** |
| `modules/qlocrontaskmanager` | 20 | A module **new in 1.8.0** shipping without AFL headers |
| `modules/hotelreservationsystem` | 13 | Core hotel module |
| `admin-dev/themes` | 13 | Back-office theme assets |
| `themes/hotel-reservation-theme` | 5 | Guest theme |
| others | 11 | `blockcart` 3, `qloduitkupayment` 2, `blockuserinfo` 2, `install-dev/upgrade` 2, `install-dev/fixtures` 2 |

Relevant to OSL-3.0 §6 (retain notices in derivative source) and to the rebranding legal review in
`../project/02-architecture-and-branding.md` §5: we must not *add* to this set, and the 26 mail
templates matter because email branding is an explicit requirement.

---

## 4. Leads investigated and cleared

Reported so the negative results are on record — each of these looked like a finding to the
scanner and was disproved by reading the code.

| Lead | Why it is safe |
|---|---|
| `admin-dev/displayImage.php:36` — `echo file_get_contents(_PS_UPLOAD_DIR_.$_GET['img'])` | Line 35 gates on `Validate::isMd5($_GET['img'])` (32 hex chars only, so path traversal is impossible), `Validate::isGenericName($_GET['name'])`, and `file_exists()`. Properly validated |
| `config/defines.inc.php:34-35` — `display_errors on`, `error_reporting(E_ALL\|E_STRICT)` | Inside `if (_PS_MODE_DEV_ === true)`; `_PS_MODE_DEV_` defaults to **false** (line 29) and the `else` branch explicitly sets `display_errors` **off**. Correct |
| `classes/ImageManager.php:326-331` — `exec('file -b --mime-type …')` | Last resort in a `finfo_open()` → `mime_content_type()` → `exec()` chain, each behind `function_exists`, and arguments wrapped in `escapeshellarg()`. Degrades gracefully when `exec` is disabled |
| `modules/qlocrontaskmanager/qlocrontaskmanager.php:207` — `shell_exec('crontab -l')` | Guarded at `:198` by `function_exists('shell_exec')` **and** at `:202-203` by an explicit `ini_get('disable_functions')` check that returns null. Safe on shared hosting |
| `system/exec/shell_exec` — 345 raw hits | Almost all are `Hook::exec()` and `Adapter_HookManager::exec()` method calls, not shell execution. Real count after correcting for method calls: 11, of which the `exec` uses are the guarded ImageManager chain above |
| "backtick shell execution" — 10,554 raw hits | Entirely SQL identifier quoting (`a.\`id_product\``). Zero real backtick-shell sites; the check was removed from the scanner |
| Directories without `.htaccess` (`cache`, `translations`, `install-dev`, `admin-dev`, `controllers`, `localization`, `tests`) | Every one has an `index.php` guard instead. **Caveat:** `index.php` prevents directory *listing* but not direct file access, so the explicit deny rules in `../deploy/live-site-hardening.md` remain the correct control |
| Committed secrets | Only three "secret-looking" tracked files: `cache/cacert.pem` and `tools/geoip/composer/ca-bundle/res/cacert.pem` (CA bundles — public by design) and `tools/htmlpurifier/.../URI.MungeSecretKey.txt` (a config-schema document). **No credentials.** `config/settings.inc.php` is absent and gitignored at `.gitignore:145` |
| `qlo_customer.passwd` plaintext | All 1,257 rows are 32-hex; none plaintext. See G1 |

---

## 5. Cryptography and password posture — a genuine strength

`classes/PasswordHashing.php` implements a **ranked hash-method list**:

- **Primary: bcrypt** — `password_hash($passwd, PASSWORD_BCRYPT)` (`:105`), verified with
  `password_verify()` (`:108`).
- **Legacy fallback: `md5(_COOKIE_KEY_ . $passwd)`** — retained so existing hashes keep working.

And it upgrades transparently on successful login, for **both** account types:

- Customers — `classes/Customer.php:413-416`: if `!$objHash->isPrimaryHash($passwd, $result['passwd'])`
  then re-hash with `passwordHash()` and `update()`.
- Employees — `classes/Employee.php:303-305`: identical pattern.

### 5.1 ⚠️ CORRECTION — the automatic rehash cannot run against the live schema

**An earlier revision of this section stated that all accounts would migrate to bcrypt
automatically on first login after the upgrade, needing no script. That is wrong, and it is
corrected here.** The mechanism above is real and well designed, but it cannot work against the
live database as it stands, because of a column width mismatch:

| | Live 1.7.0 schema | QloApps 1.8.0 install schema |
|---|---|---|
| `qlo_customer.passwd` | **`varchar(32)`** | `varchar(60)` (`install-dev/data/db_structure.sql:605`) |
| `qlo_employee.passwd` | **`varchar(32)`** | `varchar(60)` (`install-dev/data/db_structure.sql:791`) |

32 characters is exactly an MD5 hex digest — the width the legacy scheme needs. A bcrypt digest
is **60 characters**, which is precisely why 1.8.0 widened the column. **Nothing in this
repository performs that widening:** `install-dev/upgrade/sql/` stops at `1.6.1.0.sql` and
`modules/hotelreservationsystem/upgrade/` stops at `upgrade-1.6.0.php`, so there is no
1.7.0 → 1.8.0 migration of any kind (see §2.9).

So when 1.8.0 code runs `passwordHash()` on login and writes 60 characters into a
`varchar(32)` column, the write fails. How it fails depends on the server's `sql_mode`:

| Server mode | Outcome |
|---|---|
| **STRICT** (MariaDB's default since 10.2) | The UPDATE is rejected with error 1406. Because `classes/db/DbPDO.php:69` constructs the PDO handle with only `PDO::ATTR_TIMEOUT` and **no `PDO::ATTR_ERRMODE`**, PDO defaults to `ERRMODE_SILENT` — the failure is swallowed, login still succeeds, and the password **stays MD5 forever**. The advertised automatic upgrade simply never happens. |
| **NON-STRICT** | The value is **silently truncated to 32 characters**. The stored string still begins `$2y$`, so `isPrimaryHash()` reports it as already primary, but `password_verify()` can never match it. The account is **permanently locked out** and the original MD5 hash has been destroyed — unrecoverable without a reset. |

Either outcome affects all **1,257 customers and 15 employees**. The non-strict case is a
mass-lockout scenario triggered by the *first successful login* of each account, which is why
this must be fixed before upgrade rather than discovered afterwards.

**Remediation.** Widen both columns before any 1.8.0 code touches the data:

```sql
ALTER TABLE `qlo_employee` MODIFY `passwd` varchar(60) NOT NULL;
ALTER TABLE `qlo_customer` MODIFY `passwd` varchar(60) NOT NULL;
```

This is shipped as a version-controlled migration with a rollback and a pre-flight safety
check at `dev/migrations/001_widen_password_columns.up.sql` / `.down.sql`. It is
**non-destructive**: every existing 32-character MD5 hash still fits in `varchar(60)` and
remains valid, because `validateHash()` tries every supported method and re-hashes on next
login. Only after this migration does the transparent-upgrade claim above become true.

The original good news still holds, with that precondition: after widening, each account
migrates to bcrypt automatically the first time its owner logs in — no bulk script, no
downtime, no forced reset. Rotation is still advised for the 15 employee accounts because
their hashes were publicly exposed (§12), but the 1,257 customers need no forced reset.

Note also that `qlo_customer.secure_key` remains `varchar(32)` in both schemas — it is not a
bcrypt output, so it needs no widening.

Also verified: encryption is OpenSSL-first (`classes/PhpEncryptionEngine.php`), with
`classes/PhpEncryptionLegacyEngine.php:55` deriving an HMAC IV from `sha1(_COOKIE_KEY_)` only as a
fallback. `Tools::encrypt()` remains MD5-based and is used for non-secret derivation such as
admin tokens (`classes/Tools.php:1191-1193`) and cache keys (`classes/cache/Cache.php:371,382`) —
acceptable for those uses, not for passwords, and it is no longer used for passwords.

---

## 6. Third-party and vendored component inventory

| Component | Location | Notes |
|---|---|---|
| Responsive Filemanager | `admin-dev/filemanager/` | See G4 — recommend removal |
| HTMLPurifier | `tools/htmlpurifier/` | Output sanitisation library; keep |
| Defuse crypto | `tools/defuse/` | Crypto library backing `PhpEncryptionEngine` |
| PSR mailer | `tools/mailer/` | Mail abstraction |
| GeoIP | `tools/geoip/` | Includes its own CA bundle |
| SQL parser, minifiers, mobile-detect,PEAR | `tools/` | 1,138 vendored PHP files, excluded from the lint job by design |

All are vendored **in-tree** — there is no `vendor/` directory, no Composer package dependency and
no `autoload` section in `composer.json`. This is why deployment needs no build step, which matters
on cPanel shared hosting (`../project/02-architecture-and-branding.md` §10).

---

## 7. PHP 8 compatibility

| Check | Result |
|---|---|
| Removed `mysql_*` extension | 1 file (`classes/module/ImportModule.php`) — unreachable, see G5 |
| `each()` (removed in PHP 8.0) | **0** in PHP. The 174 raw hits were jQuery `.each(function…)` in embedded JS |
| `create_function()` (removed in PHP 8.0) | **0** |
| Curly-brace string offsets (removed in PHP 8.0) | **0** |
| `preg_replace` with the `/e` modifier (removed in PHP 7.0) | **0** |
| Syntax lint on PHP 8.2 / 8.3 / 8.4 | ✅ **2,247 first-party files clean**, enforced in CI on every push |

The tree is genuinely PHP 8.4-ready, which matches the host's PHP 8.4.27.

---

## 8. Quality and test coverage

- `tests/` contains bootstrap and configuration only — **zero test cases**. There is no automated
  coverage of the booking, payment, check-in or refund flows.
- CI on this branch (`.github/workflows/ci.yml`) runs a PHP 8.2/8.3/8.4 syntax matrix over 2,247
  first-party files and a localization-pack validator (84 packs, 244 countries, 0 errors, 37
  baselined upstream warnings). It replaced a dead `.travis.yml` pinned to `dist: trusty`
  (EOL 2019). **All four checks pass on the current head.**
- 36 `TODO`/`FIXME`/`XXX`/`HACK` markers across first-party code — low for a codebase this size.

**Gap:** syntax linting proves the code parses, not that it works. Phase 6 of the plan must add
workflow tests for the four flows in the brief (reservation, walk-in, housekeeping, payment)
before any production deployment. Given G3, a CI rule for *newly introduced* unescaped template
output is the highest-value additional check.

---

## 9. Directory protection and deployment hygiene

| Directory | `.htaccess` | `index.php` | Assessment |
|---|---|---|---|
| `config/` | ✅ | — | Correctly protected; holds `settings.inc.php` |
| `log/`, `upload/`, `download/`, `mails/` | ✅ | ✅ | Correctly protected |
| `modules/`, `themes/`, `img/`, `pdf/`, `classes/`, `override/`, `tools/` | ✅ | ✅ | Correctly protected |
| `cache/` | ❌ | ✅ | **Listing blocked, direct access not.** Relevant to G6 and G8 — compiled templates and `class_index.php` live here |
| `translations/`, `localization/` | ❌ | ✅ | Data files directly fetchable |
| `controllers/`, `tests/` | ❌ | ✅ | Source directly fetchable |
| `install-dev/`, `admin-dev/` | ❌ | ✅ | Must be deleted / renamed before go-live (already P2 items 16) |

`index.php` guards prevent directory listing but **do not** prevent fetching a known path, so the
explicit deny rules in `../deploy/live-site-hardening.md` are still required. On the live site today
`CHANGELOG.txt` and `composer.json` are publicly served, which discloses the exact version — the
hardening guide's rules address that in a few minutes of work.

---

## 10. Data-layer audit

Full detail in `../project/01-phase1-technical-assessment.md`. Headlines:

- Live is **QloApps 1.7.0.0** on PrestaShop schema **1.6.1.23**, host **PHP 8.4.27 / MariaDB 11.4.13**, prefix `qlo_`.
- **36 tables** and **23 columns across 17 tables** must be created before 1.8.0 code will run.
- **16 legacy tables** survive, 8 of them holding data; the two at risk are hotel *features*
  (42 rows, migratable to the new amenity tables) and *bed types* (26 rows, **no target in 1.8.0**).
- One custom module, **`cashpayment` v2.6.8**, exists in neither upstream tag; its source is
  unrecoverable until the file backup is re-uploaded.
- `payment_method` is free text with **23 distinct spellings** across 2,058 rows, including one row
  whose value is a phone number — reporting cannot be built on it without normalisation.
- Card columns are empty in all 2,058 payment rows: **no cardholder data is stored**.
- Configuration already matches the brief on currency (GHS), timezone (`Africa/Accra`), country
  (Ghana, id 94), language (English) and check-in/out (**13:00 / 12:00**). Defects:
  `PS_SHOP_NAME = lushviewhotel.net`, `MB_CANCEL_URL = http://www.yoursite.com`,
  branch latitude/longitude `0.00000000`.

---

## 11. Prioritised remediation

| # | Action | Severity | Owner | Where |
|---|---|---|---|---|
| 1 | Disable `qlohotelreview` on the live site (CVE-2025-67325, `active=1`, 0 reviews so nothing is lost) | **Critical** | Client | Live back office |
| 2 | Make the repository private; file the GitHub Support purge request | **Critical** | Client | GitHub Settings / Support |
| 3 | Rotate the 15 employee passwords (their hashes were publicly exposed). **Prerequisite for the upgrade:** apply `dev/migrations/001_widen_password_columns.up.sql` to widen `passwd` to `varchar(60)` — until then the automatic bcrypt rehash silently fails, or truncates and locks accounts out. See §5.1 | **Critical** | Client + us | Live back office / migration |
| 4 | Apply `../deploy/live-site-hardening.md` (block `CHANGELOG.txt`, `composer.json`; WAF on `bo_query`; MySQL privileges) | **High** | Client | Live server |
| 5 | Remove `admin-dev/filemanager/` unless its use is confirmed | Medium-High | Us, in Phase 3 | G4 |
| 6 | Override `Customer::add()` to use `random_bytes()` for `secure_key` | Medium | Us, in Phase 3 | G2 |
| 7 | Decide the channel-manager question; if used, override G1's customer creation | Medium | Client + us | G1 |
| 8 | Add a CI rule for newly introduced unescaped template output | Medium | Us, in Phase 3 | G3 |
| 9 | Delete `classes/module/ImportModule.php` | Low | Us, in Phase 3 | G5 |
| 10 | Protect `cache/` with explicit deny rules | Low-Medium | Client | G6, G8, §9 |
| 11 | Add workflow tests for the four hotel flows | Medium | Us, in Phase 6 | §8 |
| 12 | Legal read on OSL-3.0 §6 attribution and the 141 unlicensed files | Medium | Client counsel | G9 |

Items 1-4 are operational and **do not depend on this project** — they should happen now, against
the live 1.7.0 site, rather than waiting for the 1.8.0 upgrade.

---

## 12. What this audit does not cover

Stated so the gaps are not mistaken for clean results:

- **Runtime behaviour.** No PHP interpreter exists in this environment, so nothing was executed.
  The installer was never booted and no page was rendered. All findings are static, and PHP-8
  syntax correctness is verified only by the CI runners.
- **The live server.** No access to `lushviewhotel.net`'s filesystem, so on-disk hotfixes, actual
  file permissions, the real `.htaccess` in force, and the contents of `config/settings.inc.php`
  are all unverified. Module *enabled* state is known from the database; module *code* on disk is not.
- **Modified core files.** Blocked — the old-system file backup is unavailable
  (`../project/01-phase1-technical-assessment.md` §2).
- **Dynamic testing.** No authentication, authorisation, CSRF, rate-limiting or business-logic
  testing was performed; those require a running staging instance (Phase 6).
- **`tools/`.** 1,138 vendored PHP files were excluded from findings by design. Their own CVE
  exposure was not assessed.
