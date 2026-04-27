# Deploying `feature/temp-disable-supervisor` to Staging

Step-by-step runbook for deploying the supervisor-disabled branch to the staging server with **zero downtime exposure** for frontdesk users via the maintenance-mode + secret-bypass workflow.

---

## Server context

| | |
|---|---|
| Server prompt | `root@HomiApp:/var/www/HotelV2#` |
| Project path | `/var/www/HotelV2` |
| Branch to deploy | `feature/temp-disable-supervisor` |
| Migrations needed | **None** (this branch only comments code) |
| New dependencies | **None** (no composer.json / package.json changes) |
| Tailwind classes | **Yes — `npm run build` required** (new red/purple/yellow shades for over-time badges, ghost room indicators, etc.) |

---

## What this deploy does

- Puts site in maintenance mode with HOMI-themed 503 page (so frontdesks/customers can't act on a half-deployed app)
- Generates a secret bypass URL for **you** to verify everything before bringing the site back up
- Switches code from current branch to `feature/temp-disable-supervisor`
- Pulls latest commits, clears caches, rebuilds frontend assets
- Restarts PHP-FPM
- You verify on the bypass URL (live but only for you)
- Brings the site back up for everyone

---

## Pre-deploy checklist

Before you SSH in, have these ready:

- [ ] You know which branch staging is currently on (so you can revert if needed)
- [ ] You have the branch authorization PIN for testing transfer/cancel after deploy (Branch 1 = `30826`, Branch 2 = `12345`)
- [ ] You have a frontdesk login ready: `lecias@gmail.com` / `password` (or any active frontdesk account)
- [ ] You have access to a different browser / incognito window to verify the maintenance page is showing for non-bypass users

---

## Step 1 — SSH into staging

```bash
ssh root@HomiApp
```

You should now see:

```
root@HomiApp:~#
```

Navigate to the project:

```bash
cd /var/www/HotelV2
```

Prompt becomes:

```
root@HomiApp:/var/www/HotelV2#
```

---

## Step 2 — Capture the current state (so you can revert if needed)

```bash
root@HomiApp:/var/www/HotelV2# git branch --show-current
```

Note this down — that's the branch you'd revert to if things go wrong.

```bash
root@HomiApp:/var/www/HotelV2# git log -1 --oneline
```

Note this commit hash too — that's your rollback target.

---

## Step 3 — Take the site down with a secret bypass key

Pick a secret key. Keep it simple (you'll type it into a browser URL).

```bash
root@HomiApp:/var/www/HotelV2# php artisan down --render="errors::503" --secret="deploy-2026-04-27"
```

Expected output:

```
Application is now in maintenance mode.
```

**The site is now down for everyone.** Anyone visiting `https://your-staging-url.com` sees the HOMI-themed 503 maintenance page.

### Verify the maintenance page is showing (BEFORE proceeding)

Open an **incognito window** in your local browser and visit your staging URL. You should see:
- Animated polygon background
- HOMI logo with rotating brand-blue rings
- "System is Updating" headline
- Animated progress bar
- "Deploying updates" pulsing indicator

If you see this, maintenance mode is working. Close the incognito window.

### Get yourself bypass access

In your **regular browser** (not incognito), visit:

```
https://your-staging-url.com/deploy-2026-04-27
```

(Replace with your actual staging URL + the secret key from step 3.)

You'll be redirected to home. A `laravel_maintenance` cookie is now set in your browser. From now on, you can browse the staging site normally while everyone else still sees the 503.

---

## Step 4 — Fetch all branches from origin

```bash
root@HomiApp:/var/www/HotelV2# git fetch origin
```

Expected output (something like):

```
remote: Enumerating objects: 47, done.
remote: Counting objects: 100% (47/47), done.
...
From https://github.com/GabJeungSook/HotelV2
   abc1234..def5678  feature/temp-disable-supervisor -> origin/feature/temp-disable-supervisor
```

---

## Step 5 — Switch to the feature branch

```bash
root@HomiApp:/var/www/HotelV2# git checkout feature/temp-disable-supervisor
```

If you get a warning like *"Your local changes would be overwritten by checkout"*:

```bash
root@HomiApp:/var/www/HotelV2# git stash push -m "staging-pre-deploy-$(date +%Y%m%d_%H%M%S)"
root@HomiApp:/var/www/HotelV2# git checkout feature/temp-disable-supervisor
```

(The stashed changes are saved; you can `git stash list` to see them later.)

Expected output:

```
Branch 'feature/temp-disable-supervisor' set up to track remote branch 'feature/temp-disable-supervisor' from 'origin'.
Switched to a new branch 'feature/temp-disable-supervisor'
```

(Or `Switched to branch 'feature/temp-disable-supervisor'` if it already existed.)

---

## Step 6 — Pull the latest commits

```bash
root@HomiApp:/var/www/HotelV2# git pull origin feature/temp-disable-supervisor
```

Should report `Already up to date.` if you fetched in step 4. Otherwise it'll fast-forward.

### Verify you're on the right commit

```bash
root@HomiApp:/var/www/HotelV2# git log -7 --oneline
```

Expected to include these commits (or newer):

```
305eb61 fix: hide Supervisor role from admin user create/edit forms
9e5a11e docs: add staging branch-switch + pull guide
25ce668 fix: null-safe total_deduction read in GuestTransaction render
50c8109 fix: restore authorization-cancel modal removed by supervisor module
1db6fa5 feat: temporarily disable supervisor approval module
83ca311 chore: keep over-time queries as commented-out for quick re-enable
41b8b00 revert: remove OVER TIME sidebar section from room monitoring
```

---

## Step 7 — Verify migrations are up to date (no new ones expected)

```bash
root@HomiApp:/var/www/HotelV2# php artisan migrate:status | tail -10
```

Every line should say `Ran` in the rightmost column. If everything is `Ran`, you're good — **DO NOT run `php artisan migrate`** for this branch (no new migrations).

If for some reason there are pending migrations (shouldn't happen):

```bash
root@HomiApp:/var/www/HotelV2# php artisan migrate --force
```

---

## Step 8 — Clear all Laravel caches

```bash
root@HomiApp:/var/www/HotelV2# php artisan view:clear
root@HomiApp:/var/www/HotelV2# php artisan config:clear
root@HomiApp:/var/www/HotelV2# php artisan cache:clear
root@HomiApp:/var/www/HotelV2# php artisan route:clear
```

If using Redis for cache/sessions:

```bash
root@HomiApp:/var/www/HotelV2# redis-cli FLUSHALL
```

Expected output for each: `Application cache cleared!`, `Configuration cache cleared!`, etc.

---

## Step 9 — Rebuild frontend assets (Tailwind)

```bash
root@HomiApp:/var/www/HotelV2# npm ci
root@HomiApp:/var/www/HotelV2# npm run build
```

Wait for both to finish. Watch for errors. The build outputs new files in `public/build/`.

---

## Step 10 — Restart PHP-FPM

```bash
root@HomiApp:/var/www/HotelV2# sudo systemctl restart php8.2-fpm
```

(Adjust version if your server has `php8.1-fpm` or similar.)

Verify it's running:

```bash
root@HomiApp:/var/www/HotelV2# sudo systemctl status php8.2-fpm | head -5
```

Should show `active (running)`.

---

## Step 11 — Verify the deploy in your bypass browser

Your regular browser (with the `laravel_maintenance` cookie) can still access the site even though it's "down" for everyone else. Open it and test:

### Test 1 — Login flow
- Go to staging URL → login page should load (HOMI branded)
- Login: `lecias@gmail.com` / `password`
- Should land on Frontdesk dashboard

### Test 2 — Frontdesk monitoring
- Navigate to **Room Monitoring**
- Confirm the page loads
- Confirm the right sidebar shows ONLY two sections: **CHECK-IN GUEST** and **CHECKOUT GUEST** (no OVER TIME section)
- Confirm the left sidebar does NOT show "Override Requests" menu item

### Test 3 — Transfer Room
- Click an Occupied room → **Manage**
- Click **Transfer Room**
- Pick a target room + reason
- Click **Save & Pay**
- Should show "Are you sure?" confirmation dialog (NO supervisor selection)
- Click Confirm → transfer completes

### Test 4 — Cancel transaction
- Click an Occupied room → **Manage**
- Click **Cancel** action (wherever that lives in your UI)
- Deposit Summary modal appears
- Click **PROCEED**
- **AUTHORIZATION CODE modal should appear** (this was the bug we fixed)
- Enter PIN: `30826` (Branch 1) or `12345` (Branch 2)
- Click PROCEED
- "Are you sure you want to cancel?" → Click "Yes, cancel it"
- Should succeed and redirect to Room Monitoring (no error)

### Test 5 — Supervisor login
- Logout
- Login as a supervisor user (if you have one in DB)
- Should land on **HOMI-themed "Module on Hold" page** with logout button
- Click logout button → returns to login page

### Test 6 — Admin user creation
- Login as admin
- Go to User management
- Create new user form → role dropdown should NOT show "Supervisor" option
- Edit existing user form → same: no "Supervisor" option

### Test 7 — BackOffice reports
- Login as back_office user
- Reports → **"Supervisor's Report"** should NOT appear in the list
- Archives → same

If all 7 tests pass, the deploy is good.

---

## Step 12 — Bring the site back up

```bash
root@HomiApp:/var/www/HotelV2# php artisan up
```

Expected output:

```
Application is now live.
```

The site is now live for everyone. The HOMI 503 page is gone.

### Verify in incognito

Open an incognito window → visit your staging URL → should now load the live site (login page or whatever the public landing is).

---

## Step 13 — Communicate to staff

Tell the frontdesk team:

> "Staging has been updated. A few changes:
> 1. Transfer Room and Cancel Transaction now use the **authorization code** (PIN) — same as before the supervisor module. Branch 1 PIN = 30826.
> 2. Supervisor approval workflow is paused — no waiting for supervisor approval.
> 3. The room lists in the right sidebar (Check-in / Checkout Guest) now refresh every 10 seconds (was 1 second) — no functional change, just slightly less server load.
> 4. New ghost room cleanup tool available on Admin → Ghost Rooms (and there's a Fix button on individual rows in monitoring)."

---

## Rollback (if something is wrong)

If you spot a problem in Step 11 (during your bypass-browser verification), you can roll back BEFORE bringing the site up:

### Quick rollback to previous branch

```bash
root@HomiApp:/var/www/HotelV2# git checkout <previous-branch-name-from-step-2>
root@HomiApp:/var/www/HotelV2# php artisan view:clear
root@HomiApp:/var/www/HotelV2# php artisan config:clear
root@HomiApp:/var/www/HotelV2# php artisan cache:clear
root@HomiApp:/var/www/HotelV2# php artisan route:clear
root@HomiApp:/var/www/HotelV2# redis-cli FLUSHALL
root@HomiApp:/var/www/HotelV2# npm ci && npm run build
root@HomiApp:/var/www/HotelV2# sudo systemctl restart php8.2-fpm
```

Then re-test in your bypass browser. When good:

```bash
root@HomiApp:/var/www/HotelV2# php artisan up
```

---

## Troubleshooting

### "I lost my secret key — site stuck in maintenance mode for me too"

```bash
root@HomiApp:/var/www/HotelV2# php artisan up
root@HomiApp:/var/www/HotelV2# php artisan down --render="errors::503" --secret="new-key-2026"
```

Then visit `https://your-staging-url.com/new-key-2026` again to re-set the cookie.

### "500 error after deploy"

```bash
root@HomiApp:/var/www/HotelV2# tail -100 storage/logs/laravel.log
```

99% of the time, fix is:

```bash
root@HomiApp:/var/www/HotelV2# php artisan view:clear
root@HomiApp:/var/www/HotelV2# php artisan config:clear
root@HomiApp:/var/www/HotelV2# sudo systemctl restart php8.2-fpm
```

### "Tailwind classes look broken"

Frontend wasn't rebuilt:

```bash
root@HomiApp:/var/www/HotelV2# npm ci
root@HomiApp:/var/www/HotelV2# npm run build
```

### "Can't checkout — local changes blocking"

```bash
root@HomiApp:/var/www/HotelV2# git stash push -m "blocking-changes-$(date +%s)"
root@HomiApp:/var/www/HotelV2# git checkout feature/temp-disable-supervisor
```

(Stashed changes preserved — `git stash list` shows them, `git stash pop` brings them back.)

### "redis-cli FLUSHALL says permission denied or auth"

Check Redis password:

```bash
root@HomiApp:/var/www/HotelV2# grep "^requirepass" /etc/redis/redis.conf
```

If there's a password:

```bash
root@HomiApp:/var/www/HotelV2# redis-cli -a "<password>" FLUSHALL
```

---

## Quick-reference cheat sheet (one-liner deployment)

For repeat deploys after the first time, you can chain everything:

```bash
ssh root@HomiApp
cd /var/www/HotelV2
php artisan down --render="errors::503" --secret="deploy-$(date +%Y%m%d)" && \
git fetch origin && \
git checkout feature/temp-disable-supervisor && \
git pull origin feature/temp-disable-supervisor && \
php artisan view:clear && \
php artisan config:clear && \
php artisan cache:clear && \
php artisan route:clear && \
redis-cli FLUSHALL && \
npm ci && npm run build && \
sudo systemctl restart php8.2-fpm
# (visit https://staging.url/deploy-YYYYMMDD to bypass + verify)
# then:
php artisan up
```

---

## Post-deploy monitoring (first hour)

```bash
# Watch for errors in real-time
root@HomiApp:/var/www/HotelV2# tail -f storage/logs/laravel.log

# Check that PHP-FPM is healthy
root@HomiApp:/var/www/HotelV2# sudo systemctl status php8.2-fpm

# Check Redis (if used)
root@HomiApp:/var/www/HotelV2# redis-cli INFO memory | grep used_memory_human

# Check Nginx access log for 5xx errors
root@HomiApp:/var/www/HotelV2# tail -100 /var/log/nginx/access.log | awk '$9 ~ /^5/ {print}'
```

If you see any `[ERROR]` entries in `laravel.log` after the deploy, copy the message and investigate. Most issues at this stage are cache-related and clear with another `view:clear` + `config:clear` + restart.

---

## Going back to a different branch later

When you need to switch back to `future-updates` (full supervisor enabled) or `master`:

```bash
ssh root@HomiApp
cd /var/www/HotelV2
php artisan down --render="errors::503" --secret="rollback-2026"
git fetch origin
git checkout future-updates    # or master
git pull origin future-updates
php artisan migrate --force    # in case the target branch has migrations
php artisan view:clear && php artisan config:clear && php artisan cache:clear && php artisan route:clear
redis-cli FLUSHALL
npm ci && npm run build
sudo systemctl restart php8.2-fpm
# verify in bypass browser, then:
php artisan up
```

---

*Last updated: April 27, 2026*

*Companion docs:*
- *`documentation/staging-branch-switch-guide.md` — generic branch-switch reference*
- *`documentation/operations/maintenance-mode.md` — `php artisan down` flag deep-dive*
- *`documentation/production-deployment-checklist.md` — production deploy checklist (with Redis client install)*
