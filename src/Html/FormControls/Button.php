<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

class Button extends FormControl {

	/**
	 * Button type submit|reset|button, default is button.
	 */
	private string $type = 'submit';

	/**
	 * Specifies where to send the form-data when a form is submitted. Only for type="submit".
	 * Chainable method.
	 * 
	 * @param	string	URL.
	 */
	public function formaction(string $url): static {

		$this->attributes['formaction'] = $url;
		return $this;

	}

	/**
	 * Specifies how form-data should be encoded before sending it to a server. Only for type="submit".
	 * Possible values: application/x-www-form-urlencoded | multipart/form-data | text/plain.
	 * Chainable method.
	 *
	 * @param	string	Encoding type, default is application/x-www-form-urlencoded.
	 */
	public function formenctype(string $type): static {

		if (!in_array($type, ['application/x-www-form-urlencoded', 'multipart/form-data', 'text/plain'])) {
			throw new \InvalidArgumentException('Button formenctype must be application/x-www-form-urlencoded, multipart/form-data or text/plain.');
		}

		$this->attributes['formenctype'] = $type;
		return $this;

	}

	/**
	 * Specifies the HTTP method to use when sending form-data. Only for type="submit".
	 * Possible values: get | post. Chainable method.
	 */
	public function formmethod(string $method): static {

		if (!in_array($method, ['get', 'post'])) {
			throw new \InvalidArgumentException('Button formmethod must be get or post.');
		}

		$this->attributes['formmethod'] = $method;
		return $this;

	}

	/**
	 * Specifies that the form-data should not be validated on submission. Only for type="submit".
	 * Chainable method.
	 */
	public function formnovalidate(bool $novalidate = true): static {

		if ($novalidate) {
			$this->attributes['formnovalidate'] = 'formnovalidate';
		} else {
			unset($this->attributes['formnovalidate']);
		}

		return $this;

	}

	/**
	 * Specifies where to display the response after submitting the form. Only for type="submit".
	 * Possible values: _blank | _self | _parent | _top | framename. Chainable method.
	 *
	 * @param	string	Target name.
	 */
	public function formtarget(string $target): static {

		if (!in_array($target, ['_blank', '_self', '_parent', '_top']) && preg_match('/\s/', $target)) {
			throw new \InvalidArgumentException('Button formtarget must be _blank, _self, _parent, _top or a valid framename.');
		}

		$this->attributes['formtarget'] = $target;
		return $this;

	}

	/**
	 * Specifies a which popover element to invoke. Chainable method.
	 *
	 * @param	string	Element ID.
	 */
	public function popovertarget(string $elementId): static {

		$this->attributes['data-popovertarget'] = $elementId;
		return $this;

	}

	/**
	 * Specifies what happens to the popover element when the button is clicked. Possible values:
	 * hide | show | toggle. Chainable method.
	 *
	 * @param	string	Action, default is toggle.
	 */
	public function popovertargetaction(string $action): static {

		if (!in_array($action, ['hide', 'show', 'toggle'])) {
			throw new \InvalidArgumentException('Button popovertargetaction must be hide, show or toggle.');
		}

		$this->attributes['data-popovertargetaction'] = $action;
		return $this;

	}

	/**
	 * Render an HTML button form control with an optional FontAwesome icon prefixed.
	 */
	public function render(): string {

		$ret = '<button type="' . $this->type . '"' ;

		if ($this->id) {
			$ret .= 'id=' . $this->id;
		}

		if ($this->name) {
			$ret .= ' ' . $this->nameProperty();
		}

		$ret .= $this->processProperties() . '>';

		$ret .= trim(htmlspecialchars((string)$this->caption)) . ' </button>';

		return $ret;

	}

	/**
	 * Set type for a Button (submit, reset, button). Chainable method.
	 */
	public function type(string $type): self {

		if (!in_array($type, ['submit', 'reset', 'button'])) {
			throw new \InvalidArgumentException('Button type must be submit, reset or button.');
		}

		$this->type = $type;
		return $this;

	}

	/**
	 * Button’s validation is always true.
	 */
	public function validate(): bool {

		return true;

	}

}