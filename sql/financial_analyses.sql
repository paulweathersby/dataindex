-- Saved financial analysis shown on a company page until a confirmed visitor refreshes it.
-- Run against the company database. The accounts documents themselves are not stored.

CREATE TABLE IF NOT EXISTS financial_analyses (
    company_number VARCHAR(12) NOT NULL,
    result_json MEDIUMTEXT NOT NULL,
    processed_at DATETIME NOT NULL,
    PRIMARY KEY (company_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
