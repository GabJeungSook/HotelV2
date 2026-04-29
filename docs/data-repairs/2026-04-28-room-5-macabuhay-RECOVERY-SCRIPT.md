# Room #5 / Macabuhay Daisyjun — Overtime Recovery Script (SQL alternative)

**Date:** 2026-04-28
**Target:** Production database (`homi_app`)
**Affected guest:** Macabuhay Daisyjun (cid=12333, guest_id=14623)
**Affected room:** Room #5 (room_id=5) — already free, in normal use
**Estimated time:** 3-5 minutes (SQL only)
**Reference docs:**
- `docs/incidents/2026-04-28-fix-all-unresolved-incident-report.md`
- `docs/data-repairs/2026-04-28-room-5-macabuhay-overtime-followup.md`

---

## ⚠ Read this first — admin UI is the preferred path

The followup doc (`2026-04-28-room-5-macabuhay-overtime-followup.md`) recommends doing this through the **admin UI** (Add Damage Charges → Deduct Deposit). That path:

- Generates proper Laravel events
- Updates `cash_on_drawers` and `shift_logs` running totals automatically
- Has a clean audit trail
- Cannot easily corrupt running shift counters

This SQL is a **fallback** in case the admin UI flow is unavailable, broken, or the operator wants a single transactional batch instead of two clicks. **Use the admin UI first if possible.**

If you do run this SQL, you must:
- Manually verify shift counters (`cash_on_drawers`, `shift_logs.totals`) afterward
- Note the bypass in the activity_logs `description` so future audits can trace it back

---

## 🎯 Goal

Post the missing ₱350 overtime fee to Macabuhay's already-closed record, applying her remaining ₱200 deposit toward it. The remaining ₱150 is documented as a bug-attributable write-off.

---

## ❓ Problem summary

| What happened | Value |
|---|---|
| Macabuhay's last paid period expired | 2026-04-28 03:57 |
| Frontdesk processed her checkout | 2026-04-28 05:46:36 |
| Overtime gap | ~1 hour 49 minutes |
| Fee that should have posted | **₱350** |
| Why it didn't post | Operator skipped the overtime calc during the chaotic post-bug morning |
| Her remaining deposit (held in record) | **₱200** |
| Net hotel loss after partial deposit forfeit | **₱150** |

---

## 🔧 The fix

Three writes inside one transaction:

| Table | Action | Effect |
|---|---|---|
| `transactions` | INSERT type_id=4 (Damage Charges) | Records the ₱350 charge |
| `transactions` | INSERT type_id=5 (Cashout / Deduct Deposit) | Records the ₱200 deposit forfeit |
| `checkin_details` (id=12333) | UPDATE total_deduction 700 → 900 | Keeps record's deduction tally accurate |
| `activity_logs` | INSERT × 2 | Audit trail entries |

---

## 🚦 Pre-conditions (must be true before running)

- [ ] You verified Macabuhay's record is `is_check_out=1` (she's already gone — this is a past-record cleanup, NOT a live op)
- [ ] You verified `total_deduction = 700` currently (no one else has posted this fee)
- [ ] Connected to PRODUCTION database (`homi_app`) via TablePlus
- [ ] You have a current open shift_log row OR you know the correct `shift_log_id` to use
- [ ] You understand admin UI is preferred and have a reason to use SQL instead

---

## 📋 The complete recovery script

Run statements **one at a time** in TablePlus (click on the statement, then **Run Current**). Verify each result before continuing.

### STEP 1 — Pre-flight check (read-only, safe)

```sql
SELECT cd.id AS cid,
       cd.guest_id,
       g.name AS guest,
       cd.room_id,
       r.number AS room,
       cd.is_check_out,
       cd.check_out_at,
       cd.total_deposit,
       cd.total_deduction,
       (cd.total_deposit - cd.total_deduction) AS remaining_deposit
FROM checkin_details cd
JOIN rooms r ON r.id = cd.room_id
JOIN guests g ON g.id = cd.guest_id
WHERE cd.id = 12333;
```

**Expected result — 1 row:**

| Field | Expected value |
|---|---|
| cid | `12333` |
| guest_id | `14623` |
| guest | `Macabuhay daisyjun` |
| room_id | `5` |
| room | `5` |
| is_check_out | `1` ✓ |
| check_out_at | `2026-04-28 05:46:36` ✓ |
| total_deposit | `900` |
| **total_deduction** | **`700`** ⚠ (the value we're about to bump to 900) |
| **remaining_deposit** | **`200`** (this is what we forfeit) |

**Decision:** If `total_deduction = 700` AND `remaining_deposit = 200` → proceed. Otherwise stop — someone may have already processed this.

---

### STEP 2 — Find the current open shift_log_id (read-only, safe)

```sql
SELECT id AS shift_log_id, branch_id, shift, started_at, ended_at, user_id
FROM shift_logs
WHERE branch_id = 1
  AND ended_at IS NULL
ORDER BY id DESC
LIMIT 3;
```

**Expected result:** 1 row representing the currently-open shift on the production branch (PM shift on 2026-04-28). Note the `id` value — you will use it as `<SHIFT_LOG_ID>` in Step 4 and Step 5.

If there is no open shift, you must coordinate with frontdesk to either open one OR pick the shift_log that was active during Macabuhay's checkout (the AM shift on 2026-04-28). The cleanest choice: use the shift_log that is currently OPEN (so the totals roll into today's close).

---

### STEP 3 — Open transaction (draft mode)

```sql
START TRANSACTION;
```

**Expected result:** `Query OK`

**⚠ Critical:** keep this same SQL Query tab open through Step 7. New tab = new connection = transaction lost.

---

### STEP 4 — Insert the Damage Charges row (₱350)

Replace `<SHIFT_LOG_ID>` with the value you got from Step 2.

```sql
INSERT INTO transactions
  (branch_id, shift_log_id, checkin_detail_id, room_id, guest_id, floor_id,
   transaction_type_id, assigned_frontdesk_id, description,
   payable_amount, paid_amount, change_amount, deposit_amount,
   paid_at, remarks, is_co, shift, is_override,
   created_at, updated_at)
VALUES
  (1, <SHIFT_LOG_ID>, 12333, 5, 14623, 1,
   4, '"[2,\\"N\\\\/A\\\\"]"', 'Damage Charges',
   350, 0, 0, 0,
   NOW(), 'Overtime past 03:57 not posted (2026-04-28 incident, bug-related). Backfilled via SQL — see docs/data-repairs/2026-04-28-room-5-macabuhay-RECOVERY-SCRIPT.md', 0, 'PM', 0,
   NOW(), NOW());
```

**Expected result:** `1 row affected`

**Notes:**
- `transaction_type_id = 4` = Damage Charges (verified against `transaction_types` table)
- `payable_amount = 350` records the charge owed
- `paid_amount = 0` and `deposit_amount = 0` here because the Cashout row in Step 5 represents the deposit-forfeit payment
- `floor_id = 1` is Room #5's floor (verify against rooms table if unsure)
- `assigned_frontdesk_id` is the Laravel JSON-encoded format used by other rows: replace `[2,"N/A"]` with the actual operator's user_id if you want a clean audit attribution
- The `remarks` text references this exact recovery script so future audits can trace the SQL backfill

---

### STEP 5 — Insert the Cashout / Deduct Deposit row (₱200)

```sql
INSERT INTO transactions
  (branch_id, shift_log_id, checkin_detail_id, room_id, guest_id, floor_id,
   transaction_type_id, assigned_frontdesk_id, description,
   payable_amount, paid_amount, change_amount, deposit_amount,
   paid_at, remarks, is_co, shift, is_override,
   created_at, updated_at)
VALUES
  (1, <SHIFT_LOG_ID>, 12333, 5, 14623, 1,
   5, '"[2,\\"N\\\\/A\\\\"]"', 'Cashout',
   200, 0, 0, 200,
   NOW(), 'Apply remaining deposit toward overtime damage charge. ₱150 written off as bug-attributable loss. See docs/data-repairs/2026-04-28-room-5-macabuhay-RECOVERY-SCRIPT.md', 0, 'PM', 0,
   NOW(), NOW());
```

**Expected result:** `1 row affected`

**Notes:**
- `transaction_type_id = 5` = Cashout (used for "Deduct Deposit" / deposit forfeit in this app)
- `deposit_amount = 200` is the deposit being applied
- This pairs with the Damage Charge from Step 4 to net out: ₱350 charged − ₱200 deposit applied = ₱150 hotel write-off

---

### STEP 6 — Update Macabuhay's total_deduction (700 → 900)

```sql
UPDATE checkin_details
SET total_deduction = 900,
    updated_at = NOW()
WHERE id = 12333 AND total_deduction = 700;
```

**Expected result:** `1 row affected`

**Decision:**
- ✅ `1 row affected` → continue to Step 7
- ❌ `0 rows affected` → `total_deduction` was not 700 (someone posted in parallel?). Run `ROLLBACK;` and reassess.

---

### STEP 7 — Insert the activity_logs entries (audit trail)

Replace `<USER_ID>` with the operator's user id (e.g. 2 for admin, or whoever is running this). The followup uses the convention of branch_id=1.

```sql
INSERT INTO activity_logs (branch_id, user_id, activity, description, created_at, updated_at)
VALUES
  (1, <USER_ID>, 'Add Damage Charges',
   'Added new damage charges of ₱350 for guest Macabuhay daisyjun (overtime not posted at 05:46 checkout — bug-attributable, backfilled via SQL)',
   NOW(), NOW()),
  (1, <USER_ID>, 'Deducted Deposit',
   'Deducted deposit of ₱200 for guest Macabuhay daisyjun (applied to overtime damage charge — ₱150 written off)',
   NOW(), NOW());
```

**Expected result:** `2 rows affected`

---

### STEP 8 — Verify before COMMIT (read-only, inside transaction)

```sql
-- Macabuhay's record state
SELECT cd.id AS cid,
       cd.total_deposit,
       cd.total_deduction,
       (cd.total_deposit - cd.total_deduction) AS remaining_deposit,
       cd.updated_at
FROM checkin_details cd
WHERE cd.id = 12333;

-- The two new transactions
SELECT id, transaction_type_id, description, payable_amount, deposit_amount, remarks, created_at
FROM transactions
WHERE checkin_detail_id = 12333
  AND created_at >= CURDATE()
ORDER BY id DESC
LIMIT 5;

-- The two new activity logs
SELECT id, activity, description, created_at
FROM activity_logs
WHERE description LIKE '%Macabuhay daisyjun%'
  AND created_at >= CURDATE()
ORDER BY id DESC
LIMIT 5;
```

**Expected result:**

| Check | Expected |
|---|---|
| `total_deduction` | `900` ✅ |
| `remaining_deposit` | `0` ✅ |
| New transactions count (today) | 2 (one Damage Charges, one Cashout) |
| New activity_logs count (today) | 2 (Add Damage Charges, Deducted Deposit) |

**Decision:**
- ✅ All four checks match → run Step 9 (COMMIT)
- ❌ Any mismatch → run `ROLLBACK;` and investigate

---

### STEP 9 — COMMIT (point of no return)

```sql
COMMIT;
```

**Expected result:** `Query OK`

**🎉 Recovery complete:**
- Macabuhay's record now reflects the missing ₱350 charge and ₱200 deposit forfeit
- Audit trail visible in `transactions` and `activity_logs`
- `total_deduction` matches business reality (900 = full deposit applied)

**If Step 8 verification failed, run instead:**

```sql
ROLLBACK;
```

This discards all 5 writes (2 transactions + 1 update + 2 activity_logs). Database returns to bug-state. Investigate before retrying.

---

## 🛠 STEP 10 — Reconcile shift_logs / cash_on_drawers (manual check)

This is the side-effect that the admin UI handles automatically but raw SQL **does not**:

1. Check `shift_logs` running totals for the active shift — do they need bumping by ₱350 (damage) or ₱200 (deposit out)? Hotel-specific accounting decides.
2. Check `cash_on_drawers` — typically NOT updated for non-cash transactions (deposit forfeit doesn't move cash), so usually no change needed for this case.
3. If your monthly accounting separately tracks "Damage Charges revenue" and "Deposit Forfeitures," confirm both saw an increment.

If unsure, ask the back-office user to spot-check daily totals on the next shift close.

---

## 🛡 Why this script is safe

| Safety mechanism | How it works |
|---|---|
| Transaction wrapper | All 5 writes commit/rollback together |
| Idempotent guard on UPDATE | `AND total_deduction = 700` makes the UPDATE a no-op on re-run |
| Pre-flight check | Step 1 confirms starting state before any write |
| Pre-COMMIT verification | Step 8 verifies all writes landed correctly |
| Targeted writes | Single record updates by primary key (cid=12333) |
| Audit trail in remarks | Both transactions reference this script for traceability |
| Reversible | ROLLBACK before COMMIT; explicit reverse SQL after COMMIT (below) |

---

## 🔄 Rollback procedure (if anything goes wrong AFTER commit)

If after Step 9 you discover something is wrong:

```sql
START TRANSACTION;

-- Remove the two new transactions (use the IDs from Step 8 verification)
DELETE FROM transactions
WHERE checkin_detail_id = 12333
  AND created_at >= CURDATE()
  AND transaction_type_id IN (4, 5)
  AND remarks LIKE '%2026-04-28-room-5-macabuhay-RECOVERY-SCRIPT.md%';

-- Restore total_deduction
UPDATE checkin_details
SET total_deduction = 700,
    updated_at = NOW()
WHERE id = 12333 AND total_deduction = 900;

-- Remove the two activity logs
DELETE FROM activity_logs
WHERE description LIKE '%Macabuhay daisyjun%'
  AND created_at >= CURDATE()
  AND activity IN ('Add Damage Charges', 'Deducted Deposit');

-- Verify
SELECT total_deduction FROM checkin_details WHERE id = 12333;  -- expect 700
SELECT COUNT(*) FROM transactions
  WHERE checkin_detail_id = 12333 AND created_at >= CURDATE() AND transaction_type_id IN (4,5);  -- expect 0

COMMIT;
```

---

## 🟢 Simpler alternative — single-INSERT path (just forfeit ₱200, no damage row)

If the operator only wants to **forfeit her remaining ₱200 deposit** without recording the formal ₱350 damage charge, the minimum viable version is:

```sql
START TRANSACTION;

INSERT INTO transactions
  (branch_id, shift_log_id, checkin_detail_id, room_id, guest_id, floor_id,
   transaction_type_id, assigned_frontdesk_id, description,
   payable_amount, paid_amount, change_amount, deposit_amount,
   paid_at, remarks, is_co, shift, is_override,
   created_at, updated_at)
VALUES
  (1, <SHIFT_LOG_ID>, 12333, 5, 14623, 1,
   5, '"[2,\\"N\\\\/A\\\\"]"', 'Cashout',
   200, 0, 0, 200,
   NOW(), 'Overstay deposit forfeit — overtime ~2h past 03:57 not posted at 05:46 checkout (2026-04-28 incident bug-related). Single-insert simplified path. See docs/data-repairs/2026-04-28-room-5-macabuhay-RECOVERY-SCRIPT.md', 0, 'PM', 0,
   NOW(), NOW());

UPDATE checkin_details
SET total_deduction = 900,
    updated_at = NOW()
WHERE id = 12333 AND total_deduction = 700;

INSERT INTO activity_logs (branch_id, user_id, activity, description, created_at, updated_at)
VALUES
  (1, <USER_ID>, 'Deducted Deposit',
   'Deducted deposit of ₱200 for guest Macabuhay daisyjun (overstay forfeit — backfill of missed posting at 05:46 checkout)',
   NOW(), NOW());

-- Verify
SELECT total_deduction FROM checkin_details WHERE id = 12333;  -- expect 900

COMMIT;
```

**Trade-off:** simpler (3 writes vs 5), but the audit trail is less explicit — a future auditor sees only "deposit deducted" rather than "₱350 damage charged then ₱200 deposit applied." Choose based on your accounting preference.

---

## 🧾 Damage assessment (for reference)

| Item | Amount | Status |
|---|---:|---|
| Cash she paid (room + 3 deposits) | ₱1,250 | ✓ Already collected |
| Net deposit applied to extensions | ₱700 | ✓ Already deducted |
| Remaining deposit before this fix | ₱200 | Held (waiting to forfeit) |
| Overtime owed | ₱350 | The missing posting |
| **After this script:** deposit applied | **₱200** | Recovered |
| **After this script:** write-off | **₱150** | Bug-attributable loss |

---

## ✅ Operator checklist

- [ ] Step 1 — Pre-flight SELECT — confirmed `total_deduction=700` and `remaining_deposit=200`
- [ ] Step 2 — Captured current `shift_log_id`
- [ ] Step 3 — START TRANSACTION
- [ ] Step 4 — INSERT Damage Charges (₱350) — 1 row affected
- [ ] Step 5 — INSERT Cashout (₱200) — 1 row affected
- [ ] Step 6 — UPDATE total_deduction (700→900) — 1 row affected
- [ ] Step 7 — INSERT 2 activity_logs — 2 rows affected
- [ ] Step 8 — Verify SELECTs — all 4 checks pass
- [ ] Step 9 — COMMIT
- [ ] Step 10 — Manual shift_logs / cash_on_drawers spot-check
- [ ] (Optional) Note ₱150 write-off in monthly accounting log

---

## 📝 Sign-off

| Role | Status | Notes |
|---|---|---|
| Database operator | _(name + timestamp)_ | Ran Steps 1-9 |
| Back-office reviewer | _(name)_ | Confirmed shift_logs / monthly P&L line up |
| ₱150 logged in monthly bug-loss tracker | _(date)_ | |

---

## 🔗 Related documents

- Followup (admin UI path, recommended): `docs/data-repairs/2026-04-28-room-5-macabuhay-overtime-followup.md`
- Parent incident: `docs/incidents/2026-04-28-fix-all-unresolved-incident-report.md`
- Final trace: `docs/incidents/2026-04-28-final-trace-and-solution.md`
- Room #71 recovery script (sibling): `docs/data-repairs/2026-04-28-room-71-elmettose-RECOVERY-SCRIPT.md`

---

*End of recovery script. Prefer the admin UI path unless you have a specific reason to use this SQL.*
