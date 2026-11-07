# Branding plan — from "QloApps / Hotel Prime" to Lush View Hotel

Goal: every guest-facing surface says Lush View Hotel, without editing core
application files. The licence permits this: OSL-3.0 (core) and AFL-3.0
(modules) allow rebranding of UI, logo and name; what they require is that
source notices and `LICENSE.md` stay, and that derivative works communicated
publicly remain under the same licences. Nothing here relicenses anything.

Reconnaissance date: 2026-11-07, against QloApps 1.8.0.0 in this repository and
against a working local install (`qloapps_fresh`).

---

## 1. The surface map

| # | What a guest sees | Where it actually comes from | How it changes | Core edit needed? |
| --- | --- | --- | --- | --- |
| 1 | Header logo, its `title`/`alt` | `{$logo_url}`, `{$shop_name}` in `themes/hotel-reservation-theme/header.tpl:172-174` → `PS_LOGO`, `PS_SHOP_NAME` | Back office: Preferences → General (name), Appearance (logo, mobile logo, favicon) | **No** |
| 2 | Browser tab title | `{$meta_title}` (header.tpl:33) → Meta/SEO + CMS meta titles | Back office: Preferences → SEO & URLs; CMS page meta | **No** |
| 3 | Front-page headline "Hotel Prime", demo paragraphs, testimonials | CMS page content installed from `install-dev/langs/en/data/cms.xml` (fixtures) plus home-block module settings | Back office: Preferences → CMS (edit the pages); module configure pages for the home blocks and testimonial block | **No** |
| 4 | "From" name on outgoing email | `PS_SHOP_NAME` (`classes/Mail.php:169,334,377`) | Same setting as #1 | **No** |
| 5 | Invoice / PDF header | `PS_SHOP_NAME` + logo in `pdf/` templates | Same settings as #1 | **No** |
| 6 | Favicon | `PS_FAVICON` | Back office: Appearance | **No** |
| 7 | `<meta name="generator" content="QloApps 1.8.0">` | literal, `header.tpl:40` | template edit | **Yes — decision D1: leave it** |
| 8 | HTML comment `<!-- Built with QloApps -->` | literal, `header.tpl:80` | template edit | **Yes — decision D1: leave it** |
| 9 | Back-office wordmark and login logo | literals in `admin-dev/` templates and assets | template/asset edits | Yes — deferred, staff-only surface |
| 10 | Installer wizard branding | `install-dev/` templates and assets | template/asset edits | Yes — deferred, runs once per install |
| 11 | JS event namespaces `QloApps:updateRoomOccupancy`, `:updateRoomQuantity`, `:afterBookingFormInit` (`js/product.js:1043,1047,1358`, `js/occupancy.js:225,332,346`) | functional contract between core JS and modules | **never rename** | **Never** |

The important finding: rows 1–6, the entirety of what a guest actually sees,
are configuration- or database-driven. The only guest-reachable literals are
rows 7–8, and both are invisible (a meta tag and a comment).

---

## 2. Decisions recorded

- **D1 — keep rows 7 and 8.** The generator meta and the "Built with QloApps"
  comment are invisible to guests, and keeping them preserves upstream
  attribution at zero cost. Removing them would require editing a core theme
  template for no guest-visible gain. Revisit only if a client contract
  demands their removal; the change is two lines and is approval-gated.
- **D2 — never rename row 11.** Those event namespaces are a functional
  contract; renaming them silently breaks module listeners.
- **D3 — brand strings live in `LVH_BRAND_*` Configuration keys**, owned by the
  `lvhbranding` module, read anywhere via `LvhBranding::brand('PHONE')`. Later
  LVH modules (emails, reports, guest messages) must use these, not literals.
- **D4 — logo and favicon travel through the core mechanism** (Appearance
  upload → `PS_LOGO`/`PS_FAVICON`), not through module-copied files, so PDFs,
  emails and the theme all pick them up without overrides. Master source
  assets live in `modules/lvhbranding/views/img/` for reuse and versioning.
- **D5 — mail and PDF template changes, if ever needed, go through the theme
  override chain** (`themes/hotel-reservation-theme/mails/…`) rather than
  editing `mails/` or `pdf/` in place.

---

## 3. The `lvhbranding` module (v0.1.0, shipped)

- Stores the eight brand keys (name, tagline, email, phone, address, Facebook,
  X, theme colour). Only `LVH_BRAND_NAME` and `LVH_BRAND_EMAIL` are seeded on
  install (from `PS_SHOP_NAME` / `PS_SHOP_EMAIL`); the rest start empty and are
  filled in from the configure page, so the module never publishes an invented
  contact detail. Existing values survive a reinstall.
- `hookDisplayHeader` emits `<meta name="theme-color">` and
  `<meta property="og:site_name">` so social shares and mobile browser chrome
  carry the brand.
- Back-office configure page edits the keys and points at the core settings
  and CMS pages that own rows 1–3, so nobody has to remember this document.
- `LvhBranding::brand($short, $default)` is the read API for other modules.
- Uninstall removes exactly its own keys and nothing else.

---

## 4. Ordered tasks

- **B1 — done.** Module skeleton, keys, header meta, configure page, this plan.
- **B2 — owner action, ~10 minutes, back office only.** Preferences → General:
  shop name `Lush View Hotel`. Appearance: upload logo, mobile logo, favicon.
  Modules → LVH Branding → Configure: fill tagline, phone, address and any
  social URLs with the real, verified values. Verify: header shows the new logo
  and `alt`; a test email's From name is the hotel, not a domain string.
- **B3 — owner action, ~30 minutes, back office only.** Preferences → CMS:
  replace the "Hotel Prime" fixture copy (headline, about paragraphs,
  testimonials — names "Calrk Kent" and "john Doe" included) with real Lush
  View content or delete the blocks. Then the home-block and testimonial
  module configure pages. Verify: front page carries no "Hotel Prime",
  "QloApps" or placeholder guest names.
- **B4 — logo design.** Generate two or three candidate marks, pick one, store
  masters in `modules/lvhbranding/views/img/`, upload via B2. Say the word and
  I will produce the candidates.
- **B5 — guest email audit.** Grep `mails/en/*.html` and module mail templates
  for literal "QloApps"/"Webkul" strings; where found, override via the theme
  mail chain (D5), never in place.
- **B6 — PDF audit.** Same for `pdf/` (invoice footer et al.), using whatever
  override path exists for PDF templates; research first, edits second.
- **B7 — deferred, approval-gated.** Back-office wordmark/login logo (row 9)
  and installer branding (row 10). Staff-only surfaces; do them last and only
  if the client cares.

Production rollout of B2/B3 is a separate deployment task against the live
site, scheduled like any other content change; nothing in this plan touches
production.

---

## 5. Verification checklist

- [ ] `grep -ri "hotel prime" ` against rendered front page: zero hits.
- [ ] View-source of the home page: `og:site_name` = Lush View Hotel;
      `theme-color` present; generator meta still QloApps (D1, expected).
- [ ] Test booking confirmation email From name = Lush View Hotel (send it to
      yourself only, with email method set to a real local sink, never SMTP).
- [ ] Invoice PDF header = Lush View Hotel + new logo.
- [ ] `git status` clean of core edits: every changed file lives under
      `modules/lvhbranding/`, `docs/`, or `dev/`.
