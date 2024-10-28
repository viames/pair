<?php

namespace Pair\Html;

use Pair\Html\FormControl;
use Pair\Html\FormControls\Address;
use Pair\Html\FormControls\Button;
use Pair\Html\FormControls\Checkbox;
use Pair\Html\FormControls\Color;
use Pair\Html\FormControls\Date;
use Pair\Html\FormControls\Datetime;
use Pair\Html\FormControls\Email;
use Pair\Html\FormControls\File;
use Pair\Html\FormControls\Hidden;
use Pair\Html\FormControls\Input;
use Pair\Html\FormControls\Select;
use Pair\Html\FormControls\Textarea;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Collection;
use Pair\Support\Logger;

class Form {

	/**
	 * List of all controls added to this form.
	 * @var FormControl[]
	 */
	private array $controls = [];

	/**
	 * List of class to add on each controls.
	 * @var string[]
	 */
	private array $controlClasses = [];

	/**
	 * Class to add on each labels.
	 */
	private ?string $labelClasses = NULL;

	/**
	 * Adds an Address input object to this Form object. Chainable method.
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function address(string $name, array $attributes = []): Address {

		$control = new Address($name, $attributes);
		$this->addControl($control);
		return $control;

	}

	/**
	 * Adds a Button object to this Form object. Chainable method.
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function button(string $name, array $attributes = []): Button {

		$control = new Button($name, $attributes);
		$this->addControl($control);
		return $control;

	}

	/**
	 * Adds a Checkbox input object to this Form object. Chainable method.
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function checkbox(string $name, array $attributes = []): Checkbox {

		$control = new Checkbox($name, $attributes);
		$this->addControl($control);
		return $control;

	}

	/**
	 * Adds a Color input object to this Form object. Chainable method.
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function color(string $name, array $attributes = []): Color {

		$control = new Color($name, $attributes);
		$this->addControl($control);
		return $control;

	}

	/**
	 * Adds a Date input object to this Form object. Chainable method.
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function date(string $name, array $attributes = []): Date {
		
		$control = new Date($name, $attributes);
		$this->addControl($control);
		return $control;

	}

	/**
	 * Adds a Datetime input object to this Form object. Chainable method.
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function datetime(string $name, array $attributes = []): Datetime {

		$control = new Datetime($name, $attributes);
		$this->addControl($control);
		return $control;

	}

	/**
	 * Adds an Email input object to this Form object. Chainable method.
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function email(string $name, array $attributes = []): Email {

		$control = new Email($name, $attributes);
		$this->addControl($control);
		return $control;

	}

	/**
	 * Adds a File input object to this Form object. Chainable method.
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function file(string $name, array $attributes = []): File {

		$control = new File($name, $attributes);
		$this->addControl($control);
		return $control;

	}

	/**
	 * Adds a text Input object to this Form object. Default type is Text.
	 * Chainable method.
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function input(string $name, array $attributes = []): Input {

		$control = new Input($name, $attributes);
		$this->addControl($control);
		return $control;

	}

	/**
	 * Adds a Hidden input object to this Form object. Default type is Text.
	 * Chainable method.
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function hidden(string $name, array $attributes = []): Hidden {

		$control = new Hidden($name, $attributes);
		$this->addControl($control);
		return $control;

	}

	/**
	 * Adds a Select object to this Form object. Chainable method.
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function select(string $name, array $attributes = []): Select {

		$control = new Select($name, $attributes);
		$this->addControl($control);
		return $control;

	}

	/**
	 * Adds a Textarea object to this Form object. Chainable method.
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function textarea(string $name, array $attributes = []): Textarea {

		$control = new Textarea($name, $attributes);
		$this->addControl($control);
		return $control;

	}

	/**
	 * Add a FormControl object to controls list of this Form.
	 */
	private function addControl(FormControl $control): void {

		$this->controls[$control->name] = $control;

	}

	/**
	 * Return the control object by its name.
	 */
	public function control(string $controlName): ?FormControl {

		if (substr($controlName, -2) == '[]') {
			$controlName = substr($controlName, 0, -2);
		}

		if ($this->controlExists($controlName)) {
			return $this->controls[$controlName];
		} else {
			Logger::error('Field control “' . $controlName . '” has not been defined in Form object');
			return NULL;
		}

	}

	/**
	 * Remove a control form a Form object.
	 * @param	string	Control name.
	 */
	public function removeControl(string $controlName): bool {

		if (substr($controlName, -2) == '[]') {
			$controlName = substr($controlName, 0, -2);
		}

		if (!$this->controlExists($controlName)) {
			return FALSE;
		}

		unset($this->controls[$controlName]);
		return TRUE;

	}

	/**
	 * Set all registered controls as readonly.
	 */
	public function allReadonly(): void {

		foreach ($this->controls as $control) {
			$control->readonly();
		}

	}

	/**
	 * Check whether the control exists.
	 * @param	string	Control name.
	 */
	public function controlExists($name): bool {

		return array_key_exists($name, $this->controls);

	}

	/**
	 * Assigns all attributes of passed ActiveRecord children to controls with same name.
	 * @param	ActiveRecord	An object inherited by ActiveRecord.
	 */
	public function values(ActiveRecord $object): void {

		if (is_object($object) and is_subclass_of($object, 'Pair\Orm\ActiveRecord')) {

			$properties = $object->getAllProperties();

			foreach ($properties as $name=>$value) {
				if (array_key_exists($name, $this->controls)) {
					$control = $this->control($name);
					$control->value($value);
				}
			}

		}

	}

	/**
	 * Returns all FormControl subclass objects registered in this Form object.
	 * @return FormControl[]
	 */
	public function controls(): array {

		return $this->controls;

	}

	/**
	 * Creates an HTML form control getting its object by its name.
	 * @param	string	HTML name for this control.
	 */
	private function renderControl(string $name): string {

		// gets control object
		$control = $this->control($name);

		if ($control) {

			// adds common CSS classes to requested control
			if (count($this->controlClasses)) {
				$control->class(implode(' ', $this->controlClasses));
			}

			return $control->render();

		} else {

			return '';

		}

	}

	/**
	 * Print the HTML code of a form control by its name.
	 * @param	string	HTML name of the wanted control.
	 */
	public function printControl(string $name): void {

		print $this->renderControl($name);

	}

	/**
	 * Print the HTML code of a control’s label.
	 * @param	string	HTML name of the wanted control.
	 */
	public function printLabel(string $controlName): void {

		// gets control object
		$control = $this->control($controlName);

		if ($control) {
			if (isset($this->labelClasses) and $this->labelClasses) {
				$control->labelClass($this->labelClasses);
			}
			$control->printLabel();
		}

	}

	/**
	 * Validates all form field controls and returns a FormValidation result object.
	 */
	public function isValid(): bool {

		$valid = TRUE;

		foreach ($this->controls as $control) {

			if (!$control->validate()) {
				$valid = FALSE;
			}

		}

		return $valid;

	}

	/**
	 * Return a list of unvalid FormControl objects.
	 * @return FormControl[]
	 */
	public function unvalidControls(): array {

		$unvalids = [];

		foreach ($this->controls as $control) {

			if (!$control->validate()) {
				$unvalids[] = $control;
			}

		}

		return $unvalids;

	}

	/**
	 * Adds a common CSS class to all controls of this form at render time. Chainable.
	 * @param	string	CSS Class name.
	 */
	public function classForControls(string $class): Form {

		$this->controlClasses[] = $class;

		return $this;

	}

	/**
	 * Create an HTML select control starting from an object array and setting a default
	 * value (optional).
	 * @param	string	Select’s name.
	 * @param	Collection|array	Array with object as options.
	 * @param	string	Property name of the value for option object (default is “value”).
	 * @param	string	Property name of the text for option object (default is “text”).
	 * @param	string	Value selected in this select (default NULL).
	 * @param	string	Extended parameters as associative array tag=>value.
	 * @param	string	Prepend empty value (default NULL, no prepend).
	 */
	public static function buildSelect(string $name, Collection|array $list, string $valName='value', string $textName='text', $value=NULL, $attributes=NULL, $prependEmpty=NULL): string {

		$control = new Select($name, $attributes);
		$control->options($list, $valName, $textName)->value($value);

		if ($prependEmpty) {
			$control->prependEmpty($prependEmpty);
		}

		return $control->render();

	}

	/**
	 * Creates an HTML input form control.
	 * @param	string	HTML name for this control.
	 * @param	string	Default value (NULL default).
	 * @param	string	Type (text -default-, email, tel, url, color, password, number, bool, date, datetime, file, image, address, hidden).
	 * @param	string	More parameters as associative array tag=>value (optional).
	 */
	public static function buildInput(string $name, string $value=NULL, string $type='text', $attributes=[]): string {

		$control = new Input($name, $attributes);
		$control->type($type)->value($value);

		return $control->render();

	}

	/**
	 * Creates a TextArea input field.
	 * @param	string	HTML name for this control.
	 * @param   int		Rows value.
	 * @param   int		Columns value.
	 * @param	string	Default value (NULL default).
	 * @param	string	More parameters as associative array tag=>value (optional).
	 */
	public static function buildTextarea(string $name, int $rows, int $cols, $value=NULL, $attributes=[]): string {

		$control = new Textarea($name, $attributes);
		$control->rows($rows)->cols($cols)->value($value);

		return $control->render();

	}

	/**
	 * Creates an HTML button form control prepending an optional icon.
	 * @param	string	Text for the button.
	 * @param	string	Type (submit -default-, button, reset).
	 * @param	string	HTML name for this control (optional).
	 * @param	string	More parameters as associative array tag=>value (optional).
	 * @param	string	Name of Font Awesome icon class (optional).
	 */
	public static function buildButton(string $value, string $type='submit', string $name=NULL, $attributes=[], $faIcon=NULL): string {

		$control = new Button($name, $attributes);
		$control->type($type)->setFaIcon($faIcon)->value($value);

		return $control->render();

	}

	/**
	 * Sets a common CSS class for all labels of this form.
	 */
	public function classForLabels(string $class): Form {

		$this->labelClasses = $class;

		return $this;

	}

}