# Background workers

## Purpose

Epesi normally uses `QUEUE_CONNECTION=sync`: a queued operation runs in the web or cron request
that dispatched it. That is intentional. Many shared hosts provide only a once-per-minute cron
entry, not Supervisor, Redis, or permission to start child processes; leaving jobs in a
`database` queue with no consumer loses work from the user's point of view.

This document is the design for an **optional, durable database-worker mode**. It is a
prerequisite for moving non-critical follow-up work out of a request. It does not change the
default installation, make all notifications asynchronous, or replace `cron.php`.

## Operating modes

| Mode | Queue connection | Consumer | What application code may defer |
| --- | --- | --- | --- |
| Default / no CLI cron | `sync` | none | Nothing: retain today’s synchronous semantics. |
| CLI cron worker enabled | `sync` remains the default; selected jobs explicitly use `database` | A separate host cron entry invokes a bounded database-queue drain every minute | Only jobs that declare a durable, idempotent asynchronous contract. |
| Supervised worker (future) | Same selected `database` queue | Supervisor/system service runs `queue:work` | Same contract; operationally lower latency. |

Do not flip the global `QUEUE_CONNECTION` setting to `database`. Password-reset mail, contact
verification, and any existing `sendNow()` call deliberately need immediate success/failure
feedback. Explicit connections also make the fallback predictable.

## First slice implemented

The optional slice is enabled with `EPESI_BACKGROUND_WORKER_ENABLED=true`. It adds the named
`epesi-background` database connection while leaving `QUEUE_CONNECTION=sync` untouched. A host
cron entry can invoke `php artisan epesi:background:drain`; the command runs Laravel's database
worker with `--stop-when-empty`, finite attempts, a 45-second default maximum, and a 30-second
per-job timeout. It records each drain attempt for the Administration → Background worker health
page, which reports enabled state, the last successful drain, pending/reserved jobs, oldest age,
and failed jobs.

Unread-mail checks are the first deferred job. When the flag is off (the default), `mail:check`,
the Mail widget, and the cron URL continue asking each account synchronously. When it is on,
each account gets one ID-only, retry-bounded job on `epesi-background`; the account records the
check start, completion time, cached result, and any error. A cron URL by itself never drains
the database queue, so an installation without the separate CLI drain entry stays on the
synchronous path.

## CLI-cron worker lifecycle

The installer/Administration UI must offer the worker only after confirming that the host can
run a PHP command from cron. It supplies a separate, documented cron entry such as:

```text
* * * * * /path/to/php /path/to/artisan queue:work database --queue=epesi-background --stop-when-empty --max-time=45 --tries=3 --timeout=30 --no-interaction
```

The exact PHP path remains host-specific, just like `cron.php`. This is a **separate entry**,
not a subprocess started by `CronRunner`: the latter must still support the cron URL and hosts
with `proc_open` disabled. The drain is time-bounded so the next invocation can recover from a
stuck worker; the queue reservation/`retry_after` must exceed the job timeout by a safe margin.

Before enabling it, verify all of the following:

1. `jobs` and `failed_jobs` migrations are present and current.
2. The command runs under the same release, PHP version, storage path, and database connection
   as the web app.
3. The cache/lock store is shared where a job needs a lock.
4. The job queue has one named queue (`epesi-background` initially), finite timeout/tries, and
   a documented retry policy.
5. Administration can show: worker enabled/disabled, last successful drain, pending/reserved
   count, oldest queued age, and failed-job count. It must warn when enabled but no successful
   drain is seen within a short multiple of the cron interval.

`cron.php` continues running scheduled application work. A cron-URL-only host cannot consume a
Laravel database queue; it remains in synchronous mode rather than falsely reporting work as
complete.

## Job contract

Every deferred operation must be safe when it is delivered late, retried, or delivered more
than once.

- Dispatch only after the database transaction commits. For an external side effect that must
  correspond exactly to a committed record, persist an outbox/work row in that transaction and
  let the job claim it atomically.
- Carry stable record IDs and an idempotency key, not hydrated models, raw credentials, or a
  user session. Re-read authorization/visibility at execution time when it matters.
- Give each job a narrow timeout, bounded attempts/backoff, and an explicit terminal failure
  state with an administrator-visible retry path. Do not silently drop `failed_jobs`.
- Make the user-visible state honest: **pending**, **completed**, or **failed**. A request must
  never say “not sent” merely because post-send filing is pending or failed.
- Long-running workers must reset request-derived state between jobs (locale, authentication,
  tenant-like context, and any static/request memoization). Restart a supervised worker after a
  deploy or custom-translation change.

## Safe adoption order

1. **Unread-mail checks:** queue one account check at a time, recording its start/result/error
   and retaining the cached-count/error contract. This prevents a slow IMAP account from holding
   later scheduled tasks, but does not claim a fresher count before the job completes.
2. **Sent-folder filing after accepted SMTP delivery:** preserve synchronous SMTP delivery and
   attachment construction. Once SMTP succeeds, enqueue idempotent IMAP Sent filing and expose
   any pending/failure state. This is the first compose-path latency candidate.
3. **Archive-on-send:** only after its UI can distinguish sent from archive pending/failed and
   retries cannot create duplicate CRM mails or links.
4. **Watchdog fan-out:** write a committed activity-linked outbox batch, then re-check
   visibility at delivery and preserve each recipient's first-unseen marker. Measure listener
   time and watcher distribution before adopting it.

Do not defer SMTP delivery, password-reset/verification mail, manual Roundcube archiving,
ordinary file uploads, module install/update, or legacy import merely because a queue is
available. Those paths currently require immediate result semantics or a separately designed
progress/recovery UX.

## Rollout and verification

Start with one non-destructive job type on one installation. Measure queue age, job duration,
attempts, failures, cron overlap, and the originating request duration before/after. Exercise
success, timeout, exception, retry, duplicate delivery, deploy/restart, disabled worker, and
cron-URL-only fallback cases. Keep synchronous behavior as the feature-off path until the
Administration health signal and recovery process have proved reliable.
