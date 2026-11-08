# NAi integration

NAi is an **interface and orchestration layer**. NAi remains the underlying AI development engine.

## Principles

1. NAi never runs a local model.
2. NAi never fabricates an assistant response when NAi is unavailable.
3. Prompts and compact workspace context are forwarded to the configured NAi endpoint.
4. Connection failures are surfaced as failed messages and audit events.
5. Credentials are encrypted at rest and never sent to the browser.

## Configuration

Environment defaults:

```dotenv
ARENA_BASE_URL=
ARENA_API_KEY=
ARENA_WORKSPACE_REF=nowills-group
ARENA_CHAT_PATH=/v1/chat
ARENA_HEALTH_PATH=/v1/health
ARENA_TIMEOUT=45
```

Admins can override these in **Settings**. Database-stored values take precedence; environment values act as fallbacks.

Because NAi deployments may expose different approved API surfaces, NAi uses a small, explicit gateway contract. Point `ARENA_BASE_URL` at the approved NAi API or at a thin bridge that forwards to NAi using your deployment's supported protocol.

## Health check

`GET {ARENA_BASE_URL}{ARENA_HEALTH_PATH}`

Headers:

```http
Accept: application/json
Authorization: Bearer <ARENA_API_KEY>   # when configured
X-NAi-Workspace: <ARENA_WORKSPACE_REF> # when configured
```

Any `2xx` response marks the connection as connected. Non-2xx/network failures mark it as error.

## Chat request

`POST {ARENA_BASE_URL}{ARENA_CHAT_PATH}`

```json
{
  "workspace": "nowills-group",
  "conversation_id": "nai-12",
  "session_id": null,
  "messages": [
    { "role": "user", "content": "Review our estate health." }
  ],
  "context": {
    "platform": "NAi (Nowills AI)",
    "role": "orchestration-interface",
    "generated_at": "2026-11-08T12:00:00+00:00",
    "counts": { "applications": 6, "repositories": 8, "databases": 6, "infrastructure": 6, "projects": 4 },
    "applications": [],
    "projects": [],
    "infrastructure": []
  },
  "stream": false
}
```

The context is a compact, read-only operational snapshot. It is not a training payload and does not include credentials.

## Scoped conversations and playbooks

Conversations can be scoped to a single record: an application, repository, database,
infrastructure resource, or project. Open one from the record's **Ask NAi**
button (`/arena/start/{type}/{id}`).

When a conversation is scoped, the payload gains `context.subject` and (when a
playbook was used) `context.playbook`:

```json
{
  "context": {
    "platform": "NAi (Nowills AI)",
    "playbook": "incident-triage",
    "subject": {
      "type": "applications",
      "label": "Application",
      "id": 3,
      "name": "Inventory Sync",
      "attributes": { "status": "degraded", "environment": "production", "tech_stack": ["PHP", "MySQL"] },
      "linked": { "repository": {}, "databases": [], "infrastructure": [] },
      "monitoring": {
        "recent_checks": [{ "status": "degraded", "http_code": 503, "latency_ms": 812, "source": "probe" }],
        "open_incidents": [{ "title": "Inventory Sync is degraded", "severity": "major", "check_count": 6 }]
      }
    }
  }
}
```

Subject context rules:

- Metadata only — NAi stores no credentials, so none can be forwarded.
- `monitoring` is present only for applications, databases, and infrastructure, and
  carries the last 10 checks plus open incidents. Each row keeps its `source`, so
  NAi can distinguish real probes from labelled demo samples.
- Projects carry up to 40 tasks and a progress percentage instead of `monitoring`.
- Linked records are names/statuses only, never nested secrets or full dumps.

### Playbooks

`App\Support\Playbooks` holds prompt **scaffolds** — instructions addressed to NAi
AI, never answers or answer templates. Available playbooks:

| Subject | Playbooks |
|---|---|
| Applications | Health review, Incident triage, Deployment plan, Draft runbook, Security review |
| Repositories | Review strategy, Release checklist, Dependency audit |
| Databases | Resilience review, Migration plan, Capacity review |
| Infrastructure | Hardening plan, Capacity review, Cost review |
| Projects | Delivery plan, Task breakdown, Risk review |

Selecting a playbook renders its prompt with the record's name and sends it as a
normal user message. If NAi is unconfigured the message fails honestly like any
other prompt; the playbook never produces local output.

## From reply to tracked work

A completed NAi reply can be saved as a project task (**Save reply as a task**).
The task keeps `source_message_id`, so the board links back to the conversation and
shows a "From NAi" chip.

Guards:

- Only `role=assistant`, `status=complete`, non-empty replies qualify — a failed
  request can never become a task (`422` if attempted directly).
- Write role required; only the conversation owner may convert its replies.
- One task per reply; repeat attempts are rejected instead of duplicating work.

## Supported response shapes

NAi accepts the first non-empty string found at:

- `data.reply`
- `reply`
- `message.content`
- `choices[0].message.content`
- `output_text`
- `content`

Optional fields:

```json
{
  "reply": "…NAi response…",
  "request_id": "req_123",
  "session_id": "arena_session_456",
  "usage": { "total_tokens": 1234 }
}
```

If your NAi deployment returns a different shape, adapt `App\Services\ArenaGateway::normalizeReply()` and, if necessary, the payload construction in `chat()`.

## Failure behavior

When NAi is not configured or returns an error:

- The user prompt is stored.
- An assistant message is stored with `status=failed` and a clear error.
- No local completion is generated.
- The event is written to the audit log.

## Verification

```bash
php artisan arena:test
```

In the UI: **NAi → Test connection**, or **Settings → Test NAi connection**.
