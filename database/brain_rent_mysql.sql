-- =============================================
-- DATABASE: brain_rent  (MySQL)
-- BrainRent - Async Expert Thinking Platform
-- =============================================

CREATE DATABASE IF NOT EXISTS brain_rent CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE brain_rent;

-- =============================================
-- TABLE 1: users
-- =============================================
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(100)   NOT NULL,
    email           VARCHAR(150)   NOT NULL UNIQUE,
    password_hash   VARCHAR(255)   NOT NULL,
    phone           VARCHAR(20),
    user_type       ENUM('client','expert','both','admin') NOT NULL DEFAULT 'client',
    profile_photo   VARCHAR(500),
    bio             TEXT,
    country         VARCHAR(100),
    timezone        VARCHAR(50),
    is_email_verified TINYINT(1)   DEFAULT 0,
    is_active       TINYINT(1)     DEFAULT 1,
    created_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_user_type (user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE 2: expert_profiles
-- =============================================
CREATE TABLE expert_profiles (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    user_id                 INT             NOT NULL UNIQUE,
    headline                VARCHAR(255),
    qualification           VARCHAR(255),
    domain                  VARCHAR(150),
    skills                  TEXT,
    expertise_areas         TEXT,           -- JSON: ["AI","PHP","Marketing"]
    experience_years        INT,
    current_role_name       VARCHAR(200),
    company                 VARCHAR(200),
    linkedin_url            VARCHAR(500),
    portfolio_url           VARCHAR(500),
    rate_per_session        DECIMAL(10,2)   NOT NULL,
    currency                VARCHAR(3)      DEFAULT 'USD',
    session_duration_minutes INT            DEFAULT 10,
    max_response_hours      INT             DEFAULT 48,
    is_available            TINYINT(1)      DEFAULT 1,
    max_active_requests     INT             DEFAULT 5,
    total_sessions          INT             DEFAULT 0,
    total_earnings          DECIMAL(12,2)   DEFAULT 0,
    average_rating          DECIMAL(3,2)    DEFAULT 0,
    total_reviews           INT             DEFAULT 0,
    is_verified             TINYINT(1)      DEFAULT 0,
    verification_docs       VARCHAR(500),
    created_at              TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_ep_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ep_available (is_available),
    INDEX idx_ep_rating (average_rating),
    INDEX idx_ep_rate (rate_per_session)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE 3: expertise_categories
-- =============================================
CREATE TABLE expertise_categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)    NOT NULL,
    slug        VARCHAR(100)    NOT NULL UNIQUE,
    icon        VARCHAR(50),
    parent_id   INT             NULL,
    is_active   TINYINT(1)      DEFAULT 1,

    CONSTRAINT fk_ec_parent FOREIGN KEY (parent_id)
        REFERENCES expertise_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed categories
INSERT INTO expertise_categories (name, slug, icon) VALUES
('Technology',       'technology',        'fa-laptop-code'),
('Business Strategy','business-strategy', 'fa-chess'),
('Marketing',        'marketing',         'fa-bullhorn'),
('Legal',            'legal',             'fa-gavel'),
('Finance',          'finance',           'fa-chart-line'),
('Health & Medicine','health-medicine',   'fa-heartbeat'),
('Design & Creative','design-creative',   'fa-palette'),
('Psychology',       'psychology',        'fa-brain'),
('Education',        'education',         'fa-graduation-cap'),
('Engineering',      'engineering',       'fa-cogs');

-- =============================================
-- TABLE 4: expert_categories (many-to-many)
-- =============================================
CREATE TABLE expert_categories (
    expert_profile_id   INT NOT NULL,
    category_id         INT NOT NULL,

    PRIMARY KEY (expert_profile_id, category_id),
    CONSTRAINT fk_exc_profile  FOREIGN KEY (expert_profile_id)
        REFERENCES expert_profiles(id) ON DELETE CASCADE,
    CONSTRAINT fk_exc_category FOREIGN KEY (category_id)
        REFERENCES expertise_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE 5: thinking_requests  (Core table)
-- =============================================
CREATE TABLE thinking_requests (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    client_id               INT             NOT NULL,
    expert_id               INT             NOT NULL,
    title                   VARCHAR(255)    NOT NULL,
    problem_text            TEXT,
    problem_voice_path      VARCHAR(500),
    problem_voice_duration  INT,
    category_id             INT,
    urgency                 ENUM('normal','urgent','critical') DEFAULT 'normal',
    agreed_rate             DECIMAL(10,2)   NOT NULL,
    currency                VARCHAR(3)      DEFAULT 'USD',
    status                  ENUM('submitted','accepted','declined','thinking','responded','completed','disputed','cancelled','expired')
                                DEFAULT 'submitted',
    response_deadline       TIMESTAMP       NULL,
    accepted_at             TIMESTAMP       NULL,
    thinking_started_at     TIMESTAMP       NULL,
    responded_at            TIMESTAMP       NULL,
    completed_at            TIMESTAMP       NULL,
    payment_status          ENUM('pending','held','released','refunded') DEFAULT 'pending',
    payment_id              VARCHAR(255),
    created_at              TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_tr_client   FOREIGN KEY (client_id)   REFERENCES users(id),
    CONSTRAINT fk_tr_expert   FOREIGN KEY (expert_id)   REFERENCES users(id),
    CONSTRAINT fk_tr_category FOREIGN KEY (category_id) REFERENCES expertise_categories(id),
    INDEX idx_tr_client  (client_id),
    INDEX idx_tr_expert  (expert_id),
    INDEX idx_tr_status  (status),
    INDEX idx_tr_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE 6: thinking_request_attachments
-- =============================================
CREATE TABLE thinking_request_attachments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    request_id      INT             NOT NULL,
    uploaded_by     INT             NOT NULL,
    file_path       VARCHAR(500)    NOT NULL,
    file_name       VARCHAR(255),
    file_size_mb    DECIMAL(10,2),
    file_type       VARCHAR(100),
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_att_request FOREIGN KEY (request_id)
        REFERENCES thinking_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_user    FOREIGN KEY (uploaded_by)
        REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE 7: thinking_responses
-- =============================================
CREATE TABLE thinking_responses (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    request_id              INT             NOT NULL UNIQUE,
    expert_id               INT             NOT NULL,
    written_response        TEXT,
    voice_response_path     VARCHAR(500),
    voice_duration_seconds  INT,
    key_insights            TEXT,           -- JSON array
    action_items            TEXT,           -- JSON array
    resources_links         TEXT,           -- JSON array
    actual_thinking_minutes INT,
    created_at              TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_resp_request FOREIGN KEY (request_id)
        REFERENCES thinking_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_resp_expert  FOREIGN KEY (expert_id)
        REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE 8: reviews
-- =============================================
CREATE TABLE reviews (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    request_id      INT             NOT NULL UNIQUE,
    reviewer_id     INT             NOT NULL,
    expert_id       INT             NOT NULL,
    rating          TINYINT         NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review_text     TEXT,
    clarity_rating  TINYINT         CHECK (clarity_rating BETWEEN 1 AND 5),
    depth_rating    TINYINT         CHECK (depth_rating BETWEEN 1 AND 5),
    usefulness_rating TINYINT       CHECK (usefulness_rating BETWEEN 1 AND 5),
    is_public       TINYINT(1)      DEFAULT 1,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_rev_request  FOREIGN KEY (request_id)  REFERENCES thinking_requests(id),
    CONSTRAINT fk_rev_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id),
    CONSTRAINT fk_rev_expert   FOREIGN KEY (expert_id)   REFERENCES users(id),
    INDEX idx_reviews_expert (expert_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE 9: payments
-- =============================================
CREATE TABLE payments (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    request_id          INT             NOT NULL,
    payer_id            INT             NOT NULL,
    payee_id            INT             NOT NULL,
    amount              DECIMAL(10,2)   NOT NULL,
    platform_fee        DECIMAL(10,2),
    expert_payout       DECIMAL(10,2),
    currency            VARCHAR(3)      DEFAULT 'USD',
    gateway             ENUM('razorpay','stripe') NOT NULL,
    gateway_payment_id  VARCHAR(255),
    gateway_order_id    VARCHAR(255),
    status              ENUM('created','captured','held','released','refunded','failed') DEFAULT 'created',
    captured_at         TIMESTAMP       NULL,
    released_at         TIMESTAMP       NULL,
    refunded_at         TIMESTAMP       NULL,
    created_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_pay_request FOREIGN KEY (request_id) REFERENCES thinking_requests(id),
    CONSTRAINT fk_pay_payer   FOREIGN KEY (payer_id)   REFERENCES users(id),
    CONSTRAINT fk_pay_payee   FOREIGN KEY (payee_id)   REFERENCES users(id),
    INDEX idx_payments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE 10: expert_wallet
-- =============================================
CREATE TABLE expert_wallet (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    expert_user_id      INT             NOT NULL UNIQUE,
    available_balance   DECIMAL(12,2)   DEFAULT 0,
    pending_balance     DECIMAL(12,2)   DEFAULT 0,
    total_earned        DECIMAL(12,2)   DEFAULT 0,
    total_withdrawn     DECIMAL(12,2)   DEFAULT 0,
    bank_account_name   VARCHAR(200),
    bank_account_number VARCHAR(50),
    bank_ifsc           VARCHAR(20),
    upi_id              VARCHAR(100),
    updated_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_wallet_user FOREIGN KEY (expert_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE 11: notifications
-- =============================================
CREATE TABLE notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             NOT NULL,
    type        VARCHAR(50),    -- 'new_request','response_ready','payment_released'
    title       VARCHAR(255),
    message     TEXT,
    link        VARCHAR(500),
    is_read     TINYINT(1)      DEFAULT 0,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE 12: user_sessions (Auth)
-- =============================================
CREATE TABLE user_sessions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             NOT NULL,
    token       VARCHAR(255)    NOT NULL UNIQUE,
    ip_address  VARCHAR(45),
    user_agent  VARCHAR(500),
    expires_at  TIMESTAMP       NOT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_sess_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- STORED PROCEDURE: Release Payment on Completion
-- =============================================
DELIMITER $$

CREATE PROCEDURE sp_complete_request(
    IN p_request_id INT,
    IN p_client_id  INT
)
BEGIN
    DECLARE v_expert_id   INT;
    DECLARE v_payout      DECIMAL(10,2);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 'error' AS result, 'Transaction failed' AS message;
    END;

    START TRANSACTION;

    -- Verify request belongs to client and is in 'responded' state
    IF NOT EXISTS (
        SELECT 1 FROM thinking_requests
        WHERE id = p_request_id AND client_id = p_client_id AND status = 'responded'
    ) THEN
        ROLLBACK;
        SELECT 'error' AS result, 'Request not found or not in responded status' AS message;
    ELSE
        SELECT expert_id INTO v_expert_id FROM thinking_requests WHERE id = p_request_id;
        SELECT expert_payout INTO v_payout FROM payments WHERE request_id = p_request_id;

        -- Mark request completed
        UPDATE thinking_requests
        SET status = 'completed', completed_at = CURRENT_TIMESTAMP, payment_status = 'released'
        WHERE id = p_request_id;

        -- Release payment
        UPDATE payments
        SET status = 'released', released_at = CURRENT_TIMESTAMP
        WHERE request_id = p_request_id;

        -- Credit expert wallet
        UPDATE expert_wallet
        SET available_balance = available_balance + v_payout,
            pending_balance   = pending_balance   - v_payout,
            total_earned      = total_earned      + v_payout,
            updated_at        = CURRENT_TIMESTAMP
        WHERE expert_user_id = v_expert_id;

        COMMIT;
        SELECT 'success' AS result;
    END IF;
END$$

DELIMITER ;

-- =============================================
-- STORED PROCEDURE: Update Expert Rating
-- =============================================
DELIMITER $$

CREATE PROCEDURE sp_update_expert_rating(
    IN p_expert_id INT
)
BEGIN
    UPDATE expert_profiles
    SET average_rating = (
            SELECT IFNULL(AVG(rating), 0)
            FROM reviews WHERE expert_id = p_expert_id AND is_public = 1
        ),
        total_reviews  = (
            SELECT COUNT(*) FROM reviews WHERE expert_id = p_expert_id
        )
    WHERE user_id = p_expert_id;
END$$

DELIMITER ;

-- =============================================
-- SEED DATA: Demo Users
-- =============================================
INSERT INTO users (full_name, email, password_hash, user_type, country, is_email_verified, is_active) VALUES
('Marcus Reynolds', 'marcus@brainrent.com', '$2y$12$demohashedpassword1',  'expert', 'USA', 1, 1),
('Priya Mehta',     'priya@brainrent.com',  '$2y$12$demohashedpassword2',  'expert', 'India', 1, 1),
('David Osei',      'david@brainrent.com',  '$2y$12$demohashedpassword3',  'expert', 'Ghana', 1, 1),
('Arjun Sharma',    'arjun@brainrent.com',  '$2y$12$demohashedpassword4',  'client', 'India', 1, 1);

INSERT INTO expert_profiles
    (user_id, headline, expertise_areas, experience_years, current_role_name, company,
     rate_per_session, currency, session_duration_minutes, max_response_hours,
     is_available, is_verified, average_rating, total_sessions, total_reviews)
VALUES
(1, 'Senior AI Architect & Product Strategist',
   '["AI/ML","LLMs","System Architecture","Product Strategy","SaaS"]',
   15, 'Founding Engineer', 'Meridian AI',
   120.00, 'USD', 10, 24, 1, 1, 4.90, 312, 218),

(2, 'Business Strategy Consultant · McKinsey Alum',
   '["Strategy","M&A","Startups","OKRs"]',
   12, 'Independent Consultant', 'McKinsey Alum',
   90.00, 'USD', 10, 36, 1, 1, 4.80, 241, 174),

(3, 'SaaS Finance Expert & CFO Advisor',
   '["Finance","Pricing","SaaS Metrics","Fundraising"]',
   10, 'CFO', 'ScaleVest',
   75.00, 'USD', 10, 48, 1, 1, 4.70, 189, 132);

INSERT INTO expert_wallet (expert_user_id, available_balance, pending_balance, total_earned) VALUES
(1, 8450.00, 1890.00, 12840.00),
(2, 5200.00,  900.00,  8100.00),
(3, 3100.00,  450.00,  5200.00);
