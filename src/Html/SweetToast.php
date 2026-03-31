<?php

namespace Pair\Html;

/**
 * Create the JS code for a SweetAlert2 toast notification.
 */
class SweetToast {

	/**
	 * Applies a balloon like toast. Kept for API compatibility.
	 */
	private ?bool $balloon = null;

	/**
	 * The class that will be applied to the toast popup.
	 */
	private ?string $class = null;

	/**
	 * Show the close button. Default is true.
	 */
	private ?bool $close = null;

	/**
	 * Close the toast on escape key. Default is true.
	 */
	private ?bool $closeOnEscape = null;

	/**
	 * Display mode. Kept for API compatibility.
	 */
	private int $displayMode = 0;

	/**
	 * Icon of the toast notification. Custom icon classes are rendered through iconHtml.
	 */
	private ?string $icon = null;

	/**
	 * Id of the toast notification.
	 */
	private ?string $id = null;

	/**
	 * Image of the toast notification.
	 */
	private ?string $image = null;

	/**
	 * Text message of the toast notification.
	 */
	private string $message;

	/**
	 * Position of the toast notification. When null, the SweetAlert2 default is used.
	 */
	private ?string $position = null;

	/**
	 * Enable timeout progress bar.
	 */
	private ?bool $progressBar = null;

	/**
	 * Enables display the overlay layer on the page. Kept for API compatibility.
	 */
	private ?bool $overlay = null;

	/**
	 * Theme class applied to the popup. Kept for API compatibility.
	 */
	private ?string $theme = null;

	/**
	 * Amount in milliseconds to close the toast or false to disable.
	 */
	private bool|int $timeout = 5000;

	/**
	 * Title of the toast notification.
	 */
	private string $title;

	/**
	 * Type of the toast notification.
	 */
	private string $type = 'info';

	/**
	 * Constructor method.
	 */
	public function __construct(string $title, string $message, ?string $type = 'info') {

		$this->title = $title;
		$this->message = $message;

		$validTypes = ['info', 'success', 'warning', 'error', 'question', 'progress'];

		$this->type = in_array($type, $validTypes, true) ? $type : 'info';

	}

	/**
	 * Applies a balloon like toast. Default is false. Chainable method.
	 */
	public function balloon(bool $flag): self {

		$this->balloon = $flag;
		return $this;

	}

	/**
	 * Set the class that will be applied to the toast popup. Chainable method.
	 */
	public function class(string $class): self {

		$this->class = $class;
		return $this;

	}

	/**
	 * Show the close button. Default is true. Chainable method.
	 */
	public function close(bool $flag): self {

		$this->close = $flag;
		return $this;

	}

	/**
	 * Close the toast on escape key. Default is true. Chainable method.
	 */
	public function closeOnEscape(bool $flag): self {

		$this->closeOnEscape = $flag;
		return $this;

	}

	/**
	 * Set the display mode. Kept for API compatibility. Chainable method.
	 */
	public function displayMode(int $mode): self {

		if (!in_array($mode, [1, 2], true)) {
			$mode = 0;
		}

		$this->displayMode = $mode;
		return $this;

	}

	/**
	 * Returns the mapped SweetAlert2 position for the configured toast position.
	 */
	private function getSweetAlertPosition(): string {

		return match ($this->normalizePosition($this->position)) {
			'top-end' => 'top-end',
			'top-start' => 'top-start',
			'top' => 'top',
			'bottom-end' => 'bottom-end',
			'bottom-start' => 'bottom-start',
			'bottom' => 'bottom',
			'center' => 'center',
			default => 'top-end'
		};

	}

	/**
	 * Returns the standard SweetAlert2 icon for the current type.
	 */
	private function getSweetAlertType(): string {

		return 'progress' === $this->type ? 'info' : $this->type;

	}

	/**
	 * Returns the popup class list used by SweetAlert2.
	 */
	private function getPopupClasses(): string {

		$classes = [];

		if ($this->class) {
			$classes[] = $this->class;
		}

		if ($this->theme) {
			$classes[] = $this->theme;
		}

		if ($this->balloon) {
			$classes[] = 'pair-swal-toast-balloon';
		}

		return trim(implode(' ', $classes));

	}

	/**
	 * Useful for debugging. Returns the text message of the toast.
	 */
	public function getText(): string {

		return $this->message;

	}

	/**
	 * Icon class (font-icon of your choice, Icomoon, Fontawesome etc.). Chainable method.
	 */
	public function icon(string $icon): self {

		$this->icon = $icon;
		return $this;

	}

	/**
	 * Set the id of the toast. Default null. Chainable method.
	 */
	public function id(string $id): self {

		$this->id = $id;
		return $this;

	}

	/**
	 * Set the image of the toast. Chainable method.
	 */
	public function image(string $image): self {

		$this->image = $image;
		return $this;

	}

	/**
	 * Returns true when the configured icon is one of the standard SweetAlert2 icons.
	 */
	private function isStandardSweetAlertIcon(): bool {

		return in_array((string)$this->icon, ['success', 'error', 'warning', 'info', 'question'], true);

	}

	/**
	 * Returns the normalized position token used by both supported toast drivers.
	 */
	private function normalizePosition(?string $position): string {

		$normalized = strtolower(trim((string)$position));
		$normalized = str_replace(['_', ' '], '-', $normalized);

		return match ($normalized) {
			'topright', 'top-right', 'topend', 'top-end' => 'top-end',
			'topleft', 'top-left', 'topstart', 'top-start' => 'top-start',
			'topcenter', 'top-center' => 'top',
			'bottomright', 'bottom-right', 'bottomend', 'bottom-end' => 'bottom-end',
			'bottomleft', 'bottom-left', 'bottomstart', 'bottom-start' => 'bottom-start',
			'bottomcenter', 'bottom-center' => 'bottom',
			'center' => 'center',
			default => 'top-end'
		};

	}

	/**
	 * Available positions are: bottomRight, bottomLeft, topRight, topLeft, topCenter,
	 * bottomCenter, center, plus SweetAlert2 aliases such as top-end and bottom-start.
	 * When unset, the SweetAlert2 default is used. Chainable method.
	 */
	public function position(string $position): self {

		$this->position = $position;
		return $this;

	}

	/**
	 * Enable timeout progress bar. Default is true. Chainable method.
	 */
	public function progressBar(bool $flag): self {

		$this->progressBar = $flag;
		return $this;

	}

	/**
	 * Returns js code for displaying a front-end user toast.
	 */
	public function render(): string {

		$script =
			'if (typeof Swal === "undefined") {' .
				'console.error("SweetAlert2 library not found.");' .
			'} else {' .
				'Swal.fire({';

		$script .= 'toast: true,';

		if (null !== $this->position) {
			$script .= 'position: "' . addslashes($this->getSweetAlertPosition()) . '",';
		}

		$script .= 'title: "' . addcslashes($this->title, "\"\n\r") . '",';
		$script .= 'text: "' . addcslashes($this->message, "\"\n\r") . '",';
		$script .= 'showConfirmButton: false,';
		$script .= 'showCloseButton: ' . (($this->close ?? true) ? 'true' : 'false') . ',';
		$script .= 'allowEscapeKey: ' . (($this->closeOnEscape ?? true) ? 'true' : 'false') . ',';
		$script .= 'timerProgressBar: ' . (($this->progressBar ?? true) ? 'true' : 'false');

		if (false !== $this->timeout) {
			$script .= ',timer: ' . (int)$this->timeout;
		}

		if ($this->image) {
			$script .= ',imageUrl: "' . addslashes($this->image) . '"';
		}

		if ($this->icon) {
			if ($this->isStandardSweetAlertIcon()) {
				$script .= ',icon: "' . addslashes($this->icon) . '"';
			} else {
				$iconHtml = '<i class="' . htmlspecialchars($this->icon, ENT_QUOTES) . '"></i>';
				$script .= ',iconHtml: "' . addslashes($iconHtml) . '"';
			}
		} else {
			$script .= ',icon: "' . addslashes($this->getSweetAlertType()) . '"';
		}

		$popupClasses = $this->getPopupClasses();

		if ('' !== $popupClasses) {
			$script .= ',customClass: { popup: "' . addslashes($popupClasses) . '" }';
		}

		if ($this->id) {
			$script .= ',didOpen: function(toast) { toast.id = "' . addslashes($this->id) . '"; }';
		}

		$script .= '});';
		$script .= '}';

		return $script;

	}

	/**
	 * Enables display the overlay layer on the page, default is false. Chainable method.
	 */
	public function overlay(bool $flag): self {

		$this->overlay = $flag;
		return $this;

	}

	/**
	 * It can be a custom popup class name. Chainable method.
	 */
	public function theme(string $theme): self {

		$this->theme = $theme;
		return $this;

	}

	/**
	 * Set the timeout amount in milliseconds to close the toast or false to disable. Chainable method.
	 */
	public function timeout(bool|int $timeout): self {

		if (is_int($timeout) or false === $timeout) {
			$this->timeout = $timeout;
		}

		return $this;

	}

}
