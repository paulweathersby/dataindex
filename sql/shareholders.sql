-- Shareholder register read from Companies House filings.
-- Run against the company database (the same database as `companies` and `filings`).
--
-- shareholder_snapshots records the set of filings last used, so a company is
-- read again only when a relevant document is new.
-- shareholders holds the holders from that reading. Documents themselves are not stored.

CREATE TABLE IF NOT EXISTS shareholder_snapshots (
    company_number VARCHAR(12) NOT NULL,
    filing_marker CHAR(64) NOT NULL,
    source VARCHAR(16) NOT NULL,
    summary VARCHAR(500) NOT NULL,
    processed_at DATETIME NOT NULL,
    PRIMARY KEY (company_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shareholders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_number VARCHAR(12) NOT NULL,
    holder_name VARCHAR(255) NOT NULL,
    share_class VARCHAR(80) NOT NULL DEFAULT '',
    shares DECIMAL(20, 4) NULL,
    percentage DECIMAL(6, 2) NULL,
    holding_note VARCHAR(160) NOT NULL DEFAULT '',
    source_type VARCHAR(8) NOT NULL,
    source_date DATE NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY shareholders_company (company_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
