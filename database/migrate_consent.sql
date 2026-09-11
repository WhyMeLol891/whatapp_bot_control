USE whatsapp_bot;

ALTER TABLE contacts
  ADD COLUMN consent_status ENUM('unknown','opt_in','opt_out') NOT NULL DEFAULT 'unknown' AFTER company,
  ADD COLUMN consented_at DATETIME NULL AFTER consent_status;