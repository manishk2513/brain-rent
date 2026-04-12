-- =============================================
-- ADD PENDING EXPERT REVIEW QUEUE
-- =============================================

USE brain_rent;

CREATE TABLE IF NOT EXISTS pending_expert_profiles (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    user_id                 INT             NOT NULL,
    desired_user_type       ENUM('expert','both') NOT NULL DEFAULT 'expert',
    headline                VARCHAR(255),
    qualification           VARCHAR(255),
    domain                  VARCHAR(150),
    skills                  TEXT,
    expertise_areas         TEXT,
    experience_years        INT,
    current_role_name       VARCHAR(200),
    company                 VARCHAR(200),
    linkedin_url            VARCHAR(500),
    portfolio_url           VARCHAR(500),
    rate_per_session        DECIMAL(10,2)   NOT NULL DEFAULT 0,
    currency                VARCHAR(3)      DEFAULT 'USD',
    session_duration_minutes INT            DEFAULT 10,
    max_response_hours      INT             DEFAULT 48,
    status                  ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note              TEXT,
    reviewed_by             INT             NULL,
    reviewed_at             TIMESTAMP       NULL,
    created_at              TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_pep_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_pep_admin FOREIGN KEY (reviewed_by)
        REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_pep_status_created (status, created_at),
    INDEX idx_pep_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
