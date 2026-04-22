<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Pair\Data\ArraySerializableData;
use Pair\Data\ReadModel;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Search\SearchIndexableReadModel;
use Pair\Services\MeilisearchClient;
use Pair\Tests\Support\TestCase;

/**
 * Covers MeilisearchClient request shaping without calling Meilisearch.
 */
class MeilisearchClientTest extends TestCase {

	/**
	 * Define the minimal routing constant needed by PairException logging in isolated tests.
	 */
	protected function setUp(): void {

		parent::setUp();

		if (!defined('URL_PATH')) {
			define('URL_PATH', null);
		}

	}

	/**
	 * Verify index creation includes the UID and primary key.
	 */
	public function testCreateIndexBuildsPayload(): void {

		$client = new FakeMeilisearchClient();

		$response = $client->createIndex('products', 'id');
		$request = $client->lastRequest();

		$this->assertSame(101, $response['taskUid']);
		$this->assertSame('POST', $request['method']);
		$this->assertSame('/indexes', $request['path']);
		$this->assertSame(['uid' => 'products', 'primaryKey' => 'id'], $request['payload']);

	}

	/**
	 * Verify full document replacement uses the primaryKey query parameter.
	 */
	public function testAddOrReplaceDocumentsBuildsPrimaryKeyQuery(): void {

		$client = new FakeMeilisearchClient();

		$client->addOrReplaceDocuments('products', [
			['id' => 1, 'title' => 'Espresso'],
		], 'id');

		$request = $client->lastRequest();

		$this->assertSame('POST', $request['method']);
		$this->assertSame('/indexes/products/documents?primaryKey=id', $request['path']);
		$this->assertSame([['id' => 1, 'title' => 'Espresso']], $request['payload']);

	}

	/**
	 * Verify partial document updates use the PUT documents endpoint.
	 */
	public function testAddOrUpdateDocumentsBuildsUpdateRequest(): void {

		$client = new FakeMeilisearchClient();

		$client->addOrUpdateDocuments('products', [
			['id' => 1, 'price' => 12.5],
		]);

		$request = $client->lastRequest();

		$this->assertSame('PUT', $request['method']);
		$this->assertSame('/indexes/products/documents', $request['path']);
		$this->assertSame([['id' => 1, 'price' => 12.5]], $request['payload']);

	}

	/**
	 * Verify read models implementing SearchIndexableReadModel provide index metadata and documents.
	 */
	public function testIndexReadModelsUsesSearchContractDefaults(): void {

		$client = new FakeMeilisearchClient();

		$client->indexReadModels([
			new FakeSearchProductReadModel(7, 'Arabica', true),
		], replace: true);

		$request = $client->lastRequest();

		$this->assertSame('POST', $request['method']);
		$this->assertSame('/indexes/products/documents?primaryKey=id', $request['path']);
		$this->assertSame([
			[
				'id' => 7,
				'title' => 'Arabica',
				'is_active' => true,
			],
		], $request['payload']);

	}

	/**
	 * Verify plain read models can still be indexed when the caller supplies index metadata.
	 */
	public function testIndexReadModelsSupportsPlainReadModelsWithExplicitIndex(): void {

		$client = new FakeMeilisearchClient();

		$client->indexReadModels([
			new FakePlainSearchReadModel(9, 'Plain payload'),
		], 'articles', 'id');

		$request = $client->lastRequest();

		$this->assertSame('PUT', $request['method']);
		$this->assertSame('/indexes/articles/documents?primaryKey=id', $request['path']);
		$this->assertSame([['id' => 9, 'title' => 'Plain payload']], $request['payload']);

	}

	/**
	 * Verify plain read models require explicit index metadata.
	 */
	public function testPlainReadModelRequiresExplicitIndex(): void {

		$this->expectException(PairException::class);
		$this->expectExceptionCode(ErrorCodes::MEILISEARCH_ERROR);

		(new FakeMeilisearchClient())->indexReadModels([
			new FakePlainSearchReadModel(9, 'Plain payload'),
		]);

	}

	/**
	 * Verify search requests support filters, facets, pagination, and vector/hybrid options.
	 */
	public function testSearchNormalizesAdvancedOptions(): void {

		$client = new FakeMeilisearchClient();

		$client->search('products', 'coffee', [
			'filter' => 'is_active = true',
			'facets' => ['category'],
			'hits_per_page' => 10,
			'page' => 2,
			'hybrid' => ['semanticRatio' => 0.5, 'embedder' => 'default'],
			'vector' => [0.1, 0.2],
			'retrieve_vectors' => true,
			'attributes_to_retrieve' => ['id', 'title'],
		]);

		$request = $client->lastRequest();

		$this->assertSame('POST', $request['method']);
		$this->assertSame('/indexes/products/search', $request['path']);
		$this->assertSame('coffee', $request['payload']['q']);
		$this->assertSame('is_active = true', $request['payload']['filter']);
		$this->assertSame(['category'], $request['payload']['facets']);
		$this->assertSame(10, $request['payload']['hitsPerPage']);
		$this->assertSame(2, $request['payload']['page']);
		$this->assertSame(['semanticRatio' => 0.5, 'embedder' => 'default'], $request['payload']['hybrid']);
		$this->assertSame([0.1, 0.2], $request['payload']['vector']);
		$this->assertTrue($request['payload']['retrieveVectors']);
		$this->assertSame(['id', 'title'], $request['payload']['attributesToRetrieve']);
		$this->assertArrayNotHasKey('limit', $request['payload']);

	}

	/**
	 * Verify search defaults to the configured limit when pagination options are absent.
	 */
	public function testSearchUsesDefaultLimitWhenPaginationIsAbsent(): void {

		$client = new FakeMeilisearchClient();

		$client->search('products', 'coffee');

		$this->assertSame(20, $client->lastRequest()['payload']['limit']);

	}

	/**
	 * Verify facet search maps aliases to Meilisearch payload keys.
	 */
	public function testFacetSearchBuildsPayload(): void {

		$client = new FakeMeilisearchClient();

		$client->facetSearch('products', 'category', 'cof', [
			'filter' => 'is_active = true',
			'matching_strategy' => 'last',
		]);

		$request = $client->lastRequest();

		$this->assertSame('POST', $request['method']);
		$this->assertSame('/indexes/products/facet-search', $request['path']);
		$this->assertSame('category', $request['payload']['facetName']);
		$this->assertSame('cof', $request['payload']['facetQuery']);
		$this->assertSame('last', $request['payload']['matchingStrategy']);

	}

	/**
	 * Verify index settings can configure filtering, sorting, faceting, and embedders.
	 */
	public function testUpdateSettingsNormalizesSearchSettings(): void {

		$client = new FakeMeilisearchClient();

		$client->updateSettings('products', [
			'filterable_attributes' => ['category', 'is_active'],
			'sortable_attributes' => ['price'],
			'searchable_attributes' => ['title', 'description'],
			'embedders' => [
				'default' => [
					'source' => 'userProvided',
					'dimensions' => 3,
				],
			],
		]);

		$request = $client->lastRequest();

		$this->assertSame('PATCH', $request['method']);
		$this->assertSame('/indexes/products/settings', $request['path']);
		$this->assertSame(['category', 'is_active'], $request['payload']['filterableAttributes']);
		$this->assertSame(['price'], $request['payload']['sortableAttributes']);
		$this->assertSame(['title', 'description'], $request['payload']['searchableAttributes']);
		$this->assertSame('userProvided', $request['payload']['embedders']['default']['source']);

	}

	/**
	 * Verify empty arrays are preserved because Meilisearch uses them to clear settings.
	 */
	public function testUpdateSettingsKeepsEmptyArrays(): void {

		$client = new FakeMeilisearchClient();

		$client->updateSettings('products', [
			'filterable_attributes' => [],
		]);

		$this->assertSame([], $client->lastRequest()['payload']['filterableAttributes']);

	}

	/**
	 * Verify delete-batch requests send document identifiers as the payload.
	 */
	public function testDeleteDocumentsBuildsBatchPayload(): void {

		$client = new FakeMeilisearchClient();

		$client->deleteDocuments('products', [1, 'sku-2']);

		$request = $client->lastRequest();

		$this->assertSame('POST', $request['method']);
		$this->assertSame('/indexes/products/documents/delete-batch', $request['path']);
		$this->assertSame(['1', 'sku-2'], $request['payload']);

	}

	/**
	 * Verify the configured API key helper reflects client configuration.
	 */
	public function testApiKeySetReflectsConfiguration(): void {

		$this->assertTrue((new FakeMeilisearchClient())->apiKeySet());
		$this->assertFalse((new MeilisearchClient('https://search.example.test', ''))->apiKeySet());

	}

	/**
	 * Verify empty document lists are rejected before outbound requests.
	 */
	public function testEmptyDocumentsAreRejected(): void {

		$this->expectException(PairException::class);
		$this->expectExceptionCode(ErrorCodes::MEILISEARCH_ERROR);

		(new FakeMeilisearchClient())->addOrReplaceDocuments('products', []);

	}

}

/**
 * Test double that records Meilisearch requests and returns deterministic payloads.
 */
final class FakeMeilisearchClient extends MeilisearchClient {

	/**
	 * Captured Meilisearch requests.
	 *
	 * @var	array<int, array{method: string, path: string, payload: array}>
	 */
	private array $requests = [];

	/**
	 * Build the fake with deterministic host and key values.
	 */
	public function __construct() {

		parent::__construct('https://search.example.test', 'master-key', 'products');

	}

	/**
	 * Return the most recent captured request.
	 *
	 * @return	array{method: string, path: string, payload: array}
	 */
	public function lastRequest(): array {

		return $this->requests[count($this->requests) - 1] ?? ['method' => '', 'path' => '', 'payload' => []];

	}

	/**
	 * Capture request details and return deterministic Meilisearch-shaped responses.
	 */
	protected function requestJson(string $method, string $path, array $payload = []): array {

		$this->requests[] = [
			'method' => $method,
			'path' => $path,
			'payload' => $payload,
		];

		if (str_ends_with($path, '/search')) {
			return [
				'hits' => [],
				'query' => $payload['q'] ?? '',
				'limit' => $payload['limit'] ?? null,
			];
		}

		return [
			'taskUid' => 101,
			'status' => 'enqueued',
			'type' => 'documentAdditionOrUpdate',
		];

	}

}

/**
 * Search-indexable read model used by Meilisearch tests.
 */
final readonly class FakeSearchProductReadModel implements SearchIndexableReadModel {

	use ArraySerializableData;

	/**
	 * Build the fake read model.
	 */
	public function __construct(private int $id, private string $title, private bool $active) {}

	/**
	 * Return the target test index UID.
	 */
	public static function searchIndexUid(): string {

		return 'products';

	}

	/**
	 * Return the target test primary key.
	 */
	public static function searchPrimaryKey(): string {

		return 'id';

	}

	/**
	 * Export API-facing data.
	 *
	 * @return	array<string, mixed>
	 */
	public function toArray(): array {

		return [
			'id' => $this->id,
			'title' => $this->title,
		];

	}

	/**
	 * Export the search document.
	 *
	 * @return	array<string, mixed>
	 */
	public function searchDocument(): array {

		return [
			'id' => $this->id,
			'title' => $this->title,
			'is_active' => $this->active,
		];

	}

}

/**
 * Plain read model used to verify explicit index metadata fallback.
 */
final readonly class FakePlainSearchReadModel implements ReadModel {

	use ArraySerializableData;

	/**
	 * Build the fake plain read model.
	 */
	public function __construct(private int $id, private string $title) {}

	/**
	 * Export the read model.
	 *
	 * @return	array<string, mixed>
	 */
	public function toArray(): array {

		return [
			'id' => $this->id,
			'title' => $this->title,
		];

	}

}
