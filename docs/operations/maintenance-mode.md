# Runbook: Putting HOMI into Maintenance Mode

**Audience:** Developers / sysadmins deploying updates to HOMI
**Goal:** Take the site offline for everyone except yourself, deploy your changes, then bring it back up
**Time required:** 30 seconds to take down, 30 seconds to bring back up

---

## What this does

Running `php artisan down` puts Laravel into **maintenance mode**:

- Every visitor sees the HOMI-branded 503 page (`resources/views/errors/503.blade.php`)
- All routes — including API endpoints — return HTTP 503
- The site stays in this state **indefinitely** until you run `php artisan up`
- There's no time limit, no auto-recovery — it stays down until you bring it back

The `--secret` flag gives you a **bypass URL** so you can keep working on the live site while everyone else is locked out.

---

## When to use it

| Use case | Use maintenance mode? |
|----------|----------------------|
| Running migrations that change data structure | ✅ Yes |
| Deploying a release with breaking schema changes | ✅ Yes |
| Importing a fresh DB backup over the live DB | ✅ Yes |
| Long-running data repair scripts | ✅ Yes |
| Hot-fix of a single Blade file or asset | ❌ No, just deploy directly |
| Quick config tweak (`.env`) | ❌ No |

---

## Step-by-step

### 1. Take the site down

```bash
php artisan down --render="errors::503" --secret="my-bypass-key-2026"
```

**Flag breakdown:**

| Flag | Purpose |
|------|---------|
| `--render="errors::503"` | Use the HOMI-branded 503 page instead of Laravel's default |
| `--secret="my-bypass-key-2026"` | Generate a magic URL that bypasses maintenance for whoever has it |
| `--refresh=30` *(optional)* | Add `Retry-After: 30` HTTP header so browsers auto-refresh every 30s |

**Pick your own secret.** Use anything you'll remember — but in production make it unguessable (e.g. a UUID):

```bash
php artisan down --render="errors::503" --secret="$(uuidgen)"
```

After running, every visitor to `http://hotelv2.test` sees the maintenance page. Including you, until you do step 2.

---

### 2. Bypass the maintenance for yourself

Open your browser and visit your secret URL:

```
http://hotelv2.test/my-bypass-key-2026
```

What happens:

1. Laravel sees the secret in the URL path
2. Sets a maintenance bypass cookie (`laravel_maintenance`) on your browser
3. Redirects you to `http://hotelv2.test`
4. From now on, your browser sees the live site normally — everyone else still sees the 503

The cookie is browser-specific. It persists across page reloads but disappears when you clear cookies or close an incognito session.

---

### 3. Test that it's working

Open two windows side by side:

| Window | URL | Expected |
|--------|-----|----------|
| Your normal browser (cookie set) | `http://hotelv2.test` | ✅ Live site, works as usual |
| Incognito window (no cookie) | `http://hotelv2.test` | 🔧 HOMI 503 maintenance page |

This confirms the bypass works. You can keep testing any URL (`/admin`, `/frontdesk`, `/api/...`) — the bypassed window has full access; the incognito window is locked out everywhere.

---

### 4. Do your work

Common deploy steps while in maintenance mode:

```bash
# pull latest code
git pull

# install any new dependencies
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# run migrations
php artisan migrate --force

# clear and re-cache
php artisan config:clear
php artisan view:clear
php artisan cache:clear
php artisan route:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

You can verify each step by refreshing `http://hotelv2.test` in your bypassed browser.

---

### 5. Bring the site back up

```bash
php artisan up
```

The site is now live for everyone. The maintenance mode flag file in `storage/framework/down` is removed.

Verify by refreshing the incognito window — it should now show the live site instead of the 503.

---

## Useful flag combinations

### Keep an entire team / IP unblocked
```bash
php artisan down \
  --render="errors::503" \
  --secret="my-key" \
  --allow=127.0.0.1 \
  --allow=192.168.1.0/24
```

### Lost the secret key?

Just reset it — you don't have to bring the site up first:

```bash
php artisan up
php artisan down --render="errors::503" --secret="new-key"
```

---

## Common pitfalls

| Pitfall | Fix |
|---------|-----|
| **"Why does my browser still show 503?"** | The bypass cookie only sets after visiting `/your-secret-key`. Visit it once. |
| **"It worked yesterday but the bypass URL is 404 today"** | Cookie is gone but secret is unchanged — visit the URL again to re-set the cookie. |
| **"Migrations are failing during maintenance"** | Always wrap migrations in `--force` for non-interactive: `php artisan migrate --force` |
| **"I forgot to run `php artisan up`"** | The site stays down. Run `php artisan up` immediately. No data loss — just temporary inaccessibility. |
| **"Asset URLs return 503"** | Static files in `/public` may also 503 depending on web server config. Test asset paths in incognito to verify. The HOMI 503 page is self-contained (no asset deps) for this reason. |
| **"My bypass cookie disappears on every reload"** | Browser is set to clear cookies on close. Use a non-incognito window. |

---

## What gets shown to visitors

The HOMI-themed maintenance page (`resources/views/errors/503.blade.php`) shows:

- HOMI logo with rotating brand-blue rings
- "System is Updating" headline (Alkatra font, brand gradient)
- "We're rolling out improvements..." subtitle
- Animated progress bar
- "Deploying updates..." live indicator
- Auto-refreshes every 30 seconds (via `<meta http-equiv="refresh">`)

It's fully self-contained — no Tailwind, no Vite assets — so it loads even when your asset pipeline is mid-deploy.

---

## Quick-reference cheat sheet

```bash
# Take down with secret bypass
php artisan down --render="errors::503" --secret="KEY_HERE"

# Visit in browser to bypass
http://hotelv2.test/KEY_HERE

# Bring back up
php artisan up

# Reset the secret key (without bringing up)
php artisan up && php artisan down --render="errors::503" --secret="NEW_KEY"

# Status check (Laravel doesn't have a direct command, but you can curl)
curl -I http://hotelv2.test
# → HTTP/1.1 503 Service Unavailable  (down)
# → HTTP/1.1 200 OK                    (up)
```

---

## Related files

- `resources/views/errors/503.blade.php` — the HOMI-branded maintenance page
- `resources/views/errors/minimal.blade.php` — shared layout for 401, 403, 419, 429, 500
- `resources/views/errors/404.blade.php` — branded "page not found" page
- `storage/framework/down` — the flag file Laravel creates when in maintenance (don't edit manually)

---

*Last reviewed: 2026-04-27*
