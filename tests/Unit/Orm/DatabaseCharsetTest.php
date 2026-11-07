<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Orm;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Orm\Database;
use Pair\Tests\Support\TestCase;

/** Covers the legacy charset helper without database administration privileges. */
final class DatabaseCharsetTest extends TestCase {

	/** A runtime account needs only a session command, even when the helper is called twice. */
	public function testSessionCharset(): void {
		$pdo = $this->createMock(\PDO::class);
		$pdo->expects($this->exactly(2))->method('exec')
			->with('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci')->willReturn(0);
		$pdo->expects($this->never())->method('prepare');
		$database = Database::getInstance();
		$this->setInaccessibleProperty($database, 'handler', $pdo);

		$database->setUtf8unicode();
		$database->setUtf8unicode();
	}

	/** Charset failures keep the framework exception code and original PDO cause. */
	public function testCharsetFailure(): void {
		$cause = new \PDOException('Synthetic charset failure');
		$pdo = $this->createMock(\PDO::class);
		$pdo->expects($this->once())->method('exec')->willThrowException($cause);
		$database = Database::getInstance();
		$this->setInaccessibleProperty($database, 'handler', $pdo);

		try {
			$database->setUtf8unicode();
			$this->fail('Expected a charset exception.');
		} catch (PairException $error) {
			$this->assertSame(ErrorCodes::DB_QUERY_FAILED, $error->getCode());
			$this->assertSame($cause, $error->getPrevious());
		}
	}

	/** Release the synthetic connection before another test obtains the singleton. */
	protected function tearDown(): void {
		(new \ReflectionProperty(Database::class, 'instance'))->setValue(null, null);
		parent::tearDown();
	}
}
