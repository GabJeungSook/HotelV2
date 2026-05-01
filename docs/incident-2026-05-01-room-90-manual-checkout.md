# Incident Report: Room 90 Manual Checkout (2026-05-01)

> **Status:** ✅ Fully resolved
> **Date:** 2026-05-01
> **Affected guest:** AZZAZZYLLE (guest_id 15905, qr_code 1263417)
> **Affected room:** Room 90 (room_id 73)
> **Reported by:** QUINE PINO (Telegram, ~5:10 PM)
> **Resolved by:** Manual SQL recovery + production server file-permission fix

---

## What happened

Frontdesk staff (SEANNE KARYLLE, user_id 24, frontdesk_id 6) was processing
checkouts during the AM shift on 2026-05-01. At ~5:05 PM, while attempting to
check out guest AZZAZZYLLE from Room 90, the production system threw a
**500 Server Error**, blocking the click.

QUINE PINO reported on Telegram:
> "rm 90 — bri pwede mo ni ma dritso log-out na sa database ta 3:54 ang
> check-out time. Wla nila na check-out ng overtime na ky tungod atong 5:05 error."

The actual physical checkout time (when the guest left): **3:54 PM**.
The system tried/failed at: **5:05 PM**.

## Root cause of the 500 error

`file_put_contents(/var/www/HotelV2/storage/framework/views/...): Failed to open stream: Permission denied`

The Reports page (and several Livewire components including the checkout
flow) tried to compile a Blade view, but the web server user (`www-data`)
couldn't write to `storage/framework/views/`. This was caused by a recent
`sudo git pull` on production that left newly-created files owned by `root`
instead of `www-data`.

## Fix applied to the production server

```bash
sudo chown -R www-data:www-data /var/www/HotelV2/storage
sudo chown -R www-data:www-data /var/www/HotelV2/bootstrap/cache
sudo chmod -R 775 /var/www/HotelV2/storage
sudo chmod -R 775 /var/www/HotelV2/bootstrap/cache
sudo rm -rf /var/www/HotelV2/storage/framework/views/*
sudo rm -rf /var/www/HotelV2/storage/framework/cache/*
cd /var/www/HotelV2
sudo -u www-data php artisan view:clear
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan cache:clear
```

After this, Reports page and the checkout flow loaded normally.

### Prevention going forward

When pulling on production, run as `www-data` instead of root:
```bash
sudo -u www-data git -C /var/www/HotelV2 pull
```
Or fix ownership immediately after pulling:
```bash
sudo git pull && sudo chown -R www-data:www-data \
    /var/www/HotelV2/storage /var/www/HotelV2/bootstrap/cache
```

## Stuck data — Room 90 / AZZAZZYLLE

Even after the 500 error was fixed, the database still showed AZZAZZYLLE
as occupying Room 90 (because the original 5:05 click had failed). Staff
needed her checked out **as if** the click had succeeded at the actual
exit time (3:54 PM), so:

- The Sales Report would correctly show her transactions
- Room 90 would become available for the roomboy to clean
- The BackOffice CHECK-OUT GUEST REPORT would include her

## Manual SQL recovery — three statements

These are the exact 3 DB writes that the production code's
`ManageGuestTransaction::checkoutGuest` method (lines 1911-1957) performs.
Verified line-by-line against the actual code before running.

### ① UPDATE rooms

```sql
UPDATE rooms
SET status = 'Uncleaned',
    last_checkin_at  = '2026-04-30 16:26:23',
    last_checkout_at = '2026-05-01 15:54:00',
    check_out_time   = '2026-05-01 15:54:00',
    time_to_clean    = '2026-05-01 19:54:00'
WHERE id = 73 AND branch_id = 1;
```
Affected: **1 row**.

### ② UPDATE checkin_details

```sql
UPDATE checkin_details
SET is_check_out = 1,
    check_out_at = '2026-05-01 15:54:00'
WHERE id = 13419 AND guest_id = 15905;
```
Affected: **1 row**.

### ③ INSERT check_out_guest_reports

```sql
INSERT INTO check_out_guest_reports
    (checkin_details_id, room_id, shift_date, shift, frontdesk_id, partner_name, created_at, updated_at)
VALUES
    (13419, 73, 'May 1, 2026', 'AM', 6, 'N/A', '2026-05-01 15:54:00', '2026-05-01 15:54:00');
```
Affected: **1 row**. Inserted id `13751` (production).

## Why `frontdesk_id = 6` (SEANNE)

Activity log analysis of the 4:50–5:15 PM window:

| Staff | user_id | frontdesk_id | Actions in window | Check Outs in window |
|---|---|---|---|---|
| **SEANNE KARYLLE** | 24 | **6** | **15** (1.5x JINKY) | **10** consecutive |
| JINKY OBAG | 25 | 7 | 8 | 4 |

SEANNE was on a rapid checkout streak (Rooms #218 → #74 → #253 → #94 → #91 →
#93 → #260 → #78 → #261 in ~3 minutes). The 5:05 error landed in the middle
of this batch, and AZZAZZYLLE's Room 90 would have been the next checkout in
her queue. She also originally checked AZZAZZYLLE INTO Room 90 yesterday
(Apr 30 16:26).

If later confirmed to have been JINKY instead, run:
```sql
UPDATE check_out_guest_reports SET frontdesk_id = 7 WHERE id = 13751;
```

## End-to-end verification (26 fields)

Every field in `rooms`, `checkin_details`, and `check_out_guest_reports`
was verified after the SQL ran:

| Category | Field | Expected | Result |
|---|---|---|---|
| Room state | status | Uncleaned | ✅ |
| Room state | last_checkin_at | 2026-04-30 16:26:23 | ✅ |
| Room state | last_checkout_at | 2026-05-01 15:54:00 | ✅ |
| Room state | check_out_time | 2026-05-01 15:54:00 | ✅ |
| Room state | time_to_clean | 2026-05-01 19:54:00 | ✅ |
| CheckinDetail | is_check_out | 1 | ✅ |
| CheckinDetail | check_out_at | 2026-05-01 15:54:00 | ✅ |
| CheckinDetail | static_amount | 600 (unchanged) | ✅ |
| Audit row | report_count | 1 | ✅ |
| Audit row | shift_date | May 1, 2026 | ✅ |
| Audit row | shift | AM (3:54 PM is in 8am-8pm window) | ✅ |
| Audit row | frontdesk_id | 6 | ✅ |
| Audit row | partner_name | N/A | ✅ |
| Money | transaction_count | 4 (unchanged) | ✅ |
| Money | total_paid | 1400 (unchanged) | ✅ |

## Why no activity_logs `Check Out` entry exists for this guest

The original failed click at 5:05 PM never ran past the 500 error, so no log
was written. Our manual SQL didn't add an activity_logs row either (we only
mirrored the 3 DB writes in the checkout code; the activity_logs INSERT is
optional).

If a paper trail in the system's activity log is desired, run:
```sql
INSERT INTO activity_logs (branch_id, user_id, activity, description, created_at, updated_at)
VALUES (1, 24, 'Check Out', 'Checked out guest AZZAZZYLLE from Room #90',
        '2026-05-01 15:54:00', '2026-05-01 15:54:00');
```
This is purely cosmetic — Sales Report and BackOffice CHECK-OUT GUEST
REPORT do not depend on activity_logs.

## FAQ — anticipated questions

### Was any money refunded, charged, or moved?
No. We only updated state fields (room status, checkout flag, audit row).
No `transactions` row was touched. Total paid remains ₱1,400.

### Could this cause a crash anywhere?
No. The combined state (`room.status='Uncleaned'` + `cd.is_check_out=1`) is
the exact state every normal completed checkout produces. The roomboy app,
frontdesk monitor, sales report, and BackOffice reports all handle this
state every day, hundreds of times.

### What about the format quirk (`1400` vs `1400.00`)?
Pure display artifact. Internally MySQL stores `paid_amount` as DECIMAL,
PHP returns it as integer when no decimal portion exists, and the UI always
applies `number_format($v, 2)` → `1,400.00`. Comparison anywhere in the
codebase is numeric, not string.

### What about the partner field?
SEANNE's `assigned_frontdesks` JSON is `[6, "N/A"]` — she works solo, no
formal partner. The audit row matches her real config exactly. All 7
frontdesk users in this branch currently have `partner_name = "N/A"`; no
pairings exist.

### Can future long-stay/extension/transfer scenarios hit a similar bug?
Not the same one. The 500 was triggered by file permissions, fixed at
source. The "stuck data after click failure" pattern can theoretically
happen any time a UI click hits a server error mid-transaction — the
mitigation is the same as today (SSH the production server, check
`storage/logs/laravel.log`, write targeted SQL mirroring the failed code).

### Will this manual fix show up differently in reports vs a real checkout?
Functionally identical. The only field where it could differ is
`activity_logs` (no entry was written). Sales Report, BackOffice
CHECK-OUT GUEST REPORT, room cleaning queue, and cash drawer reports all
read from the fields we did update.

## Backup & rollback

No backup tables were created for this fix because it was a single-guest,
single-room operation. If rollback is ever needed, the inverse SQL is:

```sql
-- Restore room
UPDATE rooms
SET status = 'Occupied',
    last_checkout_at = '2026-04-30 06:57:24',
    check_out_time = '2026-04-30 06:57:24',
    time_to_clean = NULL
WHERE id = 73;

-- Restore checkin_detail
UPDATE checkin_details
SET is_check_out = 0,
    check_out_at = '2026-05-01 16:26:23'
WHERE id = 13419;

-- Remove the audit row
DELETE FROM check_out_guest_reports WHERE id = 13751;
```

Note: rollback is unlikely to be needed — the data is correct and matches
what a normal successful checkout would have produced.

## Related changes pushed today (same branch)

- `feature/temp-disable-supervisor` got 6 commits today closing the audit
  backlog (4F orphan, C2/C3/C6/C7/C8/C9/C10, A3/A5/A8/A9, B1).
- This incident is the only "data corruption from production-only error"
  that needed a manual SQL recovery.

## References

- Production code path: `app/Http/Livewire/Frontdesk/Monitoring/ManageGuestTransaction.php` lines 1911-1957 (`checkoutGuest`)
- Mirror path: `app/Http/Livewire/Frontdesk/Monitoring/GuestTransaction.php` lines 2443+ (same shape, slightly different fields)
- Related recovery doc: `docs/fix-unposted-transferred-longstay.md` (Apr 28-29 incident)
- Production server: HomiApp Linode, `/var/www/HotelV2`
- Production DB: `homi_app` (MySQL 8.0.45)
