# NAi — Nowills AI

**NAi** is a premium enterprise software platform for **Nowills Group**: a centralized, branded interface and management dashboard for applications, databases, repositories, development projects, hosting environments, and technical infrastructure.

> **Architectural boundary:** NAi is **not** an independent AI model. It is an intelligent interface and orchestration layer that connects to **Arena AI** as the underlying AI development engine. NAi contains no local model, no inference fallback, and no simulated AI responses.

## Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2+ / Laravel 12 |
| Database | MySQL 8 / MariaDB 10.6+ (`pdo_mysql`) |
| Frontend | Server-rendered Blade, plain CSS, vanilla JavaScript |
| AI engine | Arena AI via configurable upstream HTTP gateway |
| Build tooling | Composer only — **no Node, no npm, no Docker** |

## Features

- **Executive dashboard** — estate health, resource mix, active projects, infrastructure status, and audit activity.
- **Operational monitoring** — probe application URLs and database/infrastructure endpoints; update health status manually or on a schedule.
- **Application inventory** — environments, health status, owners, stack, URLs, and operational notes.
- **Repository registry** — GitHub/GitLab/Bitbucket/self-hosted metadata, branches, visibility, and linked applications.
- **Database inventory** — engine, host, environment, size, backup posture; credentials are never stored.
- **Infrastructure & hosting** — servers, hosting platforms, CDN, storage, network resources, regions, and SSL expiry.
- **Projects & tasks** — project keys, priorities, owners, due dates, and a Kanban task board.
- **Arena AI workspace** — conversations that forward prompts and compact workspace context to Arena AI.
- **Global search** — `Ctrl/⌘ + K` across applications, repositories, databases, infrastructure, and projects.
- **Team & roles** — admin, operator, viewer; role-based access control.
- **Audit trail** — inventory changes, project/task changes, auth events, settings changes, and Arena interactions.
- **Exports & backup** — CSV exports for inventory/activity and a secret-free JSON workspace backup.
- **Security headers** — CSP, nosniff, referrer policy, permissions policy; secure-cookie support for production.
- **Settings** — workspace profile and encrypted Arena AI connection configuration.

## Requirements

- PHP **8.2+** with extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `xmlwriter`, `ctype`, `fileinfo`, `curl`, `dom`
- MySQL **8.0+** or MariaDB **10.6+**
- Composer **2.x**
- A web server (Nginx/Apache + PHP-FPM) for production, or `php artisan serve` for local evaluation

No Node.js, npm, Vite, Docker, or container runtime is required.

## Quick start (MySQL)

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nai
DB_USERNAME=nai
DB_PASSWORD=your-strong-password

NAI_WORKSPACE_NAME="Nowills Group"
NAI_DEMO_MODE=false
```

Create the database and run migrations:

```bash
mysql -u root -p -e "CREATE DATABASE nai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --force
php artisan nai:create-admin
php artisan nai:check
php artisan serve --host=0.0.0.0 --port=8000
```

Open `http://localhost:8000` and sign in with the administrator you created.

### Optional demo workspace

For evaluation only, set:

```dotenv
NAI_DEMO_MODE=true
```

Then:

```bash
php artisan migrate:fresh --seed
```

The login page exposes **Open demo workspace**. Demo credentials (demo only): `demo@nowills.group` / `Nowills-Demo-2026!`.

> Disable `NAI_DEMO_MODE` and never run the demo seeder in production. The evaluation preview may use SQLite for convenience, but the production target and default `.env.example` are MySQL.

## Connect Arena AI

NAi does not guess or fabricate Arena responses. Configure the approved Arena AI endpoint for your deployment:

```dotenv
ARENA_BASE_URL=https://your-arena-endpoint.example.com
ARENA_API_KEY=your-arena-token
ARENA_WORKSPACE_REF=nowills-group
ARENA_CHAT_PATH=/v1/chat
ARENA_HEALTH_PATH=/v1/health
ARENA_TIMEOUT=45
```

Or configure it in **Settings → Arena AI connection** (admin only). API keys are encrypted at rest.

```bash
php artisan arena:test
```

See [`docs/ARENA-INTEGRATION.md`](docs/ARENA-INTEGRATION.md) for the request/response contract and adaptation guidance.

## Roles

| Role | Permissions |
|---|---|
| Admin | Full access, settings, Arena configuration, team management |
| Operator | Manage inventory, projects, tasks, and Arena conversations |
| Viewer | Read-only access to workspace data |

## Testing

```bash
composer install
php vendor/bin/phpunit
```

Tests use an in-memory SQLite database by default (`phpunit.xml`). Production runtime remains MySQL. GitHub Actions (`.github/workflows/ci.yml`) runs the suite on PHP 8.2–8.4 and validates migrations against a real MySQL server without Docker.

## Useful commands

```bash
php artisan nai:create-admin     # create initial administrator
php artisan nai:check            # verify runtime, database, Arena configuration
php artisan arena:test           # test upstream Arena AI health endpoint
php artisan nai:monitor          # probe registered endpoints and update health
php artisan nai:monitor --dry-run # probe without writing status changes
php artisan nai:export           # secret-free JSON workspace backup
php artisan nai:lint             # syntax-check application PHP files
php artisan migrate --force      # apply schema changes
php artisan optimize             # cache config/routes/views for production
```

## Production deployment

See [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md). In short:

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
```

Serve the `public/` directory through Nginx/Apache + PHP-FPM. Keep `.env`, `storage/`, and `vendor/` out of the web root.

## Architecture

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

```text
Users → NAi (Laravel interface + orchestration) → Arena AI (development engine)
             ↓
           MySQL (workspace inventory, projects, conversations, audit)
```

## Security notes

- CSRF protection on all state-changing forms.
- Role-based authorization middleware and gates.
- Arena API keys encrypted with Laravel `APP_KEY`.
- Inventory stores metadata only; do not enter secrets in notes fields.
- Login throttling and Arena prompt throttling enabled.
- Security headers and production secure-cookie support.
- All significant actions are written to the activity log.
- Demo mode is explicit and disabled by default.

## License

Proprietary — Nowills Group. Inter font is licensed under the SIL Open Font License (see `public/fonts/LICENSE.txt`).
