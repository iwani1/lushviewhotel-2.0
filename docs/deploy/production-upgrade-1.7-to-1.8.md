# Production upgrade runbook — lushviewhotel.net from 1.7.0 to 1.8.0

Owner-executable. Written for cPanel shared hosting (GreenGeeks) with File
Manager and phpMyAdmin; SSH commands are given where they are faster but nothing
requires them.

**Read the whole document before starting.** The order matters more than any
individual step, and the two rules that keep this reversible are:

1. **Never modify the production database in place.** Build the upgraded
   database as a *copy*, test it, then point the code at it. The original stays
   untouched as the rollback.
2. **Never overwrite the live files in place.** Stage 1.8.0 in a second
   directory, carry the irreplaceable files into it, then swap the directory
   names. The old tree stays untouched as the rollback.

With both rules honoured, a failed upgrade costs a rename and a config edit, not
a restore from backup.

---

## 0. Where this plan comes from

QloApps ships no upgrade SQL for this jump: `install-dev/upgrade/sql/` stops at
`1.6.1.0.sql`, and the official `qloautoupgrade` module is not bundled —
`classes/Upgrader.php:50-53` fetches its download link from a remote feed, so it
has to be obtained from Webkul. That module upgrades in place, which on shared
hosting means a failure part-way through is a restore-from-backup event.

So this runbook uses the delta that was **derived from your own live schema and
already proven on your own live data**:

- `dev/scripts/upgrade-live-copy.sh` diffed the working 1.8.0 install
  (`qloapps_fresh`) against a copy of the production database (`qloapps_live`)
  and produced `dev/migrations/generated/002_qloapps_live_to_1_8_0.sql`.
- Applied to `qloapps_18`: 300 tables copied, **32 tables created**, **31
  columns added**, 0 apply errors, 0 tables and 0 columns still missing against
  a real 1.8.0 install.
- The owner then verified the back office end to end on that database:
  dashboard, bookings, customers, room types and stats all work.

That local run is the rehearsal. This document is the same procedure, on the
server, with the two reversibility rules above.

---

## 1. Pre-flight — do all of this days before, zero risk

Nothing here changes anything. Every query runs against the **local copy**.

- [ ] **1.1 Make the GitHub repository private** (security runbook item 1). You
      will be moving code and database files around; the repo currently exposes
      the schema and a purged-but-fetchable export.
- [ ] **1.2 Inspect the three type differences** the migration refused to
      change. On your machine:
      ```bash
      grep '^-- DIFFERS' dev/migrations/generated/002_qloapps_live_to_1_8_0.sql
      ```
      If these are the money columns (`decimal(10,2)` on payments against
      `decimal(20,6)` on order lines, audit row A27), decide now whether to
      convert them in the same window. Do not discover this mid-upgrade.
- [ ] **1.3 Inventory the modules that matter.** Locally:
      ```bash
      podman-compose exec db mariadb -uroot -pqlo_root_dev_only -t -e "
        SELECT name, version, active FROM qloapps_live.qlo_module
        WHERE name IN ('cashpayment','qlohotelreview',
                       'qlochannelmanagerconnector','qlocmconnector',
                       'qlocrontaskmanager','hotelreservationsystem')
        ORDER BY name;"
      ```
      - `cashpayment` — **your custom payment module, and the single biggest
        risk in this upgrade.** It exists nowhere except the live server, it was
        written for 1.7.0, and 2,058 payments depend on it. It must be tested on
        a staging copy before production (step 4.4).
      - `qlohotelreview` — the module with the unpatched CVE on 1.7.0 (security
        runbook item 4). Upgrading to 1.8.0 closes that item.
      - `qlochannelmanagerconnector` — 1.8.0 replaces it with `qlocmconnector`.
        If it is `active=1`, any OTA connected through it will need
        reconfiguration. Confirm with the channel-manager provider before
        scheduling.
- [ ] **1.4 Find every non-standard file on the live server.** In cPanel File
      Manager, compare `public_html/modules/` and `public_html/override/`
      against this repository's `modules/` and `override/`. Anything on live that
      is not in the repository is custom code that must be carried across —
      `modules/cashpayment` is the one we know about; there may be others.
      Record the list.
- [ ] **1.5 Decide the bed-type question** (TASKS row B2): 9 bed types and 8
      room-type assignments have no target table in 1.8.0. Choose keep-as-orphans
      / re-model as amenities / accept the loss. Small, but it is data.
- [ ] **1.6 Confirm hosting facts.** PHP selector offers 8.2–8.5 (live already
      runs 8.4.27, which is inside 1.8.0's window); free disk for a second copy
      of the code and a second database; cPanel Backup available; whether SSH is
      enabled on your plan.
- [ ] **1.7 Book the window.** 30–60 minutes of maintenance mode, plus a full
      day for verification afterwards. Choose the lowest-traffic night. Do not
      start this late in the day with no time to verify.

---

## 2. Backups — the night before

- [ ] **2.1 Full account backup** through cPanel → Backup (or → Backup
      Wizard). Download it to your laptop as well; do not trust a backup that
      lives only on the machine it backs up.
- [ ] **2.2 Database export**: phpMyAdmin → live database → Export →
      **Custom** → format SQL → compression **None** (gzip exports have failed
      for you before) → include **CREATE DATABASE / USE** unchecked, "Add
      DROP TABLE" enabled. Download it.
- [ ] **2.3 Files**: File Manager → select `public_html` → Compress → download
      the archive.
- [ ] **2.4 Verify the backups**: the SQL file should be tens of MB and end with
      a complete statement; the archive should open. Record both file sizes. A
      backup you have not verified is not a backup.

---

## 3. Build the upgraded database as a copy

- [ ] **3.1** cPanel → MySQL Databases → create a new database, e.g.
      `<cpaneluser>_qlo18`, and add the **existing** database user to it with
      ALL PRIVILEGES. (A new user would also work but means editing more config.)
- [ ] **3.2** phpMyAdmin → select the new database → Import → the export from
      2.2. This is a copy of production; production itself is not touched.
- [ ] **3.3 Apply the password-column migration FIRST.** This is audit row A29
      and it is not optional. Live stores `qlo_employee.passwd` and
      `qlo_customer.passwd` as `varchar(32)` — exactly an MD5 digest — while
      1.8.0 writes bcrypt at 60 characters and re-hashes on every successful
      login (`Employee.php:303-305`, `Customer.php:413-415`). With a 32-character
      column the new hash is truncated, `isPrimaryHash()` then reports the
      account as already migrated, and **the account is permanently locked out**.
      That is 1,257 customers and 15 employees.
      Run `dev/migrations/001_widen_password_columns.up.sql` from this repository
      against the new database (phpMyAdmin → SQL → paste the file contents).
- [ ] **3.4 Apply the schema delta**: phpMyAdmin → new database → Import →
      `dev/migrations/generated/002_qloapps_live_to_1_8_0.sql` from your laptop.
      Same order as the local rehearsal: 001, then the delta.
- [ ] **3.5 Verify the copy matches 1.8.0** — both numbers must be 0:
      ```sql
      SELECT
        (SELECT COUNT(*) FROM information_schema.TABLES t
          WHERE t.TABLE_SCHEMA='<cpaneluser>_qlo18' AND t.TABLE_TYPE='BASE TABLE') AS tables_now,
        (SELECT COUNT(*) FROM information_schema.COLUMNS c
          WHERE c.TABLE_SCHEMA='<prefix>_fresh_reference' ) AS ignore_this;
      ```
      Simpler and sufficient — confirm the row counts survived:
      ```sql
      SELECT (SELECT COUNT(*) FROM qlo_orders)               AS bookings,
             (SELECT COUNT(*) FROM qlo_customer)             AS guests,
             (SELECT COUNT(*) FROM qlo_order_payment)        AS payments,
             (SELECT COUNT(*) FROM qlo_employee)             AS staff,
             (SELECT COUNT(*) FROM information_schema.TABLES
               WHERE TABLE_SCHEMA=DATABASE())                AS tables_total;
      ```
      Expected: 1,934 / 1,257 / 2,058 / 15 / **332**.
- [ ] **3.6 Check the character sets did not end up mixed.** The copy is
      utf8mb3; tables created from a fresh 1.8.0 install may not be:
      ```sql
      SELECT TABLE_COLLATION, COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA=DATABASE() GROUP BY TABLE_COLLATION;
      ```
      More than one row is worth noting. It worked locally, so it is not a
      blocker, but a mixed collation shows up later as "Illegal mix of
      collations" on a join — better to know which tables before you need them.

---

## 4. Stage the 1.8.0 code and test it

- [ ] **4.1 Get clean 1.8.0 code.** Download
      `https://github.com/qloapps/qloapps/archive/refs/tags/v1.8.0.zip`. Do not
      upload this repository's working copy: it contains `dev/`, `docs/` and
      local tooling that have no business on a public web server.
- [ ] **4.2 Upload and extract it into a NEW directory**, outside the web root
      if your host allows it, otherwise alongside it — e.g. `~/qlo180/`. Never
      over `public_html`.
- [ ] **4.3 Carry the irreplaceable files across** from `public_html` into the
      staged tree. Missing any one of these is a broken site:

      | Copy from live | Why |
      | --- | --- |
      | `config/settings.inc.php` | Holds `_COOKIE_KEY_`. Without the **same** key, every legacy MD5 password in the database stops validating and all 1,257 guests are locked out. Edit only `_DB_NAME_` to the new database. |
      | `img/` (whole directory) | Logo, favicon, room photos, `img/p/`, `img/hotel_header_media/` — none of it is in the code archive |
      | `modules/cashpayment/` | Custom module, exists nowhere else |
      | anything else from 1.4 | Custom code we do not know about yet |
      | `override/` contents that live has and upstream does not | Same |
      | `upload/`, `download/` | Guest-uploaded files, booking documents |
      | `.htaccess` | Friendly URLs; can be regenerated later from SEO & URLs |

      Do **not** copy `cache/` — it must start empty — and do not copy
      `config/xml/`, `config/settings.dist.php` or anything under
      `install-dev/` from live; those belong to 1.7.0.

- [ ] **4.4 Add this project's own files** from the repository:
      `modules/lvhbranding/`, `modules/lvhadminclean/`, and
      `themes/hotel-reservation-theme/modules/hotelreservationsystem/` (the
      footer override). All three are new files; none of them replaces anything.
- [ ] **4.5 Test the staged copy before touching production.** If your host
      allows subdomains, point `staging.lushviewhotel.net` at the staged
      directory and tell that database it is the shop's domain, otherwise the
      site will redirect you to the live one:
      ```sql
      UPDATE qlo_shop_domain SET domain='staging.lushviewhotel.net',
             domain_ssl='staging.lushviewhotel.net';
      ```
      Then, on staging: front page loads, a room search returns availability,
      **make a test booking and pay it with cashpayment**, back office opens,
      the bookings list shows real reservations, a test email arrives.
      Reverse that `UPDATE` before the cutover.
      If your host does not allow a subdomain, skip to section 5 — but understand
      that `cashpayment` then gets its first 1.8.0 test in front of real guests.
- [ ] **4.6 Fix what staging reveals.** Expect module-side issues rather than
      schema issues: a 1.7.0-era module that needs its own `upgrade/` script run,
      a template that moved, a hook renamed. Each one is a small, specific fix.

---

## 5. Cutover — the maintenance window

- [ ] **5.1 Enable maintenance mode on live**: back office → Preferences →
      General → Maintenance → enable, and add your own IP so you can still get
      in. Guests now see the maintenance page instead of booking into a
      half-upgraded site.
- [ ] **5.2 Take a final database export** (repeat 2.2). Any booking made since
      the night before exists only in the live database, and the copy in section
      3 does not have it. Either re-import that delta or, simpler and safer,
      **redo sections 3.1–3.5 immediately before the cutover** so the copy is
      minutes old rather than a day old.
- [ ] **5.3 Rename, do not delete.** File Manager (or SSH):
      ```
      public_html   ->  public_html_170
      ~/qlo180      ->  public_html
      ```
      With SSH this is two `mv` commands and a second of downtime:
      ```bash
      mv ~/public_html ~/public_html_170 && mv ~/qlo180 ~/public_html
      ```
- [ ] **5.4 Check `config/settings.inc.php`** in the new root: `_DB_NAME_` is
      the new database, `_DB_USER_`/`_DB_PASSWD_` are unchanged, and
      `_COOKIE_KEY_` is the original live value.
- [ ] **5.5 Permissions.** Directories 755, files 644, and writable by the web
      user: `config/`, `cache/`, `log/`, `img/`, `mails/`, `modules/`,
      `translations/`, `upload/`, `download/`,
      `themes/hotel-reservation-theme/{cache,lang,pdf/lang}`, and the directory
      containing `.htaccess`.
- [ ] **5.6 Clear the caches**: delete `cache/class_index.php` and everything
      inside `cache/smarty/compile/` and `cache/smarty/cache/` **except** the
      `index.php` guard files.
- [ ] **5.7 Disable maintenance mode**, load the homepage, then log in to
      `/admin-dev/`.

---

## 6. Post-upgrade — the same day

- [ ] **6.1 Modules**: back office → Modules. Upgrade every module flagged as
      needing it (each carries its own `upgrade/upgrade-x.y.z.php`), and install
      the ones 1.8.0 adds — 61 bundled against 56 on live, including
      `qlocmconnector` and `qlocrontaskmanager`. Then install `lvhbranding` and
      `lvhadminclean`.
- [ ] **6.2 Regenerate `.htaccess`**: Preferences → SEO & URLs → save, so the
      rewrite rules match 1.8.0. Test a room URL and a CMS URL.
- [ ] **6.3 Fix the shop identity** (branding plan rows F1/F4): Hotel General
      Configuration → Website Contact Details → Website name `Lush View Hotel`,
      Website email `info@lushviewhotel.net`, phone and address. On 1.7.0 this
      value was the domain, and 1.8.0 also feeds the footer copyright from it.
- [ ] **6.4 Verify the data**: bookings 1,934, guests 1,257, payments 2,058.
      Open three real bookings from different months and check guest name, room,
      dates, amount and payment method.
- [ ] **6.5 Verify the money**: reports and the dashboard totals against a
      figure you know from before the upgrade. This is where the 1.2 type
      differences would show up.
- [ ] **6.6 Make one real test booking** end to end, including a cashpayment,
      then cancel it.
- [ ] **6.7 Email**: send a test to your own address only. Confirm the From name
      and address, and that it does not land in spam. If it does, the sending
      host needs an SPF record covering `lushviewhotel.net`.
- [ ] **6.8 Remove the installer**: delete or rename `install-dev/`. A live
      installer is how a site gets reinstalled by a stranger.
- [ ] **6.9 Stop publishing the version fingerprint** (security runbook item 5):
      remove `CHANGELOG.txt` and `composer.json` from the web root, or deny them
      in `.htaccess`.
- [ ] **6.10 Check the cron**: 1.8.0 adds `qlocrontaskmanager`. If your host runs
      cron jobs, confirm nothing is still calling a 1.7.0 path.

---

## 7. Rollback — under two minutes, and why it works

The production database was never modified and the old tree was never
overwritten, so rollback is a rename and a config edit:

```bash
mv ~/public_html ~/qlo180_failed && mv ~/public_html_170 ~/public_html
```

Then clear `cache/` as in 5.6 and load the site. The 1.7.0 code reconnects to
the original database exactly as before. Any booking taken while 1.8.0 was live
is in the *new* database — if you roll back after taking real bookings, export
those rows first and reconcile them by hand. That is the one messy case, and it
is why 6.4–6.6 happen before you disable maintenance mode for good.

Keep `public_html_170` (or the renamed folder) and the original database for at
least two weeks.

---

## 8. What could still go wrong, honestly

| Risk | Likelihood | Mitigation |
| --- | --- | --- |
| `cashpayment` breaks on 1.8.0 | **Medium-high** — it is 1.7.0-era custom code with no maintainer | Test on staging (4.5). If it fails, the fix is a small module patch; the payments data is unaffected either way |
| Channel-manager/OTA connections drop | Medium if `qlochannelmanagerconnector` is active | 1.3 identifies it; confirm with the provider before the window |
| A module's own upgrade script never ran | Medium | 6.1 upgrades every flagged module; watch for modules that stay at a 1.7.0 version number |
| Money columns differ in precision | Known — 3 columns | 1.2 decides it in advance |
| Bed-type data orphaned | Certain, small | 1.5 decides it in advance |
| Collation mix between old and new tables | Low | 3.6 surfaces it before cutover |
| Emails go to spam after the From address changes | Medium | 6.7 tests it; SPF on the host fixes it |
| Disk space for two code trees and two databases | Low | 1.6 checks it |

The official `qloautoupgrade` module remains an alternative if you would rather
have a vendor-supported path. It upgrades in place, so it needs the backups in
section 2 to be real, and it has no way to know about `cashpayment` or your
other custom files — sections 1.4 and 4.3 apply to it just the same.
