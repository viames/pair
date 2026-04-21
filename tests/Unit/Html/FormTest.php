<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Html;

use Pair\Html\Form;
use Pair\Html\FormControls\Text;
use Pair\Html\UiTheme;
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
	 * Verify native rendering does not inject framework classes when no UI framework is selected.
	 */
	public function testNativeThemeDoesNotInjectFrameworkClasses(): void {

		$form = new Form();
		$form->text('displayName')->label('Display name');
		$form->textarea('bio')->label('Biography');
		$form->button('save')->caption('Save');
		$form->select('status')->options([
			'draft' => 'Draft',
			'live' => 'Live',
		]);

		$controlsHtml = $form->renderControls();
		$labelHtml = $form->control('displayName')->renderLabel();

		$this->assertStringNotContainsString('class="input"', $controlsHtml);
		$this->assertStringNotContainsString('class="textarea"', $controlsHtml);
		$this->assertStringNotContainsString('class="button"', $controlsHtml);
		$this->assertStringNotContainsString('<div class="select">', $controlsHtml);
		$this->assertStringNotContainsString('class="label"', $labelHtml);

	}

	/**
	 * Verify duplicate form classes are collapsed while preserving insertion order.
	 */
	public function testClassForFormDeduplicatesCssClasses(): void {

		$form = (new Form())->classForForm('stacked compact compact');

		$this->assertStringContainsString('class="stacked compact"', $form->open());

	}

	/**
	 * Verify label descriptions render a native tooltip marker when no UI framework is selected.
	 */
	public function testNativeThemeRendersNativeLabelHelpTooltip(): void {

		$control = (new Text('email'))
			->label('Email')
			->description('Used for notifications.');

		$html = $control->renderLabel();

		$this->assertStringContainsString('<abbr class="form-control-help" title="Used for notifications."', $html);
		$this->assertStringContainsString('<span aria-hidden="true">?</span>', $html);
		$this->assertStringNotContainsString('data-toggle="tooltip"', $html);
		$this->assertStringNotContainsString('data-tooltip=', $html);

	}

	/**
	 * Verify label descriptions render Bootstrap tooltip attributes when Bootstrap is selected explicitly.
	 */
	public function testBootstrapThemeRendersBootstrapLabelHelpTooltip(): void {

		UiTheme::setCurrent('bootstrap');

		$control = (new Text('email'))
			->label('Email')
			->description('Used for notifications.');

		$html = $control->renderLabel();

		$this->assertStringContainsString('data-toggle="tooltip"', $html);
		$this->assertStringContainsString('data-bs-toggle="tooltip"', $html);
		$this->assertStringContainsString('title="Used for notifications."', $html);
		$this->assertStringContainsString('<span aria-hidden="true">?</span>', $html);

	}

	/**
	 * Verify label descriptions render Bulma tooltip attributes when Bulma is selected.
	 */
	public function testBulmaThemeRendersBulmaLabelHelpTooltip(): void {

		UiTheme::setCurrent('bulma');

		$control = (new Text('email'))
			->label('Email')
			->description('Used for notifications.');

		$html = $control->renderLabel();

		$this->assertStringContainsString('class="label"', $html);
		$this->assertStringContainsString('has-tooltip-arrow', $html);
		$this->assertStringContainsString('data-tooltip="Used for notifications."', $html);
		$this->assertStringContainsString('<span aria-hidden="true">?</span>', $html);

	}

	/**
	 * Verify Bulma theme classes are injected automatically on supported controls.
	 */
	public function testBulmaThemeAddsControlAndLabelClasses(): void {

		UiTheme::setCurrent('bulma');

		$form = new Form();
		$form->text('displayName')->label('Display name');
		$form->textarea('bio')->label('Biography');
		$form->button('save')->caption('Save');
		$form->select('status')->options([
			'draft' => 'Draft',
			'live' => 'Live',
		]);

		$labelsHtml = $form->control('displayName')->renderLabel();
		$controlsHtml = $form->renderControls();

		$this->assertStringContainsString('class="label"', $labelsHtml);
		$this->assertStringContainsString('class="input"', $controlsHtml);
		$this->assertStringContainsString('class="textarea"', $controlsHtml);
		$this->assertStringContainsString('class="button"', $controlsHtml);
		$this->assertStringContainsString('<div class="select">', $controlsHtml);

	}

	/**
	 * Verify standalone controls outside Form still inherit Bulma classes.
	 */
	public function testStandaloneControlRenderUsesActiveThemeDefaults(): void {

		UiTheme::setCurrent('bulma');

		$control = (new Text('title'))->placeholder('Title');

		$this->assertStringContainsString('class="input"', $control->render());

	}

}
