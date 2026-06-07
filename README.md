# BantayPurrPaws

Stray animal rescue and adoption system — PHP, MySQL, HTML/CSS/JS.

## InfinityFree deployment

**Full guide:** [docs/DEPLOY_INFINITYFREE.md](docs/DEPLOY_INFINITYFREE.md)

Quick steps:
1. Create InfinityFree hosting + MySQL database
2. Import `sql/infinityfree_import.sql` in phpMyAdmin
3. Copy `.env.example` → `.env` with your credentials
4. Upload all files to `htdocs/`
5. Run `deploy/verify.php?key=YOUR_DEPLOY_VERIFY_KEY`
6. Delete `deploy/verify.php` when done

Also see:
- [docs/HOSTING_CHECKLIST.md](docs/HOSTING_CHECKLIST.md)
- [docs/TROUBLESHOOTING_INFINITYFREE.md](docs/TROUBLESHOOTING_INFINITYFREE.md)

## Local development (XAMPP)

1. Import `sql/bantaypurrpaws_import.sql` or run migrations
2. Copy `.env.example` → `.env` with `DB_HOST=localhost`, `APP_URL=http://localhost/purrpaws`
3. Set `APP_DEBUG=1` for local dev only

Default admin: `anthony.domasig@evsu.edu.ph` / `password` — change after first login.

## Stack

- PHP 8+ · MySQL/MariaDB · PDO prepared statements
- Brevo API for email (InfinityFree-compatible HTTPS)
- No Node, Docker, Redis, or cron required for production
