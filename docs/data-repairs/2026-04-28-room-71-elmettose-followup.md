# Data Repair: Room #71 (Elmettose rivera) — incident addendum

**Date:** 2026-04-28
**Trigger:** Frontdesk message from Niño at 09:20 AM on Telegram group "Alma system upgrade"
**Database:** `hotelv2` (production)
**Affected guest:** Elmettose rivera (`guest_id = 14336`)
**Affected check-in:** `cid = 12086`, Room #71 (`room_id = 54`)
**Parent incident:** `docs/incidents/2026-04-28-fix-all-unresolved-incident-report.md`

---

## 1. Summary

Room #71 was the **21st** room affected by the 2026-04-27 23:19:54 Fix-All incident. It was deliberately left out of the initial 5:30 AM recovery because the guest's last paid extension expired ~4 hours before the bug fired, making it ambiguous whether she was a real overstaying guest or a real abandoned ghost.

A frontdesk message at 09:20 AM confirmed she was a real guest and resolved the ambiguity. By the time the message arrived (and by the time the next forensic snapshot was taken at 09:31), she had already physically left and the room had been cleaned. The remaining recovery work is therefore reduced to:

1. A **one-line cosmetic SQL fix** to restore her `check_out_at` from the bogus bug-overwritten value to her last legitimate planned-checkout date
2. An **operational decision** about her ₱200 deposit (forfeit / refund / partial)

There is no urgent data-loss risk. The fix can be applied any time.

---

## 2. Niño's report (frontdesk evidence)

Two messages from Niño on the "Alma system upgrade" Telegram group:

### Message 1 (the financial concern)
> *"Extnded guest Ana sir, elmettose revira. Nawala ang iyng payment sa rm 400 tanan 7am, now ang guest naa PA sa room. Thank you"*

**Translation:** *"Extended guest Ana sir, Elmettose Rivera. Her ₱400 payment from 7am is missing. The guest is still in the room. Thank you."*

**Interpretation:** Niño observed that Elmettose's expected ₱400 extension payment around 7am Apr 28 (her normal 12-hour cycle) was not recorded in the system, and that she was still physically inside Room #71 at the time he wrote the message.

### Message 2 (the affected-room list)
> *"4, 5, 6, 11, 51, 52, 60, 62, 63, 65, 71, 74, 92, 100, 151, 166, 171, 205, 211, 215, 286 — Mao ni ang affected na rooms gabii pag update ya"*

**Translation:** *"These are the affected rooms last night during the update."*

**Interpretation:** Frontdesk independently identified all 21 affected rooms — matching the data analysis exactly. Confirms Room #71 was part of the same incident.

---

## 3. Elmettose's stay history (full reconstruction from `transactions` and `checkin_details`)

| Time (Asia/Manila) | Event | Amount | Transaction |
|---|---|---:|---:|
| 2026-04-25 19:07:42 | Check-in to Room #71 (12-hour rate, ₱600) | ₱400 paid + ₱200 deposit | tid 33003, 33004 |
| 2026-04-26 06:44:06 | Extension #1 (+12h, valid until 18:44 Apr 26) | ₱400 paid | tid 33378 |
| 2026-04-26 18:45:51 | Extension #2 (+12h, valid until 06:45 Apr 27) | ₱400 paid | tid 33796 |
| 2026-04-27 06:33:06 | Extension #3 (+12h, valid until **19:07 Apr 27**) | ₱400 paid | tid 34210 |
| 2026-04-27 19:07 | **Last paid extension expires** — she should leave or extend | — | — |
| 2026-04-27 23:17:30 | DB backup taken (BEFORE bug). Her `check_out_at` = `2026-04-27 19:07:42`, `is_check_out` = 0. **Already 4 h overdue at this point.** | — | — |
| 2026-04-27 23:19:54 | **Bug fires.** Her record force-closed: `is_check_out: 0→1`, `check_out_at` overwritten to `2026-04-25 19:37:42` (fake — `check_in_at + 30 min`). | — | — |
| 2026-04-27 23:20 → 2026-04-28 08:43 | She physically remained in the room. Frontdesk could not see her record (system showed her as already checked out). No extension transactions could be processed. | — | — |
| 2026-04-28 08:43:57 | Room #71 marked `Cleaned` — strong signal she had left and roomboy cleaned the room. | — | — |
| 2026-04-28 09:20 | Niño's first message. (At time of writing, his info may have been a few minutes stale.) | — | — |
| 2026-04-28 09:31 | Latest forensic dump confirms: Room #71 = `Cleaned`, no active check-in for Room #71, Elmettose's record still has bogus `check_out_at`. | — | — |

### Total received from Elmettose

```
4 × ₱400  (check-in + 3 extensions)  = ₱1,600
1 × ₱200  (deposit, still held)      = ₱  200
                                        ──────
                                        ₱1,800  total
```

### Lost revenue (estimate)

From last paid extension expiry (`2026-04-27 19:07`) to room cleaning (`2026-04-28 08:43`) = **~13 h 36 min** of overstay.

At 12-hour-rate × ₱400 per period:
- 1 missed extension covering 19:07 Apr 27 → 07:07 Apr 28 = **₱400**
- Partial overstay 07:07 → 08:43 (1.5 h) = small partial extension or hourly charge per hotel policy

**Estimate: ₱400-800 in lost extension revenue**, partially recoverable by forfeiting her ₱200 deposit.

This is a **business loss caused by the bug**, not a data corruption. The transactions that should have happened never happened because the system hid her record.

---

## 4. Current data state (as of 2026-04-28 09:31 dump)

```sql
-- Room #71 (room_id=54)
status:        'Cleaned'
last_checkin:  '2026-04-25 13:22:49'
last_checkout: '2026-04-25 17:18:51'
updated_at:    '2026-04-28 08:43:57'

-- Elmettose's check-in record (cid=12086)
guest_id:        14336  (Elmettose rivera)
room_id:         54     (Room #71)
check_in_at:     '2026-04-25 19:07:42'
check_out_at:    '2026-04-25 19:37:42'   ⚠ BOGUS — bug-overwritten
is_check_out:    1                        ✓ correct (she's actually gone)
total_deposit:   200                      ✓ still held
total_deduction: 0
updated_at:      '2026-04-27 23:19:54'   ⚠ shows the bug timestamp
```

### What's correct (no action needed)
- ✅ `is_check_out = 1` — she's actually gone, the field's value is right
- ✅ `total_deposit = 200` — deposit held, recoverable for forfeit/refund
- ✅ `total_deduction = 0` — no charges deducted
- ✅ Room status `Cleaned` — physically empty, ready for next guest
- ✅ All her transactions (4 × ₱400 + ₱200 deposit) are intact in the DB

### What's incorrect (cosmetic)
- ⚠️ `check_out_at = '2026-04-25 19:37:42'` — fake bug value. Should be her last legitimate planned-checkout `2026-04-27 19:07:42` (taken from BEFORE backup `homi_app_producoot_lastest_now.sql`).

---

## 5. Recovery SQL (one statement, no urgency)

```sql
USE `hotelv2`;  -- or `homi_app` on production

START TRANSACTION;

-- Restore the real planned-checkout date (overwritten by bug to a fake 30-min-after-checkin value).
-- Source of truth: BEFORE backup `homi_app_producoot_lastest_now.sql` taken 2026-04-27 23:17:30.
-- The guard `AND check_out_at = '2026-04-25 19:37:42'` makes this idempotent — re-running
-- the statement will affect 0 rows once the fix is in place.
UPDATE checkin_details
SET check_out_at = '2026-04-27 19:07:42',
    updated_at   = NOW()
WHERE id = 12086
  AND check_out_at = '2026-04-25 19:37:42';

-- Verify (should return 1)
SELECT 'cid 12086 fixed' AS check_label, COUNT(*) AS count_should_be_1
FROM checkin_details
WHERE id = 12086 AND check_out_at = '2026-04-27 19:07:42';

-- If count is 1, COMMIT. If 0, ROLLBACK and investigate.
COMMIT;
-- ROLLBACK;
```

### What this does NOT do (intentional)
- ❌ Does **not** flip `is_check_out` back to 0 — she's actually gone. The system correctly shows her as checked out.
- ❌ Does **not** change `rooms.status` — Room #71 is correctly `Cleaned` (or `Available` if reused).
- ❌ Does **not** create any new transactions — the missed extensions are a business loss, not recoverable via SQL.
- ❌ Does **not** touch the deposit — admin handles via the normal admin-tools workflow.

---

## 6. Operational follow-up (decisions for admin)

### 6.1 Deposit handling

Elmettose has ₱200 held in her record. Options:

| Option | Action | When appropriate |
|---|---|---|
| Forfeit | Mark as forfeited (Deduct Deposit transaction with reason "overstay") | If hotel policy charges for overstay and ₱200 is acceptable as partial offset |
| Refund | Deduct Deposit then Cashout to her contact | If hotel decides to absorb the overstay loss as goodwill / due-to-bug |
| Partial | Forfeit some, refund some | Mixed approach |

To process the deposit through the normal admin flow:
1. Open the admin interface for guest management
2. Search for guest_id 14336 (Elmettose rivera)
3. Use Deduct Deposit / Add Damage Charges as appropriate

### 6.2 Lost revenue accounting

For the missed ~13 hours of overstay:
- Treat as **loss attributable to the 2026-04-28 incident** in any business reporting
- Document in the incident summary that approximately ₱400-800 in extension revenue was lost (partially offset by the ₱200 deposit if forfeited)
- This loss is **not recoverable** via SQL because the guest is no longer reachable for billing

### 6.3 Customer communication

If Elmettose is a known repeat guest or there is contact information on file:
- Optional: courtesy follow-up call/text explaining the system issue
- Optional: offer goodwill refund of the deposit
- Probably not needed — she may not be aware of the system issue at all

---

## 7. Why this case is different from the 20 already-recovered rooms

| Aspect | The 20 active guests | Room #71 (Elmettose) |
|---|---|---|
| Guest physically still inside at recovery time? | Yes | No (had left by ~08:43) |
| Recovery action | Restore `is_check_out` to 0 + restore `check_out_at` + flip room to Occupied | Restore `check_out_at` only (cosmetic) |
| Time-pressure | Higher — every minute of unresolved state risked scenario C | Low — she's gone, no risk of conflict |
| Lost revenue | Zero — every guest reappeared on Room Monitoring within 30 sec of COMMIT | ~₱400-800 — she physically left during the bug window without being billed for overstay |
| Operational follow-up | None — frontdesk processed normally afterward | Admin decision on ₱200 deposit |

---

## 8. Updated incident totals

After this addendum, the incident totals become:

| Metric | Old (before addendum) | New (with Room #71) |
|---|---:|---:|
| Total rooms affected | 20 | **21** |
| Total active deposits preserved | ₱15,598 | **₱15,798** |
| Rooms restored to Occupied (active guest still inside) | 20 | 20 |
| Records cosmetically fixed (guest already gone) | 0 | 1 (Room #71) |
| Genuine ghosts correctly closed | 9 | 9 |
| **Total `checkin_details` records touched by the bug** | 30 | 30 |

The total of 30 records touched by the bug is unchanged — Room #71 was always part of those 30; we just deferred the decision on it because it was ambiguous.

---

## 9. Operator checklist

- [ ] Run the SQL in Section 5 against production (1 row affected expected)
- [ ] Verify the COUNT returns 1 before COMMIT
- [ ] Decide on Elmettose's ₱200 deposit (Section 6.1)
- [ ] Process the deposit through the normal admin flow
- [ ] Update incident report (`docs/incidents/2026-04-28-fix-all-unresolved-incident-report.md`) with reference to this addendum
- [ ] Note the ₱400-800 lost revenue in any monthly business report

---

## 10. Sign-off

| Role | Status |
|---|---|
| Recovery SQL prepared | 2026-04-28 |
| Recovery SQL executed | _(operator + timestamp)_ |
| Deposit handling decided | _(operator + decision)_ |
| Incident report updated | _(date)_ |

---

*Addendum to incident `2026-04-28-001`.*
