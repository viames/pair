<?php

namespace Pair\Html;

/**
 * Create a SweetAlert2 v11.14.5 JS modal.
 */
class SweetAlert {

	/**
	 * Title of the alert.
	 */
	private string $title;

	/**
	 * Text message of the alert.
	 */
	private string $text;

	/**
	 * Icon of the alert.
	 */
	private ?string $icon = null;

	/**
	 * Cancel button color.
	 */
	private ?string $cancelButtonColor = null;

	/**
	 * Cancel button text.
	 */
	private ?string $cancelButtonText = null;

	/**
	 * Cancel button callback.
	 */
	private ?string $cancelCallback = null;

	/**
	 * Confirm button color.
	 */
	private ?string $confirmButtonColor = null;

	/**
	 * Confirm button text.
	 */
	private ?string $confirmButtonText = null;

	/**
	 * Confirm button callback.
	 */
	private ?string $confirmCallback = null;

	/**
	 * Deny button color.
	 */
	private ?string $denyButtonColor = null;

	/**
	 * Deny button text.
	 */
	private ?string $denyButtonText = null;

	/**
	 * Deny button callback.
	 */
	private ?string $denyCallback = null;

	/**
	 * Input type.
	 */
	private ?string $input = null;

	/**
	 * Callback function to call on pre-confirm.
	 */
	private ?string $preConfirm = null;

	/**
	 * Callback function to call on render.
	 */
	private ?string $didRender = null;

	/**
	 * Flag to allow outside click.
	 */
	private bool $outsideClick = true;

	/**
	 * Loader flag.
	 */
	private bool $loader = false;

	public function __construct(string $title, string $text, ?string $icon = null) {

		$this->title = $title;
		$this->text	 = $text;

		$validIcons = ['info', 'success', 'error', 'warning', 'question'];

		$this->icon = in_array($icon, $validIcons) ? $icon : null;

	}

	/**
	 * Set the cancel button text and eventually the callback function and the button color.
	 * The default button’s background color is #aaa.
	 */
	public function cancel(string $text, ?string $callback = null, ?string $buttonColor = null): self {

		$this->cancelButtonText = $text;

		if ($buttonColor) {
			$this->cancelButtonColor = $buttonColor;
		}

		if ($callback) {
			$this->cancelCallback = $callback;
		}

		return $this;

	}

	/**
	 * Set the confirm button text and eventually the callback function and the button color.
	 * The default button’s background color is #3085d6. 
	 */
	public function confirm(string $text, ?string $callback = null, ?string $buttonColor = null): self {

		$this->confirmButtonText = $text;

		if ($buttonColor) {
			$this->confirmButtonColor = $buttonColor;
		}

		if ($callback) {
			$this->confirmCallback = $callback;
		}

		return $this;

	}

	/**
	 * Set the deny button text and eventually the callback function and the button color.
	 * The default button’s background color is #dd6b55.
	 */
	public function deny(string $text, ?string $callback = null, ?string $buttonColor = null): self {

		$this->denyButtonText = $text;

		if ($buttonColor) {
			$this->denyButtonColor = $buttonColor;
		}

		if ($callback) {
			$this->denyCallback = $callback;
		}

		return $this;

	}

	/**
	 * Returns the text of the alert, useful for logging.
	 */
	public function getText(): string {

		return $this->text;

	}

	/**
	 * Set the input type.
	 */
	public function input(string $type): self {

		$this->input = $type;
		return $this;

	}

	/**
	 * Set the loader flag.
	 */
	public function loader(bool $showLoaderOnConfirm): self {

		$this->loader = $showLoaderOnConfirm;
		return $this;

	}

	/**
	 * Set the didRender callback.
	 */
	public function didRender(string $callback): self {

		$this->didRender = $callback;
		return $this;

	}

	/**
	 * Set the outside click flag.
	 */
	public function outsideClick(bool $allowOutsideClick): self {

		$this->outsideClick = $allowOutsideClick;
		return $this;

	}

	/**
	 * Set the preConfirm callback.
	 */
	public function preConfirm(string $callback): self {

		$this->preConfirm = $callback;
		return $this;

	}

	/**
	 * Returns js code for displaying a front-end user modal.
	 */
	public function render(): string {

		$script =
			'if (typeof Swal === "undefined") {
				console.error("SweetAlert2 library not found.");
			} else {
				Swal.fire({';

		$script .= 'title: "' . addslashes($this->title) . '",';
		$script .= 'text: "' . addcslashes($this->text,"\"\n\r") . '",';
		$script .= $this->icon ? 'icon: "' . addslashes($this->icon) . '"' : '';

		if ($this->confirmButtonText) {
			$script .= ',confirmButtonText: "' . addslashes($this->confirmButtonText) . '"';
			$script .= ($this->confirmButtonColor ? ',confirmButtonColor: "' . $this->confirmButtonColor . '"' : '');
			$script .= ',showConfirmButton: true';
		} else {
			// confirm button is shown by default
			$script .= ',showConfirmButton: false';
		}

		if ($this->cancelButtonText) {
			$script .= ',cancelButtonText: "' . addslashes($this->cancelButtonText) . '"';
			$script .= ($this->cancelButtonColor ? ',cancelButtonColor: "' . $this->cancelButtonColor . '"' : '');
			$script .= ',showCancelButton: true';
		}

		if ($this->denyButtonText) {
			$script .= ',denyButtonText: "' . addslashes($this->denyButtonText) . '"';
			$script .= ($this->denyButtonColor ? ',denyButtonColor: "' . $this->denyButtonColor . '"' : '');
			$script .= ',showDenyButton: true';
		}

		if ($this->input) {
			$script .= ',input: "' . addslashes($this->input) . '"';
		}

		if (!$this->outsideClick) {
			$script .= ',allowOutsideClick: false';
		}

		if ($this->didRender) {
			$script .= ',didRender: function() {';
			$script .= $this->runCallback($this->didRender);
			$script .= '},';
		}

		if ($this->preConfirm) {

			if ($this->loader) {
				$script .= ',showLoaderOnConfirm: true';
			}

			$script .= ',preConfirm: function() {';
			$script .= $this->runCallback($this->preConfirm);
			$script .= '},';
		}

		if ($this->cancelCallback or $this->confirmCallback or $this->denyCallback) {

			$script .= '}).then((result) => {';

			if ($this->cancelCallback) {
				$script .= 'if (result.dismiss === Swal.DismissReason.cancel) {';
				$script .= $this->runCallback($this->cancelCallback);
				$script .= '};';
			}

			if ($this->confirmCallback) {
				$script .= 'if (result.isConfirmed) {';
				$script .= $this->runCallback($this->confirmCallback);
				$script .= '};';
			}

			if ($this->denyCallback) {
				$script .= 'if (result.isDenied) {';
				$script .= $this->runCallback($this->denyCallback);
				$script .= '};';
			}

		}

		// end of Swal.fire
		$script .= '});';

		// end else
		$script .= "\t\t\t\t}\n";
		
		return $script;

	}

	/**
	 * JavaScript code to run a function under the window object if exists.
	 */
	private function runCallback(string $callback): string {

		// sanitize the function name
		$sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $callback);

		// if it's a function, call it
		if ($sanitized == $callback) {

			$script = 'if (typeof window["' . $callback . '"] === "function") {';
			$script .= 'window["' . $callback . '"]();';
			$script .= '} else if ("' . $callback . '" !== null) {';
			$script .= 'console.log("Function " + "' . $callback . '" + " not found.");';
			$script .= '}';

		} else {

			$script = $callback . ';';

		}

		return $script;

	}

}