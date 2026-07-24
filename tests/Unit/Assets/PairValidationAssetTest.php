<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Assets;

use Pair\Tests\Support\TestCase;

/**
 * Covers static accessibility and integration contracts for PairValidation.
 */
class PairValidationAssetTest extends TestCase {

	/**
	 * Verify full form validation stays opt-in and progressively disables native UI.
	 */
	public function testFormValidationIsProgressiveAndOptIn(): void {

		$source = $this->pairValidationSource();

		$this->assertStringContainsString("const FORM_SELECTOR = 'form[data-pair-validate]';", $source);
		$this->assertStringContainsString('function bindForm(form)', $source);
		$this->assertStringContainsString('form.noValidate = true;', $source);
		$this->assertStringContainsString('previousNoValidate: form.noValidate', $source);
		$this->assertStringContainsString('event.submitter.formNoValidate', $source);
		$this->assertStringContainsString('validateForm,', $source);
		$this->assertStringNotContainsString('jQuery', $source);

	}

	/**
	 * Verify field feedback preserves ARIA relationships and custom widget adapters.
	 */
	public function testFieldFeedbackIsAccessibleAndAdapterAware(): void {

		$source = $this->pairValidationSource();

		$this->assertStringContainsString("candidate.setAttribute('aria-invalid', 'true');", $source);
		$this->assertStringContainsString("addAttributeToken(candidate, 'aria-describedby', errorId);", $source);
		$this->assertStringContainsString("registerAdapter('select2'", $source);
		$this->assertStringContainsString("registerAdapter('nice-select2'", $source);
		$this->assertStringContainsString('setFieldError,', $source);
		$this->assertStringContainsString('setServerErrors,', $source);
		$this->assertStringContainsString('pair:validation:form-invalid', $source);
		$this->assertStringContainsString('pair:validation:field-valid', $source);
		$this->assertStringContainsString('function validationFieldFor(field)', $source);
		$this->assertStringContainsString('function validityFieldsFor(field)', $source);
		$this->assertStringContainsString('field.form.elements.namedItem(field.name)', $source);
		$this->assertStringContainsString('function observeCustomWidgets(form)', $source);
		$this->assertStringContainsString('new MutationObserver', $source);
		$this->assertStringContainsString('function customWidgetFieldForMutation(form, target)', $source);
		$this->assertStringContainsString('function announceFieldError(form, field)', $source);
		$this->assertStringContainsString('label.htmlFor = candidate;', $source);
		$this->assertStringContainsString('delete field.dataset.pairValidationTouched;', $source);
		$this->assertStringNotContainsString("addEventListener('ep:content-replaced'", $source);

	}

	/**
	 * Verify messages cover the HTML ValidityState error families.
	 */
	public function testValidityStateMessagesAreCustomizable(): void {

		$source = $this->pairValidationSource();

		foreach ([
			'customError',
			'valueMissing',
			'typeMismatch',
			'patternMismatch',
			'tooShort',
			'tooLong',
			'rangeUnderflow',
			'rangeOverflow',
			'stepMismatch',
			'badInput',
		] as $validityState) {
			$this->assertStringContainsString("'" . $validityState . "'", $source);
		}

		$this->assertStringContainsString('function configure(options = {})', $source);
		$this->assertStringContainsString('data-pair-validation-summary', $source);
		$this->assertStringContainsString('function summaryMessage(form, count)', $source);
		$this->assertStringContainsString('formValidationOptions.messages[rule]', $source);

	}

	/**
	 * Return the PairValidation client source code.
	 */
	private function pairValidationSource(): string {

		$source = file_get_contents(dirname(__DIR__, 3) . '/assets/PairValidation.js');

		if (!is_string($source)) {
			$this->fail('Unable to read PairValidation.js');
		}

		return $source;

	}

}
