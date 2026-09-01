# Architecture

## Context

The application stores PDF and DOCX files for a configurable retention period. A deletion event must reach RabbitMQ after either manual or scheduled deletion. The SMTP/email consumer is outside this repository.

## Architectural style

The project is a Laravel-native modular monolith with four boundaries:

1. **Presentation** — controllers, form requests, JSON resources, Blade, Bootstrap and jQuery.
2. **Application** — use-case actions that coordinate persistence, storage and integration events.
3. **Domain** — stable contracts and business enums.
4. **Infrastructure** — Laravel Filesystem and RabbitMQ adapters.

This is intentionally not a full domain-driven design implementation. The bounded domain has only one small aggregate, so introducing generic repositories, a separate ORM entity graph, command buses or event sourcing would increase accidental complexity without protecting a real business invariant.

## Upload flow

1. `UploadStoredFileRequest` validates size, extension and detected MIME type.
2. `UploadStoredFile` asks `DocumentStorage` to write the file using a generated name.
3. Metadata, checksum and explicit `expires_at` are inserted into MySQL.
4. If the database insert fails, the action compensates by deleting the physical file.

The original filename is metadata only. Files are kept on a private disk and never exposed as public executable paths.

## Unified deletion flow

Both the HTTP endpoint and `files:delete-expired` invoke `DeleteStoredFile` with a typed `DeletionReason`.

Within one short transaction the action:

1. reloads and locks the row with `SELECT ... FOR UPDATE`;
2. returns idempotently if the row is already soft-deleted;
3. removes the physical file (a missing object counts as already removed);
4. soft-deletes the metadata row and stores the reason;
5. inserts a uniquely constrained outbox event.

Keeping local filesystem I/O inside the transaction is a deliberate bounded trade-off. For a remote object store at high scale, deletion should become a recoverable state machine (`active -> deleting -> deleted`) so network I/O does not extend a database lock.

## Transactional outbox

Publishing directly after deletion can lose a notification when RabbitMQ is unavailable. The outbox row is therefore committed in the same MySQL transaction as the logical deletion.

`outbox:publish` sends pending messages with:

- a durable topic exchange and queue;
- persistent delivery mode;
- mandatory routing;
- publisher confirms;
- an event UUID as `message_id`;
- exponential retry capped at five minutes.

Delivery is **at least once**. A future email consumer must deduplicate using `event_id`; exactly-once delivery across MySQL and RabbitMQ is not claimed.

## Storage adapter decision

PDF and DOCX do not have separate storage adapters because format is a validation concern, not a storage strategy. `DocumentStorage` abstracts the backend, allowing local storage to be replaced by S3 or another object store without changing application actions.

## Scheduling

- `files:delete-expired` runs every minute and processes rows in ID-ordered chunks.
- `outbox:publish` runs every ten seconds.
- `withoutOverlapping()` prevents re-entry in the provided single-scheduler deployment.

Multiple scheduler replicas would additionally require a shared cache lock or a database-backed claim/lease on outbox rows.

## Message contract

Exchange: `file.events` (topic, durable)
Queue: `email.notifications` (durable)
Routing key: `file.deleted`

```json
{
  "event_id": "uuid",
  "event_type": "file.deleted",
  "occurred_at": "ISO-8601 UTC timestamp",
  "recipient": "notifications@example.com",
  "data": {
    "file_id": "ULID",
    "original_name": "document.pdf",
    "mime_type": "application/pdf",
    "size_bytes": 12345,
    "checksum_sha256": "hex digest",
    "uploaded_at": "ISO-8601 UTC timestamp",
    "expires_at": "ISO-8601 UTC timestamp",
    "deletion_reason": "manual|expired"
  }
}
```

## Consistency guarantees

| Scenario | Result |
| --- | --- |
| Database insert fails after upload | Physical file is removed by compensation |
| Two delete requests race | Row lock serializes them; one outbox event is created |
| File is already absent | Deletion proceeds idempotently |
| RabbitMQ is down | Outbox event remains pending and is retried |
| Publisher dies after broker confirm but before DB update | Event may be delivered again; consumer deduplicates by `event_id` |
