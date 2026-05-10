-- Create bearer sessions for native/mobile API authentication.
CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `access_token_hash` char(64) NOT NULL,
  `refresh_token_hash` char(64) DEFAULT NULL,
  `access_expires_at` datetime NOT NULL,
  `refresh_expires_at` datetime DEFAULT NULL,
  `device_name` varchar(120) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_tokens_access_token_hash_unique` (`access_token_hash`),
  UNIQUE KEY `api_tokens_refresh_token_hash_unique` (`refresh_token_hash`),
  KEY `api_tokens_user_id_idx` (`user_id`),
  KEY `api_tokens_access_lookup_idx` (`access_token_hash`, `access_expires_at`, `revoked_at`),
  KEY `api_tokens_refresh_lookup_idx` (`refresh_token_hash`, `refresh_expires_at`, `revoked_at`)
);
