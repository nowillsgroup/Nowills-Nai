# NAi architecture

## Positioning

NAi sits **on top of Arena AI**. It provides branding, workspace management, operational inventory, project coordination, auditability, and a conversation interface. Arena AI provides the development intelligence.

```text
┌────────────────────────────────────────────────────────────┐
│                        Nowills Group                       │
│  Admins / Operators / Viewers                              │
└───────────────┬────────────────────────────────────────────┘
                │ HTTPS
┌───────────────▼────────────────────────────────────────────┐
│ NAi — Laravel 12                                           │
│  • Blade UI + vanilla JS/CSS                               │
│  • Auth, roles, CSRF, audit                                │
│  • Inventory: apps, repos, databases, infrastructure       │
│  • Projects/tasks, conversations, settings                 │
│  • ArenaGateway (HTTP boundary, no model)                  │
└───────────────┬──────────────────────────────┬─────────────┘
                │                              │
        ┌───────▼───────┐              ┌───────▼────────────┐
        │ MySQL         │              │ Arena AI           │
        │ workspace DB  │              │ development engine │
        └───────────────┘              └────────────────────┘
```

## Backend modules

- `App\Services\ArenaGateway` — upstream Arena AI boundary.
- `App\Services\WorkspaceContext` — compact operational context for Arena prompts.
- `App\Support\Inventory` — inventory type definitions and validation.
- `App\Support\Audit` — activity log helper.
- `App\Services\ResourceMonitor` — endpoint health probes, check history, incident transitions, retention pruning.
- `App\Services\MonitoringStats` — bounded read-side aggregations (uptime, latency, sparklines) for the monitoring board.
- `App\Services\WorkspaceExporter` — secret-free operational backup.
- `App\Http\Middleware\SecurityHeaders` — baseline browser security headers.
- `App\Http\Controllers` — dashboard, inventory, projects, Arena, settings, team, activity, search, auth.

## Data model

- `users` — role: admin/operator/viewer.
- `applications`, `repositories`, `database_connections`, `infrastructures` — operational inventory.
- `projects`, `tasks` — delivery coordination.
- `conversations`, `messages` — Arena AI interaction history.
- `arena_connections` — encrypted upstream connection settings.
- `settings` — workspace settings.
- `activities` — audit trail.

## Frontend

Server-rendered Blade with a custom design system in `public/css/nai.css` and progressive enhancement in `public/js/nai.js`. No Node build pipeline is required.

## Security boundaries

- Authentication required for all workspace routes.
- `can:write` gate for mutating inventory/project/Arena actions.
- `role:admin` middleware for settings and team management.
- Arena API key encrypted via Eloquent `encrypted` cast.
- No credential fields in inventory forms.
- Login throttling and audit logging.

## What NAi deliberately does not do

- It does not implement or host an AI model.
- It does not replace Arena AI development capabilities.
- It does not generate fake AI responses when disconnected.
- It does not require Node.js or Docker.
