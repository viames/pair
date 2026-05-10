<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Helpers;

use Pair\Helpers\Translator;
use Pair\Tests\Support\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Covers translation placeholder formatting contracts.
 */
class TranslatorTest extends TestCase {

	/**
	 * Verify the public translator accepts numeric placeholder values without accepting booleans.
	 */
	public function testDoSignatureAcceptsNumericPlaceholderVariables(): void {

		$parameter = (new ReflectionMethod(Translator::class, 'do'))->getParameters()[1];
		$type = $parameter->getType();

		$this->assertInstanceOf(ReflectionUnionType::class, $type);

		$typeNames = array_map(
			static fn(ReflectionNamedType $namedType): string => $namedType->getName(),
			$type->getTypes()
		);

		$this->assertContains('int', $typeNames);
		$this->assertContains('float', $typeNames);
		$this->assertNotContains('bool', $typeNames);

	}

	/**
	 * Verify safe fallback formatting handles integer placeholder values.
	 */
	public function testSafeDoFormatsIntegerPlaceholderVariables(): void {

		$this->assertSame(
			'ActiveRecord class errors: 3',
			Translator::safeDo('TEST_INTEGER_PLACEHOLDER', 3, 'ActiveRecord class errors: %s')
		);

	}

	/**
	 * Verify safe fallback formatting handles float placeholder values.
	 */
	public function testSafeDoFormatsFloatPlaceholderVariables(): void {

		$this->assertSame('Score: 2.5', Translator::safeDo('TEST_FLOAT_PLACEHOLDER', 2.5, 'Score: %s'));

	}

}
