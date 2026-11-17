-- Run in the SMP Portal database (not either ERP database).
-- MariaDB 10.4+: rerunnable; adds one optional image filename column.
-- No existing agent, contact, assignment or ERP record is updated/deleted.
ALTER TABLE shalimar_agents
    ADD COLUMN IF NOT EXISTS image_filename VARCHAR(255) NULL DEFAULT NULL AFTER email;

SHOW COLUMNS FROM shalimar_agents LIKE 'image_filename';
