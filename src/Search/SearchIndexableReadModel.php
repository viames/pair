<?php

declare(strict_types=1);

namespace Pair\Search;

use Pair\Data\ReadModel;

/**
 * Contract for read models that can publish a Meilisearch-ready document.
 */
interface SearchIndexableReadModel extends ReadModel {

	/**
	 * Return the target Meilisearch index UID.
	 */
	public static function searchIndexUid(): string;

	/**
	 * Return the document primary key field used by the search index.
	 */
	public static function searchPrimaryKey(): string;

	/**
	 * Export the search document that should be indexed.
	 *
	 * @return	array<string, mixed>
	 */
	public function searchDocument(): array;

}
