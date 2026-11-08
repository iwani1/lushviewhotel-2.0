# Getting 1.8.0 running on the real hotel data

Goal: QloApps 1.8.0 serving the live dataset (1,934 bookings, 1,257 guests,
2,058 payments) instead of an empty install.

## Why there is no official path

`install-dev/upgrade/sql/` ships deltas for 1.4.1, 1.5.0, 1.5.1.0, 1.5.2.0,
1.6.0.0 and 1.6.1.0 — **nothing for 1.7.0 or 1.8.0**. The 1-Click Upgrade
module is not bundled either; only its empty `admin-dev/autoupgrade/backup/`
directory is present. So the schema gap has to be derived, not downloaded.

## What is done instead

The delta is computed from two databases that already exist locally:

- `qloapps_live` — the imported production data, still on the 1.7.0 schema.
  **Read only.**
- `qloapps_fresh` — a working 1.8.0 install, used as the schema reference.
- `qloapps_18` — the output: a copy of `qloapps_live`, upgraded.

`dev/scripts/upgrade-live-copy.sh` creates the copy, adds the ~36 tables 1.8.0
expects (with their reference rows taken from the fresh install), adds the ~23
missing columns with 1.8.0's exact definitions, and **reports but does not
change** columns whose type differs between versions — converting live money
columns (`decimal(10,2)` vs `decimal(20,6)`) is a decision, not a side effect.
Output goes to `dev/migrations/generated/`, which is gitignored because it is
derived from the production schema.

## Passwords: no mass reset needed

`classes/PasswordHashing.php` validates both bcrypt and the legacy
`md5(_COOKIE_KEY_ . password)` scheme, and `classes/Employee.php:289-306`
rewrites a legacy hash to bcrypt on the first successful login. Live staff and
guest passwords therefore survive the upgrade and improve themselves.

One caveat for the **local copy**: the legacy md5 check needs the same
`_COOKIE_KEY_` the live server uses, and that value lives in the live
`config/settings.inc.php`, not in the database. Without it, legacy passwords
will not validate locally. For any account you need locally, set a fresh bcrypt
hash instead:

```bash
podman-compose exec app php dev/scripts/set-admin-password.php you@example.com 'LocalDev-2026!'
```

On production, the real `_COOKIE_KEY_` is already in place, so this caveat does
not apply there.

## Run it

```bash
cd ~/Music/newhotel/lushviewhotel-2.0
git pull
podman-compose up -d
dev/scripts/upgrade-live-copy.sh
dev/scripts/use-db.sh 18
```

Then open `http://localhost:8080/admin-dev/`. If anything 500s:

```bash
podman-compose logs app | tail -40
```

## Result of the first successful run (2026-11-08)

```
300 tables copied from qloapps_live
missing tables: 32   -> created, with reference rows from qloapps_fresh
missing columns: 31  -> added with 1.8.0's definitions
type differences: 3  -> reported, not changed
tables still missing vs 1.8.0 : 0
columns still missing vs 1.8.0: 0
apply errors: 0
```

`qloapps_18` is now schema-complete against a real 1.8.0 install, and the back
office serves the production data: the dashboard, the bookings list and every
stats KPI (`today_arrivals`, `occupied_rooms`, `total_due_amount`,
`average_order_value`, `conversion_rate`) all return 200 with no PHP fatals.

Two generator bugs had to be fixed to get there, both in how a column default is
re-read from `information_schema`: MariaDB 10.2+ returns `COLUMN_DEFAULT` as a
literal expression, so a varchar default arrives already wrapped in quotes and
an explicit `DEFAULT NULL` arrives as the string `NULL`. Quoting either produced
statements the client could not parse, and because the client stops at the first
error, one bad line silently discarded every `ALTER TABLE` after it. The apply
now runs with `--force` and reports both counts, so an incomplete schema can
never pass unnoticed again.

## Resolved follow-ups (2026-11-08)

- **All 3 reported type differences are now closed (0 remaining):**
  - `qlo_referrer.passwd` (`varchar(32)` → `varchar(60) DEFAULT NULL`, 0 live
    rows) folded into `dev/migrations/001_widen_password_columns.up.sql`.
  - `qlo_order_payment.amount` and `qlo_order_payment_detail.amount`
    (`decimal(10,2)` → `decimal(20,6) NOT NULL`, 2,058 live rows each) widened
    losslessly in `dev/migrations/003_widen_payment_amounts.up.sql` after owner
    approval, eliminating the audit-row A27 precision mismatch.
  - `dev/scripts/upgrade-live-copy.sh` applies `001` and `003` to `qloapps_18`
    (leaving `qloapps_live` untouched) before diffing, and dumps non-vocabulary
    tables (`qlo_qctm_cron_task*`, demo amenity links, transactional logs)
    structure-only (`--no-data`) so `qloapps_fresh` IDs never leak into live
    data.

## Not handled yet (phase 2, only if a page needs it)

- New **rows** in tables that both versions have — hooks, tabs, meta. A missing
  hook row makes a block not render; it does not crash the page.
- Backfilling new tables that reference existing bookings.

Each is fixed against a specific error, not speculatively.

## Rollback

`dev/scripts/use-db.sh fresh` (or `live`) at any time; `qloapps_18` is
disposable — `DROP DATABASE qloapps_18;` and rerun the script. `qloapps_live`
is never written to, and production is never contacted.

## Done for today means

Dashboard loads with no 500, the bookings list shows the real reservations,
login works, and `podman-compose logs app` is clean.
