# Phase 2 — Custom Application Architecture & Branding System

**Depends on:** `01-phase1-technical-assessment.md`
**Status:** PROPOSAL — awaiting approval before any code is written.

---

## 1. Design principles

These are the rules every later decision must satisfy. They come from your brief and from what
the audit found.

1. **Never edit core files.** QloApps provides two supported extension points and we use only
   those:
   - `override/` — drop-in class and controller overrides
     (`override/classes/`, `override/controllers/front/`, `override/controllers/admin/`).
   - `modules/<name>/` — self-contained modules with their own controllers, models, views,
     SQL and hooks.
   Editing core means every future upstream security patch becomes a manual three-way merge.
   The live site is already four security fixes behind precisely because patching is manual.
2. **Reuse before rebuild.** §5.4 of the assessment shows 1.8.0 already covers most of the
   functional requirement. We re-skin and extend; we build net-new only where a genuine gap
   exists (housekeeping, audit log, guest identity, payment normalisation).
3. **Every change must survive an upstream upgrade.** If a modification would be lost by
   replacing a directory, it belongs in `override/` or a module — not in the directory.
4. **Nothing hard-coded.** Identity, colours, times and fees come from configuration, not from
   edited templates.
5. **Cache discipline.** After adding an override, `cache/class_index.php` must be deleted so
   the autoloader rebuilds. After template changes, clear
   `cache/smarty/compile/*` and `cache/smarty/cache/*`. On cPanel this is File Manager →
   delete; no shell needed.

---

## 2. Proposed structure

```
lushviewhotel-2.0/                     ← QloApps 1.8.0 core, unmodified except where noted
├── override/                          ← SUPPORTED core extension point
│   ├── classes/                       ← e.g. Customer.php additions
│   └── controllers/admin/             ← e.g. re-skinned dashboard controller
├── modules/
│   ├── lvhbranding/                   ← NEW  centralised branding + identity  (AFL-3.0)
│   ├── lvhdashboard/                  ← NEW  dashboard widgets & quick actions
│   ├── lvhhousekeeping/               ← NEW  room status, assignments, schedules, log
│   ├── lvhpayments/                   ← NEW  normalised payment methods, MoMo, receipts
│   ├── lvhguests/                     ← NEW  identity documents, preferences, requests
│   ├── lvhreports/                    ← NEW  reporting + Excel/PDF export
│   ├── lvhauditlog/                   ← NEW  admin action & login history
│   ├── lvhsettings/                   ← NEW  single hotel-settings screen
│   └── cashpayment/                   ← RECOVERED from live server (§6.2 of assessment)
├── themes/
│   └── hotel-reservation-theme/       ← guest-facing; branded via lvhbranding, not by editing
├── admin-dev/themes/default/          ← back office; re-skinned via override + CSS, see §7
├── localization/gh.xml                ← ALREADY ADDED on this branch (GHS + Act 1151 taxes)
└── docs/project/                      ← these documents
```

All new modules use an `lvh` prefix so they are unambiguous against upstream `qlo*`, `wk*`,
`dash*`, `stats*` and `block*` namespaces, and so a future upstream sync can never collide
with them.

**Why modules and not a rewrite:** each module installs and uninstalls cleanly, carries its own
SQL migrations, registers its own permissions and tabs, and can be disabled on the live site if
it misbehaves — without touching the booking engine. That is exactly the maintainability your
brief asks for in items 8 and 10.

---

## 3. Centralised branding configuration (`lvhbranding`)

Your brief lists eleven things that must be configurable from one place. Here is the design.

**Storage:** QloApps `Configuration::` keys (which live in `qlo_configuration`, the same table
that already holds 459 live settings). No new table is needed for scalar settings, which keeps
migration trivial and means the branding travels with the database backup. Multi-value assets
(logos) are stored as files under `img/lvh/` with their paths in configuration.

| Configurable | Key | Mechanism |
|---|---|---|
| Application name | `LVH_APP_NAME` | Replaces the *display* name only. Also set core `PS_SHOP_NAME` (currently the string `lushviewhotel.net` — a defect, see §6) |
| Short name / initials | `LVH_APP_SHORT` | Sidebar collapsed state, favicon fallback |
| Primary colour | `LVH_COLOR_PRIMARY` | CSS custom property, injected into both themes |
| Secondary colour | `LVH_COLOR_SECONDARY` | CSS custom property |
| Accent / danger colours | `LVH_COLOR_ACCENT`, `LVH_COLOR_DANGER` | CSS custom properties |
| Logo (guest site) | `img/lvh/logo.jpg` | Mirrors `img/logo.jpg` |
| Logo (invoice / PDF) | `img/lvh/logo_invoice.jpg` | Mirrors `img/logo_invoice.jpg` |
| Logo (email) | `img/lvh/logo_mail.jpg` | Mirrors `img/logo_mail.jpg` |
| Favicon | `img/lvh/favicon.ico` | Mirrors `img/favicon.ico` |
| Login page branding | `LVH_LOGIN_TITLE`, `LVH_LOGIN_BG` | Consumed by the login template override |
| Email footer / address | `LVH_EMAIL_FOOTER` | Injected as a Smarty variable |
| PDF / invoice footer | `LVH_PDF_FOOTER` | Consumed by `pdf/` template overrides |
| Booking confirmation text | `LVH_CONFIRMATION_NOTE` | Order-confirmation hook |
| Support contact | `LVH_SUPPORT_EMAIL`, `LVH_SUPPORT_PHONE` | Replaces upstream support links |

**Implementation route:** the module registers hooks (`displayHeader`, `displayBackOfficeHeader`,
`displayFooter`, `actionEmailVars`, `displayInvoiceFooter`) and injects the values as Smarty
variables plus a generated stylesheet of CSS custom properties. Templates then reference
`var(--lvh-color-primary)` instead of hard-coded colours. This means **rebranding is a data
change, not a code change** — you can re-colour the whole product from one form.

**Assets that must physically exist** (6 files today, all currently QloApps artwork):
`img/favicon.ico`, `img/logo.jpg`, `img/logo_invoice.jpg`, `img/logo_mail.jpg`,
`img/logo_stores.gif`, `img/logo_stores.png`. We add `img/lvh/` originals and copy them over the
core paths at install, so both core code that hard-codes `img/logo.jpg` and our own templates
resolve correctly. **I need you to supply the logo artwork** — I cannot invent a brand identity.

---

## 4. Branding surface area — measured, not estimated

Occurrences of the string "QloApps" in the tree (excluding vendored `tools/`):

| Location | Occurrences | Guest-visible? | Approach |
|---|---|---|---|
| `install-dev/` | 1,792 | Only during install | Leave. The installer is deleted after install (§4.3 of the audit), so branding it is wasted effort and creates upgrade conflicts |
| `modules/` | 1,002 | Some | Only touch the modules we ship; leave upstream module internals alone |
| `admin-dev/` | 216 | No (staff only) | Re-skin via override + CSS; targeted text replacement in the few staff-visible strings |
| `classes/` | 88 | No | **Do not touch** — these are comments, log messages and exception text |
| `controllers/` | 64 | Some | Only via `override/` |
| `themes/` | 42 | **Yes** | Highest priority — see below |
| `pdf/` | 6 | Yes (invoices) | Handle via `LVH_PDF_FOOTER` + template override |
| `mails/` | 0 | — | Email templates contain no QloApps string; branding comes from `PS_SHOP_NAME` and the logo |
| `translations/` | 0 | — | Nothing to do |
| **Total** | **3,102** | | |

**The guest-facing work is 8 lines, not 3,102.** In `themes/hotel-reservation-theme/`:

| File:line | Content | Action |
|---|---|---|
| `header.tpl:40` | `<meta name="generator" content="QloApps {{ _QLO_VERSION_ }}" />` | Replace with your own generator string or remove |
| `header.tpl:80` | `<!-- Built with QloApps -->` | **Legal review needed** — see §5 |
| `js/occupancy.js:225,332,346` | `$(document).trigger("QloApps:updateRoomOccupancy")` | ⚠️ **DO NOT CHANGE** |
| `js/product.js:1043,1047,1358` | `$(document).on('QloApps:updateRoomQuantity')` | ⚠️ **DO NOT CHANGE** |

**The six JavaScript occurrences are not branding.** They are jQuery custom-event *namespaces*
used to wire the occupancy selector to the booking form. Renaming them breaks room selection and
therefore the booking funnel — the single most important flow on the site. A bulk find-and-replace
across the theme would silently destroy it. This is the main reason the rebrand must be surgical.

---

## 5. Licence constraints on rebranding — read this before promising anything

Core is **OSL-3.0**; modules are **AFL-3.0**. The relevant clauses, quoted from `LICENSE.md`:

- **§1(c)** — the right to distribute or communicate derivative works to the public comes
  *"with the proviso that copies of Original Work or Derivative Works that You distribute or
  communicate shall be licensed under this Open Software License."* This is a **copyleft**
  condition: derivative works stay OSL-3.0.
- **§4 Exclusions** — *"Neither the names of Licensor, nor the names of any contributors … nor
  any of their trademarks or service marks, may be used to endorse or promote products derived
  from this Original Work without express prior permission … No license is granted to the
  trademarks of Licensor even if such marks are included in the Original Work."*
- **§5 External Deployment** — using the work *"as an application intended for use over a
  network"* counts as distribution under §1(c). **A public hotel booking website is exactly
  that.**
- **§6 Attribution Rights** — *"You must retain, in the Source Code of any Derivative Works that
  You create, all copyright, patent, or trademark notices from the Source Code of the Original
  Work."*

**What this means in practice:**

| You want to | Permitted? |
|---|---|
| Rename the application, own logo, own favicon, own colours | ✅ Yes |
| Remove QloApps from guest-facing UI, admin UI, emails, PDFs, booking confirmations | ✅ Yes |
| Remove QloApps from the login screen, dashboard, sidebar, footer | ✅ Yes |
| Use the QloApps name/logo to *promote* your product | ❌ No — §4 |
| Strip the OSL/AFL licence headers from PHP source files | ❌ No — §6 |
| Delete `LICENSE.md` or module licence headers | ❌ No — §6 |
| Claim sole authorship of the whole system | ❌ No — §6 |
| Relicense the derivative work as proprietary/closed | ⚠️ **§1(c) + §5 say no** for the parts derived from OSL core |
| License your **own original modules** (`lvh*`) as you choose | ✅ Likely yes — they are your work, though anything that links OSL code inherits obligations |

**Practical reading:** you can absolutely have an independently branded product that guests and
staff experience as *yours*. What OSL-3.0 does not let you do is close the source of the
derivative work. Note this is not a new burden — **the live site already runs QloApps publicly
today**, so the obligation already applies.

**Action for you:** get a legal read before we finalise the branding module, specifically on
(a) whether the `<!-- Built with QloApps -->` comment in `header.tpl:80` is an attribution
notice under §6 or merely a comment, and (b) how you want your own `lvh*` modules licensed.
I am not a lawyer and this is not legal advice; I am flagging exactly which text to put in front
of one. This is item 25 in the existing action plan.

---

## 6. Configuration defects found in the live database

These are cheap fixes with high visibility. All are in `qlo_configuration` or
`qlo_htl_branch_info` and need no code:

| Setting | Live value | Problem / action |
|---|---|---|
| `PS_SHOP_NAME` | `lushviewhotel.net` | **The domain was typed into the shop-name field.** This string is used as the email sender name (`classes/Mail.php:169`), in email subjects (`classes/Mail.php:334`), as `{shop_name}` in every mail template (`classes/Mail.php:377`) and in page meta titles (`classes/Meta.php:240`). Set to **`Lush View Hotel`** |
| `MB_CANCEL_URL` | `http://www.yoursite.com` | **Leftover placeholder** from a module's default config. Point it at the real site or disable the feature |
| `qlo_htl_branch_info.latitude` / `.longitude` | `0.00000000` | Map pin for the property is at Null Island. Set the real Tema coordinates |
| `qlo_htl_branch_info.check_in` | `13:00` | ✅ **Already matches your requirement** |
| `qlo_htl_branch_info.check_out` | `12:00` | ✅ **Already matches your requirement** |
| `PS_TIMEZONE` | `Africa/Accra` | ✅ Already correct |
| `PS_CURRENCY_DEFAULT` | `1` → `GHS`, `₵`, "Ghana Cedis", active | ✅ **GHS is already the default currency** |
| `PS_SHOP_COUNTRY_ID` | `94` → `iso_code=GH`, `call_prefix=233` | ✅ Ghana already selected |
| `PS_LANG_DEFAULT` | `1` → English | ✅ Single-language install |
| `QHR_MAX_IMAGES_PER_REVIEW` | `5` | Review module accepts **5 image uploads per review** — the upload path behind CVE-2025-67325. Another reason to disable `qlohotelreview` now |
| `PS_ALLOW_EMP_MAX_ATTEMPTS` | `3` | ✅ Login throttling already enabled — keep it |
| `PS_MAIL_METHOD` / `PS_MAIL_SERVER` | `1` (SMTP) / `smtp.…` | SMTP is configured; staging must **not** reuse these credentials (§ staging rules in doc 03) |

**Net effect on your Module 9:** currency, timezone, country, language, check-in and check-out
are *already* what you specified. Module 9's real work is a consolidated settings screen, tax
configuration (Ghana's Act 1151 rules are already prepared in `localization/gh.xml` on this
branch), invoice configuration and email settings — not re-entering basics.

---

## 7. Administrative dashboard redesign

The live site already runs eleven `dash*` modules (`dashactivity`, `dashoccupancy`,
`dashtrends`, `dashavailability`, `dashperformance`, `dashgoals`, `dashinsights`,
`dashproducts`, `dashguestcycle`, plus `graphnvd3` for charts). QloApps' dashboard is a
**hook-based grid**: modules register as dashboard zones and the controller renders them.

So the redesign is:

1. **Keep the hook mechanism** — it is why the existing widgets work and it lets us add ours
   without editing the controller.
2. **Add `lvhdashboard`** registering the widgets your brief asks for that are missing:
   today's check-ins, today's check-outs, occupied vs available rooms, pending reservations,
   revenue overview, payment summary, recent activity, quick actions.
3. **Re-skin via CSS custom properties + an `override/controllers/admin/AdminDashboardController.php`**
   if template changes are needed, rather than editing `admin-dev/` in place.
4. **Sidebar and navigation**: QloApps builds the admin menu from module-registered tabs
   (`qlo_tab`, `qlo_tab_lang`). Our modules register their own tabs, so navigation is
   data-driven — no template surgery, and new modules appear automatically.

The admin login page is `admin-dev/login.php` with templates under
`admin-dev/themes/default/template/controllers/login/` and styles in
`admin-dev/themes/default/sass/controllers/_login.sass`. Note the theme ships **precompiled
CSS** and there is no Node/npm build in this repository, so SASS edits will not take effect
unless we also ship compiled CSS. **Plan: override with our own stylesheet, do not rely on the
SASS toolchain** — that keeps us inside cPanel's constraints.

---

## 8. Module standard (applies to every `lvh*` module)

Each module directory follows one layout, so any of them can be handed to another developer:

```
modules/lvhhousekeeping/
├── lvhhousekeeping.php          ← module class: install/uninstall, hooks, permissions
├── config.xml                   ← module metadata
├── logo.png
├── install/
│   ├── install.sql              ← CREATE TABLE (idempotent: IF NOT EXISTS)
│   └── uninstall.sql            ← DROP TABLE (only on explicit uninstall)
├── upgrade/
│   └── upgrade-1.1.0.php        ← versioned migrations, one file per version step
├── controllers/
│   ├── admin/                   ← AdminLvh*Controller.php
│   └── front/                   ← if guest-facing
├── classes/                     ← models, one ObjectModel per table
├── views/
│   ├── templates/admin/
│   ├── templates/front/
│   ├── templates/hook/
│   ├── css/  js/
└── README.md                    ← purpose, permissions, tables, hooks, config keys
```

Non-negotiable rules for all of them:

- **Database access only through `Db::getInstance()`** with `pSQL()`, `(int)`, `(float)`
  casting or parameterised `execute()`/`executeS()`. Never string-concatenated user input —
  the `bo_query` SQL-injection CVEs this release fixes are exactly that mistake.
- **All template output escaped.** `{$var|escape:'html':'UTF-8'}` in Smarty;
  `Tools::safeOutput()` in PHP. CVE-2026-89268 and CVE-2026-103587/88/89 are unescaped-output
  bugs.
- **CSRF**: use the framework's token helpers on every state-changing admin form
  (`Tools::getAdminTokenLite()` / the `token` field in admin controllers). Never hand-roll.
- **Permissions**: register a tab per module and gate actions on `Profile`/`Access`
  (`qlo_access`, which 1.8.0 extends with a `kpi` column). Receptionist, housekeeping,
  accounting and administrator roles are `qlo_profile` rows — data, not code.
- **Migrations are versioned and forward-only within a module**, matching the framework's own
  `upgrade/upgrade-<version>.php` convention, which `Module::loadUpgradeVersionList()`
  (`classes/module/Module.php:615-670`) discovers automatically.
- **Install must be idempotent** and uninstall must not silently destroy data — `uninstall.sql`
  runs only on explicit uninstall, and the README states what it drops.

⚠️ **A trap the audit already found upstream.** `Module::loadUpgradeVersionList()` returns
success even when no matching upgrade file exists, so a module's version gets stamped in the
database **with no migration run and no error raised**. That is precisely how a 1.7.0 → 1.8.0
file-copy silently corrupts a database. Every `lvh*` module must therefore ship an upgrade file
for *every* version step it declares, and our CI must check that the declared version has a
matching upgrade path.

---

## 9. Mapping your nine required modules onto reality

| # | Your module | Build as | Reuse from 1.8.0 | Net-new work |
|---|---|---|---|---|
| 1 | Dashboard | `lvhdashboard` | 11 `dash*` modules + `graphnvd3`, hook-based grid | Check-in/out today, occupied/available, pending, quick actions, redesigned shell |
| 2 | Room Management | extend `hotelreservationsystem` via override | `htl_room_type`, `htl_room_information`, `htl_room_status`, `htl_room_disable_dates`, `htl_connected_room`, `htl_amenity`, `htl_image` + `htl_image_category` | Maintenance & housekeeping states (→ module 6), amenity UI |
| 3 | Reservation Management | extend core + `lvhdashboard` links | Full booking engine, `htl_booking_detail`, refund rules, **new `htl_booking_status` + `htl_booking_status_history`** | Walk-in / telephone / online channel tagging, modification UI |
| 4 | Guest Management | `lvhguests` | `qlo_customer` (1,257), `qlo_guest` (22,725), new `customer.id_country` | ID documents, preferences, special requests, stay history view |
| 5 | Payment Management | `lvhpayments` (+ recover `cashpayment`) | `qlo_order_payment` (2,058), `qlo_order_payment_detail` (+`receipt_number`), `qlo_order_slip` (+`remark`), `qlo_order_return` (+`event_type`) | **Normalised method dictionary**, MoMo, partial payments, outstanding balances, refunds, daily report |
| 6 | Housekeeping | `lvhhousekeeping` | `htl_room_status`, new `htl_booking_status_history` (checkout events) | **Almost entirely net-new**: dirty/clean/inspection/maintenance, assignments, schedules, activity log |
| 7 | Staff & Permissions | `lvhauditlog` + profile data | `qlo_employee` (15), `qlo_access` (+`kpi`), `qlo_profile`, `PS_ALLOW_EMP_MAX_ATTEMPTS=3` | Role presets, **activity log and login history are net-new** |
| 8 | Reports & Analytics | `lvhreports` | New `qlohotelreports` module (#1791), 15 `stats*` modules | Excel/PDF export, the specific report set you listed |
| 9 | Hotel Settings | `lvhsettings` | `qlo_configuration` (459 rows), `qlo_htl_branch_info`, `localization/gh.xml` | Consolidated screen; tax rules for Act 1151; invoice config |

**Recommended build order** (each independently testable, per your §9.7):
`lvhbranding` → `lvhsettings` → `lvhpayments` → `lvhhousekeeping` → `lvhdashboard` →
`lvhguests` → `lvhauditlog` → `lvhreports`.

Rationale: branding first because every later module should render through it; settings second
because it holds configuration the others read; payments third because it is the highest-value
operational gap and the one with a live custom module to reconcile.

**Before building `lvhreports`, evaluate the bundled `qlohotelreports`** (#1791, new in 1.8.0,
not installed live). If it covers daily/monthly/annual revenue and occupancy, we extend it
rather than duplicate it — your own instruction.

---

## 10. GreenGeeks / cPanel constraints the architecture must respect

| Constraint | Design consequence |
|---|---|
| No root, no SSH-only tooling | Everything deployable by File Manager upload + phpMyAdmin import |
| **No Composer build needed** | `composer.json` has zero package dependencies; libraries are vendored in `tools/`. Confirmed: no `vendor/`, no `autoload` section |
| **No Node/npm build** | No `package.json`/`Gruntfile`. Admin CSS ships precompiled → we override with our own stylesheet instead of editing SASS |
| `max_execution_time ≥ 500` required | Shared plans often cap lower. Long migrations must be **chunked and resumable**, not one long script |
| `memory_limit ≥ 128M`, `upload_max_filesize ≥ 16M` | Verify in cPanel → Select PHP Version → Options. A 15 MB SQL import may need raising or splitting |
| `soap` extension required | Not always enabled on shared hosting — **verify before anything else** |
| `allow_url_fopen = On` required | Sometimes disabled for security; the installer and the 1-Click Upgrade add-on both need it |
| Cron via cPanel, not system cron | Use the new `qlocrontaskmanager` (#1714) or a cPanel cron job hitting a token-protected URL |
| No long-running processes | No queue workers, no websockets. Notifications by mail/cron only |
| Staging must not send real guest email | Override `PS_MAIL_METHOD` to a log/smtp-sink on staging; never copy production SMTP credentials |

**Verification script to run in cPanel → Terminal (or as a temporary PHP file):**

```
php -r 'echo PHP_VERSION,"\n";foreach(["gd","pdo_mysql","curl","soap","openssl","simplexml","zip"] as $e){printf("%-12s %s\n",$e,extension_loaded($e)?"OK":"MISSING");}
printf("memory_limit        %s\nmax_execution_time  %s\nupload_max_filesize %s\nallow_url_fopen     %s\n",
ini_get("memory_limit"),ini_get("max_execution_time"),ini_get("upload_max_filesize"),ini_get("allow_url_fopen"));'
```

Expected: PHP 8.4.27, all seven extensions OK, memory_limit ≥128M, max_execution_time ≥500,
upload_max_filesize ≥16M, allow_url_fopen On. **Send me the output** and I will confirm whether
1.8.0 will install on this account or what must be changed first.
