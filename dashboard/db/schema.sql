-- Dashboard Schema Setup

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NULL DEFAULT NULL, -- NULL for agency staff/admin, set for client-role users
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('client', 'staff', 'admin') NOT NULL DEFAULT 'client',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `client_hub_keys` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL UNIQUE,
  `hub_api_key` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `posts_cache` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `hub_post_id` INT NOT NULL, -- References the post ID returned from the Hub
  `client_id` INT NOT NULL,
  `content` TEXT NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'draft',
  `platform` VARCHAR(50) NOT NULL,
  `media_path` VARCHAR(512) DEFAULT NULL, -- Local CDN media link cache
  `scheduled_at` TIMESTAMP NULL DEFAULT NULL,
  `published_at` TIMESTAMP NULL DEFAULT NULL,
  `external_post_id` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_hub_post` (`hub_post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `reset_token` VARCHAR(255) NOT NULL UNIQUE,
  `expires_at` TIMESTAMP NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate-limiting table for tracking failed login attempts
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(50) NOT NULL,
  `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_attempt_lookup` (`email`, `ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Default Admin User
-- Email: admin@ourcompany.com
-- Password: adminpassword
INSERT INTO `users` (`email`, `password`, `role`)
VALUES ('admin@ourcompany.com', 'adminpassword', 'admin')
ON DUPLICATE KEY UPDATE id=id;
