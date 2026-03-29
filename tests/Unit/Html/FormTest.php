<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Html;

use Pair\Html\Form;
use Pair\Tests\Support\TestCase;

/**
 * Covers lightweight form rendering helpers that can be exercised without the full application stack.
 */
class FormTest extends TestCase {

	/**
	 * Verify form-level attributes and registered controls are rendered into the final markup.
	 */
	public function testRenderIncludesFormAttributesAndControls(): void {

		$form = (new Form())
			->action('/submit')
			->autocomplete(false)
			->attribute('data-track', 'save')
			->classForForm('stacked compact')
			->classForControls('form-control')
			->id('profile-form')
			->method('post');

		$form->text('displayName')
			->required()
			->placeholder('Display name')
			->value('Alice');

		$html = $form->render();

		$this->assertStringContainsString('<form ', $html);
		$this->assertStringContainsString('action="/submit"', $html);
		$this->assertStringContainsString('autocomplete="off"', $html);
		$this->assertStringContainsString('data-track="save"', $html);
		$this->assertStringContainsString('class="stacked compact"', $html);
		$this->assertStringContainsString('name="displayName"', $html);
		$this->assertStringContainsString('class="form-control"', $html);
		$this->assertStringContainsString('placeholder="Display name"', $html);
		$this->assertStringContainsString('value="Alice"', $html);

	}

	/**
	 * Verify duplicate form classes are collapsed while preserving insertion order.
	 */
	public function testClassForFormDeduplicatesCssClasses(): void {

		$form = (new Form())->classForForm('stacked compact compact');

		$this->assertStringContainsString('class="stacked compact"', $form->open());

	}

}
