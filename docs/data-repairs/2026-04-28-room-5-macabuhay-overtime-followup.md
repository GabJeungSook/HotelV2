# Data Repair: Room #5 (Macabuhay Daisyjun) — overtime billing reconciliation

**Date:** 2026-04-28
**Trigger:** Quine Pino's Telegram message at 5:30 PM Apr 28 reporting that Room #5's ₱350 overtime fee was not posted at checkout
**Database:** `homi_app` (production)
**Affected guest:** Macabuhay Daisyjun (`guest_id = 14623`)
**Affected check-in:** `cid = 12333`, Room #5 (`room_id = 5`)
**Parent incident:** `docs/incidents/2026-04-28-fix-all-unresolved-incident-report.md`
**Recovery method:** Admin UI (no SQL required)

---

## ⚠ Status verdict — PAST RECORD problem, NOT current operational issue

| Indicator | Value | Meaning |
|---|---|---|
| Macabuhay's `is_check_out` | `1` | She's already correctly checked out |
| Macabuhay's `check_out_at` | `2026-04-28 05:46:36` | Frontdesk processed her exit at 5:46 AM |
| Macabuhay physically inside Room #5? | **No** | Already gone for ~13+ hours |
| Room #5 current status | `Uncleaned` (after later guest left 17:44:27) | Room is in normal active use |
| Multiple guests since her checkout | 5+ events on Room #5 today | Romel seploc, Vince alex, George carpentero, etc. |
| Operational impact NOW | **None** | This is a past billing reconciliation, not a live issue |

**Conclusion: Room #5 is operating normally. The only issue is the missing ₱350 overtime fee in Macabuhay's transaction history. This is bookkeeping cleanup, not an emergency.**

---

## 1. Source — Quine Pino's report

Quine Pino's message on the "Alma system upgrade 2025" Telegram group at 5:30 PM Apr 28:

> *"Sir @Nrparagas (april. 27pm report) rm. 5 kulng posting 350, iyng check out time 3:57am lng pro NG check out sya 5:56am na (over time pro walay posting payment) as per FD on duty nawala. Daw ni sa system gabie.. Thank you"*

**Translation:** "Sir, regarding the April 27 PM report: Room 5 is missing posting of ₱350. Her check-out time was supposed to be 3:57 AM only, but actual check-out was 5:56 AM (overtime, but no posting/payment). As per the frontdesk on duty, [the entry] disappeared from the system last night."

---

## 2. Macabuhay's full stay reconstruction

| Time (Asia/Manila) | Event | Money flow |
|---|---|---:|
| 2026-04-26 15:57:02 | Check-in to Room #5 (12-hour rate ₱350) | +₱350 (room) +₱200 (deposit) +₱355 (excess deposit) |
| 2026-04-27 03:16:18 | Extension #1 (+12h, paid with deposit, valid until 15:16) | -₱350 from deposit |
| 2026-04-27 15:16:59 | Add Deposit (top-up) | +₱345 (deposit) |
| 2026-04-27 15:17:48 | Extension #2 (+12h, paid with deposit, valid until **2026-04-28 03:57**) | -₱350 from deposit |
| 2026-04-27 23:17:30 | DB backup taken — `check_out_at='2026-04-28 03:57:02'`, `is_check_out=0` ✓ | — |
| 2026-04-27 23:19:54 | **Bug fires** — `is_check_out: 0→1`, fake `check_out_at` overwrites real | — |
| 2026-04-28 03:57:42 | Last paid period expires (she should leave or extend) | — |
| 2026-04-28 03:57 → 05:46 | **~1 hour 49 minutes overtime** without payment posted | ⚠ **₱350 not charged** |
| 2026-04-28 05:30 | First recovery (other 20 rooms) — Macabuhay's record reactivated | — |
| 2026-04-28 05:46:36 | Frontdesk processes Check Out from Room #5. **No overtime posting created.** | -₱? (just normal Check Out, no overtime fee) |
| 2026-04-28 09:31 onward | Multiple new guests use Room #5 normally | — |
| 2026-04-28 17:44:27 | Latest unrelated guest checks out → Room #5 status `Uncleaned` | — |

---

## 3. Money math (current state)

```
Deposits she paid:           200 + 355 + 345 = ₱900   (matches total_deposit=900)
Deductions used (extensions): 350 + 350      = ₱700   (matches total_deduction=700)
Remaining deposit (still held in her record):  ₱200
                                                ────
Total revenue from her so far:                  ₱1,600 (₱400 check-in + 2 extensions × ₱350 + ₱500 unaccounted)
```

Wait — let me re-verify. Her transactions:

| tid | Description | Paid Amount |
|---:|---|---:|
| 33003 | Guest Check In | ₱350 |
| 33004 | Deposit (Room Key & TV Remote) | ₱200 |
| 33686 | Deposit (Excess Amount) | ₱355 |
| 34141 | Extension (paid with deposit) | ₱350 (deducted from deposit, no new cash) |
| 34431 | Deposit (top-up) | ₱345 |
| 34432 | Extension (paid with deposit) | ₱350 (deducted from deposit, no new cash) |

**Cash actually paid by guest: ₱350 (room) + ₱200 + ₱355 + ₱345 (deposits) = ₱1,250**

(Extensions paid via deposit don't add new cash — they apply existing deposit.)

```
Cash received:                             ₱1,250
Net deposit still held:                    ₱200
                                            ────
Total currently in hotel hands from her:   ₱1,250 (already collected) — minus what's been used
```

---

## 4. The missing ₱350 — what should have happened

When she checked out at 5:46 AM (1h49m past 03:57), hotel policy charges ₱350 for the overtime period (one extra 12-hour-rate period, even partially used).

That ₱350 should have been recorded as:
- An Extension or Damage Charge transaction for ₱350
- Applied to her remaining deposit (₱200) → covers most
- Remaining ₱150 → either collected in cash or written off

**None of this happened.** The Check Out at 5:46 was processed without any overtime posting.

---

## 5. The solution — 2 admin UI clicks (no SQL)

**Cleanest path:** use the normal admin web interface. No SQL needed. Generates proper transaction records and activity_logs.

### Step 1 — Find Macabuhay's record

1. Open admin web interface
2. Navigate to: **admin → Guests** (or Guest Records / Past Guests, depending on your admin layout)
3. Search: `"Macabuhay daisyjun"` or `guest_id = 14623`
4. Open the record for `cid = 12333` (the one in Room #5, checked out 5:46 AM today)

### Step 2 — Add the Damage Charge for ₱350

1. Click **"Add Damage Charges"** button
2. Enter:
   - **Amount:** `350`
   - **Reason:** `Overtime past 03:57 not posted (2026-04-28 incident bug-related)`
3. Click Save

This creates:
- A new `transactions` row (transaction_type_id for Damage Charges)
- An `activity_logs` entry: "Add Damage Charges of ₱350 for guest Macabuhay daisyjun"

### Step 3 — Deduct her remaining ₱200 deposit toward the charge

1. Click **"Deduct Deposit"** button
2. Enter:
   - **Amount:** `200`
   - **Reason:** `Apply toward overtime damage charge (2026-04-28 incident)`
3. Click Save

This creates:
- A new `transactions` row (transaction_type_id for Deduct Deposit)
- An `activity_logs` entry: "Deducted deposit of ₱200 for guest Macabuhay daisyjun"
- Her `total_deduction` auto-increments from 700 → 900

### Step 4 — (Optional) Document the ₱150 write-off

In monthly accounting, log:

> 2026-04-28 — Bug-attributable loss: ₱150 (Macabuhay Daisyjun overstay overtime, partial deposit-offset). Reference: docs/data-repairs/2026-04-28-room-5-macabuhay-overtime-followup.md

---

## 6. Result after the solution

### Macabuhay's record after admin processing

```
checkin_details (cid=12333):
  is_check_out:    1                            ✓ (unchanged — she's gone)
  check_out_at:    2026-04-28 05:46:36          ✓ (unchanged)
  total_deposit:   900                          ✓ (unchanged)
  total_deduction: 900                          ← was 700 (+200 from new deposit deduction)
  Net deposit:     0                            (fully applied)

New transactions:
  + Damage Charges  ₱350   (overtime not posted)
  + Deduct Deposit  ₱200   (applied to damage)

New activity_logs:
  + "Add Damage Charges of ₱350 for guest Macabuhay daisyjun"
  + "Deducted deposit of ₱200 for guest Macabuhay daisyjun"
```

### Hotel financial outcome

| Metric | Value |
|---|---:|
| Cash collected from her (during stay) | ₱1,250 |
| Net deposit applied (originally) toward extensions | ₱700 |
| Net deposit applied toward overtime (after this fix) | ₱200 |
| **Hotel total revenue from her** | **₱1,250 cash + ₱200 deposit applied = ₱1,450** |
| Overtime owed but unpaid | ₱150 (write-off) |
| **Documented bug-attributable loss** | **₱150** |

---

## 7. Why this approach is correct

### Why admin UI (not SQL)

| Method | Audit trail | Activity logs | Recommended |
|---|---|---|---|
| **Admin UI (Add Damage Charges + Deduct Deposit)** | ✅ Full, proper Laravel transactions + activity_logs | ✅ Auto-generated | ✅ **YES** |
| Direct SQL INSERT into transactions | ❌ Missing Laravel events, possible orphaned data | ❌ Manual insert needed | ❌ No |
| Direct UPDATE of `total_deduction` | ❌ No transaction row, audit gap | ❌ Manual insert needed | ❌ No |

### Why this is fair to all parties

| Party | Outcome |
|---|---|
| **Macabuhay (guest, already gone)** | Standard policy: deposit forfeited for overstay. No surprise — overtime was always a charge. |
| **Hotel** | Recovers ₱200 of ₱350 owed. Properly records ₱150 as bug-attributable loss. |
| **Future audits** | Can see exactly what happened: damage charge documents the missed billing, deposit deduction shows recovery. |
| **Accounting close-out** | Monthly P&L shows correct revenue + clearly documented one-off loss. |

---

## 8. What this DOES NOT touch

| Thing | Reason |
|---|---|
| Macabuhay's `is_check_out` | She's already gone — keep as 1 |
| Macabuhay's `check_out_at` | Already correct (5:46:36) |
| Room #5's status | Currently `Uncleaned` (proper state from latest unrelated guest) |
| Other guests' records | Separate from this fix |
| Room #5's recovery (the original 20-room recovery) | Already done at 5:30 AM |

---

## 9. Why the overtime didn't post (root cause)

The bug at 23:19:54 force-closed Macabuhay's record. By 5:30 AM the recovery had restored `is_check_out=0` and other fields. By 5:46 AM frontdesk processed her checkout.

But during the chaotic morning:
- Her dates were briefly bug-overwritten then restored
- Frontdesk's checkout flow may have computed overtime against a confused state
- Or the operator simply skipped the overtime calculation because of the operational chaos
- Result: a normal "Check Out" transaction was processed, but no overtime fee got attached

This is a **derivative failure** of the original bug — not the bug directly, but a consequence of operating under the bug's chaos.

---

## 10. Operator checklist

- [ ] Open admin web interface
- [ ] Navigate to Guests / Past Guest Records
- [ ] Search for "Macabuhay daisyjun" or guest_id=14623
- [ ] Confirm record is the right one (cid=12333, Room #5, checked out 5:46 AM Apr 28)
- [ ] Click **Add Damage Charges**
  - Amount: ₱350
  - Reason: "Overtime past 03:57 not posted (2026-04-28 incident bug-related)"
  - Save
- [ ] Click **Deduct Deposit**
  - Amount: ₱200
  - Reason: "Apply toward overtime damage charge"
  - Save
- [ ] Verify in her record: `total_deduction` is now 900 (was 700)
- [ ] Verify 2 new transactions visible in her transaction history
- [ ] Verify 2 new entries in activity_logs ("Add Damage Charges" + "Deducted deposit")
- [ ] (Optional) Note ₱150 write-off in monthly accounting log

---

## 11. Time pressure & priority

| Aspect | Status |
|---|---|
| Operational impact NOW | **None** (room functioning normally) |
| Financial impact NOW | **₱200 deposit at risk** (held but not applied) |
| Time-sensitivity | **Low** — billing reconciliation, not emergency |
| Best practice | Complete within 24 hours of incident for clean accounting |

**Priority order across all incident issues:**

1. Room #71 (Elmettose) — **active operational issue** — fix first (currently in progress)
2. Room #5 (Macabuhay) — **past billing cleanup** — handle after #71 is done

---

## 12. Sign-off

| Step | Status |
|---|---|
| Issue documented | 2026-04-28 |
| Solution prepared | 2026-04-28 |
| Damage Charge added in admin UI | _(operator + timestamp)_ |
| Deposit deducted in admin UI | _(operator + timestamp)_ |
| ₱150 write-off logged in monthly report | _(date + amount)_ |
| Reviewer confirmed clean | _(reviewer + date)_ |

---

## 13. Related documents

- Parent incident: `docs/incidents/2026-04-28-fix-all-unresolved-incident-report.md`
- Final trace: `docs/incidents/2026-04-28-final-trace-and-solution.md`
- Bug report: `docs/bugs/2026-04-28-fixall-unresolved-flips-active-extension-checkins.md`
- Code review: `docs/bugs/2026-04-28-unresolved-checkins-code-review.md`
- 20-room recovery (where Macabuhay was reactivated at 5:30 AM): `docs/data-repairs/2026-04-28-restore-10-flipped-rooms-after-fixall.md`
- Recovery SQL: `docs/data-repairs/2026-04-28-recover.sql`
- Room #71 followup: `docs/data-repairs/2026-04-28-room-71-elmettose-followup.md`
- Room #71 recovery script: `docs/data-repairs/2026-04-28-room-71-elmettose-RECOVERY-SCRIPT.md`

---

*This document covers the second-tier follow-up for the 2026-04-28 incident: a billing reconciliation that's purely historical and operationally low-priority.*
