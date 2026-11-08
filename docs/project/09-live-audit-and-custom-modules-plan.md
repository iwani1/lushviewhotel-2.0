# Phase 3 — Live System Audit Findings & Custom LVH Module Blueprint

**Date:** 2026-11-08
**Source:** Live `lvhaudit` export from `lushviewhotel.net` (652 ms read-only run; zero PII or secrets)

---

## 1. Pre-Flight Questions Closed by the Live Audit

| Area | Live Finding | Impact on 1.8.0 Upgrade |
|---|---|---|
| **PHP & Extensions** | `PHP 8.4.27` (`litespeed`), `memory_limit=512M`, `max_execution_time=600`, `upload_max_filesize=64M`, `Africa/Accra`; all 9 required 1.8.0 extensions loaded | Already inside QloApps 1.8.0's supported `>=8.2 <8.6` window |
| **Database Server** | `11.4.13-MariaDB`, `300` InnoDB tables (`299` `utf8mb3_general_ci`, `1` `latin1_swedish_ci`), `sql_mode=''` | Matches local migration pipeline (`001` + `003` + `002`) |
| **`override/` directory** | `0` custom files | Clean — no override conflicts |
| **Themes & PDFs** | `0` modified `.tpl` / `.css` / `.js` files | Clean — safe to deploy unmodified 1.8.0 theme & PDF templates |
| **`classes/Profile.php`** | Only core PHP file edited on live (casts `view`/`add`/`edit`/`delete` to `(int)` for MariaDB/PHP 8) | Already fixed upstream in QloApps 1.8.0 (`classes/Profile.php:156-161`); safe to replace |
| **`cashpayment` (`2.6.8`)** | **DB-only orphan** (`id_module = 55`); folder `modules/cashpayment/` does not exist on disk. `modules/cheque/cheque.php` was edited in place (`$this->name = 'cashpayment'`), and `0` orders ever used it | Safe to replace `modules/cheque/` with upstream 1.8.0 and delete orphan `id_module = 55` rows; replace with proper `lvhpayments` |
| **Uninstalled PrestaShop 1.6 leftovers** | 22 uninstalled legacy directories in `modules/` (`blockwishlist`, `blocklayered`, `ganalytics`, `mailalerts`, etc.) | Moving old `public_html` aside for clean 1.8.0 removes all 22 dead directories and frees thousands of inodes |
| **`qlohotelreview` (`1.0.2`)** | **Active** in live DB (`CVE-2025-67325`) | Disable immediately on live 1.7.0 back office before cutover |
| **Brand artwork** | Custom `img/favicon.ico` + `lushviewhotelnet-logo-*.jpg` (header, invoice, mail, mobile) + `hotel_header_image_*.jpg` on disk; `PS_SHOP_NAME` still set to `'lushviewhotel.net'` and `BLOCKSOCIAL_*` still points to `qloapps` | Copy `img/` across in step 4.2; update `PS_SHOP_NAME` to `'Lush View Hotel'` and clean social links |
| **cPanel Mailbox / Inode Quota (`URGENT`)** | `qlo_log` shows `550 Mailbox is full / Blocks limit exceeded / Inode limit exceeded` after `10,629` internal `order_conf_admin` emails | Purge cPanel mailbox + disable redundant internal `order_conf_admin` copies so backups and booking saves are not blocked |

---

## 2. How Lush View Hotel Actually Operates (Evidence from Live Data)

1. **99.8% Front-Desk Walk-In Operation**
   - `1,947` of `1,951` orders are Back-Office orders (`bo_order`).
   - `94%` of stays (`2,228` of `2,366` non-cancelled bookings) are **same-day / walk-in** arrivals, and `87%` (`2,051`) are **1-night stays** (`13:00` check-in → `12:00` check-out).
   - **Staff structure (`15` active employees):** `1` SuperAdmin, `7` Receptionists (`Profile 11` — view/add/edit, no delete), `3` Supervisors (`Profile 15`), `3` Accountants (`Profile 16`), `1` IT Team (`Profile 18`), plus `Managers` (`Profile 17`).

2. **Free-Text Payment Entry Creates Reconciliation Friction**
   - All transactions are in **GHS (`₵`)**, split across **Cash (~54%)**, **Mobile Money / MoMo (~42%, 99% with transaction reference IDs)**, **POS Card (~4%)**, and **Bank Transfer / Management Comp (<1%)**.
   - `121` orders have **2 to 5 split/installment payments**, and `24` orders carry open unpaid balances.
   - Because QloApps Back Office uses a free-text `payment_method` box, receptionists have typed **18 different label variants** (`Cash Payment`, `Cash`, `cash`, `momo`, `Momo payment`, `mom`, `P.O.S`, `POS`, `P O S`, `P. O. S`, `awaiting payment`, `partial payment`, `Approved by Management`, and even a raw MoMo reference number in the method column).

3. **34 Physical Guest Rooms + Conference Hall Workaround**
   - **34 Guest Rooms** across First & Second floors:
     - `Standard Room` (`8` rooms: `SR-101`–`SR-108`)
     - `Double Standard Rooms` (`11` rooms: `DR-109`–`DR-128`)
     - `Executive Rooms` (`7` rooms: `ER-110`–`ER-127`)
     - `Suites Rooms` (`8` rooms: `SUTR-116`–`SUTR-134`)
   - **Conference Hall (`CON-1`..`CON-4`):** Modelled as **4 separate fake room types** (`1 Hour`, `2 Hours`, `3 Hours`, `24 Hours`) with duplicate URL slugs (`conference-rooms-duplicate-duplicate-duplicate`) because QloApps lacks hourly hall/event booking and shared hall availability.

4. **Check-In / Check-Out & Housekeeping Gap**
   - `97.3%` of booking rows sit in `Checked-Out` (`id_status = 3`) or `Allotted` (`48` rows) with almost none tracked live in `Checked-In` (`3` rows), and all `38` physical rooms remain permanently on `status = 1` with `0` housekeeping/maintenance statuses.
   - `294` customers are repeat guests (`42` loyal VIPs with `5–36` stays each), yet `0` guest ID documents (`htl_booking_document`) and `0` extra folio services (`htl_booking_demands` — restaurant, bar, laundry, late check-out) are captured in QloApps.

5. **Taxes & Mail Noise**
   - All `52` default US state sales taxes (`US-AL`..`US-DC`) are still active while `0` Ghana tax rules are assigned (`id_tax_rules_group = 0` on all rooms).
   - Every walk-in booking triggers multiple internal `order_conf_admin` emails (`10,629` sent vs `179` guest emails), filling the server mailbox.

---

## 3. Proposed Custom `lvh*` Module Suite

Built strictly as self-contained QloApps 1.8.0 modules (`modules/lvh*/`), zero core edits, role-aware for **Receptionists, Supervisors, Accountants, Managers, and SuperAdmin**:

1. **`lvhpayments` — Ghana Payment Normaliser, MoMo/POS Tracker & Shift Cash-Up**
   - Replaces the free-text Back-Office payment method box with a strict, clean selector: **Cash (GHS)**, **MTN MoMo**, **Telecel Cash**, **AT Money**, **POS / Card**, **Bank Transfer**, **Corporate / Credit**, **Management Comp** (requiring supervisor note).
   - Enforces **MoMo / POS Transaction Reference ID** when MoMo or POS is selected, supports clean **split-tender** (e.g. part Cash + part MoMo on one booking), provides a 1-click historical normaliser migration to group the existing 18 spelling variants for clean reporting, and gives **Receptionists & Accountants** an **End-of-Shift Cash / MoMo / POS Reconciliation & Printable Receipt** screen.

2. **`lvhfrontdesk` — Fast 1-Screen Walk-In Desk, In-House Board & VIP/Guest ID Capture**
   - Purpose-built for the `94%` same-day walk-in workflow: single-screen Room Rack showing all 34 rooms (`101`–`134`) color-coded by **Vacant Clean / Vacant Dirty / Occupied / Due Out (`12:00`) / Maintenance**, 30-second walk-in check-in, automatic **Repeat Guest / VIP badge** (e.g. `12th stay`), Ghana Card / Passport ID number & phone capture, quick folio extras (Restaurant, Laundry, Late Check-Out), and 1-click Check-In / Check-Out + Open-Balance alert (`24` unpaid/partial orders).

3. **`lvhhousekeeping` — Room Turnover & Maintenance Status Board**
   - Automatically flips a room (`SR-101`..`SUTR-134`) to **Dirty** on Check-Out, lets Housekeeping / Supervisors mark rooms **Cleaning → Inspected / Ready** or **Out of Order** (with reason), and warns Reception before checking a walk-in guest into an uncleaned room.

4. **`lvhconference` — Hourly Conference & Event Hall Booking Manager**
   - Solves the 4-duplicate-room-type workaround (`1h / 2h / 3h / 24h`) by binding packages to a **single shared physical Conference Hall calendar** with real **start/end time slots** (`08:00–11:00`, full-day, multi-day), attendee count (up to 60+), AV/catering add-ons, deposit/balance tracking, and zero double-booking across hourly tiers.

5. **`lvhreports` & `lvhauditlog` — Accountant & Supervisor Daily Flash + Staff Action Log**
   - Daily Manager/Accountant Flash Report (Occupancy %, ADR, RevPAR, Revenue by Cash vs MoMo vs POS, Outstanding Balances aging list, Repeat Guest leaderboard) with CSV/PDF export.
   - Staff Audit Log recording who created/modified a booking, posted a payment, granted a discount/`Approved by Management` comp, or changed room status (critical with 7 receptionists sharing shifts).

6. **Upgrade & Hygiene Enhancements (`lvhbranding` + `lvhadminclean` + Mail/Tax Cleanup)**
   - Expand `lvhbranding` + `lvhadminclean` to fix `'Hotel Prime'` / `'lushviewhotel.net'` titles, quiet the `order_conf_admin` email flood on walk-in orders, deactivate the 52 US state taxes, and optionally activate Ghana tax breakdown (`localization/gh.xml`).
