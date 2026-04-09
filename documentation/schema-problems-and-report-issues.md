# Schema Problems & Report Issues

This document outlines the structural problems in the current database schema and codebase that cause recurring report inaccuracies. These issues stem from the project being built incrementally without upfront planning — features were added meeting-by-meeting, and the schema was never designed with reporting in mind.

---

## Table of Contents

1. [Dual Ledger System](#1-dual-ledger-system)
2. [String-Based Deposit Classification](#2-string-based-deposit-classification)
3. [Hardcoded Values in Reports](#3-hardcoded-values-in-reports)
4. [Shift Cutoff Time Inconsistency](#4-shift-cutoff-time-inconsistency)
5. [Forwarded Balance Chain Fragility](#5-forwarded-balance-chain-fragility)
6. [Same Data Different Fields](#6-same-data-different-fields)
7. [Missing shift_log_id on Admin Check-Ins](#7-missing-shift_log_id-on-admin-check-ins)
8. [CheckOutGuest Reads Only First Transaction](#8-checkoutguest-reads-only-first-transaction)
9. [CashOnHand Filters by Today Not by Shift](#9-cashonhand-filters-by-today-not-by-shift)
10. [No Validation Between Opening Cash and Ending Cash](#10-no-validation-between-opening-cash-and-ending-cash)
11. [Forwarded Deposit Chain Walks All Sessions From Day One](#11-forwarded-deposit-chain-walks-all-sessions-from-day-one)
12. [Overlap Guest Detection Is Fragile](#12-overlap-guest-detection-is-fragile)
13. [Room Deposit Count Uses Guest Count Not Actual Transactions](#13-room-deposit-count-uses-guest-count-not-actual-transactions)
14. [Unclaimed Deposit Logic Depends on Remarks String](#14-unclaimed-deposit-logic-depends-on-remarks-string)
15. [Original Check-In Transaction Is Modified on Transfer](#15-original-check-in-transaction-is-modified-on-transfer)
16. [No Shift Summary Snapshot](#16-no-shift-summary-snapshot)
17. [Expenses and Remittances Not Reconciled Against Cash Flow](#17-expenses-and-remittances-not-reconciled-against-cash-flow)
18. [Performance Issues in Report Queries](#18-performance-issues-in-report-queries)
19. [Summary Table: Problem vs Impact vs Fix](#19-summary-table)
20. [Recommended Fixes](#20-recommended-fixes)

---

## 1. Dual Ledger System

### The Problem

There are two independent systems tracking money movement that are never reconciled:

| System | Table | Used By |
|--------|-------|---------|
| Guest transactions | `transactions` | SalesReportV2, FrontdeskReportV2, CheckOutGuest |
| Cash drawer entries | `cash_on_drawers` | CashOnHand, BeginningCash |

When a guest pays for a room, a `Transaction` record is created. But `CashOnDrawer` entries are created separately as manual cash entries. These two tables can have different totals for the same shift because they are populated independently.

### Where It Breaks

- **SalesReportV2** sums from `transactions` table
- **CashOnHand** (end of shift) sums from `cash_on_drawers` table
- **BeginningCash** (start of shift) reads from `cash_on_drawers` table
- The totals from these two systems can diverge, making shift reconciliation unreliable

### Affected Files

- `app/Http/Livewire/BackOffice/SalesReportV2.php` — reads `transactions`
- `app/Http/Livewire/Frontdesk/CashOnHand.php` — reads `cash_on_drawers`
- `app/Http/Livewire/Frontdesk/BeginningCash.php` — reads `cash_on_drawers`

---

## 2. String-Based Deposit Classification

### The Problem

Deposit transactions (`transaction_type_id = 2`) are classified by matching the `remarks` text field:

```php
// To identify room key deposit:
str_contains(strtolower($remarks), 'room key')

// To identify non-room-key deposits:
where('remarks', '!=', 'Deposit From Check In (Room Key & TV Remote)')
```

There is no `deposit_type` column. The system relies entirely on exact string matching to differentiate between:
- Room key & TV remote deposit
- Guest security deposit
- Excess payment deposit
- Transfer excess deposit

### Where It Breaks

- If the `remarks` text is changed even slightly in any check-in/payment component, every report that filters by remarks will break silently
- Reports get different results depending on whether they use `str_contains()` (partial match) or `==` / `!=` (exact match)
- No database-level guarantee that deposit types are categorized consistently

### Affected Files

- `app/Http/Livewire/BackOffice/SalesReportV2.php` — `str_contains()` for room key detection
- `app/Http/Livewire/BackOffice/FrontdeskReportV2.php` — remarks-based filtering
- `app/Http/Livewire/Frontdesk/GuestTransactions/CheckOutGuest.php` — exact string match on remarks

---

## 3. Hardcoded Values in Reports

### The Problem

**Room deposit amount = 200** is hardcoded directly in the sales report:

```php
// SalesReportV2.php
$room_deposit = ($shift_checkins + $forwarded_count) * 200;
```

This is not read from actual transaction amounts or branch settings. It is a multiplication of guest count times 200.

### Where It Breaks

- If a branch uses a different deposit amount (e.g., 300), the report is wrong
- If a guest has a partial deposit or no deposit, the report still counts them as 200
- If the deposit amount changes over time, historical reports are retroactively wrong
- The `branches` table has `initial_deposit` but the report ignores it

### Affected Files

- `app/Http/Livewire/BackOffice/SalesReportV2.php` — hardcoded `* 200`

---

## 4. Shift Cutoff Time Inconsistency

### The Problem

Different parts of the codebase use different times to determine AM vs PM shift:

| Component | AM Shift | PM Shift | Cutoff |
|-----------|----------|----------|--------|
| SalesReportV2 | 6:00 AM - 5:59 PM | 6:00 PM - 5:59 AM | **6:00 PM** |
| FrontdeskReportV2 | 6:00 AM - 5:59 PM | 6:00 PM - 5:59 AM | **6:00 PM** |
| CheckOutGuest | 8:00 AM - 7:59 PM | 8:00 PM - 7:59 AM | **8:00 PM** |

```php
// CheckOutGuest.php — uses 8 PM cutoff
'shift' => (now()->hour >= 8 && now()->hour < 20) ? 'AM' : 'PM',

// Reports use 6 PM cutoff for shift grouping
```

### Where It Breaks

- A transaction created at 7:00 PM via CheckOutGuest is labeled `AM` (because 7 PM < 8 PM cutoff)
- The same transaction in SalesReportV2 falls into the `PM` shift (because 7 PM >= 6 PM cutoff)
- The transaction appears in a different shift depending on which screen you look at

### Affected Files

- `app/Http/Livewire/Frontdesk/GuestTransactions/CheckOutGuest.php` — 8 PM cutoff
- `app/Http/Livewire/BackOffice/SalesReportV2.php` — 6 PM cutoff
- `app/Http/Livewire/BackOffice/FrontdeskReportV2.php` — 6 PM cutoff

---

## 5. Forwarded Balance Chain Fragility

### The Problem

Both `SalesReportV2` and `FrontdeskReportV2` calculate forwarded balances by walking through ALL previous shifts chronologically:

```php
// Pseudocode of the chain:
for each previous_session in all_sessions_since_day_1:
    forwarded_balance = net_sales(previous_session) + forwarded_balance(previous_session)
```

This creates a **cumulative dependency chain** where every shift's report depends on every shift that came before it.

### Where It Breaks

- If any single historical shift has:
  - Wrong `beginning_cash`
  - Missing transactions
  - Corrupted overlap data
  - Manually edited amounts
- Then that error **cascades forward to every future shift report**
- There is no way to "reset" the chain without fixing the source error
- There is no snapshot or checkpoint to break the chain

### Affected Files

- `app/Http/Livewire/BackOffice/SalesReportV2.php` — `calculateForwardedDepositsFromPreviousShift()`
- `app/Http/Livewire/BackOffice/FrontdeskReportV2.php` — `calculateForwardedBalance()`

---

## 6. Same Data Different Fields

### The Problem

Different components read deposit amounts from different fields on the same `transactions` table:

| Component | Field Used | Purpose |
|-----------|-----------|---------|
| CheckOutGuest | `deposit_amount` | Show guest's deposit at checkout |
| SalesReportV2 | `payable_amount` | Sum deposits for shift report |
| FrontdeskReportV2 | `payable_amount` | Sum deposits for frontdesk report |
| CashOnHand | `CashOnDrawer.amount` | Sum cash in drawer |

### Where It Breaks

- `deposit_amount` and `payable_amount` can have different values on the same transaction
- A report summing `payable_amount` gets a different total than checkout summing `deposit_amount`
- No clear convention on which field represents the "actual" deposit amount

### Affected Files

- `app/Http/Livewire/Frontdesk/GuestTransactions/CheckOutGuest.php` — reads `deposit_amount`
- `app/Http/Livewire/BackOffice/SalesReportV2.php` — reads `payable_amount`

---

## 7. Missing shift_log_id on Admin Check-Ins

### The Problem

When an admin creates a check-in through `AdminCheckInCo`, the transactions are created **without** setting `shift_log_id`:

```php
// AdminCheckInCo.php — no shift_log_id set
Transaction::create([
    'transaction_type_id' => 1,
    'payable_amount' => $rate->price,
    // shift_log_id is NOT included
]);
```

In contrast, frontdesk check-ins via `CheckInFromKiosk` always set `shift_log_id`.

### Where It Breaks

- Shift-based reports (`SalesReportV2`, `FrontdeskReportV2`) filter by `shift_log_id`
- Transactions without `shift_log_id` are **invisible** to these reports
- Revenue from admin check-ins does not appear in any shift report
- Cash reconciliation is off because real money was received but not counted in the shift

### Affected Files

- `app/Http/Livewire/Admin/CheckInCo.php` — missing `shift_log_id` on transaction creation

---

## 8. CheckOutGuest Reads Only First Transaction

### The Problem

When loading guest deposits at checkout, the code uses `->first()`:

```php
$deposit_remote_and_key = Transaction::where('transaction_type_id', 2)
    ->where('remarks', 'Deposit From Check In (Room Key & TV Remote)')
    ->first()?->deposit_amount ?? 0;

$deposit_except_remote_and_key = Transaction::where('transaction_type_id', 2)
    ->where('remarks', '!=', 'Deposit From Check In (Room Key & TV Remote)')
    ->first()?->deposit_amount ?? 0;
```

### Where It Breaks

- If a guest has **multiple deposit transactions** (e.g., initial deposit + additional deposit), only the first one is read
- The remaining deposits are invisible at checkout
- Guest is shown a lower deposit balance than they actually have
- Refund calculation at checkout is wrong

### Affected Files

- `app/Http/Livewire/Frontdesk/GuestTransactions/CheckOutGuest.php`

---

## 9. CashOnHand Filters by Today Not by Shift

### The Problem

```php
CashOnDrawer::where('transaction_date', now()->toDateString())
    ->whereNot('transaction_type', 'deposit')
    ->sum('amount');
```

The query filters by **today's date**, not by the current shift's time range.

### Where It Breaks

- If a shift spans midnight (PM shift: 6 PM to 6 AM), only transactions from the current calendar day are counted
- A night shift that starts at 10 PM would lose all transactions from 10 PM to midnight (they belong to "yesterday")
- If multiple shifts happen on the same day, all their CashOnDrawer entries are combined together

### Affected Files

- `app/Http/Livewire/Frontdesk/CashOnHand.php`

---

## 10. No Validation Between Opening Cash and Ending Cash

### The Problem

At shift start, `beginning_cash` is manually entered. At shift end, `end_cash` is manually entered. There is no validation:

```
Expected: beginning_cash + total_sales - total_expenses - total_remittances = end_cash
Actual: No check. Both values are accepted as-is.
```

### Where It Breaks

- If the frontdesk miscounts cash, the error is permanently stored
- The forwarded balance chain (Problem #5) picks up this error and propagates it to all future shifts
- Back office has no automated way to detect discrepancies
- No alert or warning when numbers don't add up

### Affected Files

- `app/Http/Livewire/Frontdesk/BeginningCash.php`
- `app/Http/Livewire/Frontdesk/CashOnHand.php`

---

## 11. Forwarded Deposit Chain Walks All Sessions From Day One

### The Problem

```php
// SalesReportV2.php — calculateForwardedDepositsFromPreviousShift()
// Walks ALL sessions chronologically from the very first session
$allSessions = ShiftLog::orderBy('time_in')->get();
foreach ($allSessions as $session) {
    $running_balance += $own_deposits - $own_cashouts;
}
```

### Where It Breaks

- **Performance**: Every report generation re-processes the entire history. As the hotel operates longer, reports get slower
- **Accuracy**: If early sessions have missing data, the running balance is permanently skewed
- **No reset mechanism**: Cannot "close the books" and start fresh from a known-good balance
- **O(n²) complexity**: For each current report, iterates all past sessions and each session queries its own transactions

### Affected Files

- `app/Http/Livewire/BackOffice/SalesReportV2.php`
- `app/Http/Livewire/BackOffice/FrontdeskReportV2.php`

---

## 12. Overlap Guest Detection Is Fragile

### The Problem

When shifts overlap (e.g., previous shift ends at 6:05 PM and current shift starts at 6:00 PM), the system tries to detect "overlap guests":

```php
// Overlap: guest checked out during the gap between shifts
$overlapGuests = CheckinDetail::where('check_in_at', '<', $shift_time_in)
    ->whereBetween('check_out_at', [$shift_time_in, $prev_shift_time_out]);
```

For overlap guests, **synthetic transactions** are generated (not real database records) and injected into the report.

### Where It Breaks

- If `prev_shift.time_out` is null or wrong, overlap detection fails entirely
- Synthetic rows are generated in PHP, not from actual data — they can drift from reality
- If a guest's checkout was processed by a different frontdesk, the overlap logic may assign it to the wrong shift
- The 5-minute overlap window is assumed, not configured — if shifts don't overlap, or overlap by more, the logic breaks

### Affected Files

- `app/Http/Livewire/BackOffice/SalesReportV2.php` — overlap guest detection and synthetic row creation
- `app/Http/Livewire/BackOffice/FrontdeskReportV2.php` — same overlap logic

---

## 13. Room Deposit Count Uses Guest Count Not Actual Transactions

### The Problem

In FrontdeskReportV2, room deposit count is calculated by counting guests, not by counting actual deposit transactions:

```php
// Counts guests who haven't checked out = assumed to have room deposits
$room_deposit_count = $occupying_guests_without_checkout;
```

### Where It Breaks

- A guest who checked in without paying deposit (e.g., deposit waived) is still counted
- A guest with partial deposit is counted as a full deposit
- If deposit was refunded mid-stay, the count doesn't reflect it

### Affected Files

- `app/Http/Livewire/BackOffice/FrontdeskReportV2.php`

---

## 14. Unclaimed Deposit Logic Depends on Remarks String

### The Problem

When calculating unclaimed deposits (guests who checked out but deposit wasn't fully returned), the system filters by remarks:

```php
// Only counts deposits where remarks does NOT contain 'room key'
// Assumes room key deposit is always returned at checkout
```

### Where It Breaks

- If a guest's room key deposit was NOT returned (damaged/lost), it's excluded from unclaimed because the remarks say "room key"
- String matching is case-sensitive in some places, case-insensitive in others
- New deposit types with different remarks text won't be categorized correctly

### Affected Files

- `app/Http/Livewire/BackOffice/SalesReportV2.php`
- `app/Http/Livewire/BackOffice/FrontdeskReportV2.php`

---

## 15. Original Check-In Transaction Is Modified on Transfer

### The Problem

When a guest transfers rooms, the **original check-in transaction** (type_id=1) is **modified in place**:

```php
// TransferRoom.php
$original_checkin_transaction->update([
    'payable_amount' => $new_room_rate,
    'remarks' => 'Updated remarks with new room',
]);
```

### Where It Breaks

- The original check-in amount is lost — no historical record of what the guest originally paid
- If a report queries check-in transactions by date, it sees the **modified** amount, not the original
- Audit trail is broken — cannot trace what changed and when
- If transfer is disputed, there's no evidence of the original charge

### Affected Files

- `app/Http/Livewire/Frontdesk/Monitoring/TransferRoom.php`

---

## 16. No Shift Summary Snapshot

### The Problem

There is no table that stores the **final calculated totals** for a completed shift. Every time a report is viewed, it:

1. Finds all occupying guests for the period
2. Queries all their transactions
3. Calculates forwarded balances from all previous shifts
4. Builds synthetic rows for overlaps and forwarded guests
5. Sums everything up

### Where It Breaks

- The same shift can show different numbers depending on **when** the report is generated (if transactions are added/modified after the shift ends)
- Performance degrades over time as history grows
- No way to "lock" a shift's numbers for accounting purposes
- Debugging is difficult because there's no stored result to compare against

### Recommended

A `shift_summaries` table that stores calculated totals at shift close:

```
shift_log_id, total_room_sales, total_deposits, total_extensions,
total_transfers, total_damages, total_cashouts, total_expenses,
total_remittances, forwarded_balance, calculated_at
```

---

## 17. Expenses and Remittances Not Reconciled Against Cash Flow

### The Problem

Expenses and remittances are recorded as separate entries:

```php
// Expenses
Expense::create(['amount' => $amount, 'shift_log_id' => $shift_id]);

// Remittances
Remittance::create(['total_remittance' => $amount, 'shift_log_id' => $shift_id]);
```

But there is no link between these and the transaction/cash flow. No validation that:
- Expense amount doesn't exceed available cash
- Remittance amount matches what's physically in the drawer
- Total expenses + remittances + remaining cash = opening cash + sales

### Where It Breaks

- Frontdesk can record an expense of 10,000 even if only 500 is in the drawer
- Back office sees expense totals that don't match actual cash movement
- Shift reconciliation has no integrity check

### Affected Files

- `app/Http/Livewire/Frontdesk/Remittance.php`
- `app/Http/Livewire/Frontdesk/CashOnHand.php`
- `app/Http/Livewire/BackOffice/Expense.php`

---

## 18. Performance Issues in Report Queries

### The Problem

Report generation involves:

1. `calculateForwardedDepositsFromPreviousShift()` — iterates ALL historical sessions
2. `getForwardedGuestRows()` — queries transactions per guest (N+1 pattern)
3. `getTransactionRows()` — complex query with multiple OR conditions
4. No caching of intermediate results

### Impact

- As the hotel operates for months/years, reports get progressively slower
- Each report view triggers the full calculation chain
- Multiple users viewing reports simultaneously multiply the database load
- No pagination or lazy loading of historical data

### Affected Files

- `app/Http/Livewire/BackOffice/SalesReportV2.php`
- `app/Http/Livewire/BackOffice/FrontdeskReportV2.php`

---

## 19. Summary Table

| # | Problem | Impact on Reports | Severity |
|---|---------|-------------------|----------|
| 1 | Dual ledger system | Cash totals differ between reports | Critical |
| 2 | String-based deposit classification | Deposits miscategorized, silent breaks | Critical |
| 3 | Hardcoded room deposit = 200 | Wrong deposit totals if amount differs | High |
| 4 | Shift cutoff inconsistency (6PM vs 8PM) | Transactions in wrong shift | High |
| 5 | Forwarded balance chain fragility | One bad shift corrupts all future reports | Critical |
| 6 | Same data, different fields | Different totals from different reports | High |
| 7 | Missing shift_log_id on admin check-ins | Revenue invisible to shift reports | High |
| 8 | CheckOutGuest reads only first deposit | Guest shown wrong deposit balance | Medium |
| 9 | CashOnHand filters by today not shift | Night shift cash count wrong | Medium |
| 10 | No opening/ending cash validation | Errors propagate silently | Medium |
| 11 | Chain walks all sessions from day one | Slow reports, cascading errors | High |
| 12 | Fragile overlap detection | Synthetic rows drift from reality | Medium |
| 13 | Deposit count by guest count not transactions | Wrong deposit count | Medium |
| 14 | Unclaimed deposit depends on remarks | Wrong unclaimed amounts | Medium |
| 15 | Original transaction modified on transfer | Audit trail broken, wrong historical data | High |
| 16 | No shift summary snapshot | Reports change after shift ends | High |
| 17 | Expenses/remittances not validated | Cash reconciliation unreliable | Medium |
| 18 | Performance issues in queries | Reports slow down over time | Medium |

---

## 20. Recommended Fixes

### Priority 1 — Critical (Fix These First)

**A. Add `deposit_type` column to `transactions` table**
```
deposit_type ENUM('room_key', 'guest', 'excess', 'transfer') NULLABLE
```
- Stop relying on remarks string matching
- Migrate existing data by parsing current remarks
- Update all check-in, checkout, transfer components to set this field

**B. Ensure `shift_log_id` is ALWAYS set**
- Fix AdminCheckInCo to set shift_log_id on all transactions
- Add database NOT NULL constraint (after backfilling existing data)

**C. Unify cash tracking — choose one source of truth**
- Option A: Derive CashOnDrawer from Transactions (eliminate manual entries)
- Option B: Keep both but add reconciliation check that flags mismatches
- Reports should query from a single source, not mixed sources

**D. Store `deposit_amount` in `branches` table**
- Replace hardcoded `200` with `$branch->initial_deposit`
- Already has the column — just need to use it in reports

### Priority 2 — High (Fix After Critical)

**E. Standardize shift cutoff time**
- Create a single constant or branch setting: `shift_cutoff_hour`
- Use it in ALL components: CheckOutGuest, SalesReportV2, FrontdeskReportV2
- Suggested: add `shift_cutoff_hour` column to `branches` table

**F. Create `shift_summaries` table**
- Store finalized totals when a shift is closed
- Reports read from this table instead of recomputing everything
- Break the forwarded balance chain — each shift is self-contained
- Add a "recalculate" button for back office if corrections are needed

**G. Preserve original transaction on room transfer**
- Instead of modifying the original check-in transaction, create a new adjustment transaction
- Keep the original record intact for audit purposes

**H. Fix CashOnHand to filter by shift time range, not today's date**
- Use `shift_log.time_in` and `shift_log.time_out` instead of `now()->toDateString()`

### Priority 3 — Medium (Quality of Life)

**I. Sum deposits instead of using `.first()`**
- CheckOutGuest should `->sum('deposit_amount')` not `->first()?->deposit_amount`

**J. Add opening/ending cash validation**
- Calculate expected ending cash and show discrepancy to frontdesk
- Store discrepancy amount in shift_logs for back office review

**K. Add query caching for report calculations**
- Cache forwarded balance results per shift (they don't change for closed shifts)
- Invalidate cache only when a correction is made

**L. Paginate historical session queries**
- Don't load all sessions from day one
- Use shift_summaries (Fix F) to break the chain

---

## Data Flow Diagram — Current vs Ideal

### Current (Problematic)

```
CHECK-IN
  ├─> Transaction (type=1, room charge)
  ├─> Transaction (type=2, deposit — classified by remarks string)
  └─> CheckinDetail (static_amount, total_deposit)

DURING STAY
  ├─> Transaction (type=6/7/8/9 — extension/transfer/amenity/food)
  ├─> CashOnDrawer (manual entry — SEPARATE from transactions)
  ├─> Expense (manual — no cash validation)
  └─> Remittance (manual — no cash validation)

CHECKOUT
  ├─> Transaction (type=4 damage, type=5 cashout)
  ├─> CheckinDetail updated (is_check_out, check_out_at)
  └─> Shift determined by DIFFERENT cutoff times

REPORTS QUERY
  ├─> SalesReportV2 ──────> transactions (payable_amount) + chain of ALL shifts
  ├─> FrontdeskReportV2 ──> transactions (payable_amount) + chain of ALL shifts
  ├─> CashOnHand ──────────> cash_on_drawers (amount) ← DIFFERENT SOURCE
  ├─> BeginningCash ───────> cash_on_drawers (amount) ← DIFFERENT SOURCE
  └─> CheckOutGuest ───────> transactions (deposit_amount) ← DIFFERENT FIELD
```

### Ideal (After Fixes)

```
CHECK-IN
  ├─> Transaction (type=1, deposit_type=null, shift_log_id=ALWAYS SET)
  ├─> Transaction (type=2, deposit_type='room_key', shift_log_id=ALWAYS SET)
  └─> CheckinDetail (static_amount, total_deposit)

DURING STAY
  ├─> Transaction (all types — shift_log_id ALWAYS SET)
  ├─> Expense (validated against available cash)
  └─> Remittance (validated against available cash)

CHECKOUT
  ├─> Transaction (type=4/5 — shift determined by SINGLE cutoff constant)
  └─> CheckinDetail updated

SHIFT CLOSE
  └─> ShiftSummary snapshot created (totals locked, forwarded balance stored)

REPORTS QUERY
  ├─> SalesReportV2 ──────> transactions (payable_amount) + ShiftSummary
  ├─> FrontdeskReportV2 ──> transactions (payable_amount) + ShiftSummary
  ├─> CashOnHand ──────────> transactions (SAME source) + validated
  └─> CheckOutGuest ───────> transactions (payable_amount) ← SAME FIELD
```
