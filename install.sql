CREATE DATABASE IF NOT EXISTS switinvest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE switinvest;

CREATE TABLE IF NOT EXISTS api_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    api_key TEXT NOT NULL,
    base_url VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO api_providers (name, display_name, api_key, is_active) VALUES
('openrouter', 'OpenRouter', '', 1),
('openai', 'OpenAI', '', 1),
('anthropic', 'Anthropic', '', 1)
ON DUPLICATE KEY UPDATE id=id;

CREATE TABLE IF NOT EXISTS admin_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO admin_settings (setting_key, setting_value) VALUES
('password_hash', '')
ON DUPLICATE KEY UPDATE id=id;
