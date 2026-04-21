<?php

declare(strict_types=1);

namespace Pair\Html\UiRenderers;

use Pair\Core\Router;
use Pair\Html\FormControl;
use Pair\Html\UiTheme;

/**
 * Bulma-compatible renderer for Pair HTML helpers.
 */
class BulmaUiRenderer extends NativeUiRenderer {

	/**
	 * Return the Bulma renderer name.
	 */
	public function name(): string {

		return UiTheme::BULMA;

	}

	/**
	 * Return Bulma classes automatically injected on known controls.
	 *
	 * @return	string[]
	 */
	public function controlClasses(FormControl $control): array {

		if (is_a($control, 'Pair\Html\FormControls\Button')) {
			return ['button'];
		}

		if (is_a($control, 'Pair\Html\FormControls\Textarea')) {
			return ['textarea'];
		}

		// Select and file inputs need dedicated wrappers, while custom widgets keep their own markup.
		if (
			is_a($control, 'Pair\Html\FormControls\Select')
			or is_a($control, 'Pair\Html\FormControls\File')
			or is_a($control, 'Pair\Html\FormControls\Checkbox')
			or is_a($control, 'Pair\Html\FormControls\Toggle')
			or is_a($control, 'Pair\Html\FormControls\Hidden')
			or is_a($control, 'Pair\Html\FormControls\Image')
			or is_a($control, 'Pair\Html\FormControls\Meter')
			or is_a($control, 'Pair\Html\FormControls\Progress')
		) {
			return [];
		}

		return ['input'];

	}

	/**
	 * Return Bulma label classes plus caller-provided classes.
	 */
	public function labelClasses(?string $customClasses = null): ?string {

		return $this->mergeClasses(['label'], $customClasses);

	}

	/**
	 * Render Bulma tooltip-compatible markup for label descriptions.
	 */
	public function labelHelpTooltip(string $description): string {

		$description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
		$questionMark = '<span aria-hidden="true">?</span>';
		$ariaLabel = ' aria-label="' . $description . '"';

		return '<span class="form-control-help has-tooltip-arrow has-tooltip-multiline" role="button" tabindex="0" data-tooltip="' . $description . '"' . $ariaLabel . '>' . $questionMark . '</span>';

	}

	/**
	 * Return Bulma select wrapper classes.
	 */
	public function selectWrapperClasses(bool $multiple = false): ?string {

		return $multiple ? 'select is-multiple' : 'select';

	}

	/**
	 * Return the Bulma notification class.
	 */
	public function alertClass(string $variant = 'primary'): string {

		return 'notification is-' . $this->normalizeVariant($variant, true);

	}

	/**
	 * Return the Bulma tag class.
	 */
	public function badgeClass(string $variant = 'primary'): string {

		return 'tag is-' . $this->normalizeVariant($variant, true);

	}

	/**
	 * Return the Bulma floating helper.
	 */
	public function endAlignmentClass(): string {

		return 'is-pulled-right';

	}

	/**
	 * Render Bulma pagination markup.
	 */
	public function pagination(Router $router, int $page, int $pages): string {

		$range = $this->paginationRange($page, $pages);
		$render = '<nav class="pagination" role="navigation" aria-label="pagination">';

		if ($page > 1) {
			$render .= '<a class="pagination-previous" href="' . $router->getPageUrl(1) . '" aria-label="Go to the first page">«</a>';
		}

		if ($page < $pages) {
			$render .= '<a class="pagination-next" href="' . $router->getPageUrl($pages) . '" aria-label="Go to the last page">»</a>';
		}

		$render .= '<ul class="pagination-list">';

		for ($i = $range['min']; $i <= $range['max']; $i++) {

			$linkClass = ($i == $page) ? 'pagination-link is-current' : 'pagination-link';
			$aria = ($i == $page)
				? ' aria-label="Page ' . $i . '" aria-current="page"'
				: ' aria-label="Go to page ' . $i . '"';

			$render .= '<li><a class="' . $linkClass . '"' . $aria . ' href="' . $router->getPageUrl($i) . '">' . $i . '</a></li>';

		}

		$render .= '</ul></nav>';

		return $render;

	}

}
