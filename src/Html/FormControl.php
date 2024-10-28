<?php

namespace Pair\Html;

use Pair\Core\Application;
use Pair\Support\Logger;
use Pair\Support\Post;
use Pair\Support\Translator;

abstract class FormControl {

	/**
	 * Name of this control is HTML control name tag.
	 */
	private string $name;

	/**
	 * DOM object unique ID.
	 */
	private ?string $id = NULL;

	/**
	 * Current value for this control object.
	 */
	private mixed $value = NULL;

	/**
	 * Flag for set this field as required.
	 */
	private bool $required = FALSE;

	/**
	 * Flag for set this field as disabled.
	 */
	private bool $disabled = FALSE;

	/**
	 * Flag for set this field as readonly.
	 */
	private bool $readonly = FALSE;

	/**
	 * Flag for set this control name as array.
	 */
	private bool $arrayName = FALSE;

	/**
	 * Control placeholder text.
	 */
	private ?string $placeholder = NULL;

	/**
	 * Minimum allowed length for value.
	 */
	private ?int $minLength = NULL;

	/**
	 * Maximum allowed length for value.
	 */
	private ?int $maxLength = NULL;

	/**
	 * List of optional attributes as associative array.
	 * @var string[]
	 */
	private array $attributes = [];

	/**
	 * Container for all control CSS classes.
	 * @var string[]
	 */
	private array $class = [];

	/**
	 * Optional label for this control.
	 */
	private ?string $label = NULL;

	/**
	 * Optional description for this control.
	 */
	private ?string $description = NULL;

	/**
	 * CSS class for label.
	 */
	private ?string $labelClass;

	/**
	 * Build control with HTML name tag and optional attributes.
	 *
	 * @param	string	Control name.
	 * @param	array	Optional attributes (tag=>value).
	 */
	public function __construct(string $name, ?array $attributes=[]) {

		// remove [] from array and set TRUE to arrayName property
		if (substr($name, -2) == '[]') {
			$name = substr($name, 0, -2);
			$this->setArrayName();
		}

		$this->name			= $name;
		$this->attributes	= (array)$attributes;

	}

	/**
	 * Return property’s value if set. Throw an exception and returns NULL if not set.
	 *
	 * @param	string	Property’s name.
	 * @throws	Exception
	 */
	public function __get(string $name): mixed {

		try {

			if (!property_exists($this, $name)) {
				throw new \Exception('Property “'. $name .'” doesn’t exist for object '. get_called_class());
			}

			return $this->$name;

		} catch (\Exception $e) {

			trigger_error($e->getMessage());
			return NULL;

		}

	}

	/**
	 * Magic method to set an object property value.
	 *
	 * @param	string	Property’s name.
	 * @param	mixed	Property’s value.
	 */
	public function __set(string $name, mixed $value): void {

		$this->$name = $value;

	}

	/**
	 * Return a string value for this object, matches the control’s label.
	 */
	public function __toString(): string {

		return $this->getLabelText();

	}

	/**
	 * Adds a single data attribute, prepending the string "data-" to the given name.
	 * @param	string	Data attribute name.
	 * @param	string	Value.
	 */
	public function data(string $name, string $value): static {

		$this->attributes['data-' . $name] = $value;

		return $this;

	}

	/**
	 * Sets value for this control subclass.
	 */
	public function value(string|int|float|\DateTime|NULL $value): static {

		// special behavior for DateTime
		if (is_a($value, 'DateTime') and is_a($this, 'Pair\Html\FormControl')) {

			// if UTC date, set user timezone
			if (defined('UTC_DATE') and UTC_DATE) {
				$app = Application::getInstance();
				$value->setTimezone($app->currentUser->getDateTimeZone());
			}

			// can be datetime or just date
			$format = (is_a($this, 'Pair\Html\FormControls\Date')) ? $this->dateFormat : $this->datetimeFormat;
			$this->value = $value->format($format);

		} else {

			$this->value = (string)$value;

		}

		return $this;

	}

	/**
	 * Set the control ID.
	 *
	 * @param	string	Control identifier.
	 */
	public function id(string $id): static {

		$this->id = $id;
		return $this;

	}

	/**
	 * Sets this field as required (enables JS client-side and PHP server-side validation).
	 * Chainable method.
	 */
	public function required(): static {

		$this->required = TRUE;
		return $this;

	}

	/**
	 * Sets this field as disabled only. Chainable method.
	 */
	public function disabled(): static {

		$this->disabled = TRUE;
		return $this;

	}

	/**
	 * Sets this field as read only. Chainable method.
	 */
	public function readonly(): static {

		$this->readonly = TRUE;
		return $this;

	}

	/**
	 * Sets this field as array. Will add [] to control name. Chainable method.
	 */
	public function setArrayName(): static {

		$this->arrayName = TRUE;
		return $this;

	}

	/**
	 * Sets placeholder text. Chainable method.
	 *
	 * @param	string	Placeholder’s text.
	 */
	public function placeholder(string $text): static {

		$this->placeholder = $text;
		return $this;

	}

	/**
	 * Sets minimum length for value of this control. It’s a chainable method.
	 *
	 * @param	int	Minimum length for value.
	 */
	public function minLength(int $length): static {

		$this->minLength = $length;
		return $this;

	}

	/**
	 * Sets maximum length for value of this control. It’s a chainable method.
	 *
	 * @param	int	Maximum length for value.
	 */
	public function maxLength(int $length): static {

		$this->maxLength = $length;
		return $this;

	}

	/**
	 * Adds CSS single class, classes string or classes array to this control, avoiding
	 * duplicates. This method is chainable.
	 *
	 * @param	string|array	Single class name, list space separated or array of class names.
	 */
	public function class(string|array $class): static {

		// classes array
		if (is_array($class)) {

			// adds all of them
			foreach ($class as $c) {
				if (!in_array($c, $this->class)) {
					$this->class[] = $c;
				}
			}

		// single class
		} else if (is_string($class) and !in_array($class, $this->class)) {

			$this->class[] = $class;

		}

		return $this;

	}

	/**
	 * Set a label for this control as text or translation key. Chainable method.
	 *
	 * @param	string	The text label or the uppercase translation key.
	 */
	public function label(string $label): static {

		$this->label = $label;

		return $this;

	}

	/**
	 * Return the control’s label.
	 */
	public function getLabelText(): string {

		// no label, get it by the control’s name
		if (!$this->label) {

			$label = ucwords(preg_replace(['/(?<=[^A-Z])([A-Z])/', '/(?<=[^0-9])([0-9])/'], ' $0', $this->name));

		// check if it’s a translation key, uppercase over 3 chars
		} else if (strtoupper($this->label) == $this->label and strlen($this->label) > 3) {

			$label = Translator::do($this->label);

		// simple label
		} else {

			$label = $this->label;

		}

		return $label;

	}

	/**
	 * Set a description for this control as text. Chainable method.
	 *
	 * @param	string	The text description.
	 */
	public function description(string $description): static {

		$this->description = $description;

		return $this;

	}

	/**
	 * Set CSS class for all the controls label. Chainable method.
	 */
	public function labelClass(string $class): static {

		$this->labelClass = $class;

		return $this;

	}

	/**
	 * Print the control’s label tag even with required-field class.
	 */
	public function printLabel(): void {

		$label = '<label for="' . htmlspecialchars($this->name) . '"';
		
		if (isset($this->labelClass) and $this->labelClass) {
			$label .= ' class="' . $this->labelClass . '"';
		}

		$label .= '>';

		// if required, add required-field css class
		$label .= ($this->required and !$this->readonly and !$this->disabled)
		? '<span class="required-field">' . htmlspecialchars($this->getLabelText()) . '</span>'
		: $this->getLabelText();

		if ($this->description) {
			$label .= ' <i class="fal fa-question-circle" data-toggle="tooltip" data-placement="auto" title="' . htmlspecialchars((string)$this->description) . '"></i>';
		}

		$label .= '</label>';

		print $label;

	}

	/**
	 * Print the HTML code of this FormControl.
	 *
	 * @param	string	HTML name of the wanted control.
	 */
	public function printControl(): void {

		print $this->render();

	}

	/**
	 * Process and return the common control attributes.
	 */
	protected function processProperties(): string {

		$ret = '';

		if ($this->id) {
			$ret .= ' id="' . $this->id . '"';
		}

		if ($this->required and !is_a($this, 'Pair\Html\FormControls\Checkbox') and !is_a($this, 'Pair\Html\FormControls\Radio')) {
			$ret .= ' required';
		}

		if ($this->disabled) {
			$ret .= ' disabled';
		}

		if ($this->readonly) {
			$ret .= ' readonly';
		}

		if ($this->placeholder) {
			$ret .= ' placeholder="' . $this->placeholder . '"';
		}

		// CSS classes
		if (count($this->class)) {
			$ret .= ' class="' . implode(' ', $this->class) . '"';
		}

		// misc tag attributes
		foreach ($this->attributes as $attr=>$val) {
			$ret .= ' ' . $attr . '="' . str_replace('"','\"',$val) . '"';
		}

		return $ret;

	}

	/**
	 * Create a control name escaping special chars and adding array puncts in case of.
	 */
	protected function nameProperty(): string {

		return 'name="' . htmlspecialchars($this->name . ($this->arrayName ? '[]' : '')) . '"';

	}

	abstract public function render(): string;

	/**
	 * This is the FormControl’s default validation method. Validates this control against empty values,
	 * minimum length, maximum length, and returns TRUE if these checks pass.
	 */
	public function validate(): bool {

		$value	= Post::get($this->name);
		$valid	= TRUE;

		if ($this->required and ''==$value) {
			Logger::event('Control validation on field “' . $this->name . '” has failed (required)');
			$valid = FALSE;
		}

		// check validity of minlength attribute
		if ($this->minLength and ''!=$value and strlen($value) < $this->minLength) {
			Logger::event('Control validation on field “' . $this->name . '” has failed (minLength=' . $this->minLength . ')');
			$valid = FALSE;
		}

		// check validity of minlength attribute
		if ($this->maxLength and strlen($value) > $this->maxLength) {
			Logger::event('Control validation on field “' . $this->name . '” has failed (maxLength=' . $this->maxLength . ')');
			$valid = FALSE;
		}

		return $valid;

	}

}