<?php

declare(strict_types=1);

namespace Pair\Html\UiRenderers;

use Pair\Core\Router;
use Pair\Html\UiTheme;

/**
 * Bootstrap-compatible renderer for Pair HTML helpers.
 */
class BootstrapUiRenderer extends NativeUiRenderer {

	/**
	 * Return the Bootstrap renderer name.
	 */
	public function name(): string {

		return UiTheme::BOOTSTRAP;

	}

	/**
	 * Render Bootstrap tooltip markup for label descriptions.
	 */
	public function labelHelpTooltip(string $description): string {

		$description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
		$questionMark = '<span aria-hidden="true">?</span>';
		$ariaLabel = ' aria-label="' . $description . '"';

		return '<span class="form-control-help" role="button" tabindex="0" data-toggle="tooltip" data-bs-toggle="tooltip" data-placement="auto" data-bs-placement="auto" title="' . $description . '"' . $ariaLabel . '>' . $questionMark . '</span>';

	}

	/**
	 * Return the Bootstrap alert class.
	 */
	public function alertClass(string $variant = 'primary'): string {

		return 'alert alert-' . $this->normalizeVariant($variant);

	}

	/**
	 * Return the Bootstrap badge class.
	 */
	public function badgeClass(string $variant = 'primary'): string {

		return 'badge badge-' . $this->normalizeVariant($variant);

	}

	/**
	 * Return the Bootstrap floating helper.
	 */
	public function endAlignmentClass(): string {

		return 'float-end';

	}

	/**
	 * Render Bootstrap pagination markup.
	 */
	public function pagination(Router $router, int $page, int $pages): string {

		$range = $this->paginationRange($page, $pages);
		$render = '<div class="pagination"><nav aria-label="' . $this->translatedAttribute('PAGE_NAVIGATION') . '"><ul class="pagination">';

		if ($page > 1) {
			$render .= '<li class="page-item arrow"><a class="page-link" href="' . $router->getPageUrl(1) . '">«</a></li>';
		}

		for ($i = $range['min']; $i <= $range['max']; $i++) {

			if ($i == $page) {
				$render .= '<li class="page-item current active"><a class="page-link" href="' . $router->getPageUrl($i) . '">' . $i . '</a></li>';
			} else {
				$render .= '<li class="page-item"><a class="page-link" href="' . $router->getPageUrl($i) . '">' . $i . '</a></li>';
			}

		}

		if ($page < $pages) {
			$render .= '<li class="page-item arrow"><a class="page-link" href="' . $router->getPageUrl($pages) . '">»</a></li>';
		}

		$render .= '</ul></nav></div>';

		return $render;

	}

}
