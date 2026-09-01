# AI usage disclosure

AI was used as an implementation assistant. Architecture, consistency guarantees, security boundaries, database design, RabbitMQ topology and acceptance criteria were defined before implementation. Generated code was reviewed against those decisions and accompanied by automated tests.

This file contains both the raw user prompts that initiated the work and a normalized implementation prompt. The normalized prompt is a context-preserving transcription of the architectural discussion, not a claim that AI independently chose the architecture.

## Condensed context prompt — architecture request

> We need to determine the solutions for this Laravel test project. It must demonstrate PSR, SOLID and correct OOP at Middle/Middle+ level. Start with the architecture and then prepare professional prompts showing that AI was used to automate implementation while engineering decisions were made explicitly.

The full task specification supplied with this prompt required asynchronous PDF/DOCX upload, a 10 MB limit, database metadata, list/delete UI, automatic deletion after 24 hours, and RabbitMQ notification events for manual and automatic deletion, using Laravel, PHP 8, MySQL, Bootstrap and jQuery, with Docker as a bonus.

## Verbatim prompt — implementation request

> Добре! Тоді давай створимо проект який відповідає цим вимогам і побудований на кроках які ти описав (та архітектурою та рішеннями)

Translation:

> Good. Create the project according to these requirements and to the previously agreed steps, architecture and decisions.

## Normalized implementation prompt

```text
Implement the approved Laravel file-retention application. Treat the following
items as fixed architectural decisions; do not replace them with new patterns.

Runtime and UI:
- PHP 8.3, Laravel 13, MySQL 8, RabbitMQ, Blade, Bootstrap 5 and jQuery.
- Docker Compose must run nginx, PHP-FPM, scheduler, MySQL and RabbitMQ.
- Upload must use jQuery AJAX/FormData and expose progress without a page reload.

Boundaries:
- Keep controllers thin.
- Put use-case orchestration in application Actions.
- Define narrow DocumentStorage and DeletionEventPublisher contracts.
- Implement those contracts in Infrastructure using Laravel Filesystem and
  php-amqplib respectively.
- Do not create PDFStorage and DocxStorage adapters: file format is a validation
  concern; storage backend is the strategy boundary.
- Do not introduce a generic Eloquent repository for this single aggregate.

Storage and security:
- Accept only PDF and DOCX, maximum 10 MB, checking both detected MIME and extension.
- Store files on a private disk under generated UUID names.
- Persist original name, disk, path, MIME, extension, byte size, SHA-256 checksum,
  explicit expires_at and timestamps.
- If metadata persistence fails after storage succeeds, compensate by deleting
  the physical file.

Deletion consistency:
- Use one DeleteStoredFile action for both manual and expired deletion.
- Pass a backed enum reason: manual or expired.
- Serialize deletion with a database row lock.
- Treat an already deleted row and an already missing physical file idempotently.
- Soft-delete metadata for audit.
- Insert a uniquely constrained outbox event in the same transaction as deletion.

RabbitMQ:
- Publish the outbox event to durable topic exchange file.events, durable queue
  email.notifications, routing key file.deleted.
- Use persistent JSON messages, mandatory routing and publisher confirms.
- Include event_id/message_id, recipient from environment, file metadata and reason.
- Mark published only after confirmation; retry failures with exponential backoff.
- State explicitly that delivery is at least once and consumers deduplicate event_id.
- Do not implement the SMTP consumer.

Scheduling:
- Delete expires_at <= now rows every minute in bounded chunks.
- Publish pending outbox rows every ten seconds.
- Prevent overlapping scheduler executions.

Quality:
- PSR-4, PSR-12, strict types, constructor injection and immutable DTOs.
- Feature tests for validation, upload, manual deletion, expiration, idempotency,
  outbox payload, confirmed publication and RabbitMQ failure retry.
- Include README, architecture/trade-off documentation, CI and reproducible Docker setup.
```

## Human review checklist

- Verified that both deletion entry points use the same action.
- Verified that the event is created in the database transaction, not published from it.
- Verified that frontend filenames are inserted using jQuery `.text()`, not raw HTML.
- Verified that storage paths never use the client filename.
- Added failure-path tests rather than accepting only generated happy-path code.
- Documented at-least-once delivery and the one remaining duplicate-delivery window.
