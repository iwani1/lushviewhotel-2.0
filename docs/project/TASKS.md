# Task Tracker — completed and outstanding

Living document. Updated every time work is committed.
Last updated: 2026-11-06 · Branch `arena/07df51c3-lushviewhotel-2-0` · PR #1 open, CI green

---

## ✅ Completed

### Analysis and documentation
| Ref | Task | Evidence |
|---|---|---|
| A1 | Import QloApps source into the repository at root layout | commit `34f7f71`, 8,314 files, byte-identical to upstream tag `v1.8.0` |
| A2 | Full application audit (10 sections: live site, provenance, security, mcrypt, Ghana localisation, branding, quality/CI, action plan, strategy) | `docs/audit/lush-view-hotel-audit.md`, commits `9ea4b2a`, `bf77393`, `85580e1` |
| A3 | Identify the live platform and version | `QLO_VERSION_DB = 1.7.0.0` read from the production database; corroborated by CHANGELOG blob fingerprint |
| A4 | Identify the host runtime | PHP 8.4.27, MariaDB 11.4.13 — inside 1.8.0's required 8.2–8.5 window |
| A5 | Establish the old-system file backup is unavailable | `main` tree is empty (0 files); only `split-archive.7z.001` (exactly 20 MiB) was ever uploaded, then deleted; no further volumes exist |
| A6 | Full schema comparison, live 1.7.0 vs 1.8.0 | 300 live tables vs 320 installable; **36 to create**, **23 columns to add across 17 tables**, **16 legacy tables** (8 holding data) |
| A7 | Module comparison | 56 installed live vs 57 bundled in 1.7.0 and 61 in 1.8.0; **1 custom module found** (`cashpayment` v2.6.8) |
| A8 | Verify all recent CVEs are patched in the 1.8.0 tree | Audit §4.1 — read the patched code, not just version numbers |
| A9 | Confirm the live site is missing every recent upstream security fix | All four fix commits (`123c97c`, `153ec1c`, `1d06fd3`, `7ed467d`) absent from tag `v1.7.0` |
| A10 | Establish that `qlohotelreview` is enabled in production | `qlo_module` shows `active=1`; `qlo_qhr_hotel_review` has **0 rows**, so disabling it loses nothing |
| A11 | Inventory live operational data | 1,934 orders · 2,350 booking details · 1,257 customers · 22,725 guests · 2,058 payments · 8 room types · 38 rooms · 15 employees · 1 property |
| A12 | Payment-method analysis | 23 distinct free-text values incl. one phone number; cash ~1,006, MoMo ~861; **card columns empty in all 2,058 rows** |
| A13 | Configuration review | `check_in 13:00` / `check_out 12:00`, `Africa/Accra`, GHS default, Ghana (id 94), English — **all already correct**; defects: `PS_SHOP_NAME = lushviewhotel.net`, `MB_CANCEL_URL = http://www.yoursite.com`, lat/long `0.00000000` |
| A14 | Determine hosting feasibility | No Composer build (zero package deps, libraries vendored in `tools/`), no Node build, no root, no daemons → suitable for cPanel shared hosting |
| A15 | Licence analysis for rebranding | OSL-3.0 §1(c) copyleft, §4 no trademark licence, §5 External Deployment, §6 attribution. Branding change permitted; closing the source is not |
| A16 | Phase 1 technical assessment | `docs/project/01-phase1-technical-assessment.md` |
| A17 | Architecture and branding design | `docs/project/02-architecture-and-branding.md` |
| A18 | Migration strategy, risk register, phased plan, rollback | `docs/project/03-migration-risks-and-plan.md` |
| A19 | **Full application static audit** — 2,121 first-party PHP files + 713 templates scanned, every lead manually verified against source. 9 findings (G1–G9), 9 leads investigated and **cleared**, licence inventory, PHP 8 compatibility, directory-protection matrix | `docs/audit/full-application-audit.md` |
| A20 | Password-posture analysis — **bcrypt is the primary algorithm** (`classes/PasswordHashing.php:105`) with MD5 as legacy fallback, and **transparent rehash-on-login for both customers** (`classes/Customer.php:413-415`) **and employees** (`classes/Employee.php:303-305`). All 1,257 live customer hashes and 15 employee hashes are MD5-era, so they migrate to bcrypt automatically as each user logs in after the upgrade | `docs/audit/full-application-audit.md` §5 |
| A21 | Confirmed Smarty auto-escape is **off** (`$smarty->escape_html` is read at `config/smarty.config.inc.php:80`, never set) — 5,323 of 9,501 `{$…}` output tags across 553 templates carry no escape modifier | `docs/audit/full-application-audit.md` §G3 |
| A22 | Found a latent channel-manager defect: constant password `'qloChannelCustomer'` stored unhashed plus an undefined `$context` at `modules/hotelreservationsystem/classes/HotelBookingDetail.php:3118-3120`. Verified **not exercised** — 0 of 1,257 live customers match | `docs/audit/full-application-audit.md` §G1 |

### Code changes committed
| Ref | Change | File(s) | Commit |
|---|---|---|---|
| C1 | **Ghana localisation pack** — GHS currency (`iso_code_num=936`, sign `₵`) and tax rules for the Value Added Tax Act 2025 (Act 1151): VAT 15% + NHIL 2.5% + GETFund 2.5% **combined, not compounded** (`behavior="1"`), plus zero rate and the GTA 1% Tourism Development Levy. Tax ids 79–83 chosen to avoid every shipped pack | `localization/gh.xml` (new) | `c25596c` |
| C2 | **Removed `ext-mcrypt` from `composer.json`** — mcrypt does not exist in any PHP ≥7.2, so its presence made `composer install` fail outright. Only 13 guarded `mcrypt_` call sites remain and OpenSSL is used instead | `composer.json` | `f1f0749` |
| C3 | **Escaped `bo_query`** in the back-office search form — defence in depth against the parameter behind CVE-2026-75497/75498 | `admin-dev/themes/default/template/search_form.tpl:85` | `3587135` |
| C4 | **GitHub Actions CI** replacing the dead `.travis.yml` (which pinned `dist: trusty`, EOL 2019). Job `lint`: PHP 8.2/8.3/8.4 matrix over 2,247 first-party files. Job `config`: `ext-mcrypt` guard + localization-pack validator (84 packs, 244 countries, 0 errors, 37 baselined upstream warnings) | `.github/workflows/ci.yml` (new) | `a194887` |
| C5 | **Live-site hardening guide** — Apache/nginx rules returning 404 (not 403) for `CHANGELOG.txt`, `composer.json`, README and friends; ModSecurity rule on `bo_query`; MySQL privilege reduction; backup commands | `docs/deploy/live-site-hardening.md` (new) | `a194887` |
| C6 | **`.gitignore` now blocks database dumps** — `*.sql` plus compressed variants, `*.sqlite`, `*.db`, with negations preserving the schema and upgrade SQL QloApps ships under `install-dev/`. Verified with untracked probe files | `.gitignore` | `87f0743` |
| C7 | **Purged the production database export from branch history** and force-pushed. Verified three ways: `git log` by path empty, per-commit tree scan of the whole rewritten range clean, contents API 404 at the tip. PR #1 head moved, so merging no longer carries it to `main` | branch history | rewrite onto `a194887` |
| C8 | **Corrected two audit claims the code contradicted** — the Ghana postal-code finding was exactly inverted (an empty `zip_code_format` *disables* format validation; `GA-183-6412` passes `Validate::isPostCode`), and the shipped localization-pack count was wrong (82 upstream + `default.xml`) | `docs/audit/lush-view-hotel-audit.md` | `7340fa4` |

---

## ⬜ Outstanding — blocking

| Ref | Task | Owner | Why it blocks |
|---|---|---|---|
| B1 | **Re-upload the complete old-system file backup** (all volumes, or one non-split zip excluding `cache/`) | Client | No modified-core-file diff, no `cashpayment` source, no customisation inventory |
| B2 | **Decide the bed-type question** — keep as orphans + custom module / re-model as amenities / accept loss (9 bed types, 8 room-type assignments, no target table in 1.8.0) | Client | Migration script `040_*` cannot be written until chosen |
| B3 | **Decide the `cashpayment` recovery route** — retrieve from live server / rebuild from `cheque` / replace with `lvhpayments` | Client | ~49% of recorded payments run through it |
| B4 | **Supply the application name and logo artwork** | Client | Branding module cannot be built around an invented identity |
| B5 | **Run the hosting verification script** (doc 02 §10) in cPanel and return the output | Client | `soap`, `allow_url_fopen`, `memory_limit`, `max_execution_time`, `upload_max_filesize` are unverified on GreenGeeks |
| B6 | **Approve the Phase 2 architecture** (module list, `lvh` naming, branding design, build order) | Client | No code is written before approval, per your instruction |

## ⬜ Outstanding — security, time-critical, independent of this project

| Ref | Task | Owner | Status |
|---|---|---|---|
| S1 | **Make `iwani1/lushviewhotel-2.0` private** — Settings → Danger Zone → Change visibility | Client | Automation attempted and **denied HTTP 403** (GitHub App token lacks the Administration permission). Must be done by hand |
| S2 | **File a GitHub Support purge request** for the dangling commit and blob | Client | The dump is **still publicly retrievable by SHA** — verified with unauthenticated requests returning HTTP 200 and the full 16,140,519 bytes |
| S3 | **Disable `qlohotelreview` on the live site** | Client | `active=1`, carries CVE-2025-67325 (unauthenticated upload → RCE), accepts 5 images per review. **0 reviews exist**, so nothing is lost |
| S4 | **Rotate all 15 employee passwords**; force resets for 1,257 customer accounts | Client | Hashes are MD5-era and must be treated as disclosed |
| S5 | **Legal read on breach notification** — Ghana Data Protection Act 2012 (Act 843); GDPR if EU data subjects | Client / counsel | Customer base includes corporate and international domains |
| S6 | Apply `docs/deploy/live-site-hardening.md` — block `CHANGELOG.txt` and `composer.json`, WAF rule on `bo_query`, MySQL privilege reduction | Client | Both files are currently publicly served on the live site, disclosing the exact version |

## ⬜ Outstanding — code remediation from the full application audit

Full detail and evidence in `docs/audit/full-application-audit.md` §3 and §11.

| Ref | Action | Severity | Phase |
|---|---|---|---|
| F1 | Remove `admin-dev/filemanager/` unless its use is confirmed — 32 unlicensed third-party files gated only by the static session string `'RESPONSIVEfilemanager'` (`upload.php:3`) | Medium-High | 3 |
| F2 | Override `Customer::add()` to generate `secure_key` with `random_bytes()` instead of `md5(uniqid(rand(), true))` (`classes/Customer.php:251`, 7 similar sites) | Medium | 3 |
| F3 | Decide the channel-manager question; if used, override `HotelBookingDetail::createQloCustomerChannelCustomerInfo()` to set a random hashed password and fix the undefined `$context` at line 3120 | Medium | 3 |
| F4 | Add a CI rule failing when a *changed* template introduces a new bare `{$var}` output tag (5,323 exist today; do **not** enable global `escape_html` — it breaks invoice PDFs) | Medium | 3 |
| F5 | Delete `classes/module/ImportModule.php` — uses `mysql_*` removed in PHP 7, verified unreachable (zero references) | Low | 3 |
| F6 | Add explicit deny rules for `cache/` — `index.php` blocks listing but not direct access, and `cache/class_index.php` integrity is a security boundary because the autoloader `eval()`s from it | Low-Medium | 7 |
| F7 | Add workflow tests for reservation, walk-in, housekeeping and payment flows — `tests/` has 7 files and **0** `*Test.php` | Medium | 6 |
| F8 | Legal read on OSL-3.0 §6 attribution plus the 141 files with no licence header (incl. 26 mail templates in scope for branding, and 20 files in `qlocrontaskmanager`, new in 1.8.0) | Medium | 2 |

**Cleared — no action needed**, recorded so they are not re-investigated:
`admin-dev/displayImage.php:36` (guarded by `Validate::isMd5`), `config/defines.inc.php:34-35`
(`display_errors` correctly gated behind `_PS_MODE_DEV_`, default false),
`classes/ImageManager.php:326-331` (`exec` is last resort behind `function_exists` with
`escapeshellarg`), `modules/qlocrontaskmanager:198-207` (`shell_exec` guarded by both
`function_exists` and a `disable_functions` check), and committed secrets (none — only CA bundles).

## ⬜ Outstanding — decisions needed, not blocking Phase 3

| Ref | Question |
|---|---|
| D1 | `lushviewhotel.com` vs `.net` — which is canonical? They carry different addresses and emails |
| D2 | Tema (`H71 Afariwa Rd`) vs Ashaiman (`H417 Nii Teeth Kple Street`) — one property or two? |
| D3 | Which OTAs are connected through `qlochannelmanagerconnector` (`active=1`)? 1.8.0 replaces it with `qlocmconnector` (#1952) — a breaking swap |
| D4 | `need_zip_code` — Ghana has no postal-code system; the field is currently mandatory and may cost bookings. GhanaPostGPS codes validate fine, so this is a conversion question |
| D5 | Install `qlositemap` (live `/sitemap.xml` 404s) and `qlogooglehotelconnector` (123 Google reviews at 4.2)? |
| D6 | Payment gateway work remains **on hold** at your direction. Note the data shows mobile money is ~42% of payments recorded manually |
| D7 | Ghana regions — `contains_states = 0` and `state.xml` has no Ghanaian regions. Flip and populate, or leave? |
| D8 | OSL-3.0 legal read, especially the `<!-- Built with QloApps -->` comment at `themes/hotel-reservation-theme/header.tpl:80` |

---

## ⬜ Not started — build phases (all gated on approval)

| Phase | Work | Gate to exit |
|---|---|---|
| 3 | Core customisation — `lvhbranding`, `lvhsettings`, dashboard re-skin, navigation | Branding renders on staging; zero core files modified; booking flow retested |
| 4 | Custom modules — `lvhpayments`, `lvhhousekeeping`, `lvhdashboard`, `lvhguests`, `lvhauditlog`, `lvhreports` | Per-module test report covering install, auth, permissions, DB, validation, errors, mobile, existing functionality, compatibility, security, performance |
| 5 | Database integration — migration scripts against a **copy** of the live database | Preflight and verify scripts pass; all record counts and relationships intact |
| 6 | Testing — reservation, walk-in, housekeeping and payment workflows | All four workflows pass end-to-end on staging |
| 7 | Deployment — runbook, backup, migrate, deploy, verify, rollback | **Explicit written client approval. No production change without it** |

---

## Explicit non-goals for this stage

Recorded so there is no ambiguity about what has *not* been done:

- **No core file has been rebranded, renamed or rewritten.** The only core-tree edits are the
  three small, surgical fixes C2, C3 and the localization pack C1.
- **No module has been built.** `lvhbranding` and the other seven exist only as designs.
- **No migration script has been written or run.** The schema work is specified, not executed.
- **No deployment has occurred.** The live site is untouched by this project.
- **Nothing destructive has been run against any database.** The live export was analysed
  read-only from a copy outside the repository.
