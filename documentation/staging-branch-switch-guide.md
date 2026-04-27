# Staging — Branch Switch & Pull Guide

How to deploy a feature branch (e.g. `feature/temp-disable-supervisor`) to staging, pull updates later, and switch back to a different branch when done.

---

## 0. Prerequisites

You're SSH'd into the staging server and inside the project root:

```bash
ssh root@HomiApp     # or your staging host
cd /var/www/HotelV2
```

Confirm where you are right now:

```bash
git branch --show-current
git log -1 --oneline
```

---

## 1. First-time switch to a feature branch

Use this when you've never checked out the branch on staging before.

### Step 1 — Save any local changes (rare, but safe)

```bash
git status
```

If there are local changes you don't want to lose:

```bash
git stash push -m "staging-local-tweaks-before-switch"
```

If there's nothing important, skip the stash.

### Step 2 — Fetch all branches from origin

```bash
git fetch origin
```

This pulls the latest list of branches without changing your working tree.

### Step 3 — Switch to the feature branch

```bash
git checkout feature/temp-disable-supervisor
```

If git complains it's already checked out elsewhere or has tracked changes, see "Troubleshooting" below.

### Step 4 — Verify you're on the right commit

```bash
git branch --show-current
# expected: feature/temp-disable-supervisor

git log -3 --oneline
# expected to include: 25ce668, 50c8109, 1db6fa5 (or newer)
```

### Step 5 — Install dependencies + build assets

Only needed if `composer.json` / `package.json` / Tailwind classes changed:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

### Step 6 — Migrate (if needed)

For `feature/temp-disable-supervisor`, **NO migrations** are needed — the branch only comments code. Verify with:

```bash
php artisan migrate:status | tail -10
```

If the rightmost column says "Ran" for every migration, you're good.

For OTHER branches that may have new migrations:

```bash
php artisan migrate --force
```

### Step 7 — Clear caches

Always clear after switching branches:

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
```

If using Redis:

```bash
redis-cli FLUSHALL
```

### Step 8 — Restart services

```bash
sudo systemctl restart php8.2-fpm
sudo systemctl reload nginx
```

### Step 9 — Smoke test

Open a browser, hit your staging URL, confirm:
- Login works
- Frontdesk transfer/cancel proceeds with PIN modal (no supervisor selection)
- Supervisor login → "Module on Hold" page

---

## 2. Pulling updates on the same branch

Use this when you've already switched to the branch and just want the latest commits.

```bash
cd /var/www/HotelV2

# Make sure you're on the right branch
git branch --show-current

# Pull only that branch's latest commits
git pull origin feature/temp-disable-supervisor

# Clear caches
php artisan view:clear
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# If migrations changed
php artisan migrate --force

# If PHP files changed (controllers, livewire components)
sudo systemctl restart php8.2-fpm

# If frontend assets changed
npm ci && npm run build
```

If `composer.json` or `package.json` changed:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

---

## 3. Switching BACK to a different branch (e.g. master or future-updates)

Use this when staging is on `feature/temp-disable-supervisor` and you want to go back to `future-updates` or `master`.

```bash
cd /var/www/HotelV2

# Save any local changes (just in case)
git status
git stash push -m "before-branch-switch" 2>/dev/null

# Fetch latest
git fetch origin

# Switch
git checkout future-updates       # or master, or whatever target

# Pull latest of that branch
git pull origin future-updates

# Run migrations (in case the new branch has migrations the old one didn't)
php artisan migrate --force

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Rebuild assets
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# Restart
sudo systemctl restart php8.2-fpm
sudo systemctl reload nginx
```

---

## 4. Quick one-liner deployment

For a known, ready-to-deploy branch:

```bash
cd /var/www/HotelV2 && \
git fetch origin && \
git checkout feature/temp-disable-supervisor && \
git pull origin feature/temp-disable-supervisor && \
php artisan migrate --force && \
php artisan view:clear && \
php artisan config:clear && \
php artisan cache:clear && \
php artisan route:clear && \
redis-cli FLUSHALL && \
npm ci && npm run build && \
sudo systemctl restart php8.2-fpm
```

---

## 5. Verifying it worked

```bash
# Current branch + latest commit
git branch --show-current
git log -1 --oneline

# Migration state
php artisan migrate:status | tail -10

# No error logs
tail -50 storage/logs/laravel.log

# Process restart took effect
sudo systemctl status php8.2-fpm | head -5
```

---

## 6. Troubleshooting

### "Your local changes would be overwritten by checkout"

Some tracked file on staging has been modified. Either keep them:

```bash
git stash push -m "save-local-edits"
git checkout feature/temp-disable-supervisor
git stash pop   # only if you want them back
```

Or discard them (only if you're sure they're not needed):

```bash
git checkout .   # discards modifications to tracked files
git checkout feature/temp-disable-supervisor
```

### "Pulling without specifying how to reconcile divergent branches"

Stage changes were committed on staging directly. Either:

```bash
# Option A: keep staging commits + merge in remote
git pull --no-rebase origin feature/temp-disable-supervisor

# Option B: throw away staging commits (DANGER)
git reset --hard origin/feature/temp-disable-supervisor
```

Use Option A unless you're 100% sure.

### App throws 500 errors after switch

```bash
tail -50 storage/logs/laravel.log
```

99% of the time the cure is:

```bash
php artisan view:clear
php artisan config:clear
sudo systemctl restart php8.2-fpm
```

If still broken, missing dependencies:

```bash
composer install --no-dev --optimize-autoloader
```

### Tailwind CSS classes look wrong / unstyled

Frontend wasn't rebuilt:

```bash
npm ci && npm run build
```

### Old session cookies cause weird behavior after switch

Some users may have cached logins. Worst case, flush sessions:

```bash
redis-cli FLUSHALL    # if using Redis sessions
# or for file sessions:
rm -rf storage/framework/sessions/*
```

---

## 7. Going back to production-ready branch

When the client decides to re-enable supervisor module (or you need master flow):

```bash
cd /var/www/HotelV2
git fetch origin
git checkout future-updates
git pull origin future-updates
php artisan migrate --force
php artisan view:clear && php artisan config:clear && php artisan cache:clear && php artisan route:clear
redis-cli FLUSHALL
npm ci && npm run build
sudo systemctl restart php8.2-fpm
```

---

## 8. Quick reference

```bash
# What branch am I on?
git branch --show-current

# What's the latest commit on the current branch?
git log -1 --oneline

# What's the latest on origin/feature/temp-disable-supervisor?
git log -1 origin/feature/temp-disable-supervisor --oneline

# What changed since last pull?
git log HEAD..origin/feature/temp-disable-supervisor --oneline

# Switch + pull in one go
git checkout feature/temp-disable-supervisor && git pull
```

---

*Last updated: April 27, 2026*
