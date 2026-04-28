# Data Repair: Room #71 (Elmettose Rivera) — incident addendum

**Date:** 2026-04-28
**Trigger:** Frontdesk message from Niño at 09:20 AM on Telegram group "Alma system upgrade"; later confirmed at ~6 PM that the guest was still physically in the room
**Database:** `homi_app` (production)
**Affected guest:** Elmettose rivera (`guest_id = 14336`)
**Affected check-in:** `cid = 12086`, Room #71 (`room_id = 54`)
**Parent incident:** `docs/incidents/2026-04-28-fix-all-unresolved-incident-report.md`
**Recovery script:** `docs/data-repairs/2026-04-28-room-71-elmettose-RECOVERY-SCRIPT.md`

---

## ⚠ CORRECTION — earlier version of this doc was wrong

An earlier version of this doc (committed at `aaeb743`) assumed Elmettose had physically left the room by mid-morning Apr 28 (because the room status had flipped to `Cleaned` at 08:43 AM). It recommended a cosmetic-only fix (just `check_out_at`).

That assumption was incorrect. By 6 PM on Apr 28, frontdesk confirmed via Telegram that **Elmettose was still physically in Room #71** — she never left. The room had been cycled through `Cleaned` ↔ `Occupied` ↔ `Cleaned` 16 times during the day because kiosk kept assigning Room #71 to walk-ins (since the system showed it empty), and frontdesk kept rescue-transferring those walk-ins out.

**This revised doc reflects the corrected understanding and the proper full recovery (not just cosmetic).**

---

## 1. Summary

Room #71 was the **21st** room affected by the 2026-04-27 23:19:54 Fix-All incident. It was deliberately left out of the initial 5:30 AM recovery because the guest's last paid extension expired ~4 hours before the bug fired, making it ambiguous whether she was a real overstaying guest or a real abandoned ghost.

Through the day on Apr 28:
- A frontdesk Telegram message at 09:20 AM confirmed her presence
- A list of "21 affected rooms" was independently confirmed by frontdesk
- 16 walk-in guests were briefly assigned to Room #71 by kiosk and rescue-transferred to other rooms
- Frontdesk re-confirmed at ~6 PM that she was still physically inside

The remaining recovery work is therefore a **full restoration** (not cosmetic):
1. Reactivate her check-in record (`is_check_out: 1 → 0`)
2. Restore Room #71 status (`Cleaned → Occupied`)
3. Frontdesk then bills her overdue extensions through the normal UI

---

## 2. Niño's report (frontdesk evidence — confirmed correct)

Two messages from Niño on the "Alma system upgrade" Telegram group:

### Message 1 (the financial concern)
> *"Extnded guest Ana sir, elmettose revira. Nawala ang iyng payment sa rm 400 tanan 7am, now ang guest naa PA sa room. Thank you"*

**Translation:** Extended guest Ana sir, Elmettose Rivera. Her ₱400 payment from 7am is missing. The guest is still in the room.

**Interpretation:** Niño reported that her expected ~7 AM extension never landed in the system (because the bug had hidden her record), and that she was still physically inside the room.

### Message 2 (the affected-room list)
> *"4, 5, 6, 11, 51, 52, 60, 62, 63, 65, 71, 74, 92, 100, 151, 166, 171, 205, 211, 215, 286 — Mao ni ang affected na rooms gabii pag update ya"*

**Translation:** These are the affected rooms last night during the update.

**Interpretation:** 21 rooms affected, including #71. Matches the data analysis exactly.

### Confirmation at 6 PM Apr 28

Frontdesk confirmed in chat that the physical guest in Room #71 is **Elmettose Revira** (matches database `Elmettose rivera`, spelling variant). She had not left. The 16 walk-in transfers throughout the day were unrelated rescues, not her.

---

## 3. Elmettose's stay history (full reconstruction from `transactions` and `checkin_details`)

| Time (Asia/Manila) | Event | Amount | Transaction |
|---|---|---:|---:|
| 2026-04-25 19:07:42 | Check-in to Room #71 (12-hour rate, ₱600 total) | ₱400 paid + ₱200 deposit | tid 33003, 33004 |
| 2026-04-26 06:44:06 | Extension #1 (+12h, valid until 18:44 Apr 26) | ₱400 paid | tid 33378 |
| 2026-04-26 18:45:51 | Extension #2 (+12h, valid until 06:45 Apr 27) | ₱400 paid | tid 33796 |
| 2026-04-27 06:33:06 | Extension #3 (+12h, valid until **19:07 Apr 27**) | ₱400 paid | tid 34210 |
| 2026-04-27 19:07 | **Last paid extension expires** — she should leave or extend | — | — |
| 2026-04-27 23:17:30 | DB backup taken (`homi_app_producoot_lastest_now.sql`) — already 4 h overdue at this point | — | — |
| 2026-04-27 23:19:54 | **Bug fires.** Force-closed: `is_check_out: 0→1`, `check_out_at` overwritten to fake `'2026-04-25 19:37:42'` | — | — |
| 2026-04-27 23:20:03 | Room #71 status flipped Occupied → Available | — | — |
| 2026-04-27 23:20 → 2026-04-28 ~now | She remained physically in the room. Frontdesk could not see her record (system showed her as already checked out). No extension transactions could be processed for her. | — | — |
| 2026-04-28 ~07:01 → ~18:06 | 16 walk-ins assigned to Room #71 by kiosk; each rescue-transferred out by frontdesk. Elmettose remained physically inside throughout. | — | — |
| 2026-04-28 ~08:25 | Cosmetic fix landed: `check_out_at` restored to real value `'2026-04-27 19:07:42'`. **`is_check_out` was NOT restored — still equals 1.** | — | — |
| 2026-04-28 18:00 onward | Frontdesk reports issue, recovery prepared | — | — |

### Total received from Elmettose

```
1 × ₱400  (check-in)                    = ₱400
1 × ₱200  (deposit, still held)         = ₱200
3 × ₱400  (extensions)                  = ₱1,200
                                          ──────
                                          ₱1,800  total received
```

### Estimated unpaid overstay

From last paid extension expiry (`2026-04-27 19:07`) to time of recovery (~6 PM Apr 28) = **~23-24 hours** of overstay.

At 12-hour-rate × ₱400 per period:
- 1 missed extension `19:07 Apr 27 → 07:07 Apr 28` = **₱400**
- Partial overstay `07:07 → ~19:00 Apr 28` (~12 h) = potentially another **₱400**

**Estimate: ~₱600-800 in unpaid extensions.** Recoverable through frontdesk Check Out + Deposit Deduction (Option B in Section 5).

---

## 4. Current data state (as of 2026-04-28 6 PM dump, before recovery)

```sql
-- Elmettose's check-in record (cid=12086)
guest_id:        14336  (Elmettose rivera)
room_id:         54     (Room #71)
check_in_at:     '2026-04-25 19:07:42'
check_out_at:    '2026-04-27 19:07:42'   ✓ real value (already restored at 08:25 today)
is_check_out:    1                        ⚠ wrong — she's actually still in the room
total_deposit:   200                      ✓ still held
total_deduction: 0                        ✓
updated_at:      '2026-04-28 08:25:08'    (cosmetic fix moment)

-- Room #71 (room_id=54)
status:          'Cleaned'   ⚠ wrong — should be 'Occupied'
last_checkin_at: stale
last_checkout_at: stale
updated_at:      '2026-04-28 18:06:35'  (last walk-in rescue transfer time)
```

### What's correct (no change needed)
- ✅ `check_out_at = '2026-04-27 19:07:42'` (real value, already restored)
- ✅ `total_deposit = 200`
- ✅ `total_deduction = 0`
- ✅ All 5 transactions intact in `transactions` table

### What's wrong (needs fix)
- ⚠ `is_check_out = 1` (must be `0` — she's still active)
- ⚠ `room_status = 'Cleaned'` (must be `'Occupied'` — real guest inside)

---

## 5. Recovery procedure

The complete step-by-step script is in:
**`docs/data-repairs/2026-04-28-room-71-elmettose-RECOVERY-SCRIPT.md`**

That document has the full 8-step procedure (read-only verifications, transaction wrapper, atomic UPDATEs, pre-COMMIT verify, COMMIT, post-COMMIT verify) with expected results at each step.

### Summary of the SQL changes inside the transaction

```sql
START TRANSACTION;

UPDATE checkin_details
SET is_check_out = 0, updated_at = NOW()
WHERE id = 12086 AND is_check_out = 1;

UPDATE rooms
SET status = 'Occupied', updated_at = NOW()
WHERE id = 54 AND status IN ('Available', 'Cleaned');

-- (verify with SELECT before COMMIT)

COMMIT;
```

### Operational follow-up after COMMIT

After COMMIT, frontdesk processes her overdue billing through the normal UI. Three options:

| Option | Action | Money flow |
|---|---|---:|
| **A — She extends and stays** | Click "Add Extension" in Room Monitoring → collect ₱400 cash | +₱400 cash |
| **B — She checks out today (recommended)** | Click "Check Out" → Deduct Deposit ₱200 → collect ~₱600 cash for 2 missed extensions | +₱600 cash + ₱200 deposit applied |
| **C — Hotel absorbs the loss** | Click "Check Out" → Cashout ₱200 deposit | -₱200 (hotel goodwill) |

**Recommended: Option B** — fair to both parties. She pays for hours she actually stayed; deposit covers part. Hotel collects what's owed.

---

## 6. Why this is different from the 20 already-recovered rooms

| Aspect | The 20 active guests (recovered 5:30 AM) | Room #71 (Elmettose) |
|---|---|---|
| Was guest physically inside at recovery time? | Yes | Yes (still inside throughout the day) |
| Initial recovery action | Restore `is_check_out=0` + restore real `check_out_at` + flip room to Occupied | Was DEFERRED at 5:30 AM (ambiguous case) |
| Cosmetic-only fix attempted at 8:25 AM | N/A | `check_out_at` restored. **Not enough** — `is_check_out` still wrong, room status still wrong. |
| Required full recovery | Yes (done) | Yes (this doc — pending operator execution) |
| Operational follow-up | None — frontdesk worked normally afterward | Frontdesk must bill her overdue extensions |

---

## 7. Why we are confident this recovery is correct

1. **Source of truth.** The BEFORE backup `homi_app_producoot_lastest_now.sql` (taken 2026-04-27 23:17:30 — exactly 2 minutes 24 seconds before the bug fired) shows her real pre-bug state: `is_check_out=0`, `check_out_at='2026-04-27 19:07:42'`, room status `Occupied`. Recovery values come directly from this snapshot.

2. **Independent confirmation by frontdesk.** Niño's two Telegram messages plus the 6 PM verbal confirmation all agree she's a real guest in Room #71.

3. **Mathematical consistency.** Her transaction history (4 paid periods × 12 hours = 48 hours) matches the BEFORE backup's `check_out_at` value (`Apr 25 19:07 + 48 h = Apr 27 19:07`). The bug's "30-minute stay" value is mathematically impossible — she couldn't have paid 3 extensions if she only stayed 30 min.

4. **Activity log evidence.** No "Check Out" entry exists for her in `activity_logs`. She has never been formally checked out — she just disappeared from the system due to the bug.

5. **Idempotent + transactional safety.** The recovery SQL has guards (`AND is_check_out = 1`, `AND status IN ('Available','Cleaned')`) and is wrapped in a transaction with pre-COMMIT verification. Worst case is a no-op or a rolled-back transaction.

6. **Reversible.** Explicit ROLLBACK procedure in the recovery script if anything looks wrong post-COMMIT.

---

## 8. Updated incident totals

After this recovery, the incident totals become:

| Metric | Value |
|---|---:|
| Total rooms affected | 21 |
| Total active deposits preserved | ₱15,798 |
| Lost revenue (pre-fix unbillable hours) | ~₱600-800 (recoverable via Option B Check Out) |
| Net hotel revenue from Elmettose | ₱2,400 (₱1,600 paid + ₱200 deposit + ~₱600 cash on checkout) |
| Money lost to the bug | **₱0** (assuming Option B Check Out) |

---

## 9. Operator checklist

- [ ] Review `docs/data-repairs/2026-04-28-room-71-elmettose-RECOVERY-SCRIPT.md`
- [ ] Take a fresh backup of production before running recovery
- [ ] Run Steps 1-2 (read-only pre-flight) and confirm baseline
- [ ] Run Steps 3-6 (transaction + UPDATEs + verify) inside one TablePlus tab
- [ ] Verify Step 6 result shows `is_check_out=0` AND `room_status=Occupied`
- [ ] Run Step 7 (`COMMIT;`) — point of no return
- [ ] Run Step 8 (post-commit final verification)
- [ ] Tell frontdesk to refresh Room Monitoring
- [ ] Frontdesk processes Option B (Check Out + ₱200 deposit + ~₱600 cash)
- [ ] Verify walk-in rescue cycle has stopped (no new "from Room #71" transfers in `activity_logs`)
- [ ] Update this doc's `Sign-off` section with operator name + timestamp

---

## 10. Sign-off

| Role | Status |
|---|---|
| Recovery script prepared | 2026-04-28 (this revised version) |
| Recovery SQL executed | _(operator + timestamp)_ |
| Frontdesk billing completed (Option ___) | _(operator + amount collected)_ |
| Walk-in rescue cycle confirmed stopped | _(timestamp of last "from Room #71" transfer)_ |
| Incident addendum reviewed | _(reviewer + date)_ |

---

*Revised version. Supersedes the earlier "she left" interpretation. The truth: she stayed throughout, the bug just hid her record.*
