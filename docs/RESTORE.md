# Workspace export & safe import

NAi supports a **versioned, additive workspace merge** for inventory and project metadata. It is designed for moving missing records between NAi workspaces or restoring records that were accidentally removed — not for replacing a live database.

> For full disaster recovery, continue to use tested MySQL backups and your normal database restore procedure. A NAi JSON snapshot deliberately excludes users, credentials, Arena settings, conversations, and monitoring history.

## Workflow

### Web (administrators only)

1. Open **Settings → Workspace backup → Review a workspace import**.
2. Upload a `.json` snapshot exported by NAi (format version 1, maximum 5 MB).
3. NAi validates the file and builds a change plan. **Uploading and previewing write no workspace records.**
4. Review every section:
   - **Create** — the record identity is missing and will be added.
   - **Keep existing** — an identical record already exists and will not be touched.
   - **Conflict** — an existing record has different metadata, or multiple existing records share the same identity. The entire import is blocked until you resolve the conflict outside the importer.
5. Export the current workspace if you want a pre-import baseline.
6. Check the confirmation box and apply. NAi revalidates the plan against the live workspace, then creates all missing records in one database transaction.
7. Run monitoring checks to establish real health for newly imported resources.

Reviews are encrypted with `APP_KEY`, stored on the private local disk, bound to the uploading administrator, expire after **15 minutes**, and are consumed once. Tokens are never placed in URLs. Canceling, expiring, or failing an apply requires a new upload. Expired reviews are pruned hourly by `nai:import-prune`.

### Console

```bash
# Export a secret-free, versioned snapshot (default path is private storage).
php artisan nai:export /secure/backups/nai-workspace.json

# Default behavior is a dry run: validate and print the change plan only.
php artisan nai:import /secure/backups/nai-workspace.json
php artisan nai:import /secure/backups/nai-workspace.json --dry-run

# Explicitly apply the additions after reviewing the plan.
php artisan nai:import /secure/backups/nai-workspace.json --apply

# Remove expired/unreadable encrypted web reviews.
php artisan nai:import-prune
```

`--dry-run` and `--apply` are mutually exclusive. The console command is for trusted operators with server access; web imports require an authenticated administrator and CSRF-protected forms.

## What is imported

| Section | Import identity | Notes |
|---|---|---|
| Applications | `slug` | Name, description, URL, environment, technology stack, notes |
| Repositories | normalized URL | Provider, branch, language, visibility, lifecycle status, linked application |
| Databases | name + environment | Engine, version, host, port, size, notes, linked application |
| Infrastructure | name + environment | Type, provider, region, hostname, IP, specs, SSL expiry, notes, linked application |
| Projects | project `key` | Name, description, status, priority, start/due dates |
| Tasks | project + title + description | Status, priority, due date; nested under their project |

Source application IDs are **file-local references only**. NAi remaps them to destination IDs after creating or matching applications. Source IDs are never inserted as destination primary keys. Project/task relationships are rebuilt the same way.

## What is never imported

- Users, passwords, roles, owners, or assignees.
- Arena API keys, Arena connection settings, or workspace settings.
- Conversations, messages, or task-to-message provenance.
- Monitoring checks, incidents, uptime history, or historical health.
- Arbitrary extra JSON sections or unknown record fields.

Imported applications, databases, and infrastructure start with status **Unknown**. Application health scores and check timestamps are cleared; database backup posture starts as **Not configured** and backup timestamps are cleared; repository sync timestamps are cleared. Run `nai:monitor` or use the monitoring board to obtain live results. NAi never presents imported history as a current probe.

## Merge semantics

- **Additive only:** missing records are created. Existing records are never updated or deleted.
- **Conflict blocking:** any validation error or metadata conflict blocks the whole import; there is no partial apply.
- **Operational differences are not conflicts:** live health, sync/backup timestamps, owners, and provenance are ignored when comparing portable metadata.
- **Ambiguity is rejected:** duplicate identities inside the file, or multiple existing destination records with the same natural identity, block apply.
- **Live revalidation:** the stored plan is compared with a fresh server-side plan immediately before apply. If the workspace changed, the import stops and asks for a new review.
- **Transactional writes:** all creations and the `workspace.imported` audit entry commit together or roll back together.
- **Import serialization:** a shared cache lock prevents concurrent NAi imports. Use a shared cache store (database or Redis) for multi-node deployments; file cache locks are node-local.

## Limits and validation

- File size: **5 MiB**.
- JSON nesting: **32 levels**.
- Records per import: 500 applications, 1,000 repositories, 500 databases, 500 infrastructure resources, 500 projects, 5,000 tasks total (at most 100 tasks per project).
- Field lengths and enums match the inventory/project forms; dates must be real calendar dates (`YYYY-MM-DD` or an ISO date beginning with one).
- URLs must use HTTP/HTTPS and must not contain embedded credentials.
- Validation reports at most 100 detailed issues and blocks apply.

For web uploads, ensure PHP and the reverse proxy allow the intended size, for example:

```ini
upload_max_filesize = 5M
post_max_size = 6M
```

Also set Nginx `client_max_body_size 6m;` (or the Apache equivalent) if needed. The application still enforces its own 5 MiB limit.

## Operational runbook

1. Export the destination workspace and keep it with your normal backups.
2. Run a dry run / web preview and resolve conflicts by correcting the source snapshot or reconciling destination records manually. The importer has no overwrite mode.
3. Apply during a quiet period; avoid editing the same inventory while an import is running.
4. Verify the success message, record counts, and the `workspace.imported` activity entry (counts, created IDs, snapshot hash, source workspace).
5. Run monitoring checks and review the newly imported resources.
6. Keep JSON snapshots in access-controlled storage. They exclude secrets but still contain operational metadata.

## Troubleshooting

| Symptom | Action |
|---|---|
| “Use a version 1 NAi workspace export” | Re-export from a current NAi instance. Unversioned or third-party JSON is rejected. |
| “The workspace changed after this preview” | Upload again; a record was created, edited, or deleted after the review. |
| “This review expired, was already used, or belongs to another administrator” | Start a new preview as the same administrator. Reviews are one-time and private. |
| Conflict on an existing record | Compare the portable metadata, reconcile manually, then re-export/re-preview. NAi will not overwrite it. |
| Upload fails before validation | Check `upload_max_filesize`, `post_max_size`, and proxy body-size limits. |
| Imported resource shows Unknown | Expected. Run a real monitoring check; historical health is never inherited. |
