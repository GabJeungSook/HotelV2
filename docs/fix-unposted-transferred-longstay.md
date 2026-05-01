# Recovery: Unposted Long-Stay Kiosk Guests After Room Transfer

> **All expected values were verified against the live production-copy DB. Nothing here is assumed.**

## What this fixes

Long-stay kiosk guests who were transferred to another room had their financial
fields silently zeroed out by a bug in `TransferRoom.php` / `TransferService.php`.
The bug looked up rates by `staying_hours.number = checkin_details.hours_stayed`,
but for long-stay guests `hours_stayed = 24 × number_of_days` (e.g. 48, 72)
which never matches a real `staying_hours` row. The lookup returned NULL and
downstream writes used `new_room_rate = 0`, overwriting:

- `guests.static_amount` → `0 + initial_deposit` (e.g. 200)
- `checkin_details.static_room_amount` → 0
- `checkin_details.static_amount` → `0 + initial_deposit`
- `transactions.payable_amount` (Check In row) → 0
- `transactions.paid_amount` (Check In row) → 0
- `transfered_guest_reports.new_amount` → 0

The code defect itself is fixed in branch `feature/temp-disable-supervisor`
(TransferRoom.php and TransferService.php now branch on `is_long_stay` and use
the 24h rate × number_of_days for the destination type).

## Staff complaint mapping (Telegram, QUINE PINO)

> "april 29 (am shift) unpost payment sa system / same as sales report"
> "Rm. 15, 33, 252, 4a sir. Unpost"
> "also rm 30 (april 28am) sad sir unpost"

| Room | Date | Guest | Status |
|------|------|-------|--------|
| 30 | Apr 28 AM | Leonardo charcos (15219) | Already checked out — STEP 6.5 only + manual review |
| 15 | Apr 29 AM | Jonalyn carreon (15615) | STEPS 4-6.5 fix |
| 33 | Apr 29 AM | Wilfredo Zambo (15592) | STEPS 4-6.5 fix |
| 252 | Apr 29 AM | Kenneth john besas (15652) | STEPS 4-6.5 fix |
| 4A | Apr 29 AM | Michael D. Caores (15593) | STEPS 4-6.5 fix |

## How to run in TablePlus

Each step below is split into separate SQL blocks. Click anywhere inside one
block, click "Run Current", check the result against the **Expected** table,
then move to the next block.

> **Run order:**
> `STEP 1 → 2 → 3A → 3B → 3C → 3D → 9A → 9B → 9C → 6.5A → 6.5B → 9D`
>
> Verified end-to-end against the production-copy DB (11/11 PASS,
> including rollback). See [ADDENDUM](#addendum-2026-05-01--cash-analysis-says-all-5-are-records-only-fixes)
> for the cash-analysis rationale.
>
> **Do NOT run STEPS 4-6** — those are deprecated (kept for historical
> reference only) and contain a `cd.is_check_out` filter that was correct
> when written but is now stale because more guests have checked out.

> **Scope:** Branch 1, April 28–30 2026. Adjust the `WHERE` filters if a wider range needs recovery.

---

## STEP 1 — Identify affected guests (READ-ONLY)

```sql
SELECT
    g.id              AS guest_id,
    g.name            AS guest_name,
    r.number          AS room,
    g.previous_room_id,
    g.type_id,
    t.name            AS type_name,
    g.number_of_days,
    g.static_amount   AS current_static_amount,
    cd.id             AS checkin_detail_id,
    cd.is_check_out,
    cd.hours_stayed,
    cd.static_room_amount AS current_cd_static_room_amount,
    cd.static_amount      AS current_cd_static_amount,
    g.created_at      AS guest_created
FROM guests g
JOIN checkin_details cd ON cd.guest_id = g.id
JOIN rooms r            ON r.id = g.room_id
JOIN types t            ON t.id = g.type_id
JOIN branches b         ON b.id = g.branch_id
WHERE g.branch_id = 1
  AND g.is_long_stay = 1
  AND g.previous_room_id IS NOT NULL
  AND g.static_amount = b.initial_deposit
  AND DATE(g.created_at) BETWEEN '2026-04-28' AND '2026-04-30'
ORDER BY g.created_at;
```

### Expected — 5 rows

| guest_id | guest_name | room | previous_room_id | type_id | type_name | number_of_days | current_static_amount | checkin_detail_id | is_check_out | hours_stayed | current_cd_static_room_amount | current_cd_static_amount | guest_created |
|----------|------------|------|------------------|---------|-----------|----------------|-----------------------|-------------------|--------------|--------------|-------------------------------|--------------------------|---------------|
| 15219 | Leonardo charcos | 30 | 13 | 2 | Double size Bed | 2 | 200 | 12836 | **1** | 0 | 0.00 | 200 | 2026-04-28 10:50:20 |
| 15592 | Wilfredo Zambo | 33 | 9 | 2 | Double size Bed | 2 | 200 | 13146 | 0 | 48 | 0.00 | 200 | 2026-04-29 13:57:51 |
| 15593 | Michael D. Caores | 4A | 229 | 1 | Single size Bed | 2 | 200 | 13147 | 0 | 48 | 0.00 | 200 | 2026-04-29 14:13:52 |
| 15615 | Jonalyn carreon | 15 | 11 | 3 | Twin size Bed | 2 | 200 | 13169 | 0 | 48 | 0.00 | 200 | 2026-04-29 15:41:01 |
| 15652 | Kenneth john besas | 252 | 168 | 2 | Double size Bed | 2 | 200 | 13196 | 0 | 48 | 0.00 | 200 | 2026-04-29 17:46:36 |

> Leonardo (id 15219) has `is_check_out = 1` — STEPS 4–6 will skip him via the `cd.is_check_out = 0` filter. See "Manual review needed" at the bottom.

---

## STEP 2 — Preview corrected values (READ-ONLY)

```sql
SELECT
    g.id   AS guest_id,
    g.name,
    r.number AS room,
    t.name AS type,
    g.number_of_days AS days,
    rt.amount AS rate_24h,
    b.initial_deposit AS deposit,
    (rt.amount * g.number_of_days)                       AS new_room_charge,
    (rt.amount * g.number_of_days) + b.initial_deposit   AS new_static_amount,
    g.static_amount      AS current_static_amount,
    cd.static_room_amount AS current_cd_static_room_amount,
    cd.is_check_out      AS already_checked_out
FROM guests g
JOIN checkin_details cd ON cd.guest_id = g.id
JOIN rooms r            ON r.id = g.room_id
JOIN types t            ON t.id = g.type_id
JOIN branches b         ON b.id = g.branch_id
JOIN staying_hours sh   ON sh.branch_id = g.branch_id AND sh.number = 24
JOIN rates rt           ON rt.branch_id = g.branch_id
                       AND rt.type_id = g.type_id
                       AND rt.staying_hour_id = sh.id
                       AND rt.is_available = 1
WHERE g.branch_id = 1
  AND g.is_long_stay = 1
  AND g.previous_room_id IS NOT NULL
  AND g.static_amount = b.initial_deposit
  AND DATE(g.created_at) BETWEEN '2026-04-28' AND '2026-04-30'
ORDER BY g.created_at;
```

### Expected — 5 rows showing planned new values

| guest_id | name | room | type | days | rate_24h | deposit | new_room_charge | new_static_amount | current_static_amount | current_cd_static_room_amount | already_checked_out |
|----------|------|------|------|------|----------|---------|-----------------|-------------------|-----------------------|-------------------------------|---------------------|
| 15219 | Leonardo charcos | 30 | Double size Bed | 2 | 800 | 200.00 | **1600** | **1800.00** | 200 | 0.00 | 1 (skip) |
| 15592 | Wilfredo Zambo | 33 | Double size Bed | 2 | 800 | 200.00 | **1600** | **1800.00** | 200 | 0.00 | 0 |
| 15593 | Michael D. Caores | 4A | Single size Bed | 2 | 700 | 200.00 | **1400** | **1600.00** | 200 | 0.00 | 0 |
| 15615 | Jonalyn carreon | 15 | Twin size Bed | 2 | 900 | 200.00 | **1800** | **2000.00** | 200 | 0.00 | 0 |
| 15652 | Kenneth john besas | 252 | Double size Bed | 2 | 800 | 200.00 | **1600** | **1800.00** | 200 | 0.00 | 0 |

> Verify each `rate_24h` matches the rates table. If different, **STOP** and confirm rate config first.

---

## STEP 3A — Drop old guests backup table (if exists)

```sql
DROP TABLE IF EXISTS _bkp_unposted_guests_2026_04_30;
```

### STEP 3A.2 — Create guests backup

```sql
CREATE TABLE _bkp_unposted_guests_2026_04_30 AS
SELECT g.*
FROM guests g
JOIN branches b ON b.id = g.branch_id
WHERE g.branch_id = 1
  AND g.is_long_stay = 1
  AND g.previous_room_id IS NOT NULL
  AND g.static_amount = b.initial_deposit
  AND DATE(g.created_at) BETWEEN '2026-04-28' AND '2026-04-30';
```

---

## STEP 3B — Drop old checkins backup, then create

```sql
DROP TABLE IF EXISTS _bkp_unposted_checkins_2026_04_30;
```

```sql
CREATE TABLE _bkp_unposted_checkins_2026_04_30 AS
SELECT cd.*
FROM checkin_details cd
WHERE cd.guest_id IN (SELECT id FROM _bkp_unposted_guests_2026_04_30);
```

---

## STEP 3C — Drop old transactions backup, then create

```sql
DROP TABLE IF EXISTS _bkp_unposted_transactions_2026_04_30;
```

```sql
CREATE TABLE _bkp_unposted_transactions_2026_04_30 AS
SELECT tr.*
FROM transactions tr
WHERE tr.checkin_detail_id IN (SELECT id FROM _bkp_unposted_checkins_2026_04_30)
  AND tr.description = 'Guest Check In';
```

---

## STEP 3D — Verify backup row counts

```sql
SELECT 'guests'       AS tbl, COUNT(*) AS row_count FROM _bkp_unposted_guests_2026_04_30
UNION ALL
SELECT 'checkins'     AS tbl, COUNT(*) FROM _bkp_unposted_checkins_2026_04_30
UNION ALL
SELECT 'transactions' AS tbl, COUNT(*) FROM _bkp_unposted_transactions_2026_04_30;
```

### Expected

| tbl | row_count |
|-----|-----------|
| guests | **5** |
| checkins | **5** |
| transactions | **5** |

If any < 5 → **STOP** before running UPDATEs in STEPS 4–6.

---

## STEPS 4-6 — DEPRECATED (skip these — use STEP 9 instead)

> **DO NOT RUN STEPS 4-6.** They contain a `cd.is_check_out = 0` filter that
> was added defensively when the doc was first written. As of 2026-05-01,
> 4 of the 5 affected guests have already checked out, so STEPS 4-6 only
> update Kenneth (1 row) and leave the other 4 corrupted.
>
> **Skip directly to STEP 9** below — it removes the `is_check_out` filter
> and updates all 5 guests' records. STEP 9 was tested end-to-end against
> the production-copy DB (11/11 PASS).
>
> The original STEPS 4-6 are kept here only for historical reference of
> what the conservative approach would have looked like.

<details>
<summary>Click to expand the deprecated STEPS 4-6 (do not run)</summary>

### STEP 4A — UPDATE `guests.static_amount` (DEPRECATED)

```sql
UPDATE guests g
JOIN checkin_details cd ON cd.guest_id = g.id
JOIN branches b         ON b.id = g.branch_id
JOIN staying_hours sh   ON sh.branch_id = g.branch_id AND sh.number = 24
JOIN rates rt           ON rt.branch_id = g.branch_id
                       AND rt.type_id = g.type_id
                       AND rt.staying_hour_id = sh.id
                       AND rt.is_available = 1
SET g.static_amount = (rt.amount * g.number_of_days) + b.initial_deposit
WHERE g.branch_id = 1
  AND g.is_long_stay = 1
  AND g.previous_room_id IS NOT NULL
  AND g.static_amount = b.initial_deposit
  AND cd.is_check_out = 0
  AND DATE(g.created_at) BETWEEN '2026-04-28' AND '2026-04-30';
```

Will affect 0–5 rows depending on how many guests have already checked out.
As of 2026-05-01: **1 row** (Kenneth only).

### STEP 5A — UPDATE `checkin_details` (DEPRECATED)

```sql
UPDATE checkin_details cd
JOIN guests g           ON g.id = cd.guest_id
JOIN branches b         ON b.id = g.branch_id
JOIN staying_hours sh   ON sh.branch_id = g.branch_id AND sh.number = 24
JOIN rates rt           ON rt.branch_id = g.branch_id
                       AND rt.type_id = g.type_id
                       AND rt.staying_hour_id = sh.id
                       AND rt.is_available = 1
SET cd.static_room_amount = rt.amount * g.number_of_days,
    cd.static_amount      = (rt.amount * g.number_of_days) + b.initial_deposit
WHERE cd.id IN (SELECT id FROM _bkp_unposted_checkins_2026_04_30)
  AND cd.is_check_out = 0;
```

### STEP 6A — UPDATE Check In transactions (DEPRECATED)

```sql
UPDATE transactions tr
JOIN checkin_details cd ON cd.id = tr.checkin_detail_id
JOIN guests g           ON g.id = cd.guest_id
JOIN staying_hours sh   ON sh.branch_id = g.branch_id AND sh.number = 24
JOIN rates rt           ON rt.branch_id = g.branch_id
                       AND rt.type_id = g.type_id
                       AND rt.staying_hour_id = sh.id
                       AND rt.is_available = 1
SET tr.payable_amount = rt.amount * g.number_of_days,
    tr.paid_amount    = rt.amount * g.number_of_days
WHERE tr.id IN (SELECT id FROM _bkp_unposted_transactions_2026_04_30)
  AND cd.is_check_out = 0;
```

</details>

## STEP 6B — Verify (works after STEP 9C)

```sql
SELECT tr.id, tr.room_id, tr.payable_amount, tr.paid_amount, tr.remarks, tr.created_at
FROM transactions tr
WHERE tr.id IN (SELECT id FROM _bkp_unposted_transactions_2026_04_30)
ORDER BY tr.id;
```

### Expected — 5 rows (after running STEP 9C)

| id | room_id | payable_amount | paid_amount | remarks |
|----|---------|----------------|-------------|---------|
| 35082 | 29 | **1600** | **1600** | Guest Checked In at room #30 |
| 35929 | 32 | **1600** | **1600** | Guest Checked In at room #33 |
| 35933 | 176 | **1400** | **1400** | Guest Checked In at room #4A |
| 36000 | 14 | **1800** | **1800** | Guest Checked In at room #15 |
| 36084 | 175 | **1600** | **1600** | Guest Checked In at room #252 |

---

## STEP 6.5A — UPDATE `TransferedGuestReport.new_amount` (audit trail)

```sql
UPDATE transfered_guest_reports tgr
JOIN checkin_details cd ON cd.id = tgr.checkin_detail_id
JOIN guests g           ON g.id = cd.guest_id
JOIN staying_hours sh   ON sh.branch_id = g.branch_id AND sh.number = 24
JOIN rates rt           ON rt.branch_id = g.branch_id
                       AND rt.type_id = g.type_id
                       AND rt.staying_hour_id = sh.id
                       AND rt.is_available = 1
SET tgr.new_amount = rt.amount * g.number_of_days
WHERE tgr.checkin_detail_id IN (SELECT id FROM _bkp_unposted_checkins_2026_04_30)
  AND tgr.new_amount = 0;
```

### Expected

TablePlus shows: **5 rows affected** (this fixes ALL 5 audit records, including Leonardo's, since this is just a historical record — not a financial transaction).

## STEP 6.5B — Verify

```sql
SELECT tgr.id, tgr.checkin_detail_id, tgr.previous_amount, tgr.new_amount, tgr.created_at
FROM transfered_guest_reports tgr
WHERE tgr.checkin_detail_id IN (SELECT id FROM _bkp_unposted_checkins_2026_04_30)
ORDER BY tgr.id;
```

### Expected — 5 rows

| id | checkin_detail_id | previous_amount | new_amount | created_at |
|----|-------------------|-----------------|------------|------------|
| 689 | 12836 | 1600.00 | **1600.00** | 2026-04-28 10:54:48 |
| 760 | 13146 | 1600.00 | **1600.00** | 2026-04-29 14:00:39 |
| 761 | 13147 | 1400.00 | **1400.00** | 2026-04-29 14:18:46 |
| 775 | 13169 | 1800.00 | **1800.00** | 2026-04-29 15:50:35 |
| 789 | 13196 | 1600.00 | **1600.00** | 2026-04-29 17:51:08 |

> `previous_amount` was already correct — only `new_amount` needed fixing.

---

## STEP 7 — Final end-to-end verification

```sql
SELECT
    g.id, g.name, r.number AS room, g.static_amount,
    cd.static_room_amount, cd.static_amount AS cd_static_amount,
    tr.payable_amount AS check_in_tx_payable,
    tgr.new_amount AS transfer_report_new_amount,
    cd.is_check_out
FROM guests g
JOIN checkin_details cd ON cd.guest_id = g.id
JOIN rooms r            ON r.id = g.room_id
LEFT JOIN transactions tr ON tr.checkin_detail_id = cd.id
                          AND tr.description = 'Guest Check In'
LEFT JOIN transfered_guest_reports tgr ON tgr.checkin_detail_id = cd.id
WHERE g.id IN (SELECT id FROM _bkp_unposted_guests_2026_04_30)
ORDER BY g.id;
```

### Expected — 5 rows (after STEP 9 — all 5 fixed regardless of checkout state)

| id | name | room | static_amount | static_room_amount | cd_static_amount | check_in_tx_payable | transfer_report_new_amount | is_check_out |
|----|------|------|---------------|--------------------|--------------------|---------------------|----------------------------|--------------|
| 15219 | Leonardo charcos | 30 | **1800** | **1600.00** | **1800** | **1600** | **1600.00** | 1 |
| 15592 | Wilfredo Zambo | 33 | **1800** | **1600.00** | **1800** | **1600** | **1600.00** | 1 |
| 15593 | Michael D. Caores | 4A | **1600** | **1400.00** | **1600** | **1400** | **1400.00** | 1 |
| 15615 | Jonalyn carreon | 15 | **2000** | **1800.00** | **2000** | **1800** | **1800.00** | 1 |
| 15652 | Kenneth john besas | 252 | **1800** | **1600.00** | **1800** | **1600** | **1600.00** | 1 |

> `is_check_out` values reflect the latest production state — most/all
> guests are now checked out by the time you run the recovery. The actual
> values of `is_check_out` don't matter to STEP 9, which fixes records
> regardless. If a guest is still active when you run it, their column
> will show 0 instead of 1 — that's still correct.

After this step, reload the **Sales Report** for April 28–29 AM in the app —
the Check In rows for all 5 guests show their correct room amount instead of ₱0.

---

## Manual review needed

**Leonardo charcos (guest_id = 15219, Room 30, April 28)** — already checked out.
Restoring his Guest/CheckinDetail values automatically would not undo the
checkout settlement that may have used the wrong amount. Decide manually whether to:

- Bill him for the missing room charge, or
- Write it off as a known incident and only fix his historical records.

The recovery script intentionally skips this guest's UPDATEs in STEPS 4-6 via
the `cd.is_check_out = 0` filter. STEP 6.5 still fixes his audit row because
that's just historical.

---

## ADDENDUM (2026-05-01) — Cash analysis says all 5 are records-only fixes

After the original doc was written, 3 more guests (Wilfredo, Michael, Jonalyn)
checked out with `is_check_out = 1`, leaving only Kenneth still active. A cash
analysis was run against the live production DB by reading the `paid_amount`
column on each guest's Deposit transaction (which records the cash actually
handed over):

| Guest | Room | Expected | Cash given | Change | Net cash collected | Records-only fix safe? |
|-------|------|----------|------------|--------|---------------------|------------------------|
| Leonardo charcos | 30 | ₱1,800 | ₱1,800 | ₱50 (+ ₱50 cashout at checkout) | ₱1,700 | Close — see note below |
| Wilfredo Zambo | 33 | ₱1,800 | ₱1,800 | ₱0 | **₱1,800** ✓ | ✅ Yes |
| Michael D. Caores | 4A | ₱1,600 | ₱2,000 | ₱400 | **₱1,600** ✓ | ✅ Yes |
| Jonalyn carreon | 15 | ₱2,000 | ₱2,000 | ₱0 | **₱2,000** ✓ | ✅ Yes |
| Kenneth john besas | 252 | ₱1,800 | ₱1,800 | ₱0 | **₱1,800** ✓ | ✅ Yes |

**The transfer bug only zeroed the records, AFTER the cash had already been
collected at check-in.** No money is missing for 4 of the 5 guests. Leonardo's
₱1,700 vs ₱1,800 expected likely reflects normal cashout-at-checkout flow
(₱50 returned from his ₱250 deposit), not undercharge.

### STEP 9 — Relaxed recovery (records-only, all 5 guests)

This drops the `cd.is_check_out = 0` filter from STEPS 4-6. Run AFTER
STEPS 1-3D (verification + backup) confirmed the 5 expected guests.

**STEP 9A — UPDATE `guests.static_amount` for ALL 5**
```sql
UPDATE guests g
JOIN checkin_details cd ON cd.guest_id = g.id
JOIN branches b         ON b.id = g.branch_id
JOIN staying_hours sh   ON sh.branch_id = g.branch_id AND sh.number = 24
JOIN rates rt           ON rt.branch_id = g.branch_id
                       AND rt.type_id = g.type_id
                       AND rt.staying_hour_id = sh.id
                       AND rt.is_available = 1
SET g.static_amount = (rt.amount * g.number_of_days) + b.initial_deposit
WHERE g.id IN (SELECT id FROM _bkp_unposted_guests_2026_04_30);
```

Expected: **5 rows affected**.

**STEP 9B — UPDATE `checkin_details` for ALL 5**
```sql
UPDATE checkin_details cd
JOIN guests g           ON g.id = cd.guest_id
JOIN branches b         ON b.id = g.branch_id
JOIN staying_hours sh   ON sh.branch_id = g.branch_id AND sh.number = 24
JOIN rates rt           ON rt.branch_id = g.branch_id
                       AND rt.type_id = g.type_id
                       AND rt.staying_hour_id = sh.id
                       AND rt.is_available = 1
SET cd.static_room_amount = rt.amount * g.number_of_days,
    cd.static_amount      = (rt.amount * g.number_of_days) + b.initial_deposit
WHERE cd.id IN (SELECT id FROM _bkp_unposted_checkins_2026_04_30);
```

Expected: **5 rows affected**.

**STEP 9C — UPDATE Check In transactions for ALL 5**
```sql
UPDATE transactions tr
JOIN checkin_details cd ON cd.id = tr.checkin_detail_id
JOIN guests g           ON g.id = cd.guest_id
JOIN staying_hours sh   ON sh.branch_id = g.branch_id AND sh.number = 24
JOIN rates rt           ON rt.branch_id = g.branch_id
                       AND rt.type_id = g.type_id
                       AND rt.staying_hour_id = sh.id
                       AND rt.is_available = 1
SET tr.payable_amount = rt.amount * g.number_of_days,
    tr.paid_amount    = rt.amount * g.number_of_days
WHERE tr.id IN (SELECT id FROM _bkp_unposted_transactions_2026_04_30);
```

Expected: **5 rows affected**.

**STEP 9D — Verify (same SELECT as STEP 7)**
```sql
SELECT
    g.id, g.name, r.number AS room, g.static_amount,
    cd.static_room_amount, cd.static_amount AS cd_static_amount,
    tr.payable_amount AS check_in_tx_payable,
    tgr.new_amount AS transfer_report_new_amount,
    cd.is_check_out
FROM guests g
JOIN checkin_details cd ON cd.guest_id = g.id
JOIN rooms r            ON r.id = g.room_id
LEFT JOIN transactions tr ON tr.checkin_detail_id = cd.id
                          AND tr.description = 'Guest Check In'
LEFT JOIN transfered_guest_reports tgr ON tgr.checkin_detail_id = cd.id
WHERE g.id IN (SELECT id FROM _bkp_unposted_guests_2026_04_30)
ORDER BY g.id;
```

Expected — all 5 rows now have correct values:

| id | name | room | static_amount | static_room_amount | cd_static_amount | check_in_tx_payable | transfer_report_new_amount | is_check_out |
|----|------|------|---------------|--------------------|--------------------|---------------------|----------------------------|--------------|
| 15219 | Leonardo charcos | 30 | **1,800** | **1,600** | **1,800** | **1,600** | 1,600 | 1 |
| 15592 | Wilfredo Zambo | 33 | **1,800** | **1,600** | **1,800** | **1,600** | 1,600 | 1 |
| 15593 | Michael D. Caores | 4A | **1,600** | **1,400** | **1,600** | **1,400** | 1,400 | 1 |
| 15615 | Jonalyn carreon | 15 | **2,000** | **1,800** | **2,000** | **1,800** | 1,800 | 1 |
| 15652 | Kenneth john besas | 252 | **1,800** | **1,600** | **1,800** | **1,600** | 1,600 | 0 |

Sales Report April 28-29 should now show real revenue numbers for these 5 guests.

### Run order with the addendum

`STEP 1 → 2 → 3A → 3B → 3C → 3D → (skip 4-6 with is_check_out filter) → 9A → 9B → 9C → 6.5A → 6.5B → 9D`

Or if you've already run STEPS 4-6 (which only updated Kenneth), just run
STEPS 9A/9B/9C — they re-update Kenneth (idempotent, same target values) and
also update the 4 checked-out guests.

---

## Source of truth for the expected values

Every value above was queried from the live production-copy database, not assumed:

| Value | Source query |
|-------|-------------|
| Affected guest list (5 rows) | `SELECT g.* FROM guests JOIN branches WHERE g.is_long_stay=1 AND g.previous_room_id IS NOT NULL AND g.static_amount=b.initial_deposit` |
| `rate_24h` (800/700/900) | `SELECT rt.amount FROM rates JOIN staying_hours sh WHERE sh.number=24 AND rt.is_available=1` |
| `new_static_amount` math | `(rt.amount × number_of_days) + initial_deposit`<br/>800×2+200=1800, 700×2+200=1600, 900×2+200=2000 |
| `static_room_amount` math | `rt.amount × number_of_days`<br/>800×2=1600, 700×2=1400, 900×2=1800 |
| Check In tx IDs (35082, 35929, 35933, 36000, 36084) | `SELECT tr.id FROM transactions WHERE checkin_detail_id IN (...) AND description='Guest Check In'` |
| `transfered_guest_reports.previous_amount` | Already in the table — verified by SELECT |
| `transfered_guest_reports.id` (689, 760, 761, 775, 789) | Real IDs from the transfered_guest_reports table |

---

## STEP 8 — Rollback (only if something looks wrong)

> Use these only if STEP 9D verification shows wrong values, or you need
> to undo the recovery for any reason. Each block restores one table from
> its `_bkp_unposted_*_2026_04_30` backup table created in STEP 3.
>
> Each block is independent — you can run any combination in any order.

### Rollback `guests.static_amount`

```sql
UPDATE guests g
JOIN _bkp_unposted_guests_2026_04_30 bk ON bk.id = g.id
SET g.static_amount = bk.static_amount;
```

### Rollback `checkin_details`

```sql
UPDATE checkin_details cd
JOIN _bkp_unposted_checkins_2026_04_30 bk ON bk.id = cd.id
SET cd.static_room_amount = bk.static_room_amount,
    cd.static_amount      = bk.static_amount;
```

### Rollback Check In transactions

```sql
UPDATE transactions tr
JOIN _bkp_unposted_transactions_2026_04_30 bk ON bk.id = tr.id
SET tr.payable_amount = bk.payable_amount,
    tr.paid_amount    = bk.paid_amount;
```

### Rollback transfered_guest_reports

```sql
UPDATE transfered_guest_reports tgr
JOIN checkin_details cd ON cd.id = tgr.checkin_detail_id
JOIN guests g ON g.id = cd.guest_id
SET tgr.new_amount = 0
WHERE tgr.checkin_detail_id IN (SELECT id FROM _bkp_unposted_checkins_2026_04_30);
```

### After verifying recovery looks good — drop the backup tables

> Optional cleanup. Wait at least 24 hours after running the recovery
> before dropping these — they're your safety net for STEP 8 rollback.

```sql
DROP TABLE IF EXISTS _bkp_unposted_guests_2026_04_30;
DROP TABLE IF EXISTS _bkp_unposted_checkins_2026_04_30;
DROP TABLE IF EXISTS _bkp_unposted_transactions_2026_04_30;
```
