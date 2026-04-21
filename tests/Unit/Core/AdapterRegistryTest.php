<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Core;

use Pair\Core\AdapterRegistry;
use Pair\Tests\Support\TestCase;

/**
 * Covers the explicit runtime adapter registry.
 */
class AdapterRegistryTest extends TestCase {

	/**
	 * Verify adapters can be registered, resolved, and removed by capability key.
	 */
	public function testRegisterResolveAndRemoveAdapter(): void {

		$registry = new AdapterRegistry();
		$adapter = new \ArrayObject(['ready' => true]);

		$registry->set('payments.gateway', $adapter);

		$this->assertTrue($registry->has('payments.gateway'));
		$this->assertSame($adapter, $registry->get('payments.gateway'));
		$this->assertSame($adapter, $registry->require('payments.gateway', \ArrayObject::class));

		$registry->remove('payments.gateway');

		$this->assertFalse($registry->has('payments.gateway'));
		$this->assertNull($registry->get('payments.gateway'));

	}

	/**
	 * Verify missing adapters fail explicitly when required.
	 */
	public function testRequireFailsWhenAdapterIsMissing(): void {

		$registry = new AdapterRegistry();

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Pair adapter "ai" has not been registered.');

		$registry->require('ai');

	}

	/**
	 * Verify type expectations protect consumers from wrong adapters.
	 */
	public function testExpectedTypeIsEnforced(): void {

		$registry = new AdapterRegistry();
		$registry->set('mailer', new \stdClass());

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Pair adapter "mailer" must implement ArrayAccess.');

		$registry->get('mailer', \ArrayAccess::class);

	}

	/**
	 * Verify adapter keys must be explicit and stable.
	 */
	public function testInvalidAdapterNameIsRejected(): void {

		$registry = new AdapterRegistry();

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid Pair adapter name');

		$registry->set('not valid', new \stdClass());

	}

}
