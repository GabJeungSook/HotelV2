-- =====================================================================
-- RECOVERY SCRIPT
-- Incident: 2026-04-28 "Fix All Unresolved" force-closed 20 active rooms
--           plus 1 ambiguous case (room #71 / cid=12086) that needs
--           operator confirmation before recovery.
-- Source of truth: homi_app_producoot_lastest_now.sql (taken 2026-04-27 23:17:30)
-- Bug doc:    docs/bugs/2026-04-28-fixall-unresolved-flips-active-extension-checkins.md
-- Repair doc: docs/data-repairs/2026-04-28-restore-10-flipped-rooms-after-fixall.md
--
-- HOW TO USE
-- 1. First run on `backup-prod-before-up` MySQL DB to verify pre-state matches.
-- 2. Then run on `hotelv2` (production). All UPDATEs are idempotent-guarded.
-- 3. Inside one transaction with a built-in count check before COMMIT.
-- 4. Room #71 (cid=12086) is in a SEPARATE step — review with frontdesk first.
-- =====================================================================

USE `hotelv2`;

-- ---------------------------------------------------------------------
-- STEP 1 — Pre-flight verification on production (read-only)
-- Expect: all 20 rows currently is_check_out=1, updated_at='2026-04-27 23:19:54'
-- ---------------------------------------------------------------------

SELECT id, room_id, check_in_at, check_out_at, is_check_out, updated_at
FROM checkin_details
WHERE id IN (
    10854, 11018, 11263, 11297, 11923, 11924,
    12051, 12308, 12318, 12319, 12333, 12341,
    12347, 12356, 12387, 12388, 12393, 12395,
    12454, 12461
)
ORDER BY id;

-- Expect: all 20 rooms currently status='Available' (or 'Cleaned'/'Available' for some),
-- updated_at on or near '2026-04-27 23:20:03'
SELECT id, number, status, updated_at FROM rooms
WHERE id IN (
      4,   5,   6,  11,  40,  41,  43,  45,  46,  48,
     57,  75,  83, 106, 121, 126, 139, 145, 148, 216
)
ORDER BY id;

-- ⚠ IF any row's updated_at is NEWER than 2026-04-28 00:49,
-- a frontdesk has already started repairing manually. STOP and investigate.

-- ---------------------------------------------------------------------
-- STEP 2 — Apply recovery in a single transaction (20 confirmed rooms)
-- ---------------------------------------------------------------------

START TRANSACTION;

-- 2a. Restore the 20 force-closed check-in records.
--     Values copied directly from the BEFORE backup.
--     Each has an `AND is_check_out = 1` guard so re-runs are no-ops.

-- Original 10 (rooms #4, #11, #51, #63, #74, #92, #151, #166, #205, #215)
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 16:49:41', updated_at = '2026-04-27 17:47:03' WHERE id = 10854 AND is_check_out = 1; -- room #51
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 10:30:23', updated_at = '2026-04-27 21:05:42' WHERE id = 11263 AND is_check_out = 1; -- room #151
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 14:51:24', updated_at = '2026-04-27 21:16:05' WHERE id = 11297 AND is_check_out = 1; -- room #215
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 09:09:09', updated_at = '2026-04-27 08:14:45' WHERE id = 11923 AND is_check_out = 1; -- room #63
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 09:09:13', updated_at = '2026-04-27 08:13:44' WHERE id = 11924 AND is_check_out = 1; -- room #74
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 17:42:52', updated_at = '2026-04-27 17:10:58' WHERE id = 12051 AND is_check_out = 1; -- room #4
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 14:30:53', updated_at = '2026-04-27 08:47:42' WHERE id = 12308 AND is_check_out = 1; -- room #205
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 17:56:55', updated_at = '2026-04-26 17:56:55' WHERE id = 12356 AND is_check_out = 1; -- room #92
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 07:21:35', updated_at = '2026-04-27 17:58:02' WHERE id = 12388 AND is_check_out = 1; -- room #166
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 10:10:44', updated_at = '2026-04-27 22:28:25' WHERE id = 12454 AND is_check_out = 1; -- room #11

-- Additional 10 (rooms #286, #52, #6, #5, #65, #62, #100, #60, #171, #211)
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 00:52:59', updated_at = '2026-04-27 12:17:24' WHERE id = 11018 AND is_check_out = 1; -- room #286
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 14:57:22', updated_at = '2026-04-26 14:57:22' WHERE id = 12318 AND is_check_out = 1; -- room #52
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 14:57:55', updated_at = '2026-04-27 11:27:35' WHERE id = 12319 AND is_check_out = 1; -- room #6
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 03:57:02', updated_at = '2026-04-27 15:17:48' WHERE id = 12333 AND is_check_out = 1; -- room #5
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 16:56:26', updated_at = '2026-04-27 16:29:04' WHERE id = 12341 AND is_check_out = 1; -- room #65
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 05:36:31', updated_at = '2026-04-27 17:10:46' WHERE id = 12347 AND is_check_out = 1; -- room #62
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 07:21:20', updated_at = '2026-04-27 18:48:45' WHERE id = 12387 AND is_check_out = 1; -- room #100
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 07:34:57', updated_at = '2026-04-27 17:37:07' WHERE id = 12393 AND is_check_out = 1; -- room #60
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 19:35:20', updated_at = '2026-04-27 18:59:01' WHERE id = 12395 AND is_check_out = 1; -- room #171
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-28 10:32:35', updated_at = '2026-04-27 21:58:40' WHERE id = 12461 AND is_check_out = 1; -- room #211

-- 2b. Restore room status. Guarded so we only flip rooms still in the corrupted state.

UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id =   4 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id =   5 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id =   6 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id =  11 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id =  40 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id =  41 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id =  43 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id =  45 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id =  46 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id =  48 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id =  57 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id =  75 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id =  83 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id = 106 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id = 121 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id = 126 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id = 139 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id = 145 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id = 148 AND status IN ('Available', 'Cleaned');
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id = 216 AND status IN ('Available', 'Cleaned');

-- ---------------------------------------------------------------------
-- STEP 3 — Verify counts BEFORE committing
-- Both must return 20. If not, run ROLLBACK.
-- ---------------------------------------------------------------------

SELECT 'checkin_details restored' AS check_label, COUNT(*) AS count_should_be_20
FROM checkin_details
WHERE id IN (
    10854, 11018, 11263, 11297, 11923, 11924,
    12051, 12308, 12318, 12319, 12333, 12341,
    12347, 12356, 12387, 12388, 12393, 12395,
    12454, 12461
)
  AND is_check_out = 0;

SELECT 'rooms restored' AS check_label, COUNT(*) AS count_should_be_20
FROM rooms
WHERE id IN (
      4,   5,   6,  11,  40,  41,  43,  45,  46,  48,
     57,  75,  83, 106, 121, 126, 139, 145, 148, 216
)
  AND status = 'Occupied';

-- ---------------------------------------------------------------------
-- STEP 4 — Commit if both counts are 20. Otherwise ROLLBACK.
-- ---------------------------------------------------------------------

COMMIT;
-- ROLLBACK;  -- uncomment this and comment out COMMIT if either check failed

-- ---------------------------------------------------------------------
-- STEP 5 — Post-patch sanity: spot-check all 20 records end-to-end
-- ---------------------------------------------------------------------

SELECT cd.id AS cid, cd.is_check_out, cd.check_in_at, cd.check_out_at,
       r.id AS room_id, r.number AS room_number, r.status,
       g.name AS guest_name, cd.total_deposit
FROM checkin_details cd
JOIN rooms r ON r.id = cd.room_id
JOIN guests g ON g.id = cd.guest_id
WHERE cd.id IN (
    10854, 11018, 11263, 11297, 11923, 11924,
    12051, 12308, 12318, 12319, 12333, 12341,
    12347, 12356, 12387, 12388, 12393, 12395,
    12454, 12461
)
ORDER BY cd.id;

-- ---------------------------------------------------------------------
-- STEP 6 — AMBIGUOUS CASE: room #71 (cid=12086)
-- DO NOT run this block until frontdesk has confirmed in person.
-- This guest's planned check_out_at was 2026-04-27 19:07 — about 4 hours
-- BEFORE the bug fired. They could be:
--   (a) a real guest who overstayed past their checkout time
--   (b) a real abandoned ghost (frontdesk never closed them)
-- Decide based on physical key-card slot or guest contact.
-- ---------------------------------------------------------------------

-- IF frontdesk confirms guest is still inside / room is in use, run:
/*
START TRANSACTION;
UPDATE checkin_details SET is_check_out = 0, check_out_at = '2026-04-27 19:07:42', updated_at = '2026-04-27 06:32:53' WHERE id = 12086 AND is_check_out = 1;
UPDATE rooms SET status = 'Occupied', updated_at = NOW() WHERE id = 54 AND status IN ('Available', 'Cleaned');
COMMIT;
*/

-- IF frontdesk confirms guest left (real ghost), do nothing — the existing
-- force-close was correct. Just mark guest's deposit handling per shift policy.

-- Done. Frontdesk should refresh Room Monitoring and verify each room now
-- shows the correct guest. Watch storage/logs/laravel.log for 5 minutes.
