<?php

declare(strict_types=1);

use Pair\Data\ArraySerializableData;
use Pair\Data\MapsFromRecord;
use Pair\Data\ReadModel;
use Pair\Http\Input;
use Pair\Http\JsonResponse;
use Pair\Orm\ActiveRecord;
use Pair\Web\PageResponse;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Minimal ActiveRecord double used for v3/v4 benchmark comparisons.
 */
final class BenchmarkRecord extends ActiveRecord {

	/**
	 * Synthetic primary key definition.
	 */
	public const TABLE_KEY = 'id';

	/**
	 * Synthetic table name.
	 */
	public const TABLE_NAME = 'benchmark_records';

	/**
	 * Synthetic identifier.
	 */
	public mixed $id = null;

	/**
	 * Synthetic name.
	 */
	public mixed $name = null;

	/**
	 * Synthetic email.
	 */
	public mixed $email = null;

	/**
	 * Return a stable bind map for the benchmark double.
	 *
	 * @return	array<string, string>
	 */
	public static function getBinds(): array {

		return [
			'id' => 'id',
			'name' => 'name',
			'email' => 'email',
		];

	}

	/**
	 * Seed the benchmark record with scalar values.
	 *
	 * @param	array<string, mixed>	$payload	Scalar benchmark payload.
	 */
	public function seed(array $payload): static {

		$this->keyProperties = ['id'];
		$this->id = $payload['id'] ?? null;
		$this->name = $payload['name'] ?? null;
		$this->email = $payload['email'] ?? null;

		return $this;

	}

	/**
	 * Export the benchmark record as a plain array.
	 *
	 * @return	array<string, mixed>
	 */
	public function toArray(): array {

		return [
			'id' => $this->id,
			'name' => $this->name,
			'email' => $this->email,
		];

	}

}

/**
 * Small explicit read model used in benchmark comparisons.
 */
final readonly class BenchmarkReadModel implements ReadModel, MapsFromRecord {

	use ArraySerializableData;

	/**
	 * Build the benchmark read model.
	 */
	public function __construct(
		public int $id,
		public string $name,
		public string $email
	) {}

	/**
	 * Map the benchmark record into the explicit read model.
	 */
	public static function fromRecord(ActiveRecord $record): static {

		return new self(
			(int)$record->id,
			(string)$record->name,
			(string)$record->email
		);

	}

	/**
	 * Export the read model as an array.
	 *
	 * @return	array<string, mixed>
	 */
	public function toArray(): array {

		return [
			'id' => $this->id,
			'name' => $this->name,
			'email' => $this->email,
		];

	}

}

/**
 * Simple typed page state used for layout benchmarks.
 */
final readonly class BenchmarkPageState {

	/**
	 * Build the page state.
	 */
	public function __construct(public string $message) {}

}

$recordReflection = new ReflectionClass(BenchmarkRecord::class);
$record = $recordReflection->newInstanceWithoutConstructor();
$record->seed([
	'id' => 7,
	'name' => 'Alice',
	'email' => 'alice@example.test',
]);

$templateFile = createBenchmarkTemplate();

$scenarios = [
	'bootstrap_minimal_v4_input' => [
		'iterations' => 100000,
		'run' => static function(): void {
			new Input('POST', ['page' => '1'], ['name' => 'Alice'], ['content-type' => 'application/json']);
		},
	],
	'record_payload_v3_to_array' => [
		'iterations' => 200000,
		'run' => static function() use ($record): void {
			$record->toArray();
		},
	],
	'record_payload_v4_read_model' => [
		'iterations' => 200000,
		'run' => static function() use ($record): void {
			BenchmarkReadModel::fromRecord($record)->toArray();
		},
	],
	'json_endpoint_v4_prepare' => [
		'iterations' => 200000,
		'run' => static function() use ($record): void {
			$payload = BenchmarkReadModel::fromRecord($record);
			new JsonResponse($payload, 200);
			json_encode($payload->toArray());
		},
	],
	'server_rendered_v4_page' => [
		'iterations' => 20000,
		'run' => static function() use ($templateFile): void {
			$response = new PageResponse($templateFile, new BenchmarkPageState('Hello Pair v4'));
			ob_start();
			$response->send();
			ob_end_clean();
		},
	],
];

print "Pair v4 benchmarks\n";
print "==================\n";

foreach ($scenarios as $name => $scenario) {

	$result = benchmark((int)$scenario['iterations'], $scenario['run']);

	printf(
		"%-28s total=%8.3fms avg=%8.3fus iterations=%d\n",
		$name,
		$result['totalMs'],
		$result['avgUs'],
		$scenario['iterations']
	);

}

if (file_exists($templateFile)) {
	unlink($templateFile);
}

/**
 * Benchmark a closure for a fixed number of iterations.
 *
 * @return	array{avgUs: float, totalMs: float}
 */
function benchmark(int $iterations, callable $callback): array {

	$start = hrtime(true);

	for ($i = 0; $i < $iterations; $i++) {
		$callback();
	}

	$elapsedNanoseconds = hrtime(true) - $start;
	$totalMilliseconds = $elapsedNanoseconds / 1_000_000;
	$averageMicroseconds = $elapsedNanoseconds / $iterations / 1_000;

	return [
		'avgUs' => $averageMicroseconds,
		'totalMs' => $totalMilliseconds,
	];

}

/**
 * Create the temporary PHP layout file used by the page-response benchmark.
 */
function createBenchmarkTemplate(): string {

	$templateFile = tempnam(sys_get_temp_dir(), 'pair-benchmark-layout-');

	if (false === $templateFile) {
		throw new RuntimeException('Unable to allocate a temporary layout for benchmark-v4.');
	}

	file_put_contents($templateFile, '<?php print htmlspecialchars($state->message, ENT_QUOTES, "UTF-8"); ?>');

	return $templateFile;

}
