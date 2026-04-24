# Unresolved Check-Ins Report

**Prepared:** 2026-04-24
**Purpose:** Complete inventory of guest records that are not checked out, for management discussion and decision.

---

## Executive summary

The system has **33 guest records** with `is_check_out = 0` that have passed their expected checkout time. These split into two very different categories:

| Category | Count | Description | Severity |
|----------|-------|-------------|----------|
| **A. Ghost records** | **9** | Old records (3–45 days), room was reused by other guests, never resolved | 🔴 High — real bug |
| **B. Late checkouts (today)** | **24** | Current guests whose stay expired 0–14 hours ago, still physically in room | 🟢 Normal — process as they leave |

**Total money currently tied up:**
- Deposits held: ₱11,049 (all categories combined)
- Charges already recorded: ₱17,808

---

## Category A — Ghost records (9 records) 🔴

These are the **real problem**. Guests who were checked in weeks or months ago, walked out without checking out, and the room has since been used by other guests. The original record is stuck open.

### The 9 ghost records

| # | Guest | QR Code | Room | Checked in | Expected out | Hours overdue | Room status now | Deposit | Charges |
|---|-------|---------|------|-----------|--------------|---------------|-----------------|---------|---------|
| 1 | Johnny Deita | 1260415 | 3C | 2026-03-10 19:08 | 2026-03-11 07:08 | 1067h (~44d) | Occupied | ₱200 | ₱336 |
| 2 | PARBA, ANTONIO JR. | 1260624 | 89 | 2026-03-11 17:19 | 2026-03-12 11:19 | 1038h (~43d) | Occupied | ₱0 | ₱280 |
| 3 | Erhajev | 1260835 | 210 | 2026-03-12 13:01 | 2026-03-13 13:01 | 1013h (~42d) | Available | ₱200 | ₱672 |
| 4 | Jeff Mallari | 1261107 | 139 | 2026-03-13 12:32 | 2026-03-13 18:32 | 1007h (~42d) | Uncleaned | ₱200 | ₱280 |
| 5 | Lydia auditor | 1261077 | 29 | 2026-03-13 10:31 | 2026-03-14 10:31 | 991h (~41d) | Occupied | ₱200 | ₱616 |
| 6 | Yeow lai kar | 1260850 | 6 | 2026-03-12 13:51 | 2026-03-14 13:51 | 988h (~41d) | Occupied | ₱200 | ₱1,232 |
| 7 | Annie | 1261229 | 66 | 2026-03-14 07:17 | 2026-03-14 19:17 | 982h (~41d) | Occupied | ₱608 | ₱392 |
| 8 | Jezz | 1266844 | 165 | 2026-04-06 16:42 | 2026-04-07 16:42 | 409h (~17d) | Available | ₱400 | ₱1,600 |
| 9 | Nenitte militante | 1260732 | 152 | 2026-04-21 00:38 | 2026-04-21 12:38 | 77h (~3d) | Available | ₱200 | ₱400 |

**Category A totals: 9 records, ₱2,208 deposits, ₱5,808 charges.**

---

## Category B — Late checkouts today (24 records) 🟢

These are **current guests** who are physically still in the room, but their stay expired within the last 14 hours. Normal operational flow — frontdesk processes their checkout (or extension) as they leave.

These are NOT the same bug as ghosts. They become ghosts only if nobody processes them and the room is later used by someone else.

### The 24 late-checkout records

| # | Guest | QR Code | Room | Checked in | Expected out | Hours late | Deposit | Charges |
|---|-------|---------|------|-----------|--------------|-----------|---------|---------|
| 1 | Ron oliver mariano | 1261409 | 5D | 04-23 15:52 | 04-24 03:52 | 14h | ₱200 | ₱350 |
| 2 | Jilyana dante | 1261266 | 151 | 04-22 22:30 | 04-24 10:30 | 7h | ₱1,086 | ₱450 |
| 3 | Ojie desoacido | 1261400 | 82 | 04-23 15:03 | 04-24 15:03 | 3h | ₱600 | ₱800 |
| 4 | Emmanuel Pasilan | 1261615 | 4B | 04-24 09:55 | 04-24 15:55 | 2h | ₱200 | ₱250 |
| 5 | Diana montalba | 1260315 | 158 | 04-19 16:03 | 04-24 16:03 | 2h | ₱1,800 | ₱800 |
| 6 | Cyhrus | 1261621 | 171 | 04-24 10:05 | 04-24 16:05 | 2h | ₱200 | ₱300 |
| 7 | Jorry trinidad | 1261417 | 11 | 04-23 16:12 | 04-24 16:12 | 2h | ₱200 | ₱900 |
| 8 | Sofia sartorio | 1261420 | 15 | 04-23 16:23 | 04-24 16:23 | 1h | ₱400 | ₱450 |
| 9 | Arnold pagdato | 1261624 | 209 | 04-24 10:32 | 04-24 16:32 | 1h | ₱200 | ₱300 |
| 10 | Andy | 1261125 | 97 | 04-22 16:47 | 04-24 16:47 | 1h | ₱200 | ₱800 |
| 11 | Dysebelle Askalani | 1261628 | 5G | 04-24 10:52 | 04-24 16:52 | 1h | ₱200 | ₱250 |
| 12 | Zedrik Bendijo | 1261630 | 86 | 04-24 10:53 | 04-24 16:53 | 1h | ₱655 | ₱300 |
| 13 | Airene | 1261632 | 83 | 04-24 10:56 | 04-24 16:56 | 1h | ₱200 | ₱300 |
| 14 | Marlou | 1261633 | 68 | 04-24 11:03 | 04-24 17:03 | 1h | ₱200 | ₱300 |
| 15 | Cute | 1261426 | 32 | 04-23 17:09 | 04-24 17:09 | 1h | ₱200 | ₱800 |
| 16 | Batchok | 1261636 | 3F | 04-24 11:12 | 04-24 17:12 | 1h | ₱200 | ₱250 |
| 17 | CJ Montero | 1261639 | 29 | 04-24 11:24 | 04-24 17:24 | 0h | ₱200 | ₱300 |
| 18 | Nasim comilao | 1261429 | 160 | 04-23 17:26 | 04-24 17:26 | 0h | ₱700 | ₱400 |
| 19 | Biboy | 1261640 | 50 | 04-24 11:28 | 04-24 17:28 | 0h | ₱200 | ₱300 |
| 20 | Venery zapanta | 1261434 | 224 | 04-23 17:32 | 04-24 17:32 | 0h | ₱200 | ₱800 |
| 21 | Glenn bermoy | 1261442 | 5F | 04-23 17:51 | 04-24 17:51 | 0h | ₱200 | ₱700 |
| 22 | Placido Galo jr | 1261452 | 37 | 04-23 18:06 | 04-24 18:06 | 0h | ₱200 | ₱800 |
| 23 | Triza fe blago | 1261453 | 206 | 04-23 18:06 | 04-24 18:06 | 0h | ₱200 | ₱800 |
| 24 | Roxanne luga | 1261646 | 67 | 04-24 12:08 | 04-24 18:08 | 0h | ₱200 | ₱300 |

**Category B totals: 24 records, ₱8,841 deposits, ₱12,000 charges.**

*All 24 have room status = Occupied. All within 0–14 hours overdue. These are active stays pending normal processing.*

---

## Combined totals

| Metric | Category A (Ghosts) | Category B (Today's Late) | Total |
|--------|---------------------|---------------------------|-------|
| Count | 9 | 24 | 33 |
| Deposits | ₱2,208 | ₱8,841 | ₱11,049 |
| Charges | ₱5,808 | ₱12,000 | ₱17,808 |
| Oldest record | 45 days | Today | — |

---

## Rooms that would be blocked IF we re-enable the guards

This is important: not every unresolved record blocks a room. Many of the unresolved records belong to CURRENT guests who are physically in the room (status = Occupied), and those rooms wouldn't show in kiosk/roomboy anyway. Here's the real impact:

### Branch 1 total room inventory
| Metric | Count |
|--------|-------|
| **Total rooms in branch 1** | **231** |
| Rooms currently Occupied (with active guests — normal) | 120 |
| Rooms Available / Cleaned / Uncleaned (fair game for kiosk/roomboy) | 111 |

### Rooms actually blocked if guards re-enable today

| Block location | Count | Which rooms |
|----------------|-------|-------------|
| **Kiosk (Available + unresolved record)** | **3** | Rooms 152, 165, 210 |
| **Roomboy (Uncleaned + unresolved record)** | **1** | Room 139 |
| **Total blocked** | **4** | — |

### Rooms NOT affected by the block

| Category | Count | Reason |
|----------|-------|--------|
| Occupied with active guests | 120 | Already hidden by status, block doesn't change anything |
| Available/Cleaned/Uncleaned without unresolved records | 107 | No drift, block doesn't fire |

### The headline number

**Out of 231 rooms, only 4 (1.7%) would be temporarily stuck if we re-enable the guards.**

| Visual breakdown | |
|------------------|--|
| 🟢 Working normally | **227 rooms (98.3%)** |
| 🔴 Temporarily blocked | **4 rooms (1.7%)** |

These 4 rooms are the Category A ghosts (Nenitte, Jezz, Erhajev, Jeff Mallari). The other 5 Category A ghosts are on rooms that currently show as Occupied, so the block wouldn't fire on them — but WILL fire when those occupants leave and try to cycle through.

### Why the 24 Category B records don't cause blocks now

All 24 late-checkout records are on rooms with status = Occupied. Kiosk doesn't show Occupied rooms. Roomboy doesn't show Occupied rooms. So the block has nothing to fire on. These 24 will only cause problems later IF frontdesk doesn't process their checkout before the room cycles to another guest.

---

## Why the ghosts happened (Category A only)

### Simple explanation

**The system only checked room AVAILABILITY, not whether the previous guest had CHECKED OUT.**

Before accepting a new guest, the old code only asked ONE question:
> *"Is this room's status `Available` or `Cleaned`?"*

It never asked the second question:
> *"Has the previous guest's check-in record been properly closed?"*

So if a room got marked "Available" for any reason while the previous guest's record was still open, the next guest could walk in and create a second record on the same room — producing a ghost.

### Why the two signals can disagree

The system has two separate signals that should always match:

| Signal A: `rooms.status` | Signal B: `checkin_details.is_check_out` |
|---|---|
| Available / Occupied / Uncleaned / Cleaned | 0 = still checked in / 1 = checked out |
| Controlled by: roomboy, admin, frontdesk | Controlled by: frontdesk only |

### Who causes the drift

Three different actors can accidentally create a ghost:

**1. The guest (walks out silently)**
Guest physically leaves without stopping at frontdesk. No checkout processed. Signal B stays at 0. Room status (Signal A) doesn't change — but the guest is gone.

**2. The roomboy (finish cleaning too early)**
When roomboy clicks "Finish Cleaning" on a room, the system flips `rooms.status` from `Uncleaned` to `Available`. The old code did this flip WITHOUT checking if the previous guest's record was still open. So if a guest walked out and somehow the room got to "Uncleaned" (even though no proper checkout happened), the roomboy's normal cleaning action could flip the room to Available while Signal B was still 0 — creating the condition for a ghost.

**3. Admin / manual status change**
Any code path or admin action that flips `rooms.status = 'Available'` without checking Signal B can create the drift.

The old code trusted Signal A alone. The proper check is: **both signals must agree before a room can be reused.**

### Realistic scenario — the Vee Meelita case

Real incident that happened on 2026-04-22, documented in the bug report:

| Time | Event | Signal A (room status) | Signal B (Vee's is_check_out) |
|------|-------|------------------------|-------------------------------|
| **04-22 5:35 PM** | Vee checks into Room 68 via kiosk | Occupied | 0 (checked in) |
| Next morning | Vee walks out silently — no checkout | Occupied | 0 (still open) |
| Some point | Room flipped to "Uncleaned" (maybe admin, maybe frontdesk assumed she left) | Uncleaned | 0 (still open — drift begins!) |
| Later | Roomboy cleans Room 68, clicks "Finish Cleaning" | **Available** | 0 (still open — Signal B and A are now inconsistent) |
| **04-22 8:58 PM** | Han arrives at kiosk, picks Room 68 (system sees status=Available) | Occupied (for Han) | Vee's record still 0 |
| **04-23 8:44 AM** | Han checks out normally | Uncleaned | Vee's still 0 |
| **04-23 2:32 PM** | Haron checks into Room 68 | Occupied | Vee's still 0 |
| **04-23 4:48 PM** | Haron checks out normally | Uncleaned | Vee's still 0 |
| **04-23 7:36 PM** | Larry checks into Room 68 | Occupied | Vee's still 0 |
| **04-23 8:23 PM** | Developer manually cleans Vee's record via `php artisan tinker` | — | 1 (finally closed) |

**For 26 hours and 48 minutes, Vee's record showed her as "checked in" while 3 other guests cycled through the same room.** Her ₱555 deposit was stuck. No checkout report for her stay. Dashboard showed her as a current guest.

The kiosk and roomboy code both contributed. Neither asked "is the previous guest's record still open?" before letting their action proceed.

Documented in detail: `docs/bugs/2026-04-23-ghost-checkin-races-room-reuse.md`

---

## What was done today (2026-04-24)

### Attempt 1 — Added guards
- Kiosk blocks check-in if room has unresolved previous guest
- Roomboy blocks Finish Cleaning if room has unresolved previous guest
- Shows error with old guest name

### Result — guards worked too well
- The 9 ghost records were blocking 4 rooms from being used (152, 165, 210, 139)
- Guests couldn't check in
- Operations impacted

### Attempt 2 — Temporarily disabled guards
- Guard code commented out with `TEMPORARILY DISABLED 2026-04-24` markers
- Tests marked as skipped
- Rooms usable again

**Current state:** no blocking — the ghost bug pattern can occur again until guards are re-enabled.

---

## Suggested solutions (for discussion)

### Option A — Manual cleanup by frontdesk
- Frontdesk processes checkout for each of the 9 ghosts one by one using existing QR code / scan system
- Decides deposit refund per case
- **Pro:** proper accounting, documented per case
- **Con:** takes time, requires frontdesk involvement

### Option B — Bulk SQL cleanup
- One-time SQL statement closes all 9 ghost records
- Deposits silently forfeited to hotel
- **Pro:** 30 seconds of developer time
- **Con:** no audit trail, guests could dispute later

### Option C — Small admin page (recommended)
- Developer builds a small admin-only page listing all unresolved records
- Frontdesk clicks "Resolve" on each, with deposit decision
- Proper activity log entries created
- Works for both ghosts AND future late-checkout cases
- **Pro:** audit trail, reusable tool, clean accounting
- **Con:** 1–2 hours of development work

### Option D — Do nothing
- Leave all 33 records as-is
- Guards stay disabled forever
- **Pro:** no effort
- **Con:** reports stay wrong, bug repeats, money in limbo

---

## Recommendation

**Option C (admin page).** It:
- Handles the 9 existing ghosts with proper accounting
- Gives frontdesk the tool to handle similar cases in the future (both ghosts and late checkouts)
- Unblocks re-enabling the guards
- Takes modest development effort

**Combined plan:**
1. Build small "Unresolved Records" admin page (Option C)
2. Frontdesk resolves the 9 ghosts via that page (~30 min of work)
3. The 24 late checkouts resolve themselves through normal operation
4. Re-enable the guards (5 min — uncomment 3 code blocks)
5. System now prevents new ghosts AND has a tool to resolve them

---

## Financial summary for the meeting

| Metric | Category A (Ghosts) | Category B (Late today) | Combined |
|--------|---------------------|-------------------------|----------|
| Deposits held (refund or forfeit decision) | ₱2,208 | ₱8,841 | ₱11,049 |
| Revenue recorded from these stays | ₱5,808 | ₱12,000 | ₱17,808 |
| Guards currently blocking ops | 4 rooms | 0 rooms | 4 rooms |

---

## Decisions needed from leadership

1. **For the 9 ghosts**: Option A, B, C, or D?
2. **Deposit policy for ghosts**: refund, forfeit, or case-by-case review?
3. **For the 24 late checkouts**: let them resolve via normal flow, or add a policy for auto-extend / auto-charge overdue stays?
4. **Timeline for re-enabling guards**: after option is implemented? After POS work? Specific date?
5. **Prevention going forward**: build the "Unresolved Records" admin page, or handle manually each time?

---

## Related files

- Bug analysis: `docs/bugs/2026-04-23-ghost-checkin-races-room-reuse.md`
- Manual repair reference: `docs/data-repairs/2026-04-23-vee-meelita-ghost-checkout.md`
- Tests (currently skipped): `tests/Feature/GhostCheckin/GhostCheckinGuardTest.php`
- Code markers: search for `TEMPORARILY DISABLED 2026-04-24`
- Data file: `documentation/unresolved-checkins-data.csv`

---

*Report generated from production database snapshot imported locally on 2026-04-24. Numbers may shift if records are added/resolved before the meeting.*
