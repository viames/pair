-- Add optional device and password-version metadata to bearer sessions.
ALTER TABLE `api_tokens`
  ADD COLUMN `device_hash` varchar(64) DEFAULT NULL AFTER `refresh_expires_at`,
  ADD COLUMN `password_version_hash` char(64) DEFAULT NULL AFTER `device_hash`,
  ADD KEY `api_tokens_user_device_idx` (`user_id`, `device_hash`, `revoked_at`);
