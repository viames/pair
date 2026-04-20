<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Http;

use Pair\Http\Input;
use Pair\Tests\Support\TestCase;

/**
 * Covers the immutable v4 request input object.
 */
class InputTest extends TestCase {

	/**
	 * Verify merged values prefer body input and preserve typed accessors.
	 */
	public function testInputMergesBodyOverQueryAndCastsValues(): void {

		$input = new Input(
			'post',
			['status' => 'draft', 'page' => '2'],
			['status' => 'published', 'featured' => 'true', 'tags' => ['one', 'two']],
			['content-type' => 'application/json']
		);

		$this->assertSame('POST', $input->method());
		$this->assertSame('published', $input->string('status'));
		$this->assertSame(2, $input->int('page'));
		$this->assertTrue($input->bool('featured'));
		$this->assertSame(['one', 'two'], $input->array('tags'));
		$this->assertSame('application/json', $input->header('Content-Type'));

	}

	/**
	 * Verify fromGlobals parses JSON bodies only when the request advertises JSON input.
	 */
	public function testFromGlobalsParsesJsonBody(): void {

		$_SERVER['REQUEST_METHOD'] = 'PATCH';
		$_SERVER['CONTENT_TYPE'] = 'application/json';
		$_GET = ['page' => '4'];

		$input = Input::fromGlobals('{"name":"Alice","active":"1"}');

		$this->assertSame('PATCH', $input->method());
		$this->assertSame('Alice', $input->string('name'));
		$this->assertTrue($input->bool('active'));
		$this->assertSame(4, $input->int('page'));

	}

	/**
	 * Verify has() and only() keep falsy values that are still explicitly present in the merged input.
	 */
	public function testHasAndOnlyTreatFalsyValuesAsPresent(): void {

		$input = new Input(
			'POST',
			['page' => '0'],
			['featured' => false, 'title' => '', 'missing' => null]
		);

		$this->assertTrue($input->has('page'));
		$this->assertTrue($input->has('featured'));
		$this->assertTrue($input->has('title'));
		$this->assertFalse($input->has('missing'));
		$this->assertSame([
			'page' => '0',
			'featured' => false,
			'title' => '',
		], $input->only(['page', 'featured', 'title', 'missing']));

	}

	/**
	 * Verify fromGlobals() preserves form POST data and falls back to an empty body for invalid JSON.
	 */
	public function testFromGlobalsPreservesPostBodyAndIgnoresInvalidJson(): void {

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['CONTENT_TYPE'] = 'application/json';
		$_POST = ['name' => 'Form value'];

		$formInput = Input::fromGlobals('{"name":"Json value"}');

		$this->assertSame('Form value', $formInput->string('name'));

		$_POST = [];

		$invalidJsonInput = Input::fromGlobals('{"name":');

		$this->assertSame([], $invalidJsonInput->body());
		$this->assertSame([], $invalidJsonInput->all());

	}

}
