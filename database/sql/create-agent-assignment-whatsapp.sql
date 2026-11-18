-- Run in the Shalimar Properties PORTAL database before deploying the matching app files.
-- Adds a separate, persistent WhatsApp queue. Existing SMS queue and data are untouched.
CREATE TABLE IF NOT EXISTS listing_assignment_whatsapp (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id BIGINT UNSIGNED NOT NULL,
    listing_version INT UNSIGNED NOT NULL,
    portal_agent_id BIGINT UNSIGNED NOT NULL,
    agent_erp_link_id BIGINT UNSIGNED NOT NULL,
    agent_source_id INT UNSIGNED NOT NULL,
    template_name VARCHAR(512) NOT NULL,
    language_code VARCHAR(12) NOT NULL,
    template_var_1 VARCHAR(180) NOT NULL,
    template_var_2 VARCHAR(220) NOT NULL,
    template_var_3 VARCHAR(100) NOT NULL,
    status ENUM('pending','sending','accepted','failed','uncertain','skipped') NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    test_mode TINYINT(1) DEFAULT NULL,
    recipient_mask VARCHAR(40) DEFAULT NULL,
    wamid VARCHAR(256) DEFAULT NULL,
    last_error VARCHAR(60) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    attempted_at DATETIME DEFAULT NULL,
    accepted_at DATETIME DEFAULT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY approval_assignment_whatsapp (listing_id, listing_version),
    KEY assignment_whatsapp_status_created (status, created_at),
    KEY assignment_whatsapp_wamid (wamid),
    CONSTRAINT assignment_whatsapp_listing_fk
        FOREIGN KEY (listing_id) REFERENCES portal_listings (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
