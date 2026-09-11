# WhatsApp Bot Control Panel

A consent-based internal messaging control panel for XAMPP, PHP 8.3+, MySQL 8+, and a Windows Python worker. The worker communicates with the PHP API using a bearer token and never connects directly to MySQL.

## Status

This is a secure foundation, not a production-ready WhatsApp automation deployment. The Playwright adapter is intentionally not enabled until the operator implements and tests selectors for the current WhatsApp Web version. Delivery is at-least-once job execution with an unavoidable ambiguous window if WhatsApp accepts a message before the worker reports its result.

## Setup

1. Create the database by importing `database/schema.sql` in MySQL.
2. Set `WBC_DB_DSN`, `WBC_DB_USER`, `WBC_DB_PASSWORD`, and `WBC_ENV` in Apache/PHP environment configuration, or adjust local development values in `web/includes/config.php`.
3. Generate an administrator password hash with `php -r "echo password_hash('change-me', PASSWORD_DEFAULT), PHP_EOL;"` and insert a user row. Never use that sample password in a real environment.
4. Open `http://localhost/whatapp_bot_control/web/login.php`.
5. Copy `worker/config.example.ini` to `worker/config.ini`, install dependencies from `worker/requirements.txt`, and configure a token issued by a future worker-management screen or directly in the database during development.

## Security baseline

Use HTTPS in production, set PHP error display off, keep `worker/config.ini` and uploads out of version control, restrict `web/uploads`, rotate worker tokens, and back up MySQL. Do not use this system for unsolicited messaging, stealth automation, ban evasion, or bypassing WhatsApp security.
