-- Create WebAuthn credential storage for applications that did not already enable browser passkeys.
CREATE TABLE IF NOT EXISTS `user_passkeys` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `credential_id` varchar(255) NOT NULL,
  `public_key` text NOT NULL,
  `sign_count` int unsigned NOT NULL DEFAULT 0,
  `label` varchar(120) DEFAULT NULL,
  `transports` varchar(255) DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_passkeys_credential_id_unique` (`credential_id`),
  KEY `user_passkeys_user_id_idx` (`user_id`),
  KEY `user_passkeys_active_user_idx` (`user_id`, `revoked_at`)
);
