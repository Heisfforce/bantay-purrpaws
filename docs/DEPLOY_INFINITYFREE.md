# BantayPurrPaws — InfinityFree Deployment Guide

Complete step-by-step guide to deploy BantayPurrPaws on [InfinityFree](https://infinityfree.net) shared hosting.

---

## What you need

| Item | Notes |
|------|--------|
| InfinityFree account | Free at infinityfree.net |
| Brevo account | Free tier for transactional email (recommended) |
| Google Cloud project | Optional, for Google Sign-In |
| FTP client or File Manager | FileZilla, WinSCP, or InfinityFree panel |

---

## Step 1: Create an InfinityFree account

1. Go to [https://infinityfree.net](https://infinityfree.net) and sign up.
2. Verify your email address.

---

## Step 2: Create a hosting account

1. In the InfinityFree client area, click **Create Account**.
2. Choose a subdomain (e.g. `bantaypurrpaws.infinityfreeapp.com`) or use your own domain.
3. Wait for the account to activate (usually a few minutes).
4. Note your **Control Panel URL** (VistaPanel / iFastNet).

---

## Step 3: Create a MySQL database

1. Open your hosting **Control Panel**.
2. Go to **MySQL Databases**.
3. Create a new database — note these four values:

| Setting | Example | Important |
|---------|---------|-----------|
| **MySQL Hostname** | `sql103.infinityfree.com` | This is NOT your website URL |
| **Database Name** | `if0_12345678_bantay` | |
| **Username** | `if0_12345678` | |
| **Password** | (generated) | Copy and save securely |

4. Open **phpMyAdmin** from the control panel.

---

## Step 4: Import the database

1. In phpMyAdmin, click your database name in the left sidebar.
2. Click the **Import** tab.
3. Choose file: `sql/infinityfree_import.sql`
4. Click **Go** and wait for success.

This creates all tables including security auth, registration verification, OTP, pets, reports, and a default admin user.

**Default admin (change password immediately):**
- Email: `anthony.domasig@evsu.edu.ph`
- Password: `password`

---

## Step 5: Configure environment (`.env`)

1. On your computer, copy `.env.example` to `.env`.
2. Fill in your InfinityFree values:

```env
APP_ENV=production
APP_DEBUG=0
APP_URL=https://YOUR-SUBDOMAIN.infinityfreeapp.com

DB_HOST=sqlXXX.infinityfree.com
DB_NAME=if0_XXXXX_yourdbname
DB_USER=if0_XXXXX
DB_PASSWORD=your_mysql_password

MAIL_DRIVER=brevo
BREVO_API_KEY=your_brevo_api_key
MAIL_FROM=your-verified-sender@email.com
MAIL_FROM_NAME=BantayPurrPaws

DATA_ENCRYPTION_KEY=base64:GENERATE_32_BYTE_KEY
DEPLOY_VERIFY_KEY=random-secret-for-verify-script
```

3. Generate encryption key (run locally if you have PHP):

```bash
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

---

## Step 6: Configure Brevo email (recommended)

InfinityFree often **blocks outbound SMTP**. Brevo API uses HTTPS and works reliably.

1. Sign up at [https://www.brevo.com](https://www.brevo.com).
2. Go to **SMTP & API** → **API Keys** → create a key.
3. Add and verify your sender email under **Senders**.
4. Put the API key in `.env` as `BREVO_API_KEY`.
5. Set `MAIL_FROM` to your verified sender address.

Emails used by the app:
- Login security alerts
- OTP verification
- Registration verification (link + code)
- Password reset
- Staff invitations
- Report/adoption notifications

---

## Step 7: Upload files to htdocs

Upload the entire project to your hosting **htdocs** folder via FTP or File Manager.

### Files/folders TO upload

```
htdocs/
├── admin/
├── api/
├── assets/
├── auth/
├── config/          ← includes config.php, database.php, smtp.php, security.php
├── css/
├── deploy/          ← verify.php (delete after testing)
├── includes/
├── js/
├── logs/            ← empty folder (writable)
├── security/
├── sql/             ← for reference; protected by .htaccess
├── uploads/         ← empty folder (writable)
├── .env               ← YOUR production credentials (never commit to git)
├── .htaccess
├── index.php
├── login.php
├── register.php
└── (all other .php pages)
```

### Files NOT to upload

| File | Reason |
|------|--------|
| `Dockerfile` | Local dev only |
| `logs/app.log` | Contains local dev data |
| `.git/` | Not needed on server |
| `test_db.php`, `seed.php`, `migrate_passwords.php` | Blocked by .htaccess |

---

## Step 8: Set file permissions

Via File Manager or FTP:

| Path | Permission | Why |
|------|------------|-----|
| `uploads/` | 755 (writable) | Profile photos, pet images, report photos |
| `logs/` | 755 (writable) | Application log file |
| `.env` | 644 | Readable by PHP only; blocked from web by .htaccess |
| Other files | 644 | Standard |

---

## Step 9: Run deployment verification

1. Visit: `https://YOUR-SUBDOMAIN.infinityfreeapp.com/deploy/verify.php?key=YOUR_DEPLOY_VERIFY_KEY`
2. Fix any **FAIL** items reported.
3. Optional test email: add `&test_email=you@example.com` to the URL.
4. When all checks pass:
   - **Delete** `deploy/verify.php`
   - Remove `DEPLOY_VERIFY_KEY` from `.env`

---

## Step 10: Test the application

| Test | URL / action |
|------|----------------|
| Homepage | `/index.php` |
| Registration | `/register.php` — full email link + code flow |
| Login security | `/login.php` — email challenge, number match, OTP |
| Admin panel | `/admin/dashboard.php` — login as admin |
| Google OAuth | Configure redirect URI (Step 11) |

---

## Step 11: Google OAuth (optional)

1. Visit `/auth/oauth-setup.php` on your live site — it shows the exact redirect URI.
2. In [Google Cloud Console](https://console.cloud.google.com/) → Credentials → OAuth 2.0:
   - Add the redirect URI shown
   - Copy Client ID and Secret to `.env`
3. Set `GOOGLE_REDIRECT_URI` in `.env` to match exactly.
4. **Delete** `auth/oauth-setup.php` after setup.

---

## Step 12: Production hardening checklist

- [ ] `APP_DEBUG=0` in `.env`
- [ ] Default admin password changed
- [ ] `deploy/verify.php` deleted
- [ ] `auth/oauth-setup.php` deleted (if OAuth configured)
- [ ] Brevo sender verified
- [ ] `DATA_ENCRYPTION_KEY` set and backed up securely
- [ ] `.env` never committed to public git

---

## Architecture notes for InfinityFree

| Feature | Implementation | Compatible? |
|---------|----------------|-------------|
| Database | PDO MySQL prepared statements | Yes |
| Sessions | PHP file sessions + DB session tracking | Yes |
| Email | Brevo HTTPS API (+ SMTP/mail fallback) | Yes |
| Auth security | MySQL tables, no Redis/cron required | Yes |
| File uploads | Local `uploads/` directory | Yes |
| Background jobs | None required | Yes |
| WebSockets / Node | Not used | N/A |

---

## Configuration file reference

| File | Purpose |
|------|---------|
| `config/config.php` | Loads `.env`, defines APP_* constants |
| `config/database.php` | MySQL connection settings |
| `config/smtp.php` | Brevo + SMTP + mail() fallback |
| `config/security.php` | Encryption key, dev script blocking |
| `.env` | Production secrets (upload separately) |
| `.htaccess` | Security rules, dev script blocking |

See also: [TROUBLESHOOTING_INFINITYFREE.md](TROUBLESHOOTING_INFINITYFREE.md) and [HOSTING_CHECKLIST.md](HOSTING_CHECKLIST.md).
