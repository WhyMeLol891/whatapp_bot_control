# Architecture and Security Notes

## Boundaries

Apache serves the PHP control panel and API. PHP owns sessions, permissions, MySQL, campaigns, scheduling, queue state, audit data, and protected media. The Windows worker uses only HTTPS API requests with an `Authorization: Bearer` token. It never receives database credentials.

## Queue semantics

`message_jobs` is claimed inside a database transaction using `SELECT ... FOR UPDATE`, then changed to `processing` before the transaction is released. This is compatible with the MariaDB 10.4 deployment; it serializes competing claims rather than using MySQL 8's optional `SKIP LOCKED` optimization. Reporting locks the job, requires `job.worker_id` to match the authenticated worker, and treats repeated reports as no-ops. A stale recovery script requeues jobs only when both the claim and the worker heartbeat are stale, and fails jobs that have exhausted their retry attempts.

This is at-least-once execution, not exactly-once delivery. If WhatsApp accepts a message and the worker dies before reporting success, the server cannot know whether delivery happened. The `ambiguous` result is retained in `message_logs` and is not silently retried.

## Time

User-facing scheduling should parse in `Asia/Kuala_Lumpur`; database timestamps are UTC. API payloads should use explicit ISO-8601 UTC values. The current scaffold stores operational timestamps with MySQL UTC functions.

## Production checklist

- Set `WBC_ENV=production` and database credentials outside the repository.
- Serve over HTTPS and verify secure cookies.
- Run `database/recover_stale_jobs.php` from a scheduled task.
- Restrict database access to the PHP host and protect the uploads directory.
- Issue random worker tokens, store only SHA-256 hashes, show tokens once, and rotate them.
- Add audit records to worker-management and campaign-management screens before production use.
- Install Python 3.12+, dependencies, and a pinned Playwright browser on the Windows worker.
- Test WhatsApp Web selectors against the current UI in a dedicated adapter before enabling sending.
