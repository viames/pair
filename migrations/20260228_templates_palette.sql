-- Prepares for the new templates.palette JSON column by converting existing CSV string values to JSON arrays.
UPDATE `templates`
SET `palette` = CASE
	WHEN `palette` IS NULL OR TRIM(`palette`) = '' THEN JSON_ARRAY()
	WHEN JSON_VALID(`palette`) THEN CAST(`palette` AS JSON)
	ELSE CAST(
		CONCAT(
			'["',
			REPLACE(
				REPLACE(
					REPLACE(
						REPLACE(
							TRIM(`palette`),
							', ',
							','
						),
						' ,',
						','
					),
					'\\',
					'\\\\'
				),
				'"',
				'\\"'
			),
			'"]'
		) AS JSON
	)
END;

-- Remove deprecated templates.derived column and convert templates.palette from VARCHAR CSV to JSON type.
ALTER TABLE `templates`
	DROP COLUMN `derived`,
	MODIFY COLUMN `palette` JSON NOT NULL;
