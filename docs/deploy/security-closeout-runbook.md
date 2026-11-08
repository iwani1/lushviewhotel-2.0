# Security close-out runbook — Lush View Hotel

Ordered, owner-executable checklist for the findings this project has verified.
Each item states why it matters, exactly what to do, how to verify, and what the
risk is if it is skipped. Items are independent; do them in order anyway, because
item 1 is the only one with an active public exposure.

Everything here acts on the **live production site and the GitHub repository**.
Nothing here touches the local development environment, and nothing in this
document requires downtime except item 6, which must be scheduled.

Deliberate omissions: this document does **not** record the object SHA of the
purged database export, any employee email address, or any password. Those are
known to the project owners and stay out of a public repository.

---

## 1. Make the GitHub repository private  —  URGENT, ~2 minutes, owner only

**Why.** On 2026-11-07 the repository was confirmed public (`isPrivate: false`)
and a 16,140,519-byte production database export, committed early in the
project's history and later purged by a force-push, was still served to
**unauthenticated** requests by object SHA (HTTP 200, byte-for-byte and
sha256-identical to the original). Force-push rewrites history; it does not
delete objects GitHub is already serving. While the repository is public,
anyone holding that SHA can download every guest record in it.

**Do.** GitHub → repository → **Settings → General → Danger Zone → Change
repository visibility → Make private**. Confirm with the repository name.
No tool can do this for you: the API call requires an Administration scope the
project token does not have, and GitHub gates visibility changes behind the
owner's session.

**Verify.** In a browser logged out of GitHub (or a private window), open the
repository URL: expect 404. The unauthenticated object fetch that previously
returned 200 will now return 404 as well — privacy removes public object access.

**Then.** Open a **GitHub Support** ticket asking them to purge the dangling
object from their storage, quoting the repository and the date range. Privacy
stops new access; only Support can remove the stored object.

**Risk if skipped.** Continued public availability of 1,257 guests' and 1,934
bookings' personal data. This is the single most urgent item in the document.

**Side effects to expect.** CI (GitHub Actions) keeps working on a private
repository. The open pull request is unaffected. Collaborators, if any, must be
re-invited.

---

## 2. Disable or reset live employee account id 10  —  ~2 minutes, owner only

**Why.** The live `qlo_employee` table contains one row (id 10) whose `passwd`
is a 13-character **cleartext** value — neither the legacy 32-character MD5
digest nor a 60-character bcrypt hash. Two consequences: the account can never
authenticate (the verifier matches neither scheme, so every attempt fails), and
the password itself is readable by anyone with database access. It was stored
by some broken write path at an unknown date.

**Do.** Live back office → **Administration → Employees**, find the account
corresponding to row id 10, and either disable it (if the person has left or
the account is unused) or reset its password (if it is still needed). Ask the
person whether they reused that password anywhere else; if yes, rotate there
too — a cleartext password in a database must be treated as compromised.

**Verify.** The row's `passwd` becomes a 60-character bcrypt hash, or the row's
`active` becomes 0:

```sql
SELECT id_employee, active, CHAR_LENGTH(passwd) AS len FROM qlo_employee WHERE id_employee = 10;
```

**Risk if skipped.** A live cleartext credential sits in the production
database indefinitely, and a staff member believes they have an account that
can never log in.

---

## 3. Rotate all live staff passwords  —  ~15 minutes, owner only

**Why.** The live schema stores passwords as MD5 digests salted with the
application cookie key (`varchar(32)`), a scheme with no per-user salt and no
work factor. It is only as secret as the cookie key, and the cookie key lives
in a PHP file on a shared host. Fifteen employee accounts use it.

**Do.** Live back office → **Administration → Employees** → reset each
password. Do it in one sitting. Use a password manager; QloApps requires at
least 8 characters.

**Verify.** Every row shows `CHAR_LENGTH(passwd) = 60` **only after** item 6
has widened the columns — until then a reset writes a 32-character MD5 again.
So treat this item and item 6 as a pair: widen first if you can schedule it,
otherwise rotate now and rotate again after the upgrade. Note which you did.

**Risk if skipped.** Continued reliance on an unsalted, fast hash for every
staff credential.

---

## 4. Disable the guest-review module on the live site  —  ~2 minutes, owner only

**Why.** The live installation is QloApps **1.7.0**, which predates the fix for
CVE-2025-67325 in the `qlohotelreview` module (fixed in `modules/qlohotelreview/classes/QhrHotelReview.php:290-310`
in 1.8.0; the fix commits are absent from the live tree). The live
`qlo_hotel_review`-family table holds **zero rows** — the hotel has no guest
reviews in the system — so disabling the module costs nothing and removes an
unpatched public attack surface.

**Do.** Live back office → **Modules and Services**, find **Hotel Review**
(`qlohotelreview`), **Disable** (uninstall if you are certain reviews will
always come from Google/Booking instead).

**Verify.** `https://lushviewhotel.net/` still renders; the review block no
longer appears; the module shows as disabled in the back office.

**Risk if skipped.** An unpatched, publicly reachable module on a booking site
that takes payment data flows seriously elsewhere.

---

## 5. Stop publishing the version fingerprint  —  ~5 minutes, owner or host

**Why.** The live web root serves `/CHANGELOG.txt` and `/composer.json`. Either
file lets anyone confirm the exact QloApps/PrestaShop version and therefore
which known vulnerabilities apply. Both are development artefacts with no
business being public.

**Do.** Delete both files from the live web root, or deny them in the site
`.htaccess`:

```apache
<FilesMatch "^(CHANGELOG\.txt|composer\.json|composer\.lock)$">
    Require all denied
</FilesMatch>
```

**Verify.** `curl -sI https://lushviewhotel.net/CHANGELOG.txt` returns 404 or
403, not 200. Same for `/composer.json`.

**Risk if skipped.** Free reconnaissance for anyone targeting the site.

---

## 6. Widen the password columns on production, before any 1.8.0 upgrade  —  scheduled, with a backup

**Why.** Live `qlo_employee.passwd` and `qlo_customer.passwd` are
`varchar(32)`. QloApps 1.8.0 hashes with bcrypt (exactly 60 characters) and
rehashes on login **without a guard**, while the database layer
(`classes/db/DbPDO.php`) runs with `PDO::ERRMODE_SILENT`. On a 32-character
column that means either a silently swallowed error (password stays MD5
forever) or a silent **truncation** that destroys the original hash and
permanently locks the account out on its first successful login. Nothing in
the 1.7.0→1.8.0 upgrade path in this tree widens these columns.

**Do.** In a scheduled window, with a fresh verified backup taken first:

```sql
ALTER TABLE `qlo_employee` MODIFY `passwd` varchar(60) NOT NULL;
ALTER TABLE `qlo_customer` MODIFY `passwd` varchar(60) NOT NULL;
```

This is `dev/migrations/001_widen_password_columns.up.sql` in this repository.
Widening is lossless. The rollback file in the same directory refuses to run
blind: read its guard query first.

**Verify.** `information_schema.COLUMNS` shows `CHARACTER_MAXIMUM_LENGTH = 60`
for both, and a test staff login still works immediately afterwards.

**Risk if skipped.** Upgrading to 1.8.0 without this converts every guest and
staff password into a lockout ticket, silently, on first login.

---

## 7. Correct the live shop identity and placeholder URLs  —  ~5 minutes, owner only

**Why.** `PS_SHOP_NAME` on the live site is set to the bare domain
`lushviewhotel.net`, and that value flows into outgoing email (`Mail.php`) and
meta titles (`Meta.php`), so guests receive mail "from" a domain string rather
than the hotel. Separately, `MB_CANCEL_URL` still holds the module
placeholder `http://www.yoursite.com`, which would send a guest who cancels a
payment to a parked domain.

**Do.** Back office → **Preferences → General** (shop name) → set
`Lush View Hotel`. Then **Modules → payment modules → configuration** for the
mobile-money/ bank module that owns `MB_CANCEL_URL`, and replace the
placeholder with the real cancellation page on the live domain.

**Verify.** Place no order; instead check **Preferences → General** shows the
new name, and grep the module configuration table for `yoursite`:

```sql
SELECT name, value FROM qlo_configuration WHERE value LIKE '%yoursite%';
```

expect zero rows afterwards.

**Risk if skipped.** Guest-facing email and payment-cancel flows carry
placeholder or wrong identity.

---

## 8. Data-protection readiness (Ghana, Act 843)  —  policy, not code

**Why.** The database holds 1,257 customer records and 1,934 bookings with
names, phones, emails and stay histories: personal data under the Data
Protection Act, 2012 (Act 843). Two operational gaps matter more than any code
change: there is no documented procedure for a guest asking what you hold or
asking for deletion, and the export that caused item 1 shows how easily a full
copy leaves the organisation.

**Do.** Write a one-page procedure: who receives a data request, where the
data lives (this database, phpMyAdmin backups, email), how a deletion is
carried out and evidenced, and a rule that full exports are taken only into
encrypted storage and deleted after use. Register or confirm registration with
the Data Protection Commission if the hotel processes data at scale.

**Verify.** A colleague can follow the page without asking you questions.

**Risk if skipped.** No defensible position when a guest or the Commission
asks.

---

## 9. Decide whether the back office should keep reporting to api.qloapps.com  —  ~10 minutes, owner only

**Why.** The admin panel fetches its promotional banner from `api.qloapps.com`
once a day (`Tools::addonsRequest()`, `classes/Tools.php:3375`). Every request
through that function — the banner, the daily version check, and the module and
theme catalogues — posts the shop domain, the SSL domain, the install path,
`PS_SHOP_EMAIL` (the business email address), the QloApps version, the language
and the country to a third party. There is no configuration switch:
`Tools::$is_addons_up` is a protected static that only changes when a request
fails. Item 5 stops publishing the version fingerprint to visitors; this is the
same information being volunteered outbound.

**Do.** The advertising itself is already blocked by the `lvhadminclean` module
(see `docs/project/07-backoffice-ads.md`), which stops the banner request and
therefore most of the disclosure. To stop all of it, choose one:

- a host-level outbound firewall rule denying `api.qloapps.com` — no code
  change, survives every upgrade; or
- a one-line core change, `classes/Tools.php:3374` `= true` → `= false` — needs
  re-applying after each upgrade, and disables the Modules Catalog, the Themes
  catalog and the upgrade notices.

**Verify.** No request to `api.qloapps.com` appears in the browser developer
tools or the server's outbound logs while you use the back office, and
`cache/dashboard_recommendation.html` is never re-created after you delete it.

**Risk if skipped.** Low severity but continuous: the shop's identity, contact
email and exact software version are disclosed daily to a third party — exactly
the reconnaissance item 5 exists to prevent. The cost of blocking everything is
that modules and themes can no longer be installed from inside the back office.

---

## Already closed by this project (no action needed)

- The committed export was purged from branch history and the purge verified
  three ways; item 1 closes the remaining public access.
- No cardholder data exists anywhere in the 300-table schema (the only four
  card-related columns are empty), so the environment is not a PCI cardholder
  data environment.
- No API keys, web-service credentials or cookie keys are present in the
  repository or the export.
- The local development environment never contacts production: the import
  script has no host parameter and the password tool refuses non-local hosts.
- The back-office advertising banner is blocked and the cached ad HTML deleted
  by the `lvhadminclean` module, with no application file edited; item 9 covers
  the remaining outbound traffic.
- All four recent upstream CVE fixes are present in the 1.8.0 tree used for
  local development; item 4 addresses the one module still exposed on 1.7.0.

## Order of work, one line

1 today (public exposure), 2 and 4 this week (broken account, unpatched
module), 5, 7 and 9 this week (fingerprints, identity, outbound disclosure),
3 paired with 6 in the next maintenance window (hash scheme and column width
move together), 8 whenever policy time exists.
