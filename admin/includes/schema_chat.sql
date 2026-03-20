CREATE TABLE IF NOT EXISTS chat_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    visitor_email VARCHAR(255) NULL,
    visitor_name VARCHAR(150) NULL,
    ip_address VARCHAR(45),
    status ENUM('active','closed','escalated') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_token (session_token),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    role ENUM('user','bot','admin') NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE,
    INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT,
    INDEX idx_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO chat_config (config_key, config_value) VALUES
('enabled', '1'),
('greeting', 'Hi! I''m the Core Chain assistant. Ask me anything about biometric wallets, tokenomics, security, or the ecosystem.'),
('bot_name', 'Core Chain Bot'),
('suggested_questions', '["How does biometric authentication work?","What is the $QOR token?","How is my data kept private?","What makes Core Chain different?"]'),
('fallback_message', 'I''m not sure about that. Would you like to leave your email so our team can get back to you?'),
('primary_color', '#4FC3F7');
