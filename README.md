# Prime Z Creative Operations

A Laravel and React workspace for a marketing team: ideas, briefs, assignments, production, review, scheduling, and manual performance tracking. One content idea can have several platform versions. Approval does not publish the work, and a publishing date does not change the production deadline.

Social metrics are entered by hand. This application does not connect to Facebook, Instagram, TikTok, YouTube, LinkedIn, X, or Pinterest.

## Requirements

- PHP 8.2 or 8.3 with extensions commonly enabled by XAMPP: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, and `gd` or `imagick` is not required.
- Composer 2
- Node.js 20 or newer and npm
- MySQL 8 or MariaDB 10.4+

Laravel 13 needs PHP 8.3. This project uses Laravel 12 so it can run on the PHP 8.2 runtime shipped with XAMPP.

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create the database, then set `DB_*` in `.env`. The XAMPP defaults used in development are database `primez_creative_ops`, user `root`, and an empty password.

```bash
php artisan migrate --seed
php artisan storage:link
npm run dev
php artisan serve
```

Open `http://127.0.0.1:8000`. `storage:link` is only for Laravel's public disk. Creative drafts are stored on the private disk at `storage/app/private` and are downloaded through authorized routes.

`SEED_DEMO=true` loads sample brands, people, content, and performance rows. Every seeded performance report uses the source `demo_seed`. The dashboard labels that data as demo seed. Set `SEED_DEMO=false` before seeding a real workspace.

### Demo accounts

Every demo password is `password`.

| Email | Role |
| --- | --- |
| avery@primez.test | Administrator |
| jordan@primez.test | Marketing Manager |
| sam@primez.test | Social Media Manager |
| riley@primez.test | Creative Team |
| casey@primez.test | Creative Team |
| morgan@primez.test | Creative Team |
| quinn@primez.test | Reviewer |

Change these passwords before anyone else can reach the server.

## Scheduled work

Deadline warnings and overdue notices are created by:

```bash
php artisan ops:scan-deadlines
```

The scheduler runs that command daily at 08:00. On a server, cron should call `php artisan schedule:run` every minute. Notifications are written in the application database. Email, Slack, and Messenger are not connected.

## Production

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Set `APP_URL` to the public HTTPS origin.
- Set `SESSION_SECURE_COOKIE=true` when the site is served over HTTPS.
- Run `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
- Build the frontend with `npm ci` and `npm run build`. Point the web root at `public`.
- Run the queue worker only if `QUEUE_CONNECTION` is not `sync`. Core notifications are saved during the request and do not depend on a worker.
- Keep `storage` and `bootstrap/cache` writable by PHP. Do not expose `storage/app/private` as a public directory.
- Back up the MySQL database and `storage/app/private`. A database dump without the private disk loses drafts. A file copy without the database loses the records that authorize those files.
- `UPLOAD_MAX_KB` defaults to 81920 (80 MB). Also raise PHP `upload_max_filesize` and `post_max_size` above that limit.
- Do not commit `.env` or cloud keys. The AWS variables in `.env.example` are unused until an S3 disk is deliberately selected in application code. Uploads currently always use the private local disk.

## Tests

```bash
php artisan test
```

Feature tests use SQLite in memory and do not load demo content.

## What is intentionally not automated

- No social platform API reads or writes metrics.
- No outbound email.
- S3 is configured as a Laravel disk and is not the disk used for creative files.
