-- =============================================
-- ADD TEMPORARY PAYMENT BYPASS TABLE
-- =============================================

USE brain_rent;

CREATE TABLE IF NOT EXISTS temporary_payments (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    request_id          INT             NOT NULL,
    client_id           INT             NOT NULL,
    preferred_expert_id INT             NOT NULL,
    gateway             ENUM('stripe','razorpay') NOT NULL DEFAULT 'razorpay',
    amount              DECIMAL(10,2)   NOT NULL,
    currency            VARCHAR(3)      DEFAULT 'USD',
    status              ENUM('initiated','paid','failed') NOT NULL DEFAULT 'paid',
    transaction_ref     VARCHAR(191),
    paid_at             TIMESTAMP       NULL,
    created_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_tmp_pay_request FOREIGN KEY (request_id)
        REFERENCES thinking_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_tmp_pay_client FOREIGN KEY (client_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_tmp_pay_expert FOREIGN KEY (preferred_expert_id)
        REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_tmp_pay_request (request_id),
    INDEX idx_tmp_pay_client (client_id),
    INDEX idx_tmp_pay_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;