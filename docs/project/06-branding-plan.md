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
| 1 | Header logo, its `title`/`alt` | `{$logo_url}`, `{$shop_name}` — `themes/hotel-reservation-theme/header.tpl:172-174` → `PS_LOGO`, `PS_SHOP_NAME` | Back office: Preferences → General (name), Appearance (logo, mobile logo, favicon) | **No** |
| 2 | Browser tab title | `Meta.php:240` → `<page meta_title> - <PS_SHOP_NAME>` | Back office: Preferences → SEO & URLs, and each CMS page's meta title | **No** |
| 3 | Footer copyright name | `hotelreservationsystem.php:360` feeds `WK_HTL_CHAIN_NAME` **from `PS_SHOP_NAME`**; rendered by `views/templates/hook/copyRight.tpl:3` | Same setting as #1 | **No** |
| 4 | "From" name and `[subject]` prefix on outgoing email | `PS_SHOP_NAME` (`classes/Mail.php:169,334,377`) | Same setting as #1 | **No** |
| 5 | Invoice / PDF header | `PS_SHOP_NAME` + logo in `pdf/` templates | Same settings as #1 | **No** |
| 6 | Favicon | `PS_FAVICON` | Back office: Appearance | **No** |
| 7 | Social icons in the footer | `modules/blocksocial` → `BLOCKSOCIAL_FACEBOOK`, `_TWITTER`, `_INSTAGRAM`, `_YOUTUBE`, `_PINTEREST`, `_VIMEO`, `_RSS`; template already theme-overridden at `themes/hotel-reservation-theme/modules/blocksocial/blocksocial.tpl` | Back office: Modules → Social network links | **No** |
| 8 | Hotel name, phone, address, email on booking pages | the **hotel branch record**: `HotelBranchInformation` (`email`, `map_formated_address`) joined to `qlo_address` (`phone`, `address1`, `city`, `postcode`) | Back office: Hotel Reservation System → hotel/branch settings | **No** |
| 9 | Homepage hero, "Explore the Interiors!", interior gallery, amenities and features blocks | module content: `wkabouthotelblock`, `wkhotelfeaturesblock` (demo defaults live in `WkHotelInteriorImage.php:152,197`, `WkHotelFeaturesData.php:154`) | Back office: each module's configure page | **No** |
| 10 | CMS page copy and testimonials | `qlo_cms_lang` etc., seeded at install from `install-dev/langs/en/data/cms.xml`; testimonials in `wktestimonialblock` | Back office: Preferences → CMS; module configure pages | **No** |
| 11 | `<meta name="generator" content="QloApps 1.8.0">` | literal, `header.tpl:40` | template edit | **Yes — decision D1: leave it** |
| 12 | HTML comment `<!-- Built with QloApps -->` | literal, `header.tpl:80` | template edit | **Yes — decision D1: leave it** |
| 13 | Footer "…All rights reserved. \| Powered by **QloApps**" (link to qloapps.com) | literal, `modules/hotelreservationsystem/views/templates/hook/copyRight.tpl:3` — **present in 1.8.0, not rendered on the live 1.7.0 site** | theme-level override (D6) | Module template — **approval-gated** |
| 14 | Back-office wordmark and login logo | literals in `admin-dev/` templates and assets | template/asset edits | Yes — deferred, staff-only |
| 15 | Installer wizard branding | `install-dev/` templates and assets | template/asset edits | Yes — deferred, runs once per install |
| 16 | JS event namespaces `QloApps:updateRoomOccupancy`, `:updateRoomQuantity`, `:afterBookingFormInit` (`js/product.js:1043,1047,1358`, `js/occupancy.js:225,332,346`) | functional contract between core JS and modules | **never rename** | **Never** |

Rows 1–10 — everything a guest actually reads — are configuration-, module- or
database-driven. The only guest-reachable literals are rows 11–13.

---

## 2. Live-site audit (`lushviewhotel.net`, captured 2026-11-07)

Findings are ordered by what they cost the business. Each one is fixable from
the back office unless stated otherwise.

- **F1 — `PS_SHOP_NAME` is the domain, not the hotel.** Live page title is
  `Lush VIew Hotel - lushviewhotel.net`; per `Meta.php:240` the part after the
  dash *is* `PS_SHOP_NAME`. So the shop name — which also feeds the header logo
  `alt`, the email From name, the email subject prefix and PDF headers — is
  currently a URL. Fix: set it to the hotel name (see F3 for the spelling).
- **F2 — the homepage meta title has a typo: `Lush VIew Hotel`** (capital `I`
  instead of lowercase `i`). It is the first thing Google and every browser tab
  shows. Fix in Preferences → SEO & URLs / the CMS page's meta title.
- **F3 — three spellings of the name are in production at once:**
  `Lush View Hotel` (footer, testimonial text), `Lush VIew Hotel` (page title)
  and `Lush view hotel` (inside two testimonial bodies). Pick one canonical
  form — recommended: **Lush View Hotel** — and use it everywhere.
- **F4 — upgrade regression risk.** In 1.8.0 the footer copyright name comes
  from `PS_SHOP_NAME` (`hotelreservationsystem.php:360`), while live 1.7.0
  shows `Lush View Hotel` there and `lushviewhotel.net` in the title. The
  legacy key `WK_HTL_CHAIN_NAME` still exists in the live database but 1.8.0 no
  longer reads it. **If F1 is not fixed before upgrading, the live footer will
  change from "Lush View Hotel" to "lushviewhotel.net".** Same for email From
  names and PDF headers. This is a pre-upgrade gate, not a cosmetic note.
- **F5 — demo data is still live.** The interior gallery renders alt/display
  names `Dummy Image 1` … `Dummy Image 12` (default set in
  `WkHotelInteriorImage.php:197`), the heading is the module default `Explore
  the Interiors!` (`:152`), and the amenities blurb is the module default from
  `WkHotelFeaturesData.php:154`. All are editable in the modules' configure
  pages; the alt text is read by screen readers and by Google Images.
- **F6 — three identical blocks.** "Luxurious Rooms" appears three times with
  byte-identical copy, "Restaurants" three times identical, "Swimming Pool"
  three times as `Swimming Pool available`. These are features-block entries
  that were never differentiated. Either give each real copy or delete the
  duplicates.
- **F7 — the footer carries no contact information and no social icons**, even
  though `blocksocial` is installed and theme-overridden (row 7): all
  `BLOCKSOCIAL_*` values are empty. The hotel's phone and address exist in the
  branch record (row 8) but are not surfaced in the footer.
- **F8 — the testimonials are already real and Lush View-branded** (three
  named guests with roles, kept out of this document deliberately). Keep them;
  only fix the `Lush view hotel` capitalisation inside two of the bodies (F3).
- **F9 — two domains and two addresses are published.** `lushviewhotel.com`
  (a separate site, not this codebase) shows `Nii Okoi Olaaye, Tema C22,
  Ghana`, `030 298 5459`, `info@lushviewhotel.com`, `lushview6@gmail.com`;
  Google and Facebook list `H71 Afariwa Rd, Tema, Ghana` and
  `+233 30 298 5459`. Owner must confirm the canonical address, canonical
  email, and whether `.com` stays live, redirects, or is retired. Until then
  nothing is hardcoded: the `lvhbranding` module ships with those fields empty
  on purpose.

---

## 3. Decisions recorded

- **D1 — keep rows 11 and 12.** The generator meta and the "Built with QloApps"
  comment are invisible to guests and preserve upstream attribution at zero
  cost. Removing them would mean editing a core theme template for no
  guest-visible gain. Approval-gated if a client contract ever demands it.
- **D2 — never rename row 16.** Those event namespaces are a functional
  contract; renaming them silently breaks module listeners.
- **D3 — brand strings live in `LVH_BRAND_*` Configuration keys**, owned by the
  `lvhbranding` module and read anywhere via `LvhBranding::brand('PHONE')`.
  Later LVH modules (emails, reports, guest messages) must use these, never
  literals.
- **D4 — logo and favicon travel through the core mechanism** (Appearance
  upload → `PS_LOGO`/`PS_FAVICON`), not module-copied files, so PDFs, emails
  and the theme all pick them up without overrides. Master source assets live
  in `modules/lvhbranding/views/img/`.
- **D5 — mail and PDF template changes go through the theme override chain**
  (`themes/hotel-reservation-theme/mails/…`), never by editing `mails/` or
  `pdf/` in place.
- **D6 — if the footer attribution (row 13) is ever removed, do it with a
  theme-level module template override:** add
  `themes/hotel-reservation-theme/modules/hotelreservationsystem/views/templates/hook/copyRight.tpl`.
  That is a **new file**, it leaves the module and core untouched, it survives
  module updates, and there is existing precedent in the same directory
  (`blocksocial`, `blockcart`, `blocknewsletter`, `blockuserinfo`, `bankwire`,
  `cheque`). Still approval-gated, because it changes what guests see and
  removes upstream attribution.
- **D7 — no duplicated brand data.** `lvhbranding` does not store social links
  (blocksocial owns them, row 7) and treats phone/address as a convenience copy
  of the branch record (row 8). Empty LVH keys fall back to the core keys
  (`NAME` → `PS_SHOP_NAME`, `EMAIL` → `PS_SHOP_EMAIL`). The core's
  `WK_HTL_TAG_LINE` / `WK_HTL_SHORT_DESC` are **not** used as a tagline source:
  in 1.8.0 they appear only in `deleteConfigVars()` and are never read or
  rendered, so the tagline is genuinely LVH-owned.

---

## 4. The `lvhbranding` module (v0.2.0, shipped)

- Stores six keys: name, tagline, email, phone, address, theme colour. Only
  name and email are seeded (from `PS_SHOP_NAME` / `PS_SHOP_EMAIL`); the rest
  start empty and are filled from the configure page, so the module never
  publishes an invented or unconfirmed contact detail (F9). Existing values
  survive a reinstall.
- `hookDisplayHeader` emits `<meta name="theme-color">` and
  `<meta property="og:site_name">`, so social shares and mobile browser chrome
  carry the brand. Because of the fallbacks, `og:site_name` is correct as soon
  as `PS_SHOP_NAME` is fixed — no module configuration needed.
- `LvhBranding::brand($short, $default)` is the read API for other LVH modules.
- The configure page documents rows 1–10 inline, so the owner is pointed at the
  real owner of each surface instead of typing values into the wrong place.
- Uninstall removes exactly its own keys.

---

## 5. Ordered tasks

- **B1 — done.** Module, keys, header meta, configure page, this plan.
- **B2 — owner, ~10 min, back office, local install first.** Preferences →
  General: shop name `Lush View Hotel`. Appearance: logo, mobile logo, favicon.
  Modules → LVH Branding → Configure: tagline plus the confirmed phone/address.
  Verify: browser tab title, header logo `alt`, footer copyright and a test
  email's From name all read `Lush View Hotel`.
- **B3 — owner, ~30 min, back office.** Kill the demo content: interior gallery
  display names (`Dummy Image N`) and the `Explore the Interiors!` heading in
  About Hotel Block; the amenities blurb and the three duplicated
  Rooms/Restaurants/Pool entries in Hotel Features Block; CMS fixture copy
  ("Hotel Prime" on a fresh install) under Preferences → CMS. Fix the
  `Lush VIew Hotel` meta title (F2) and the `Lush view hotel` capitalisation
  inside the testimonials (F3, F8).
- **B4 — owner, ~5 min, back office.** Social links: Modules → Social network
  links → fill Facebook/Instagram/X (F7). Decide whether the footer should also
  show the branch phone and address.
- **B5 — logo design.** Generate candidate marks, pick one, store masters in
  `modules/lvhbranding/views/img/`, upload via B2.
- **B6 — guest email audit.** Grep `mails/en/*.html` and module mail templates
  for literal "QloApps"/"Webkul" strings; override via the theme mail chain
  (D5).
- **B7 — PDF audit.** Same for `pdf/`, using whatever override path exists for
  PDF templates; research first, edits second.
- **B8 — pre-upgrade branding gate (F4).** Before any 1.7.0 → 1.8.0 upgrade of
  the live site: `PS_SHOP_NAME` must already be the hotel name, and the owner
  must have decided on row 13 (D6). Otherwise the upgrade silently rebrands the
  footer, email From names and PDF headers to a domain string.
- **Deferred, approval-gated:** rows 13, 14, 15.

Production rollout of B2–B4 is a separate scheduled content change against the
live site. Nothing in this plan touches production.

---

## 6. Verification checklist

- [ ] Browser tab title on every page type ends in `- Lush View Hotel`, never a
      domain, and never `VIew`.
- [ ] Footer copyright reads `© <year>-<current year> Lush View Hotel`.
- [ ] View-source of the homepage: `og:site_name` = Lush View Hotel,
      `theme-color` present, generator meta still QloApps (D1, expected).
- [ ] Zero hits for `Dummy Image`, `Hotel Prime`, `QloApps` (except D1 rows) and
      `Explore the Interiors` in rendered guest pages.
- [ ] Test booking-confirmation email From name = Lush View Hotel (send to
      yourself only; locally use the "Never send emails" or log-only method,
      never real SMTP against live data).
- [ ] Invoice PDF header = Lush View Hotel + new logo.
- [ ] Social icons render in the footer.
- [ ] `git status` shows no core edits: every changed file lives under
      `modules/lvhbranding/`, `themes/hotel-reservation-theme/modules/…`
      (override only, D6), `docs/`, or `dev/`.
