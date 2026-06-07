# InfinityFree Hosting Checklist

Use this checklist before and after deploying BantayPurrPaws.

---

## Pre-deployment (local)

- [ ] All features tested locally with XAMPP
- [ ] `.env.example` reviewed — no secrets committed to git
- [ ] `sql/infinityfree_import.sql` generated and ready
- [ ] Brevo account created; API key and sender verified
- [ ] `DATA_ENCRYPTION_KEY` generated and saved securely
- [ ] Google OAuth credentials ready (if using Google Sign-In)

---

## InfinityFree account setup

- [ ] InfinityFree account created and verified
- [ ] Hosting account created with subdomain or custom domain
- [ ] MySQL database created in control panel
- [ ] MySQL hostname, database name, username, password recorded

---

## Database

- [ ] Selected database in phpMyAdmin
- [ ] Imported `sql/infinityfree_import.sql` successfully
- [ ] Tables verified: `users`, `otp_tokens`, `login_attempts`, `security_events`, `registration_verifications`, `pets`, `rescue_reports`
- [ ] Default admin login tested (then password changed)

---

## File upload

- [ ] All project files uploaded to `htdocs/`
- [ ] `.env` uploaded with production values (NOT `.env.example` alone)
- [ ] `uploads/` folder exists and is writable (755)
- [ ] `logs/` folder exists and is writable (755)
- [ ] `Dockerfile` NOT uploaded
- [ ] Local `logs/app.log` NOT uploaded
- [ ] Dev scripts NOT relied upon (`test_db.php`, `seed.php`)

---

## Environment configuration (`.env`)

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=0`
- [ ] `APP_URL=https://your-subdomain.infinityfreeapp.com`
- [ ] `DB_HOST` = MySQL hostname (sqlXXX.infinityfree.com)
- [ ] `DB_NAME`, `DB_USER`, `DB_PASSWORD` correct
- [ ] `BREVO_API_KEY` set
- [ ] `MAIL_FROM` = verified Brevo sender
- [ ] `DATA_ENCRYPTION_KEY` set
- [ ] `DEPLOY_VERIFY_KEY` set (temporary)

---

## Security

- [ ] `.htaccess` in place (blocks `.env`, dev scripts)
- [ ] `includes/.htaccess` blocks direct access
- [ ] `config/.htaccess` blocks direct access
- [ ] `sql/.htaccess` blocks direct access
- [ ] `uploads/.htaccess` blocks PHP execution
- [ ] Default admin password changed
- [ ] `APP_DEBUG=0` confirmed

---

## Verification tests

- [ ] `deploy/verify.php?key=...` — all checks PASS
- [ ] Test email sent (`&test_email=...`)
- [ ] Homepage loads
- [ ] User registration (email link + code) works
- [ ] Login with security flow (email alert → number → OTP) works
- [ ] Password reset email works
- [ ] Admin dashboard accessible
- [ ] File upload (profile photo / report photo) works
- [ ] Google Sign-In works (if enabled)

---

## Post-deployment cleanup

- [ ] `deploy/verify.php` deleted
- [ ] `auth/oauth-setup.php` deleted (after OAuth configured)
- [ ] `DEPLOY_VERIFY_KEY` removed from `.env`
- [ ] `APP_DEBUG=0` re-confirmed
- [ ] Brevo API key rotated if ever exposed

---

## Security recommendations

1. **Never** set `APP_DEBUG=1` on production — exposes errors and paths.
2. **Rotate** Brevo and Google secrets if they were ever in a public repository.
3. **Back up** `.env` and `DATA_ENCRYPTION_KEY` offline — losing the key makes encrypted data unrecoverable.
4. **Change** default admin password immediately.
5. **Use HTTPS only** — InfinityFree provides free SSL; enable in panel.
6. **Review** `admin/security.php` periodically for blocked IPs and failed logins.
7. **Keep** `uploads/` non-executable — `.htaccess` included.
8. **Do not** expose phpMyAdmin credentials or `.env` contents.

---

## Feature compatibility summary

| Feature | InfinityFree | Storage |
|---------|--------------|---------|
| PHP 8 + PDO MySQL | Yes | MySQL |
| Session auth | Yes | PHP sessions + `user_sessions` table |
| CSRF protection | Yes | Session |
| bcrypt passwords | Yes | `users.password` |
| Brevo email API | Yes | HTTPS outbound |
| Login security flow | Yes | MySQL |
| Registration verification | Yes | MySQL |
| OTP | Yes | MySQL `otp_tokens` |
| Trusted devices | Yes | MySQL |
| File uploads | Yes | `uploads/` directory |
| Redis / Node / Docker | Not used | N/A |
| Cron jobs | Optional | OTP cleanup manual if needed |

---

## Quick reference URLs

Replace `YOUR-SITE` with your subdomain:

| Page | URL |
|------|-----|
| Home | `https://YOUR-SITE.infinityfreeapp.com/` |
| Login | `https://YOUR-SITE.infinityfreeapp.com/login.php` |
| Register | `https://YOUR-SITE.infinityfreeapp.com/register.php` |
| Deploy verify | `https://YOUR-SITE.infinityfreeapp.com/deploy/verify.php?key=KEY` |
| Admin | `https://YOUR-SITE.infinityfreeapp.com/admin/dashboard.php` |
