-- Summary of street-level crime shown on a postcode page.
-- Run against the company database. Individual crime records are not stored.

CREATE TABLE IF NOT EXISTS postcode_crimes (
    postcode VARCHAR(10) NOT NULL,
    result_json MEDIUMTEXT NOT NULL,
    fetched_at DATETIME NOT NULL,
    PRIMARY KEY (postcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
