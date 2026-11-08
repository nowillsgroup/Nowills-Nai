# Arena AI integration

NAi is an **interface and orchestration layer**. Arena AI remains the underlying AI development engine.

## Principles

1. NAi never runs a local model.
2. NAi never fabricates an assistant response when Arena is unavailable.
3. Prompts and compact workspace context are forwarded to the configured Arena endpoint.
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

Because Arena AI deployments may expose different approved API surfaces, NAi uses a small, explicit gateway contract. Point `ARENA_BASE_URL` at the approved Arena AI API or at a thin bridge that forwards to Arena AI using your deployment's supported protocol.

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
  "reply": "…Arena AI response…",
  "request_id": "req_123",
  "session_id": "arena_session_456",
  "usage": { "total_tokens": 1234 }
}
```

If your Arena deployment returns a different shape, adapt `App\Services\ArenaGateway::normalizeReply()` and, if necessary, the payload construction in `chat()`.

## Failure behavior

When Arena is not configured or returns an error:

- The user prompt is stored.
- An assistant message is stored with `status=failed` and a clear error.
- No local completion is generated.
- The event is written to the audit log.

## Verification

```bash
php artisan arena:test
```

In the UI: **Arena AI → Test connection**, or **Settings → Test Arena connection**.
