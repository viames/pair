<?php

declare(strict_types=1);

namespace Pair\Html\UiRenderers;

use Pair\Core\Router;
use Pair\Helpers\Translator;
use Pair\Html\FormControl;
use Pair\Html\UiRenderer;
use Pair\Html\UiTheme;

/**
 * Native HTML renderer with no framework-specific CSS classes.
 */
class NativeUiRenderer implements UiRenderer {

	/**
	 * Return the native renderer name.
	 */
	public function name(): string {

		return UiTheme::NATIVE;

	}

	/**
	 * Return no automatic control classes for native HTML.
	 *
	 * @return	string[]
	 */
	public function controlClasses(FormControl $control): array {

		return [];

	}

	/**
	 * Return caller-provided label classes without framework additions.
	 */
	public function labelClasses(?string $customClasses = null): ?string {

		return $this->mergeClasses([], $customClasses);

	}

	/**
	 * Render native tooltip markup for label descriptions.
	 */
	public function labelHelpTooltip(string $description): string {

		$description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
		$questionMark = '<span aria-hidden="true">?</span>';
		$ariaLabel = ' aria-label="' . $description . '"';

		return '<abbr class="form-control-help" title="' . $description . '"' . $ariaLabel . '>' . $questionMark . '</abbr>';

	}

	/**
	 * Native selects do not need wrappers.
	 */
	public function selectWrapperClasses(bool $multiple = false): ?string {

		return null;

	}

	/**
	 * Native alerts do not receive framework classes.
	 */
	public function alertClass(string $variant = 'primary'): string {

		return '';

	}

	/**
	 * Native badges do not receive framework classes.
	 */
	public function badgeClass(string $variant = 'primary'): string {

		return '';

	}

	/**
	 * Native markup has no alignment helper.
	 */
	public function endAlignmentClass(): string {

		return '';

	}

	/**
	 * Render native semantic pagination markup.
	 */
	public function pagination(Router $router, int $page, int $pages): string {

		$range = $this->paginationRange($page, $pages);
		$render = '<nav aria-label="' . $this->translatedAttribute('PAGINATION') . '"><ul>';

		if ($page > 1) {
			$render .= '<li><a href="' . $router->getPageUrl(1) . '" aria-label="' . $this->translatedAttribute('GO_TO_FIRST_PAGE') . '">«</a></li>';
		}

		for ($i = $range['min']; $i <= $range['max']; $i++) {
			$ariaCurrent = ($i == $page) ? ' aria-current="page"' : '';
			$render .= '<li><a href="' . $router->getPageUrl($i) . '"' . $ariaCurrent . '>' . $i . '</a></li>';
		}

		if ($page < $pages) {
			$render .= '<li><a href="' . $router->getPageUrl($pages) . '" aria-label="' . $this->translatedAttribute('GO_TO_LAST_PAGE') . '">»</a></li>';
		}

		$render .= '</ul></nav>';

		return $render;

	}

	/**
	 * Return a translated value escaped for HTML attribute context.
	 */
	protected function translatedAttribute(string $key, string|array|null $vars = null): string {

		return htmlspecialchars(Translator::safeDo($key, $vars), ENT_QUOTES, 'UTF-8');

	}

	/**
	 * Merge framework and caller classes while preserving insertion order.
	 */
	protected function mergeClasses(array $baseClasses, ?string $customClasses = null): ?string {

		$classes = $baseClasses;

		foreach (preg_split('/\s+/', trim((string)$customClasses)) ?: [] as $className) {
			if ('' !== $className and !in_array($className, $classes, true)) {
				$classes[] = $className;
			}
		}

		return count($classes) ? implode(' ', $classes) : null;

	}

	/**
	 * Normalize contextual variants between common framework names.
	 */
	protected function normalizeVariant(string $variant, bool $bulma = false): string {

		$variant = strtolower(trim($variant));

		return match ($variant) {
			'danger', 'error' => 'danger',
			'warning' => 'warning',
			'success' => 'success',
			'info' => 'info',
			'link' => 'link',
			'light' => 'light',
			'dark' => 'dark',
			'secondary' => $bulma ? 'light' : 'secondary',
			default => 'primary',
		};

	}

	/**
	 * Compute the compact pagination range shared by framework renderers.
	 *
	 * @return	array{min: int, max: int}
	 */
	protected function paginationRange(int $page, int $pages): array {

		if ($page > 5 and $page + 5 > $pages) {
			$max = $pages;
			$min = ($max - 10) > 0 ? $max - 10 : 1;
		} else if ($page <= 5) {
			$min = 1;
			$max = $pages < 10 ? $pages : 10;
		} else {
			$min = ($page - 5) > 0 ? $page - 5 : 1;
			$max = ($page + 5 <= $pages) ? $page + 5 : $pages;
		}

		return [
			'min' => $min,
			'max' => $max,
		];

	}

}
