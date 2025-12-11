<?php

namespace Pair\Html;

/**
 * Create the JS code for an iziToast v1.4.0 notification.
 */
class IziToast {

	/**
	 * Applies a balloon like toast. Default is false.
	 */
	private ?bool $balloon = null;

	/**
	 * The class that will be applied to the toast. It may be used as a reference.
	 */
	private ?string $class = null;

	/**
	 * Show "x" close button. Default is true.
	 */
	private ?bool $close = null;

	/**
	 * Close the toast on escape key. Default is null.
	 */
	private ?bool $closeOnEscape = null;

	/**
	 * Display mode.
	 */
	private int $displayMode = 0;

	/**
	 * Icon of the toast notification.
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
	 * Position of the toast notification.
	 */
	private ?string $position = null;

	/**
	 * Enable timeout progress bar.
	 */
	private ?bool $progressBar = null;

	/**
	 * Enables display the Overlay layer on the page. Default is false.
	 */
	private ?bool $overlay = null;

	/**
	 * It can be light or dark or set another class. Create and use like this ".iziToast-theme-name".
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

		$validTypes = ['info','success','warning','error','question','progress'];

		$this->type = in_array($type, $validTypes) ? $type : 'info';

	}

	/**
	 * Applies a balloon like toast. Default is false. Chainable method.
	 */
	public function balloon(bool $flag): self {

		$this->balloon = $flag;
		return $this;

	}

	/**
	 * Set the class that will be applied to the toast. It may be used as a reference. Chainable method.
	 */
	public function class(string $class): self {

		$this->class = $class;
		return $this;

	}

	/**
	 * Show "x" close button. Default is true. Chainable method.
	 */
	public function close(bool $flag): self {

		$this->close = $flag;
		return $this;

	}

	/**
	 * Close the toast on escape key. Default is false. Chainable method.
	 */
	public function closeOnEscape(bool $flag): self {

		$this->closeOnEscape = $flag;
		return $this;

	}

	/**
	 * Set 1 to wait until the toast is closed so you can open it (once) or
	 * set 2 to replaces the toast that was already opened.
	 * Default is 0. Chainable method.
	 */
	public function displayMode(int $mode): self {

		if (!in_array($mode, [1, 2])) {
			$mode = 0;
		}

		$this->displayMode = $mode;
		return $this;

	}

	/**
	 * Get the font-awesome icon by the type of the toast.
	 */
	private function getFontAwesomeIconByType(): string {

		switch ($this->type) {
			case 'info':
				$icon = 'fa-info-circle';
				break;
			case 'success':
				$icon = 'fa-check-circle';
				break;
			case 'warning':
				$icon = 'fa-exclamation-triangle';
				break;
			case 'error':
				$icon = 'fa-exclamation-circle';
				break;
			case 'question':
				$icon = 'fa-question-circle';
				break;
			case 'progress':
				$icon = 'fa-spinner fa-spin';
				break;
		}

		return 'fa ' . $icon;

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
	 * Available positions are: bottomRight, bottomLeft, topRight, topLeft, topCenter, bottomCenter,
	 * center. Default is bottomRight. Chainable method.
	 */
	public function position(string $position): self {

		$validPositions = ['bottomRight','bottomLeft','topRight','topLeft','topCenter','bottomCenter','center'];

		if (in_array($position, $validPositions)) {
			$this->position = $position;
		}

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
	 * Returns js code for displaying a front-end user modal.
	 */
	public function render(): string {

		$script =
			'iziToast.' . $this->type . '({' .
			(!is_null($this->id) ? 'id: "' . $this->id . '",' : '') .
			(!is_null($this->class) ? 'class: "' . $this->class . '",' : '') .
			'title: "' . $this->title . '",' .
			//'titleColor: "",' .
			//'titleSize: "",' .
			//'titleLineHeight: "",' .
			'message: "' . str_replace('"', '\"', $this->message) . '",' .
			//'messageColor: "",' .
			//'messageSize: "",' .
			//'messageLineHeight: "",' .
			//'backgroundColor: "",' .
			(!is_null($this->theme) ? 'theme: "' . $this->theme . '",' : '') .
			//'color: "", // blue, red, green, yellow' .
			'icon: "' . (is_null($this->icon) ? $this->getFontAwesomeIconByType() : $this->icon) . '",' .
			//'iconText: "",' .
			//'iconColor: "",' .
			//'iconUrl: null,' .
			(!is_null($this->image) ? 'image: "' . $this->image . '",' : '') .
			//'imageWidth: 50,' .
			//'maxWidth: null,' .
			//'zindex: null,' .
			//'layout: 1,' .
			(!is_null($this->balloon) ? 'balloon: ' . ($this->balloon ? 'true' : 'false') . ',' : '') .
			(!is_null($this->close) ? 'close: ' . ($this->close ? 'true' : 'false') . ',' : '') .
			(!is_null($this->closeOnEscape) ? 'closeOnEscape: ' . ($this->closeOnEscape ? 'true' : 'false') . ',' : '') .
			//'closeOnClick: false,' .
			(0 != $this->displayMode ? 'displayMode: ' . $this->displayMode . ',' : '') .
			(!is_null($this->position) ? 'position: "' . $this->position . '",' : '') .
			//'target: "",' .
			//'targetFirst: true,' .
			'timeout: ' . $this->timeout . ',' .
			//'rtl: false,' .
			//'animateInside: true,' .
			//'drag: true,' .
			//'pauseOnHover: true,' .
			//'resetOnHover: false,' .
			(!is_null($this->progressBar) ? 'progressBar: ' . ($this->progressBar ? 'true' : 'false') . ',' : '') .
			//'progressBarColor: "",' .
			//'progressBarEasing: "linear",' .
			(!is_null($this->overlay) ? 'overlay: ' . ($this->overlay ? 'true' : 'false') . ',' : '') .
			//'overlayClose: false,' .
			//'overlayColor: "rgba(0, 0, 0, 0.6)",' .
			//'transitionIn: "fadeInUp",' .
			//'transitionOut: "fadeOut",' .
			//'transitionInMobile: "fadeInUp",' .
			//'transitionOutMobile: "fadeOutDown",' .
			//'buttons: {},' .
			//'inputs: {},' .
			//'onOpening: function () {},' .
			//'onOpened: function () {},' .
			//'onClosing: function () {},' .
			//'onClosed: function () {}' .
		'});';

		return $script;

	}

	/**
	 * Enables display the Overlay layer on the page, default is false. Chainable method.
	 */
	public function overlay(bool $flag): self {

		$this->overlay = $flag;
		return $this;

	}

	/**
	 * It can be light or dark or set another class. Create and use like this ".iziToast-theme-name".
	 * Chainable method.
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