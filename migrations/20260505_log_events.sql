-- Aggiunge i metadati strutturati utili ai log eventi applicativi.
ALTER TABLE `error_logs`
	ADD COLUMN `event_name` varchar(128) NULL AFTER `level`,
	ADD COLUMN `request_method` varchar(16) NULL AFTER `path`,
	ADD COLUMN `request_data` JSON NULL AFTER `request_method`,
	ADD COLUMN `client_ip` varchar(45) NULL AFTER `referer`,
	ADD COLUMN `user_agent` varchar(512) NULL AFTER `client_ip`,
	ADD COLUMN `context_data` JSON NULL AFTER `user_agent`,
	ADD COLUMN `server_data` JSON NULL AFTER `context_data`,
	ADD COLUMN `app_version` varchar(64) NULL AFTER `server_data`,
	ADD COLUMN `environment` varchar(64) NULL AFTER `app_version`,
	ADD COLUMN `correlation_id` varchar(128) NULL AFTER `environment`,
	ADD COLUMN `trace_id` char(32) NULL AFTER `correlation_id`,
	ADD COLUMN `fingerprint` char(64) NULL AFTER `trace_id`,
	ADD COLUMN `exception_class` varchar(255) NULL AFTER `fingerprint`,
	ADD COLUMN `exception_file` varchar(512) NULL AFTER `exception_class`,
	ADD COLUMN `exception_line` int unsigned NULL AFTER `exception_file`,
	ADD COLUMN `exception_trace` mediumtext NULL AFTER `exception_line`,
	ADD INDEX `idx_log_events_event_name` (`event_name`),
	ADD INDEX `idx_log_events_app_version` (`app_version`),
	ADD INDEX `idx_log_events_environment` (`environment`),
	ADD INDEX `idx_log_events_correlation_id` (`correlation_id`),
	ADD INDEX `idx_log_events_trace_id` (`trace_id`),
	ADD INDEX `idx_log_events_fingerprint` (`fingerprint`),
	ADD INDEX `idx_log_events_exception_class` (`exception_class`);

-- Marca i record storici con un nome evento neutro.
UPDATE `error_logs`
SET `event_name` = 'legacy.error_log'
WHERE `event_name` IS NULL;

-- Conserva i payload richiesta legacy nel nuovo contenitore JSON.
UPDATE `error_logs`
SET `request_data` = JSON_OBJECT('query', `get_data`, 'body', `post_data`, 'files', `files_data`)
WHERE (`get_data` IS NOT NULL AND `get_data` NOT IN ('', 'a:0:{}', '[]'))
	OR (`post_data` IS NOT NULL AND `post_data` NOT IN ('', 'a:0:{}', '[]'))
	OR (`files_data` IS NOT NULL AND `files_data` NOT IN ('', 'N;', 'a:0:{}', '[]'));

-- Conserva i messaggi utente legacy nel contesto strutturato.
UPDATE `error_logs`
SET `context_data` = JSON_SET(COALESCE(`context_data`, JSON_OBJECT()), '$.userMessages', `user_messages`)
WHERE `user_messages` IS NOT NULL
	AND `user_messages` NOT IN ('', 'a:0:{}', '[]');

-- Rimuove i payload legacy non più persistiti come colonne dedicate.
ALTER TABLE `error_logs`
	DROP COLUMN `get_data`,
	DROP COLUMN `post_data`,
	DROP COLUMN `files_data`,
	DROP COLUMN `cookie_data`,
	DROP COLUMN `user_messages`;

-- Rinomina la tabella con un nome più coerente con eventi log strutturati.
ALTER TABLE `error_logs`
	RENAME TO `log_events`;
