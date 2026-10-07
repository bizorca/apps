-- TinyBooks, ported from SQLite (the original includes/db.php db_install()).
-- Every table is tb_-prefixed and user ids point at the shared users table.
-- TinyBooks' own users table is gone, and so is settings: it held only the
-- Anthropic key, which is now a server setting (private_html/.env.php).
--
-- Money is DECIMAL(15,2), not the original's SQLite REAL: exact cents, so a
-- balance is the sum of what was entered with no binary-float drift. The
-- import refuses any amount with more than two decimal places rather than
-- round it silently. PDO returns these as strings ("125.00").
--
-- Nullability and defaults mirror the original columns, except debit/credit,
-- which are NOT NULL DEFAULT 0 (the original only ever omitted them, giving 0).

CREATE TABLE IF NOT EXISTS tb_companies (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  name              VARCHAR(255) NOT NULL,
  type              VARCHAR(20) NULL DEFAULT 'for_profit',
  accounting_method VARCHAR(10) NULL DEFAULT 'double',
  fiscal_year_start TINYINT NULL DEFAULT 1,
  currency          CHAR(3) NULL DEFAULT 'USD',
  created_at        DATETIME NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who can open which company. The only access control TinyBooks has: every
-- company-scoped page goes through current_company(), which checks this.
-- role: 'owner' (can add/remove people) or 'member'.
CREATE TABLE IF NOT EXISTS tb_user_companies (
  user_id    INT NOT NULL,
  company_id INT NOT NULL,
  role       VARCHAR(20) NULL DEFAULT 'owner',
  PRIMARY KEY (user_id, company_id),
  CONSTRAINT fk_tb_uc_user    FOREIGN KEY (user_id)    REFERENCES users (id)        ON DELETE CASCADE,
  CONSTRAINT fk_tb_uc_company FOREIGN KEY (company_id) REFERENCES tb_companies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb_accounts (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  company_id     INT NOT NULL,
  account_number VARCHAR(50) NULL,
  name           VARCHAR(255) NOT NULL,
  type           VARCHAR(20) NOT NULL,
  subtype        VARCHAR(50) NULL,
  parent_id      INT NULL,
  description    TEXT NULL,
  is_active      TINYINT(1) NULL DEFAULT 1,
  created_at     DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tb_accounts_company_type (company_id, type),
  CONSTRAINT fk_tb_accounts_company FOREIGN KEY (company_id) REFERENCES tb_companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_tb_accounts_parent  FOREIGN KEY (parent_id)  REFERENCES tb_accounts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb_transactions (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  company_id     INT NOT NULL,
  date           DATE NOT NULL,
  description    TEXT NOT NULL,
  reference      VARCHAR(255) NULL,
  memo           TEXT NULL,
  is_reconciled  TINYINT(1) NULL DEFAULT 0,
  ai_categorized TINYINT(1) NULL DEFAULT 0,
  -- SET NULL, not the original's plain reference: deleting a shared account
  -- must not be blocked by the books it once typed into.
  created_by     INT NULL,
  created_at     DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tb_transactions_company_date (company_id, date),
  CONSTRAINT fk_tb_transactions_company FOREIGN KEY (company_id) REFERENCES tb_companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_tb_transactions_user    FOREIGN KEY (created_by) REFERENCES users (id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb_transaction_lines (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  transaction_id INT NOT NULL,
  account_id     INT NOT NULL,
  debit          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  credit         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  INDEX idx_tb_lines_account (account_id),
  CONSTRAINT fk_tb_lines_transaction FOREIGN KEY (transaction_id) REFERENCES tb_transactions (id) ON DELETE CASCADE,
  CONSTRAINT fk_tb_lines_account     FOREIGN KEY (account_id)     REFERENCES tb_accounts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb_budgets (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  account_id INT NOT NULL,
  year       SMALLINT NOT NULL,
  month      TINYINT NOT NULL,
  amount     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  UNIQUE KEY uq_tb_budgets (company_id, account_id, year, month),
  CONSTRAINT fk_tb_budgets_company FOREIGN KEY (company_id) REFERENCES tb_companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_tb_budgets_account FOREIGN KEY (account_id) REFERENCES tb_accounts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tb_reconciliations (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  company_id     INT NOT NULL,
  account_id     INT NOT NULL,
  statement_date DATE NOT NULL,
  ending_balance DECIMAL(15,2) NOT NULL,
  status         VARCHAR(20) NULL DEFAULT 'in_progress',
  created_at     DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tb_recs_company FOREIGN KEY (company_id) REFERENCES tb_companies (id) ON DELETE CASCADE,
  CONSTRAINT fk_tb_recs_account FOREIGN KEY (account_id) REFERENCES tb_accounts (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plain reference to the line, as in the original: a line a reconciliation has
-- touched cannot be deleted. transactions/edit.php checks for that first and
-- explains, where the original crashed on the constraint.
CREATE TABLE IF NOT EXISTS tb_reconciliation_items (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  reconciliation_id   INT NOT NULL,
  transaction_line_id INT NOT NULL,
  is_cleared          TINYINT(1) NULL DEFAULT 0,
  INDEX idx_tb_ritems_line (transaction_line_id),
  CONSTRAINT fk_tb_ritems_rec  FOREIGN KEY (reconciliation_id)   REFERENCES tb_reconciliations (id) ON DELETE CASCADE,
  CONSTRAINT fk_tb_ritems_line FOREIGN KEY (transaction_line_id) REFERENCES tb_transaction_lines (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
