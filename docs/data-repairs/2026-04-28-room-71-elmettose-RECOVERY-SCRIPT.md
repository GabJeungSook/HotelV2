# Room #71 / Elmettose Rivera — Complete Recovery Script

**Date:** 2026-04-28
**Target:** Production database (`homi_app`)
**Affected guest:** Elmettose Rivera (cid=12086, guest_id=14336)
**Affected room:** Room #71 (room_id=54)
**Estimated time:** 5-10 minutes (SQL + frontdesk billing)
**Reference docs:**
- `docs/incidents/2026-04-28-fix-all-unresolved-incident-report.md`
- `docs/data-repairs/2026-04-28-room-71-elmettose-followup.md`
- `docs/incidents/2026-04-28-final-trace-and-solution.md`

---

## 🎯 Goal

Reactivate Elmettose Rivera in the system so frontdesk can see her, bill her for overstay, and stop the kiosk auto-assigning Room #71 to walk-ins.

---

## ❓ Problem summary

| Reality | What system shows |
|---|---|
| Elmettose physically in Room #71 since Apr 25 | `is_check_out = 1` (system: she's gone) |
| Room #71 has a real guest | `status = Cleaned` (system: empty) |
| She owes ~₱800 for overstay (~24 hours unpaid) | Frontdesk cannot bill (record hidden) |
| Kiosk keeps auto-assigning #71 to walk-ins | 16 rescue transfers today |

---

## 🔧 The fix

Two field changes inside one transaction:

| Table | Record | Field | Now (wrong) | After fix |
|---|---|---|---|---|
| `checkin_details` | id=12086 | `is_check_out` | 1 | **0** |
| `rooms` | id=54 | `status` | `Cleaned` | **`Occupied`** |

Plus `updated_at = NOW()` on both records.

---

## 🚦 Pre-conditions (must be true before running)

- [x] Frontdesk has physically verified Elmettose is in Room #71 (confirmed name "Elmettose Revira" matches DB "Elmettose rivera")
- [x] No other active check-in for room_id=54 (verified: 0 rows)
- [x] Connected to PRODUCTION database (`homi_app`) via TablePlus
- [x] Working in a single SQL Query tab that you keep open through the entire procedure

---

## 📋 The complete recovery script

Run statements **one at a time** in order. Click on each statement, then click **Run Current** in TablePlus. Verify the expected result before moving to the next statement.

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
       r.status AS room_status,
       cd.updated_at
FROM checkin_details cd
JOIN rooms r ON r.id = cd.room_id
JOIN guests g ON g.id = cd.guest_id
WHERE cd.id = 12086;
```

**Expected result — 1 row:**

| Field | Expected value |
|---|---|
| cid | `12086` |
| guest_id | `14336` |
| guest | `Elmettose rivera` |
| room_id | `54` |
| room | `71` |
| **is_check_out** | **`1`** ⚠ (still bug state) |
| check_out_at | `2026-04-27 19:07:42` ✓ |
| total_deposit | `200` |
| **room_status** | **`Cleaned`** ⚠ (still bug state) |
| updated_at | `2026-04-28 08:25:08` |

**Decision:** If `is_check_out=1` AND `room_status=Cleaned` → proceed to Step 2. Otherwise stop and reassess.

---

### STEP 2 — Scenario C check (read-only, safe)

```sql
SELECT cd.id AS cid,
       g.name AS guest,
       cd.check_in_at,
       cd.is_check_out
FROM checkin_details cd
JOIN guests g ON g.id = cd.guest_id
WHERE cd.room_id = 54
  AND cd.is_check_out = 0;
```

**Expected result:** **0 rows** (empty result, just column headers).

**Decision:** If 0 rows → safe to proceed. Otherwise stop — there's a conflicting active check-in to handle first.

---

### STEP 3 — Open transaction (draft mode)

```sql
START TRANSACTION;
```

**Expected result:** `Query OK` (no rows).

**⚠ Critical reminders:**
- Keep the same SQL Query tab open through Step 7
- Don't open a new tab — the transaction lives in this tab
- Until COMMIT, all changes are reversible

---

### STEP 4 — Reactivate Elmettose's check-in record

```sql
UPDATE checkin_details
SET is_check_out = 0,
    updated_at   = NOW()
WHERE id = 12086 AND is_check_out = 1;
```

**Expected result:** `1 row affected`

**Decision:**
- ✅ `1 row affected` → continue to Step 5
- ❌ `0 rows affected` → run `ROLLBACK;` and stop
- ❌ Any error → run `ROLLBACK;` and report

---

### STEP 5 — Restore Room #71 to Occupied

```sql
UPDATE rooms
SET status = 'Occupied',
    updated_at = NOW()
WHERE id = 54
  AND status IN ('Available', 'Cleaned');
```

**Expected result:** `1 row affected`

**Decision:**
- ✅ `1 row affected` → continue to Step 6
- ❌ `0 rows affected` → room status is unexpected (Maintenance? Occupied?) — run `ROLLBACK;` and investigate
- ❌ Any error → run `ROLLBACK;` and report

---

### STEP 6 — Verify before COMMIT (read-only, inside transaction)

```sql
SELECT cd.id AS cid,
       g.name AS guest,
       r.number AS room,
       cd.is_check_out,
       cd.check_out_at,
       cd.total_deposit,
       r.status AS room_status,
       cd.updated_at AS cid_updated,
       r.updated_at  AS room_updated
FROM checkin_details cd
JOIN rooms r ON r.id = cd.room_id
JOIN guests g ON g.id = cd.guest_id
WHERE cd.id = 12086;
```

**Expected result — 1 row:**

| Field | Expected value |
|---|---|
| cid | `12086` |
| guest | `Elmettose rivera` |
| room | `71` |
| **is_check_out** | **`0`** ✅ (fixed!) |
| check_out_at | `2026-04-27 19:07:42` |
| total_deposit | `200` |
| **room_status** | **`Occupied`** ✅ (fixed!) |
| cid_updated | today's recent timestamp |
| room_updated | today's recent timestamp |

**Decision (the critical gate):**
- ✅ Both `is_check_out=0` AND `room_status=Occupied` → run Step 7 (COMMIT)
- ❌ Either field doesn't match → run `ROLLBACK;` and report

---

### STEP 7 — COMMIT (point of no return)

```sql
COMMIT;
```

**Expected result:** `Query OK`

**🎉 At this moment:**
- Both UPDATEs become permanent in the database
- Frontdesk dashboards reflect the change after browser refresh
- Kiosk immediately stops auto-assigning Room #71

**If Step 6 verification failed, run instead:**

```sql
ROLLBACK;
```

This discards both UPDATEs. Database returns to bug state. Investigate why Step 6 failed before retrying.

---

### STEP 8 — Post-commit final verification (read-only, safe)

```sql
SELECT cd.id AS cid,
       g.name AS guest,
       r.number AS room,
       cd.is_check_out,
       cd.check_out_at,
       cd.total_deposit,
       r.status AS room_status
FROM checkin_details cd
JOIN rooms r ON r.id = cd.room_id
JOIN guests g ON g.id = cd.guest_id
WHERE cd.id = 12086;
```

**Expected result — 1 row, same as Step 6 expected values.**

This confirms the COMMIT landed. The recovery is complete in the database.

---

## 👥 STEP 9 — Frontdesk operational follow-up (NOT SQL)

After Step 8 confirms success, frontdesk takes over via the normal admin/frontdesk UI.

### 9.1 — Refresh Room Monitoring
1. Open Room Monitoring page in browser
2. Press F5 or close+reopen tab
3. Confirm Room #71 now shows as **Occupied** with **Elmettose rivera**

### 9.2 — Process her overdue billing

Three options. Pick one (Option B recommended):

| Option | Action | Money flow |
|---|---|---:|
| **A — She extends** | Click Elmettose → "Add Extension" → collect ₱400 cash for next 12h | +₱400 cash |
| **B — She checks out (recommended)** | Click "Check Out" → Deduct Deposit ₱200 → collect ~₱600 cash for missed extensions | +₱600 cash + ₱200 deposit applied |
| **C — Goodwill** | Click "Check Out" → Cashout ₱200 deposit | -₱200 (hotel absorbs loss) |

### 9.3 — Confirm walk-in cycle stopped
After Step 7 COMMIT, the kiosk should immediately stop auto-assigning Room #71. Watch the activity_logs for any new "Room Transfer from Room #71" entries — there should be none after the COMMIT timestamp.

---

## 🛡 Why this script is safe

| Safety mechanism | How it works |
|---|---|
| Idempotent guards | `AND is_check_out = 1` and `AND status IN ('Available','Cleaned')` make the UPDATEs no-ops if already-correct |
| Transaction wrapper | Both UPDATEs commit/rollback together |
| Single record per UPDATE | Targets primary keys (id=12086, id=54) — cannot affect other records |
| Pre-execution read-only checks | Steps 1-2 confirm starting state before any change |
| Pre-COMMIT verification | Step 6 verifies the in-draft state before making permanent |
| Source-of-truth values | Recovery values came from BEFORE backup (`homi_app_producoot_lastest_now.sql`) |
| Reversible | ROLLBACK at any point before COMMIT; explicit reverse SQL after COMMIT |

---

## 🔄 Rollback procedure (if anything goes wrong AFTER commit)

If after Step 7 you discover something is wrong, this reverts the changes:

```sql
START TRANSACTION;

UPDATE checkin_details
SET is_check_out = 1,
    updated_at = NOW()
WHERE id = 12086 AND is_check_out = 0;

UPDATE rooms
SET status = 'Cleaned',
    updated_at = NOW()
WHERE id = 54 AND status = 'Occupied';

SELECT 'rollback verify' AS check_label,
       (SELECT is_check_out FROM checkin_details WHERE id = 12086) AS isco,
       (SELECT status FROM rooms WHERE id = 54) AS room_status;
-- Expected: isco=1, room_status=Cleaned

COMMIT;
```

---

## 🧾 Damage assessment

| Item | Amount | Status |
|---|---:|---|
| Money received from her (pre-bug) | ₱1,800 | ✓ Already in DB |
| Deposit held | ₱200 | ✓ Available for forfeit/refund |
| Unpaid overstay (~24 hours) | ~₱800 | ⚠ Recoverable via Option B |
| Hotel time lost on rescue transfers | ~32 minutes (16 × 2 min) | ❌ Business cost |
| 16 walk-ins inconvenienced | 16 transfers | ❌ Goodwill cost |

**Net financial result with Option B:** Hotel collects full ₱2,400 for her stay (₱1,600 paid + ₱200 deposit applied + ₱600 cash). **No money lost.**

---

## 📊 Expected end state

### Database (after Step 7 COMMIT)
```
checkin_details (cid=12086):
   is_check_out:  0
   check_out_at:  2026-04-27 19:07:42
   total_deposit: 200
   updated_at:    [today now]

rooms (id=54):
   status:        Occupied
   updated_at:    [today now]
```

### Frontdesk Room Monitoring (after refresh)
```
Room #71 — Occupied
Guest: Elmettose rivera (Phone: 09488097151)
Stay: Apr 25 19:07 → Apr 27 19:07 (overdue)
Deposit: ₱200 held

[Add Extension] [Check Out] [Transfer Room]
```

### Kiosk
```
Next available room? Room #71 = Occupied → SKIP
Picking next available...
```

---

## ✅ Operator checklist

- [ ] Step 1 — Pre-flight SELECT — confirmed bug state (is_check_out=1, status=Cleaned)
- [ ] Step 2 — Scenario C SELECT — confirmed 0 rows
- [ ] Step 3 — START TRANSACTION — Query OK
- [ ] Step 4 — UPDATE checkin_details — 1 row affected
- [ ] Step 5 — UPDATE rooms — 1 row affected
- [ ] Step 6 — Verify SELECT — is_check_out=0 AND room_status=Occupied
- [ ] Step 7 — COMMIT — Query OK
- [ ] Step 8 — Post-commit SELECT — values still correct
- [ ] Step 9.1 — Frontdesk refreshed Room Monitoring
- [ ] Step 9.2 — Frontdesk processed Elmettose's billing (Option B recommended)
- [ ] Step 9.3 — Verified walk-in cycle stopped (no new "from Room #71" transfers)

---

## 📝 Sign-off

| Role | Status | Notes |
|---|---|---|
| Database operator | _(name + timestamp)_ | Ran Steps 1-8 |
| Frontdesk on shift | _(name)_ | Processed Step 9 |
| Reviewer | _(name)_ | Verified incident closed |

---

*End of recovery script.*
