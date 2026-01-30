<?php

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;

/**
 * This class represents a toggle switch form control. Must be used with proper CSS to display as a toggle.
 */
class Toggle extends FormControl {

	/**
	 * Flag to indicate whether the label should be suppressed when rendering the control.
	 */
	private bool $labelSuppressed = false;

	public function render(): string {

		$checked = $this->value ? ' checked="checked"' : '';
		$labelText = $this->getLabelText();

		$html = '<label class="toggle">';
		$html .= '<input ' . $this->nameProperty() . ' type="checkbox" value="1"' . $checked . $this->processProperties() . ' />';
		$html .= '<span class="track" aria-hidden="true"></span>';
		
		if (!$this->labelSuppressed && $labelText) {
			$labelClass = (isset($this->labelClass) and $this->labelClass) ? ' class="' . $this->labelClass . '"' : '';
			$html .= '<span' . $labelClass . '>' . $labelText . '</span>';
		}

		$html .= '</label>';

		return $html;

	}

	/**
	 * Suppresses the label when rendering the control.
	 * 
	 * @param bool $suppress Whether to suppress the label. Default is true.
	 * @return self Returns the current instance for method chaining.
	 */
	public function suppressLabel(bool $suppress = true): self {

		$this->labelSuppressed = $suppress;
		return $this;
	
	}

}
