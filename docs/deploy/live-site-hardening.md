# Live-site hardening — lushviewhotel.net (QloApps 1.7.0)

Stop-gap measures to apply to the **production site** while the 1.7.0 → 1.8.0 migration is
planned. These address audit findings §2.2 and §2.3.

Nothing here requires downtime or a code deploy. Items 1 and 2 are the urgent ones.

---

## 1. Disable the `qlohotelreview` module — URGENT

The live site runs QloApps 1.7.0. **CVE-2025-67325** is an unauthenticated unrestricted file
upload in the hotel review feature leading to **remote code execution**, published as
affecting versions ≤1.7.0. The upstream fix (a strict `jpg/png/jpeg` allowlist plus
`ImageManager::resize()` re-encoding) is present in the 1.8.0 tree in this repository and
**absent from v1.7.0** — verified by commit ancestry.

`qlohotelreview` ships in v1.7.0 (57 bundled modules), so the code path is present.

**Action:** Back office → Modules → search `Hotel Review` → **Disable**. Re-enable after the
upgrade to 1.8.0.

This costs guest review collection temporarily. That is a far better trade than an
unauthenticated RCE on a system holding guest names, addresses, booking history and payment
records.

> Scope note: the fix's absence from v1.7.0 is confirmed. Whether the module is currently
> *enabled* on the live instance could not be verified from outside, and it is possible an
> operator back-ported a hotfix. Check the module's state in the back office and treat this
> as verify-then-remediate.

---

## 2. Block public access to version-disclosure files — URGENT

`https://lushviewhotel.net/CHANGELOG.txt` currently serves the full release history, and
`https://lushviewhotel.net/composer.json` serves the dependency manifest. Together these tell
an attacker the exact running version in two requests, which converts a public CVE list into
a targeted exploit selection.

Good news from the same probe: `/install-dev/` and `/admin-dev/` both return the QloApps 404
page, so the installer is removed and the back office is **not** on its default path. That
part of the deployment is already correct.

### Apache

Add to the **site root `.htaccess`** (or the vhost). Requires `mod_rewrite`, which QloApps
already depends on for friendly URLs.

```apache
# --- QloApps: block version and metadata disclosure ---
<IfModule mod_rewrite.c>
  RewriteEngine On

  # Release metadata: discloses the exact running version
  RewriteRule ^(CHANGELOG\.txt|composer\.json|composer\.lock)$ - [F,L,NC]

  # Project documentation: discloses stack, conventions and internal paths
  RewriteRule ^(README\.md|AGENTS\.md|CONTRIBUTING\.md|SECURITY\.md|LICENSE\.md)$ - [F,L,NC]

  # VCS and CI configuration
  RewriteRule ^(\.travis\.yml|\.gitignore|\.gitattributes)$ - [F,L,NC]
</IfModule>

# Defence in depth if mod_rewrite is unavailable
<IfModule mod_authz_core.c>
  <FilesMatch "^(CHANGELOG\.txt|composer\.(json|lock)|README\.md|AGENTS\.md|CONTRIBUTING\.md|SECURITY\.md|LICENSE\.md|\.travis\.yml|\.gitignore)$">
    Require all denied
  </FilesMatch>
</IfModule>
<IfModule !mod_authz_core.c>
  <FilesMatch "^(CHANGELOG\.txt|composer\.(json|lock)|README\.md|AGENTS\.md|CONTRIBUTING\.md|SECURITY\.md|LICENSE\.md|\.travis\.yml|\.gitignore)$">
    Order allow,deny
    Deny from all
  </FilesMatch>
</IfModule>
```

### nginx

Add inside the `server` block, **before** the QloApps rewrite rules.

```nginx
# QloApps: block version and metadata disclosure
location ~* ^/(CHANGELOG\.txt|composer\.(json|lock)|README\.md|AGENTS\.md|CONTRIBUTING\.md|SECURITY\.md|LICENSE\.md|\.travis\.yml|\.gitignore)$ {
    deny all;
    return 404;
}
```

Returning `404` rather than `403` is deliberate: `403` confirms the file exists.

### Verify after applying

Each of these should return **404 or 403**, not content:

```
https://lushviewhotel.net/CHANGELOG.txt
https://lushviewhotel.net/composer.json
https://lushviewhotel.net/README.md
https://lushviewhotel.net/AGENTS.md
```

---

## 3. Restrict the `bo_query` parameter — mitigates SQLi

**CVE-2026-75497** and **CVE-2026-75498** are SQL injection flaws in the back office via the
`bo_query` parameter (`CustomerMessage.php` and `Address.php`), CVSS 7.2 / 8.6 v4. Both are
fixed upstream in commit `123c97c`, which is **absent from v1.7.0**.

Both require an **authenticated administrator** session, so the realistic threat is a phished
or brute-forced admin account rather than anonymous attack. Two mitigations:

### 3a. WAF rule

ModSecurity, tuned to avoid blocking legitimate back-office search text:

```apache
SecRule ARGS:bo_query "@rx (?i)(union\s+(all\s+)?select|information_schema|sleep\s*\(|benchmark\s*\(|load_file\s*\(|into\s+(out|dump)file)" \
  "id:1075498,phase:2,deny,status:403,log,\
   msg:'CVE-2026-75497/75498 QloApps bo_query SQLi attempt',\
   logdata:'%{MATCHED_VAR}'"
```

### 3b. Least-privilege the database user

Also the vendor-recommended mitigation for this CVE class. The QloApps DB user needs
`SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `ALTER`, `INDEX`, `DROP` **on its own schema
only**. Revoke:

- `FILE` — prevents `LOAD_FILE()` / `INTO OUTFILE` data exfiltration
- `SUPER`, `PROCESS`, `GRANT OPTION`
- any cross-database grants

```sql
-- inspect first
SHOW GRANTS FOR CURRENT_USER;
-- example tightening (adjust user/host/schema to match)
REVOKE FILE, SUPER, PROCESS, GRANT OPTION ON *.* FROM 'qloapps_user'@'localhost';
FLUSH PRIVILEGES;
```

Do **not** revoke `DROP`/`CREATE`/`ALTER` on the application schema — the installer and
module upgrades need them.

---

## 4. Confirm the host PHP version — gates the whole migration

QloApps 1.8.0 requires **PHP `>=8.2 <8.6`**. The live `composer.json` still declares
`>=5.4 <8.0`, but that manifest is stale — it is the identical blob shared by v1.5.2 through
v1.7.0, and the 1.7.0 changelog claims PHP 8.1–8.4 support was added. So the manifest does
**not** tell you what the host actually runs.

Check directly:

```bash
php -v
# or from a script the web server executes, since CLI and FPM versions often differ:
```
```php
<?php echo PHP_VERSION, "\n", PHP_SAPI, "\n";
```

**If the host is below PHP 8.2, upgrade PHP before attempting anything else.** This is the
most likely thing to derail the migration schedule, and it is independent of every other
decision.

Also confirm the required extensions are present — QloApps 1.8.0 needs `curl`, `dom`, `gd`,
`openssl`, `pdo_mysql`, `phar`, `simplexml`, `soap`, `zip`. Note that `mcrypt` is **not**
required; it was removed from PHP core in 7.2, and the `ext-mcrypt` constraint that upstream
still declared has been removed from `composer.json` on this branch (commit `f1f0749`).

```bash
php -m | grep -Ei 'curl|dom|gd|openssl|pdo_mysql|phar|simplexml|soap|zip'
```

---

## 5. Back up before any change

Take these **before** touching anything, and verify the backup restores — an unverified
backup is not a backup.

```bash
# Database
mysqldump --single-transaction --routines --triggers --default-character-set=utf8mb4 \
  DB_NAME > lushview-$(date +%F-%H%M).sql

# Uploaded media - these live under paths an upgrade can overwrite
tar czf lushview-media-$(date +%F-%H%M).tar.gz \
  img/ modules/*/views/img/ upload/ download/ mails/ themes/

# The config holding DB credentials
cp config/settings.inc.php settings.inc.php.bak
```

The gallery and testimonial images identified in audit §2.4 live under
`modules/wkabouthotelblock/views/img/hotel_interior/` and
`modules/wktestimonialblock/views/img/hotels_testimonials_img/` — both inside module
directories, which is exactly what an upgrade replaces.

---

## Order of operations

1. Back up (§5) — before anything else
2. Disable `qlohotelreview` (§1) — removes the RCE path
3. Block disclosure files (§2) — five minutes, stops version fingerprinting
4. Confirm PHP version (§4) — gates the migration
5. WAF rule + DB privileges (§3) — defence in depth until the upgrade lands
6. Then plan the migration per audit §2.9 — **do not file-copy the 1.8.0 tree over the live
   install**; it contains no 1.7.0 → 1.8.0 database migration
