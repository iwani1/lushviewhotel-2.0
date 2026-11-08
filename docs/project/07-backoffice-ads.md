# Back-office advertising and phone-home traffic

Owner request, 2026-11-07: "remove the ads from the application".

Short answer: the ads are **back office only**. The guest-facing site has no
advertising — the only promotional text there was the footer's "Powered by
QloApps" link, already removed by the theme override shipped in commit
`3bec776` (decision D6 in `06-branding-plan.md`).

Everything below was verified against QloApps 1.8.0.0 in this repository.

---

## 1. How the advertising actually works

The banner is not stored in the codebase. It is **fetched from Webkul's server
and injected into the admin panel**:

1. `admin-dev/themes/default/template/recomended-banner.tpl` is included by the
   Dashboard (`controllers/dashboard/helpers/view/view.tpl:105`) and by the
   Modules Catalog (`controllers/modules_catalog/content.tpl`). It prints two
   hidden containers and an inline `loadRecommendation();` call.
2. `js/admin.js:1724` defines `loadRecommendation()`, which POSTs
   `action=getRecommendationContent` to `index.php`.
3. `classes/controller/AdminController.php:4235-4297` handles it:
   `updateRecommendationContent()` calls `Tools::addonsRequest('recommendation')`
   once a day per page (`Tools::isFresh(..., _TIME_1_DAY_)`), decodes the JSON
   and writes the returned HTML to
   `cache/dashboard_recommendation.html` (`AdminDashboardController.php:30`) or
   `cache/catalog_recommendation.html` (`AdminModulesCatalogController.php:29`).
4. The JS injects that HTML into `#recommendation-wrapper` and fades it in. A
   close button sets a per-tab cookie flag (`recommendationClosed`).

### What leaves the server with every one of those requests

`Tools::addonsRequest()` (`classes/Tools.php:3375-3390`) POSTs to
`api.qloapps.com` (`config/defines_uri.inc.php:87-88`) over HTTPS:

| Field | Value sent |
| --- | --- |
| `version` | your QloApps version |
| `iso_lang` | back-office language |
| `iso_code` | default country (GH) |
| `shop_url` | **your shop domain** |
| `shop_url_ssl` | **your SSL domain** |
| `physical_uri` | install path |
| `mail` | **`PS_SHOP_EMAIL` — your business email address** |

The same function serves the version check, the module catalogues and the theme
catalogue, so this is not only an ad problem: it is a daily outbound disclosure
of the shop's identity to a third party. There is **no configuration switch** —
`Tools::$is_addons_up` (`classes/Tools.php:3374`) is a protected static that is
only ever set to `false` when a request fails.

---

## 2. Inventory of every promotional surface

| ID | Surface | Location | Renders for you? | Action |
| --- | --- | --- | --- | --- |
| A1 | Advertising banner (remote HTML) | Dashboard + Modules Catalog, via `recomended-banner.tpl` | **Yes** | **Blocked** by `lvhadminclean` |
| A2 | "Help Center" panel → `QloApps Addons` shop link | `dashboard/helpers/view/view.tpl:139-142` | **Yes** | **Commercial entry removed**; Documentation, Forum and Contact kept |
| A3 | "Help Center" panel as a whole | `dashboard/helpers/view/view.tpl:127-146` | Yes | Optional toggle, **off by default** (the docs and forum links are useful) |
| A4 | Upgrade-available panel | `dashboard/helpers/view/view.tpl:119-125`, fed from `config/xml/upgrade_available.xml` by `ajaxProcessCheckUpgrade()` (`AdminController.php:1124-1131`) | Yes, when a newer version exists | **Left alone** — it is a version notice, not an ad. Blocking it needs the deeper routes in §4 |
| A5 | "subscribe to a technical support plan" upsell | `controllers/backup/helpers/list/list_header.tpl:68-71` | **No** — wrapped in `{if $host_mode}`, cloud-hosting only | Nothing to do |
| A6 | "Do you think there should be one? Let us know!" | `helpers/modules_list/list.tpl:57` | Only on `AdminPayment` with no modules for your country | Nothing to do |
| A7 | Modules Catalog / Addons marketplace pages | `AdminModulesController.php:191,201,214,233,859`, `AdminThemesController.php:520,530`, `Module.php:1813,1825,1837,2044` | Only if you open those pages | **Left alone** — that is a feature (installing modules), not an ad. It does use the same `addonsRequest` |
| A8 | Footer "Powered by QloApps" (guest-facing) | `modules/hotelreservationsystem/.../copyRight.tpl:3` | Yes | **Removed** in `3bec776` (theme override, D6) |
| A9 | `<meta name="generator" content="QloApps …">` and `<!-- Built with QloApps -->` | `themes/hotel-reservation-theme/header.tpl:40,80` | Yes, invisible | **Left alone** — decision D1 |

---

## 3. What was implemented

New module `modules/lvhadminclean/` (v0.1.0, AFL-3.0). **No application file is
edited**; everything is injected through the `displayBackOfficeHeader` hook,
which QloApps prints inside `<head>` at
`admin-dev/themes/default/template/header.tpl:102` — after jQuery and
`js/admin.js` are loaded (lines 98-100) and before the inline
`loadRecommendation()` call in the page body. That ordering is what makes the
fix reliable rather than a race.

It does five things:

1. redefines `loadRecommendation()` / `closeRecommendation()` as no-ops, so the
   banner is never requested;
2. registers a `$.ajaxPrefilter` that aborts `getRecommendationContent` and
   `recommendationClosed` anyway, as a second line of defence;
3. hides `#recommendation-wrapper` and `#recommendation-wrapper-skeleton` with
   CSS, so a stale cached banner cannot flash on screen;
4. removes only the `QloApps Addons` entry from the Help Center panel, matching
   on the link's `href` so the useful documentation and forum links survive;
5. deletes `cache/dashboard_recommendation.html` and
   `cache/catalog_recommendation.html` on install and on save. These are
   generated ad caches, not application files.

Three toggles on its configure page: block the banner (on), strip the Addons
link (on), hide the whole Help Center panel (off).

Why a module instead of deleting the markup from the templates: a core edit is
two lines smaller, but it touches application files, is silently reverted by
every upgrade, and would have to be re-applied and re-reviewed each time. The
module is reversible in one click and survives upgrades.

---

## 4. Deeper options, not implemented (each needs an explicit decision)

- **Stop every `addonsRequest`, not just the ads.** One line —
  `classes/Tools.php:3374`, `protected static $is_addons_up = true;` → `false` —
  disables the banner, the version check and all catalogue downloads, so nothing
  is ever sent to `api.qloapps.com`. Cost: the Modules Catalog and Themes pages
  stop listing installable modules, and you get no upgrade notices. This edits a
  core file, so it needs approval and must be re-applied after each upgrade.
- **Block it at the host instead.** An outbound firewall rule on the server
  denying `api.qloapps.com` achieves the same thing with no code at all, and is
  invisible to upgrades. Cost: same lost functionality, and the requests are
  still attempted (they just fail, which sets `$is_addons_up = false` for the
  rest of that request).
- **Delete the markup (Route C).** Remove the two `{include
  file='recomended-banner.tpl'}` lines and the Help Center panel from the core
  admin templates. Cleanest HTML, but edits application files and is lost on
  upgrade. Approval-gated.

Recommended: keep the module. Revisit the host-level block only if the owner
wants the shop's identity to stop being disclosed entirely — that is a privacy
decision, and it belongs with the items in
`docs/deploy/security-closeout-runbook.md`.

---

## 5. Verification

After installing the module:

- [ ] Dashboard loads with no banner and no skeleton placeholder above the
      statistics row.
- [ ] Modules → Modules Catalog shows no banner.
- [ ] Help Center panel still lists Documentation, Forum and Contact, but not
      "QloApps Addons".
- [ ] Browser dev tools → Network, filtered to `index.php`: no request carrying
      `getRecommendationContent`, and no request to `api.qloapps.com` from the
      Dashboard.
- [ ] `ls cache/*recommendation*` on the server: no such files.
- [ ] Disabling the module brings the banner back on the next page load —
      proof that nothing was edited.

Local commands (rootless Podman):

```bash
podman-compose exec app sh -c 'ls -l /var/www/html/cache/*recommendation* 2>&1'
podman-compose exec app sh -c 'rm -f /var/www/html/cache/class_index.php; find /var/www/html/cache/smarty/compile /var/www/html/cache/smarty/cache -mindepth 1 -maxdepth 1 ! -name index.php -exec rm -rf {} +'
```
