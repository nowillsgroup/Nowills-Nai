# Monitoring, uptime, and incidents

NAi monitors the technical estate that is registered in its inventory. Monitoring is
plain HTTP/TCP probing plus bookkeeping — it is not an AI feature and does not call
Arena AI.

## What gets probed

| Inventory type | Probe | Recorded status |
| --- | --- | --- |
| Applications | `GET` on the application URL (redirects followed, TLS verification configurable) | `2xx` → healthy, other codes → degraded, connection/timeout error → offline |
| Databases | TCP connect to `host:port` (port defaults per engine: MySQL/MariaDB 3306, PostgreSQL 5432, SQL Server 1433, MongoDB 27017, Redis 6379) | reachable → online, otherwise offline |
| Infrastructure | TCP connect to hostname/IP on 443, then 80 | reachable → online, otherwise offline |

Skipped, never faked:

- Applications without a URL.
- Infrastructure of type `storage` or `network`, or without a hostname/IP.
- Database credentials are never used: NAi stores metadata only, so probes are
  reachability checks, not authenticated queries.

## Check history

Every probe writes one `resource_checks` row: resource, target, status, latency in
milliseconds, HTTP code (applications only), a short message, `source`, and
`checked_at`.

- `source = probe` — a real probe performed by NAi.
- `source = demo` — seeded sample history for the demo workspace. The monitoring
  page labels this explicitly so sample data is never mistaken for measurements.

Uptime, average latency, and the 12-hour sparkline are computed from stored checks
only. When no checks exist the UI shows `—`, not an invented percentage.

## Incidents

An incident is opened on the transition into an unhealthy state and is updated (not
duplicated) while the resource stays unhealthy.

| Probe result | Incident severity |
| --- | --- |
| offline | critical |
| degraded | major |

- **Auto-resolution:** the next healthy check resolves the open incident with
  `resolution = recovered` and records the duration.
- **Manual resolution:** operators and admins can resolve an incident from the
  monitoring page with an optional note (`resolution = manual`, resolver recorded).
  If the resource is still unhealthy, the next probe opens a new incident.
- **Maintenance suppression:** a resource whose inventory status is `maintenance`
  is still probed and recorded, but raises no incident.
- Every open/resolve/prune action is written to the activity audit trail.

## Running checks

Manually (Dashboard → **Run checks**, Monitoring → **Run checks now**), or:

```bash
php artisan nai:monitor            # probe, record history, update incidents
php artisan nai:monitor --dry-run  # probe only; writes nothing at all
```

On a schedule (recommended):

```bash
NAI_MONITORING_ENABLED=true
```

```cron
* * * * * cd /var/www/nai && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler then runs `nai:monitor` every five minutes (`withoutOverlapping`) and
`nai:monitor-prune` daily at 03:15.

## Retention

```bash
NAI_MONITORING_RETENTION_DAYS=30   # default
php artisan nai:monitor-prune      # prune now
php artisan nai:monitor-prune --days=14
```

Pruning deletes check history older than the window and resolved incidents older
than the window. **Open incidents are never pruned.** Retention keeps the board's
aggregate queries bounded as the estate grows.

## Configuration reference

| Variable | Default | Purpose |
| --- | --- | --- |
| `NAI_MONITORING_ENABLED` | `false` | Enable the five-minute scheduled probe |
| `NAI_MONITORING_TIMEOUT` | `3` | Per-probe timeout in seconds |
| `NAI_MONITORING_VERIFY_SSL` | `true` | Verify TLS certificates on application probes |
| `NAI_MONITORING_RETENTION_DAYS` | `30` | Check-history retention window |

Manual runs are throttled to 6 per minute per user; the endpoint is write-gated
(`operator` and `admin` only). Viewers can read the board and export history.

## Exports

- Monitoring → **Export checks** — CSV of the retained check history
  (`/monitoring/export`). Cells are escaped against spreadsheet formula injection.
- Settings → **Export workspace JSON** — includes the monitoring block (retention,
  open incidents, last 50 resolved incidents). Passwords and Arena API keys are
  never exported.

## Behaviour in restricted networks

Probes originate from the NAi server. If the host has no route to a target (for
example a sandbox with restricted egress, or an internal hostname that only
resolves inside your VPC), the probe honestly records `offline` with the connection
error message. Point NAi at reachable endpoints, or run it inside the network that
can reach them.
