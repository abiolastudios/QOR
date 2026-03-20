CREATE TABLE IF NOT EXISTS page_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_path VARCHAR(255) NOT NULL,
    page_title VARCHAR(255) NULL,
    referrer VARCHAR(500) NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_type ENUM('desktop','mobile','tablet') NOT NULL DEFAULT 'desktop',
    browser VARCHAR(50) NULL,
    os VARCHAR(50) NULL,
    country VARCHAR(100) NULL,
    session_id VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_page (page_path),
    INDEX idx_created (created_at),
    INDEX idx_session (session_id),
    INDEX idx_device (device_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
