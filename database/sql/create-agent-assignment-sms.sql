-- Select the Shalimar Properties PORTAL database in phpMyAdmin, then run this file.
-- Creates one delivery-log table. No ERP tables or existing records are modified.
CREATE TABLE IF NOT EXISTS listing_assignment_sms (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            listing_id BIGINT UNSIGNED NOT NULL, listing_version INT UNSIGNED NOT NULL,
            portal_agent_id BIGINT UNSIGNED NOT NULL, agent_erp_link_id BIGINT UNSIGNED NOT NULL, agent_source_id INT UNSIGNED NOT NULL,
            message VARCHAR(500) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', attempts INT UNSIGNED NOT NULL DEFAULT 0,
            test_mode TINYINT NULL, recipient_mask VARCHAR(40) NULL, last_error VARCHAR(60) NULL,
            created_at DATETIME NOT NULL, attempted_at DATETIME NULL, sent_at DATETIME NULL, updated_at DATETIME NOT NULL,
            UNIQUE KEY approval_assignment(listing_id,listing_version), INDEX(status,created_at),
            CONSTRAINT assignment_sms_listing FOREIGN KEY(listing_id) REFERENCES portal_listings(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
