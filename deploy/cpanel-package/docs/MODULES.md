# NAi module list

This package contains the complete NAi workspace application. It does **not** contain a property-management/PMS module; “workspace/property name” in the installer is the display name for this NAi workspace.

| Module | What it provides | Main access |
|---|---|---|
| Authentication & roles | Email/password sign-in, throttling, audit, admin/operator/viewer gates | `/login` |
| Executive dashboard | Estate health, resource mix, active projects, infrastructure status, recent activity | `/dashboard` |
| Application inventory | Environments, owners, stack, URLs, health and operational notes | `/inventory/applications` |
| Repository registry | GitHub/GitLab/Bitbucket/self-hosted metadata, branches, visibility, linked applications | `/inventory/repositories` |
| Database inventory | Engine/host/environment/size/backup posture; no database credentials stored in inventory | `/inventory/databases` |
| Infrastructure & hosting | Servers, hosting, CDN/storage/network resources, regions, SSL expiry | `/inventory/infrastructure` |
| Projects & tasks | Project keys, priorities, owners, due dates, Kanban task board | `/projects` |
| NAi engine workspace | Scoped conversations and playbooks forwarded to a configured upstream engine; no local model or fake replies | `/arena` |
| NAi browser | External site framed full-height inside the NAi shell (branding + side navigation retained); address bar, back/forward/reload/home, focus mode, fullscreen, and an "Open in tab" escape when framing is refused | `/arena/browser` |
| Operational monitoring | URL/endpoint probes, check history, uptime/latency, incidents, recovery, manual resolution | `/monitoring` |
| Notifications | Per-user inbox, unread badge, incident/recovery and task assignment/completion notices | `/notifications` |
| Global search | Cross-inventory and project search | Topbar / `Ctrl/⌘ + K` |
| Team management | Create/edit/remove users and assign roles | Admin → `/users` |
| Settings | Workspace profile, Arena launcher URL, encrypted upstream engine connection | Admin → `/settings` |
| Audit trail | Inventory/project/auth/settings/engine activity | `/activity` |
| Exports & backup | CSV exports and versioned secret-free JSON workspace backup | Settings / export actions |
| Safe workspace import | Admin-only validation → change plan → explicit confirmation; additive merge, no imported credentials/users/history | Admin → `/settings/import` |
| Scheduled maintenance | Monitoring when enabled, monitoring/import/notification retention pruning | cPanel cron → `schedule:run` |
| Direct cPanel installer | One-time browser setup for this prebuilt package; self-deletes and locks after success | `/install.php` before installation only |

## Boundaries

- NAi is an interface/orchestration layer, not an independent AI model.
- A disconnected engine stays visibly disconnected; the application does not invent replies.
- The NAi browser frames the target site directly from the user's browser. NAi does not proxy, intercept, or store that site's credentials, and it cannot bypass the site's own frame policy. All visible labels are NAi-branded; the framed page keeps its own content and sign-in.
- Inventory forms do not store credentials. The upstream engine API key is encrypted at rest.
- Imports are additive and never import users, credentials, settings, conversations, or historical health.
- No Node.js, npm, Vite, Docker, Composer-on-server, or SSH is required for this prebuilt cPanel installation path.
