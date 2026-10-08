-- ThinkRep, ported from thinkrep.bizorca.com (the original's migrations 001,
-- 004, 005 and 007 folded together). Every table is tr_-prefixed and user ids
-- point at the shared users table, so they are signed INT to match it; a
-- foreign key across INT UNSIGNED -> INT is rejected outright. ThinkRep's own
-- users table is gone; the fields it kept beyond the shared account live in
-- tr_profiles.
--
-- The full original feature set comes across: teams, companies, scenario
-- packs, cohorts, benchmarks, submissions, hindsight and mashups.

-- What ThinkRep knows about a person beyond the shared account. One row per
-- user, created on first visit by getCurrentUser().
CREATE TABLE IF NOT EXISTS tr_profiles (
    user_id      INT NOT NULL PRIMARY KEY,
    role_title   VARCHAR(100) NULL,
    industry     VARCHAR(100) NULL,
    onboarded_at DATETIME NULL,
    -- ThinkRep content admin (reviews scenario submissions). The site owner
    -- (users.is_admin) is treated as one too; see isThinkrepAdmin().
    is_admin     TINYINT(1) NOT NULL DEFAULT 0,
    timezone     VARCHAR(50) NOT NULL DEFAULT 'America/New_York',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tr_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_mental_models (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    short_desc VARCHAR(255) NOT NULL,
    full_desc TEXT NOT NULL,
    example TEXT NOT NULL,
    counter_example TEXT NULL,
    display_order TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    admin_user_id INT NOT NULL,
    seat_limit INT UNSIGNED NOT NULL DEFAULT 50,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tr_companies_admin FOREIGN KEY (admin_user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_scenario_packs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tr_scenario_packs_company FOREIGN KEY (company_id) REFERENCES tr_companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_scenario_packs_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_scenarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    situation TEXT NOT NULL,
    correct_model_id INT UNSIGNED NOT NULL,
    -- First line is a "KEY: a|b|c" header that scoring.php parses for the
    -- key-concepts point; the rest is the model answer. Edit with care.
    ideal_reasoning TEXT NOT NULL,
    distractor_explanation TEXT NOT NULL,
    difficulty ENUM('beginner', 'intermediate', 'advanced') NOT NULL DEFAULT 'beginner',
    role_tags JSON NULL,
    industry_tags JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_mashup TINYINT(1) NOT NULL DEFAULT 0,
    pack_id INT UNSIGNED NULL,
    CONSTRAINT fk_tr_scenarios_model FOREIGN KEY (correct_model_id) REFERENCES tr_mental_models (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_scenario_choices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scenario_id INT UNSIGNED NOT NULL,
    model_id INT UNSIGNED NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    display_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_tr_scenario_model (scenario_id, model_id),
    CONSTRAINT fk_tr_choices_scenario FOREIGN KEY (scenario_id) REFERENCES tr_scenarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_choices_model FOREIGN KEY (model_id) REFERENCES tr_mental_models (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_scenario_correct_models (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scenario_id INT UNSIGNED NOT NULL,
    model_id INT UNSIGNED NOT NULL,
    rank_level ENUM('primary', 'secondary') NOT NULL,
    relevance_note TEXT NULL,
    UNIQUE KEY uq_tr_correct_model (scenario_id, model_id),
    CONSTRAINT fk_tr_correct_scenario FOREIGN KEY (scenario_id) REFERENCES tr_scenarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_correct_model FOREIGN KEY (model_id) REFERENCES tr_mental_models (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    scenario_id INT UNSIGNED NOT NULL,
    chosen_model_id INT UNSIGNED NOT NULL,
    reasoning_text TEXT NOT NULL,
    confidence TINYINT UNSIGNED NOT NULL DEFAULT 3,
    time_spent_sec INT UNSIGNED NULL,
    model_correct TINYINT(1) NOT NULL DEFAULT 0,
    reasoning_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    total_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    scoring_notes JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tr_responses_user_created (user_id, created_at),
    CONSTRAINT fk_tr_responses_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_responses_scenario FOREIGN KEY (scenario_id) REFERENCES tr_scenarios (id),
    CONSTRAINT fk_tr_responses_model FOREIGN KEY (chosen_model_id) REFERENCES tr_mental_models (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_confidence_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    score TINYINT UNSIGNED NOT NULL,
    notes TEXT NULL,
    logged_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tr_confidence_user_date (user_id, logged_date),
    CONSTRAINT fk_tr_confidence_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_blindspot_cache (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    model_id INT UNSIGNED NOT NULL,
    times_presented INT UNSIGNED NOT NULL DEFAULT 0,
    times_correct INT UNSIGNED NOT NULL DEFAULT 0,
    avg_reasoning DECIMAL(3,1) NOT NULL DEFAULT 0.0,
    avg_confidence DECIMAL(3,1) NOT NULL DEFAULT 0.0,
    overuse_count INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_tr_blindspot_user_model (user_id, model_id),
    CONSTRAINT fk_tr_blindspot_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_blindspot_model FOREIGN KEY (model_id) REFERENCES tr_mental_models (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_scenario_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    situation TEXT NOT NULL,
    correct_model_id INT UNSIGNED NOT NULL,
    ideal_reasoning TEXT NOT NULL,
    distractor_model_ids JSON NOT NULL,
    difficulty ENUM('beginner', 'intermediate', 'advanced') NOT NULL DEFAULT 'intermediate',
    role_tags JSON NULL,
    industry_tags JSON NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tr_submissions_status (status),
    INDEX idx_tr_submissions_user (user_id),
    CONSTRAINT fk_tr_submissions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_submissions_model FOREIGN KEY (correct_model_id) REFERENCES tr_mental_models (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_hindsight_reflections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    response_id INT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    new_reasoning TEXT NOT NULL,
    new_reasoning_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    scoring_notes JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tr_hindsight_response (response_id),
    CONSTRAINT fk_tr_hindsight_response FOREIGN KEY (response_id) REFERENCES tr_responses (id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_hindsight_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_mashup_responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    scenario_id INT UNSIGNED NOT NULL,
    selected_model_ids JSON NOT NULL,
    primary_model_id INT UNSIGNED NOT NULL,
    reasoning_text TEXT NOT NULL,
    confidence TINYINT UNSIGNED NOT NULL DEFAULT 3,
    time_spent_sec INT UNSIGNED NULL,
    primary_correct TINYINT(1) NOT NULL DEFAULT 0,
    secondary_found TINYINT(1) NOT NULL DEFAULT 0,
    reasoning_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    total_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    scoring_notes JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tr_mashup_user_created (user_id, created_at),
    CONSTRAINT fk_tr_mashup_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_mashup_scenario FOREIGN KEY (scenario_id) REFERENCES tr_scenarios (id),
    CONSTRAINT fk_tr_mashup_model FOREIGN KEY (primary_model_id) REFERENCES tr_mental_models (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_teams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    invite_code VARCHAR(32) NOT NULL UNIQUE,
    created_by INT NOT NULL,
    company_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tr_teams_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_team_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id INT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'member') NOT NULL DEFAULT 'member',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tr_team_user (team_id, user_id),
    CONSTRAINT fk_tr_team_members_team FOREIGN KEY (team_id) REFERENCES tr_teams (id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_team_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_team_challenges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id INT UNSIGNED NOT NULL,
    scenario_id INT UNSIGNED NOT NULL,
    started_by INT NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_tr_team_active (team_id, expires_at),
    CONSTRAINT fk_tr_challenges_team FOREIGN KEY (team_id) REFERENCES tr_teams (id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_challenges_scenario FOREIGN KEY (scenario_id) REFERENCES tr_scenarios (id),
    CONSTRAINT fk_tr_challenges_user FOREIGN KEY (started_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_company_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    role ENUM('admin', 'manager', 'member') NOT NULL DEFAULT 'member',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tr_company_user (company_id, user_id),
    CONSTRAINT fk_tr_company_members_company FOREIGN KEY (company_id) REFERENCES tr_companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_company_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- created_by is NULL for the built-in template, which ships with the content
-- seed before any user exists.
CREATE TABLE IF NOT EXISTS tr_cohorts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    team_id INT UNSIGNED NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    created_by INT NULL,
    duration_days INT UNSIGNED NOT NULL DEFAULT 30,
    is_template TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tr_cohorts_company FOREIGN KEY (company_id) REFERENCES tr_companies (id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_cohorts_team FOREIGN KEY (team_id) REFERENCES tr_teams (id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_cohorts_user FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_cohort_scenarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cohort_id INT UNSIGNED NOT NULL,
    scenario_id INT UNSIGNED NOT NULL,
    day_number INT UNSIGNED NOT NULL,
    display_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_tr_cohort_scenarios_cohort FOREIGN KEY (cohort_id) REFERENCES tr_cohorts (id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_cohort_scenarios_scenario FOREIGN KEY (scenario_id) REFERENCES tr_scenarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_cohort_enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cohort_id INT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    started_at DATE NOT NULL,
    completed_at DATETIME NULL,
    UNIQUE KEY uq_tr_cohort_user (cohort_id, user_id),
    CONSTRAINT fk_tr_enrollments_cohort FOREIGN KEY (cohort_id) REFERENCES tr_cohorts (id) ON DELETE CASCADE,
    CONSTRAINT fk_tr_enrollments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tr_benchmarks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_title VARCHAR(100) NULL,
    industry VARCHAR(100) NULL,
    model_id INT UNSIGNED NOT NULL,
    sample_size INT UNSIGNED NOT NULL DEFAULT 0,
    avg_accuracy DECIMAL(5,2) NOT NULL DEFAULT 0,
    avg_reasoning DECIMAL(3,1) NOT NULL DEFAULT 0,
    avg_score DECIMAL(4,1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tr_role_industry_model (role_title, industry, model_id),
    CONSTRAINT fk_tr_benchmarks_model FOREIGN KEY (model_id) REFERENCES tr_mental_models (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
