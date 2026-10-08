-- Commonweal (CoopConvert), ported from commonweal.app 2026-10-08.
-- Same columns as the original's migrations 001-008, cw_-prefixed, with
-- user ids pointing at the shared users table. The original users table is
-- gone: email/name/password live in the shared account, and Commonweal's
-- own per-person fields (role, coordinator approval, why-volunteer) live in
-- cw_members. The cw_users view rejoins them so pages read as before.

CREATE TABLE IF NOT EXISTS cw_members (
    user_id INT NOT NULL PRIMARY KEY,
    role ENUM('client','coordinator','admin') NOT NULL DEFAULT 'client',
    coordinator_approved TINYINT(1) NOT NULL DEFAULT 0,
    coordinator_why TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cw_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE OR REPLACE VIEW cw_users AS
    SELECT u.id, u.email, u.name, m.role, m.coordinator_approved, m.coordinator_why,
           u.email_verified_at, m.created_at
    FROM users u
    JOIN cw_members m ON m.user_id = u.id;

CREATE TABLE IF NOT EXISTS cw_businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    business_type VARCHAR(128) NULL,
    industry VARCHAR(128) NULL,
    employee_count VARCHAR(32) NULL,
    annual_revenue_range VARCHAR(64) NULL,
    owner_age_range VARCHAR(32) NULL,
    owner_timeline VARCHAR(64) NULL,
    motivation TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cw_businesses_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cw_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    coordinator_id INT NULL,
    status ENUM('intake','structure_selection','modeling','documents','review','complete') NOT NULL DEFAULT 'intake',
    stage_completed_at JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cw_cases_business FOREIGN KEY (business_id) REFERENCES cw_businesses (id) ON DELETE CASCADE,
    CONSTRAINT fk_cw_cases_coordinator FOREIGN KEY (coordinator_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cw_case_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    user_id INT NOT NULL,
    body TEXT NOT NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cw_case_notes_case FOREIGN KEY (case_id) REFERENCES cw_cases (id) ON DELETE CASCADE,
    CONSTRAINT fk_cw_case_notes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cw_structure_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    answers JSON NOT NULL,
    recommended_structure VARCHAR(64) NOT NULL,
    structure_scores JSON NOT NULL,
    completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cw_assessments_case FOREIGN KEY (case_id) REFERENCES cw_cases (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cw_deal_models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    scenario_label VARCHAR(128) NOT NULL DEFAULT 'Scenario 1',
    business_valuation DECIMAL(12,2) NOT NULL DEFAULT 0,
    seller_note_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    seller_note_rate DECIMAL(6,5) NOT NULL DEFAULT 0,
    seller_note_term_years INT NOT NULL DEFAULT 5,
    cdfi_loan_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    cdfi_loan_rate DECIMAL(6,5) NOT NULL DEFAULT 0,
    cdfi_loan_term_years INT NOT NULL DEFAULT 7,
    member_count INT NOT NULL DEFAULT 1,
    outputs JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cw_deal_models_case FOREIGN KEY (case_id) REFERENCES cw_cases (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cw_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    doc_type VARCHAR(64) NOT NULL,
    structure VARCHAR(64) NOT NULL,
    rendered_html LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cw_documents_case FOREIGN KEY (case_id) REFERENCES cw_cases (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cw_equity_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    member_name VARCHAR(255) NOT NULL,
    member_email VARCHAR(255) NULL,
    equity_class VARCHAR(64) NOT NULL DEFAULT 'Class A Worker',
    capital_account DECIMAL(12,2) NOT NULL DEFAULT 0,
    acquisition_method ENUM('cash','seller_financing','sweat_equity','patronage','gift') NOT NULL DEFAULT 'cash',
    acquisition_date DATE NULL,
    patronage_basis DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cw_equity_ledger_case FOREIGN KEY (case_id) REFERENCES cw_cases (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
