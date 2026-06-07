# InfinityFree Troubleshooting Guide

Common issues when running BantayPurrPaws on InfinityFree shared hosting.

---

## Database connection failed

**Symptoms:** Blank page, JSON error "Server configuration error", or 503 page.

**Checks:**
1. `.env` exists in htdocs root (same folder as `index.php`).
2. `DB_HOST` is the **MySQL hostname** from the panel (e.g. `sql103.infinityfree.com`), NOT your website URL.
3. `DB_NAME`, `DB_USER`, `DB_PASSWORD` match the panel exactly.
4. Database was imported via `sql/infinityfree_import.sql`.
5. In phpMyAdmin, confirm tables like `users`, `login_attempts` exist.

**Debug (temporary only):** Set `APP_DEBUG=1`, reload, read error message, then set back to `0`.

---

## 500 Internal Server Error

**Common causes on InfinityFree:**

1. **Syntax error in `.htaccess`** — InfinityFree uses Apache; `<Directory>` blocks are not allowed in `.htaccess`. The project `.htaccess` avoids this.
2. **Missing PHP extension** — Required: `pdo_mysql`, `curl`, `json`, `mbstring`, `fileinfo`.
3. **`.env` missing** — Copy from `.env.example`.
4. **PHP version** — Requires PHP 8.0+. Check in control panel → PHP Settings.

Check error log: `logs/app.log` or InfinityFree panel → Error Log.

---

## Email not sending

**Symptoms:** "Could not send verification email", empty recipient in logs.

**Fix order:**

1. **Use Brevo API** (recommended) — set `MAIL_DRIVER=brevo`, `BREVO_API_KEY`, and verified `MAIL_FROM`.
2. **Verify sender** in Brevo dashboard — unverified senders are rejected.
3. **Check libsodium** — if emails in DB show as `hash:v1:` or empty, run login with correct email; app repairs plaintext when sodium unavailable.
4. **SMTP fallback** — InfinityFree often blocks port 25/587. Only use if your host allows it:

```env
MAIL_DRIVER=smtp
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USER=
SMTP_PASSWORD=
```

5. Test via: `deploy/verify.php?key=...&test_email=you@example.com`

---

## Login OTP / security flow fails

**Symptoms:** Stuck on "Check your email", number match fails, OTP invalid.

**Checks:**
1. Security tables imported — `login_attempts`, `security_events`, `otp_tokens` must exist.
2. OTP purpose `login_security` in enum — use `infinityfree_import.sql` (includes this).
3. Brevo sending works (see above).
4. User email in database must be deliverable — check `users.email` is not corrupted `hash:v1:` value.
5. Session cookies — clear browser cookies; ensure site accessed via `https://` not `http://`.

---

## Registration verification fails

**Checks:**
1. Table `registration_verifications` exists.
2. Brevo sends link email — check spam folder.
3. `APP_URL` in `.env` matches your live HTTPS URL (links in emails use this).
4. Click link within 15 minutes; enter code within 5 minutes.

---

## Wrong URLs / redirects to localhost

**Fix:**
1. Set `APP_URL=https://yoursite.infinityfreeapp.com` in `.env` (no trailing slash).
2. Set `GOOGLE_REDIRECT_URI` to match Google Cloud Console exactly.
3. Clear browser cache.

---

## Google OAuth "Missing required parameter: client_id"

**Cause:** `GOOGLE_CLIENT_ID` is empty on the server (common on Railway — `.env` is not deployed).

**Fix:**
1. Visit `/auth/oauth-setup.php` and confirm `GOOGLE_CLIENT_ID` shows **set**.
2. Add these as hosting **Variables** (Railway) or in `.env` (local):
   - `GOOGLE_CLIENT_ID`
   - `GOOGLE_CLIENT_SECRET`
   - `APP_URL` (no trailing slash)
   - `GOOGLE_REDIRECT_URI` = `{APP_URL}/auth/google-callback.php`
3. Redeploy / restart the service after saving variables.

---

## Google OAuth "redirect_uri_mismatch"

1. Visit `/auth/oauth-setup.php` for the exact URI your server expects.
2. Add that URI in Google Cloud Console → OAuth client → Authorized redirect URIs.
3. Match `GOOGLE_REDIRECT_URI` in hosting env character-for-character (or set `APP_URL` and let the app derive the callback).

---

## Upload / image errors

**Symptoms:** Profile picture or report photo upload fails.

**Fix:**
1. `uploads/` folder exists and is writable (chmod 755).
2. File size under 5 MB (InfinityFree limit).
3. `uploads/.htaccess` prevents PHP execution (included in project).

---

## "libsodium unavailable" / encrypted email empty

InfinityFree PHP usually includes sodium. If not:
- Emails fall back to plaintext storage with `email_hash` for lookup.
- Existing `enc:v1:` or `hash:v1:` values need repair — log in with correct email to trigger repair, or update row in phpMyAdmin.

Set `DATA_ENCRYPTION_KEY` in `.env` before going live if sodium is available.

---

## Session / cookie issues

**Symptoms:** Logged out immediately, CSRF errors.

**Fix:**
1. Access site only via one URL (always HTTPS, same subdomain).
2. `APP_URL` must match the URL in the browser.
3. InfinityFree cookie path is auto-detected in `includes/auth.php`.

---

## phpMyAdmin import fails

| Error | Fix |
|-------|-----|
| Unknown collation `utf8mb4_0900_ai_ci` | Use `sql/infinityfree_import.sql` (uses `utf8mb4_unicode_ci`) |
| CREATE DATABASE denied | Select existing DB first; import file has no CREATE DATABASE |
| Table already exists | Drop all tables first, or use fresh database |

---

## Getting help

1. Check `logs/app.log` on the server (via FTP — not web accessible).
2. Run `deploy/verify.php` with your key for automated checks.
3. InfinityFree community: [https://forum.infinityfree.net](https://forum.infinityfree.net)
