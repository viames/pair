-- New column to indicate the source of the migration (app or pair).
ALTER TABLE `migrations`
ADD COLUMN `source` ENUM('app','pair') NOT NULL DEFAULT 'app' AFTER `file`;

-- Update existing records to set the source to 'app' for all migrations that currently have a NULL or empty source.
UPDATE `migrations`
SET `source` = 'app'
WHERE `source` IS NULL OR `source` = '';
