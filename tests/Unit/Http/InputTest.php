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

}
