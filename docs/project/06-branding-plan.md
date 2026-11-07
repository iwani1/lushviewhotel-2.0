# Branding plan — from "QloApps / Hotel Prime" to Lush View Hotel

Goal: every guest-facing surface says Lush View Hotel, without editing core
application files. The licence permits this: OSL-3.0 (core) and AFL-3.0
(modules) allow rebranding of UI, logo and name; what they require is that
source notices and `LICENSE.md` stay, and that derivative works communicated
publicly remain under the same licences. Nothing here relicenses anything.

Reconnaissance: 2026-11-07, against QloApps 1.8.0.0 in this repository, a
working local install (`qloapps_fresh`), and the live site
`https://lushviewhotel.net` (QloApps 1.7.0).

---

## 1. The surface map

| # | What a guest sees | Where it actually comes from | How it changes | Core edit needed? |
| --- | --- | --- | --- | --- |
| 1 | Header logo, its `title`/`alt` | `{$logo_url}`, `{$shop_name}` — `themes/hotel-reservation-theme/header.tpl:172-174` → `PS_LOGO`, `PS_SHOP_NAME` | Preferences → Themes (logo, mobile logo, mail logo — `AdminThemesController.php:146-168`); Website name (row 8) | **No** |
| 2 | Browser tab title | `Meta.php:240` → `<page meta_title> - <PS_SHOP_NAME>` | Preferences → SEO & URLs, each CMS page's meta title, and row 8 | **No** |
| 3 | Footer copyright name | `hotelreservationsystem.php:360` feeds `WK_HTL_CHAIN_NAME` **from `PS_SHOP_NAME`**; rendered by `views/templates/hook/copyRight.tpl:3` | Row 8 | **No** |
| 4 | "From" name and `[subject]` prefix on outgoing email | `PS_SHOP_NAME` (`classes/Mail.php:169,334,377`) | Row 8 | **No** |
| 5 | Invoice / PDF header | `PS_SHOP_NAME` + logo in `pdf/` templates | Rows 1 and 8 | **No** |
| 6 | Favicon | `PS_FAVICON` | Preferences → Themes | **No** |
| 7 | Social icons in the footer | `modules/blocksocial` → `BLOCKSOCIAL_FACEBOOK`, `_TWITTER`, `_INSTAGRAM`, `_YOUTUBE`, `_PINTEREST`, `_VIMEO`, `_RSS`; template already theme-overridden at `themes/hotel-reservation-theme/modules/blocksocial/blocksocial.tpl` | Modules → Social network links | **No** |
| 8 | **Website name, email, phone, address** — one page owns all four | `AdminHotelGeneralSettingsController.php:232-300`: `PS_SHOP_NAME`, `PS_SHOP_EMAIL`, `PS_SHOP_PHONE`, `PS_SHOP_ADDR1`, `PS_SHOP_ADDR2`, `PS_SHOP_CODE`, `PS_SHOP_DETAILS`, `WK_HTL_ESTABLISHMENT_YEAR`. Tab installed as "Hotel General Configuration" (`hotelreservationsystem.php:486`). Note the classic Preferences → Store Contacts tab (`AdminStoresController`, which holds the same keys) is **commented out** in `install-dev/data/xml/tab.xml:237-239` | Back office → Hotel General Configuration → section "Website Contact Details" | **No** |
| 9 | Per-branch hotel name, phone, address, email on booking pages | `HotelBranchInformation` (`email`, `map_formated_address`) joined to `qlo_address` (`phone`, `address1`, `city`, `postcode`) | Back office → hotel/branch management | **No** |
| 10 | Homepage hero, "Explore the Interiors!", interior gallery, amenities and features blocks | module content: `wkabouthotelblock`, `wkhotelfeaturesblock` (demo defaults at `WkHotelInteriorImage.php:152,197`, `WkHotelFeaturesData.php:154`) | Each module's configure page | **No** |
| 11 | CMS page copy and testimonials | `qlo_cms_lang` etc., seeded at install from `install-dev/langs/en/data/cms.xml`; testimonials in `wktestimonialblock` | Preferences → CMS; module configure pages | **No** |
| 12 | `<meta name="generator" content="QloApps 1.8.0">` | literal, `header.tpl:40` | template edit | **Yes — decision D1: leave it** |
| 13 | HTML comment `<!-- Built with QloApps -->` | literal, `header.tpl:80` | template edit | **Yes — decision D1: leave it** |
| 14 | Footer "…All rights reserved. \| Powered by **QloApps**" (link to qloapps.com) | literal, `modules/hotelreservationsystem/views/templates/hook/copyRight.tpl:3` — present in 1.8.0, **not** rendered on the live 1.7.0 site | **Removed by theme override — shipped, D6** | No core/module edit (new file only) |
| 15 | Back-office wordmark and login logo | literals in `admin-dev/` templates and assets | template/asset edits | Yes — deferred, staff-only |
| 16 | Installer wizard branding | `install-dev/` templates and assets | template/asset edits | Yes — deferred, runs once per install |
| 17 | JS event namespaces `QloApps:updateRoomOccupancy`, `:updateRoomQuantity`, `:afterBookingFormInit` (`js/product.js:1043,1047,1358`, `js/occupancy.js:225,332,346`) | functional contract between core JS and modules | **never rename** | **Never** |

Rows 1–11 — everything a guest actually reads — are configuration-, module- or
database-driven. The only guest-reachable literals are rows 12–14.

---

## 2. Live-site audit (`lushviewhotel.net`, captured 2026-11-07)

- **F1 — `PS_SHOP_NAME` is the domain, not the hotel.** Live page title is
  `Lush VIew Hotel - lushviewhotel.net`; per `Meta.php:240` the part after the
  dash *is* `PS_SHOP_NAME`. So the shop name — which also feeds the header logo
  `alt`, the footer copyright, the email From name, the email subject prefix
  and PDF headers — is currently a URL. Fix on the row 8 page.
- **F2 — the homepage meta title has a typo: `Lush VIew Hotel`** (capital `I`
  instead of lowercase `i`). It is the first thing Google and every browser tab
  shows. Fix in Preferences → SEO & URLs / the page's meta title.
- **F3 — three spellings of the name are in production at once:**
  `Lush View Hotel` (footer, testimonial text), `Lush VIew Hotel` (page title)
  and `Lush view hotel` (inside two testimonial bodies). Canonical form chosen
  by the owner: **Lush View Hotel** (see §3).
- **F4 — upgrade regression risk.** In 1.8.0 the footer copyright name comes
  from `PS_SHOP_NAME` (`hotelreservationsystem.php:360`), while live 1.7.0 shows
  `Lush View Hotel` there and `lushviewhotel.net` in the title. The legacy key
  `WK_HTL_CHAIN_NAME` still exists in the live database but 1.8.0 no longer
  reads it. **If F1 is not fixed before upgrading, the live footer, email From
  names and PDF headers will all change to "lushviewhotel.net".** Pre-upgrade
  gate B8.
- **F5 — demo data is still live.** The interior gallery renders alt/display
  names `Dummy Image 1` … `Dummy Image 12` (defaults in
  `WkHotelInteriorImage.php:197`), the heading is the module default `Explore
  the Interiors!` (`:152`), and the amenities blurb is the module default from
  `WkHotelFeaturesData.php:154`. Alt text is read by screen readers and by
  Google Images.
- **F6 — three identical blocks.** "Luxurious Rooms" appears three times with
  byte-identical copy, "Restaurants" three times identical, "Swimming Pool"
  three times as `Swimming Pool available`. Give each real copy or delete the
  duplicates.
- **F7 — the footer carries no contact information and no social icons**, even
  though `blocksocial` is installed and theme-overridden (row 7): all
  `BLOCKSOCIAL_*` values are empty. Phone and address exist in the settings
  (row 8) and the branch record (row 9) but are not surfaced in the footer.
- **F8 — the testimonials are already real and Lush View-branded** (three named
  guests with roles, deliberately not reproduced in this document). Keep them;
  only fix the `Lush view hotel` capitalisation inside two bodies (F3).
- **F9 — two domains and two addresses were published.** `lushviewhotel.com` (a
  separate site, not this codebase) shows `Nii Okoi Olaaye, Tema C22, Ghana`,
  `030 298 5459`, `info@lushviewhotel.com`, `lushview6@gmail.com`; Google and
  Facebook list `H71 Afariwa Rd, Tema, Ghana` and `+233 30 298 5459`. The owner
  has now resolved the address (see §3); the email and the fate of `.com` are
  still open.

---

## 3. Confirmed brand facts (owner decisions, 2026-11-07)

| Surface | Value to enter | Where | Status |
| --- | --- | --- | --- |
| Canonical hotel name | `Lush View Hotel` | Website name (row 8) | **confirmed** — also fixes F1, F2, F3, F4 |
| Address | `H71 Afariwa Rd, Tema, Ghana` | Address line 1 (row 8) and the branch record (row 9) | **confirmed** |
| Phone | `+233 30 298 5459` (local form `030 298 5459`) | Phone (row 8) | confirmed by the address choice; verify it is the number to publish |
| Contact / From email | `info@lushviewhotel.net`? | Website email (row 8) | **OPEN — owner typed `ino@lushviewhotel.net`**, which reads as a typo for `info@`. Must be confirmed *and* the mailbox must really exist on the `.net` domain, otherwise booking confirmations go out from an address that bounces |
| Establishment year | `2010` (current live value) | Website Launch Year (row 8) | keep unless wrong |
| Footer attribution | remove "Powered by QloApps" | theme override, D6 | **confirmed — shipped** |

Nothing in this table is hardcoded in PHP: business contact data belongs in the
database, entered once on the row 8 page, so it can change without a deploy.

---

## 4. Decisions recorded

- **D1 — keep rows 12 and 13.** The generator meta and the "Built with QloApps"
  comment are invisible to guests and preserve upstream attribution at zero
  cost. Approval-gated if a client contract ever demands their removal.
- **D2 — never rename row 17.** Those event namespaces are a functional
  contract; renaming them silently breaks module listeners.
- **D3 — `LvhBranding::brand()` is the read API** for later LVH modules
  (emails, reports, guest messages). They must never hardcode brand literals.
- **D4 — logo and favicon travel through the core mechanism** (Preferences →
  Themes → `PS_LOGO`/`PS_LOGO_MOBILE`/`PS_LOGO_MAIL`/`PS_FAVICON`), not
  module-copied files, so PDFs, emails and the theme all pick them up without
  overrides. Master source assets live in `modules/lvhbranding/views/img/`.
- **D5 — mail and PDF template changes go through the theme override chain**
  (`themes/hotel-reservation-theme/mails/…`), never by editing `mails/` or
  `pdf/` in place.
- **D6 — footer attribution removed via a theme-level module template override
  (owner-approved, shipped).** New files only:
  `themes/hotel-reservation-theme/modules/hotelreservationsystem/views/templates/hook/copyRight.tpl`
  plus the four `index.php` directory guards the theme's other override
  directories use. `classes/module/Module.php:2433-2438`
  (`_isTemplateOverloadedStatic`) resolves the theme copy **before** the
  module's own, so the module and core are untouched and the change survives
  module updates. Precedent: `blocksocial`, `blockcart`, `blocknewsletter`,
  `blockuserinfo`, `bankwire`, `cheque` in the same directory. Smarty needs a
  cache clear before the change is visible.
- **D7 — no duplicated brand data.** The core already owns name, email, phone,
  address (row 8), the branch record (row 9), logos (row 1) and social links
  (row 7). `lvhbranding` therefore stores **only** the two values the core has
  no home for — a tagline (the legacy `WK_HTL_TAG_LINE` / `WK_HTL_SHORT_DESC`
  keys appear solely in `deleteConfigVars()` and are never read or rendered in
  1.8.0) and a theme colour — and resolves everything else read-through to the
  core key. An earlier revision of the module stored name/email/phone/address
  and social URLs; that duplication was removed in v0.3.0.

---

## 5. The `lvhbranding` module (v0.3.0, shipped)

- Stores two keys: `LVH_BRAND_TAGLINE`, `LVH_BRAND_THEME_COLOR`. Existing
  values survive a reinstall; uninstall removes exactly those two.
- `LvhBranding::brand('NAME'|'EMAIL'|'PHONE'|'ADDRESS')` reads the core keys
  (`PS_SHOP_NAME`, `PS_SHOP_EMAIL`, `PS_SHOP_PHONE`, `PS_SHOP_ADDR1`);
  `brand('TAGLINE'|'THEME_COLOR')` reads this module's keys.
- `hookDisplayHeader` emits `<meta name="theme-color">` and
  `<meta property="og:site_name">`, so social shares and mobile browser chrome
  carry the brand. `og:site_name` is correct as soon as `PS_SHOP_NAME` is fixed
  — no module configuration required.
- The configure page shows the four core-owned values read-only, next to the
  exact page that edits each, so nobody types a brand value into the wrong
  place.

---

## 6. Ordered tasks

- **B1 — done.** Module, read API, header meta, configure page, this plan.
- **B1b — done (owner-approved, D6).** Footer attribution override.
- **B2 — owner, ~10 min, back office, local install first.** Hotel General
  Configuration → Website Contact Details: Website name `Lush View Hotel`,
  Website email (once §3 is confirmed), Phone `+233 30 298 5459`, Address line 1
  `H71 Afariwa Rd`, city/country Tema, Ghana. Then Preferences → Themes: logo,
  mobile logo, mail logo, favicon. Verify: browser tab title, header logo `alt`,
  footer copyright and a test email's From name all read `Lush View Hotel`.
- **B3 — owner, ~30 min, back office.** Kill the demo content: interior gallery
  display names (`Dummy Image N`) and the `Explore the Interiors!` heading in
  About Hotel Block; the amenities blurb and the three duplicated
  Rooms/Restaurants/Pool entries in Hotel Features Block; CMS fixture copy
  ("Hotel Prime" on a fresh install) under Preferences → CMS. Fix the
  `Lush VIew Hotel` meta title (F2) and the `Lush view hotel` capitalisation in
  the testimonials (F3, F8).
- **B4 — owner, ~5 min, back office.** Social links: Modules → Social network
  links → Facebook/Instagram/X (F7). Decide whether the footer should also show
  the phone and address from row 8.
- **B5 — logo design.** Generate candidate marks, pick one, store masters in
  `modules/lvhbranding/views/img/`, upload via B2.
- **B6 — guest email audit.** Grep `mails/en/*.html` and module mail templates
  for literal "QloApps"/"Webkul" strings; override via the theme mail chain
  (D5).
- **B7 — PDF audit.** Same for `pdf/`, using whatever override path exists for
  PDF templates; research first, edits second.
- **B8 — pre-upgrade branding gate (F4).** Before any 1.7.0 → 1.8.0 upgrade of
  the live site: `PS_SHOP_NAME` must already be `Lush View Hotel`, and the D6
  override must be deployed with the new code, or the upgrade silently
  rebrands the footer and adds the "Powered by QloApps" link back.
- **Deferred, approval-gated:** rows 15 and 16.

Production rollout of B2–B4 is a separate scheduled content change against the
live site. Nothing in this plan touches production.

---

## 7. Verification checklist

- [ ] Browser tab title on every page type ends in `- Lush View Hotel`, never a
      domain, never `VIew`.
- [ ] Footer copyright reads `© 2010-<current year> Lush View Hotel. All rights
      reserved.` with **no** "Powered by QloApps".
- [ ] View-source of the homepage: `og:site_name` = Lush View Hotel,
      `theme-color` present, generator meta still QloApps (D1, expected).
- [ ] Zero hits for `Dummy Image`, `Hotel Prime`, `Explore the Interiors` and
      `QloApps` (except D1 rows) in rendered guest pages.
- [ ] Test booking-confirmation email From name = Lush View Hotel (send to
      yourself only; locally use the "Never send emails" or log-only method,
      never real SMTP against live data).
- [ ] Invoice PDF header = Lush View Hotel + new logo.
- [ ] Social icons render in the footer.
- [ ] `git status` shows no core edits: every changed file lives under
      `modules/lvhbranding/`, `themes/hotel-reservation-theme/modules/…`
      (override only, D6), `docs/`, or `dev/`.
