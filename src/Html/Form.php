<?php

namespace Pair\Html;

use Pair\Exceptions\AppException;
use Pair\Exceptions\ErrorCodes;
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
use Pair\Html\FormControls\Image;
use Pair\Html\FormControls\Meter;
use Pair\Html\FormControls\Month;
use Pair\Html\FormControls\Number;
use Pair\Html\FormControls\Password;
use Pair\Html\FormControls\Progress;
use Pair\Html\FormControls\Search;
use Pair\Html\FormControls\Select;
use Pair\Html\FormControls\Tel;
use Pair\Html\FormControls\Text;
use Pair\Html\FormControls\Textarea;
use Pair\Html\FormControls\Time;
use Pair\Html\FormControls\Toggle;
use Pair\Html\FormControls\Url;
use Pair\Helpers\Post;
use Pair\Orm\ActiveRecord;
use Pair\Orm\Collection;

/**
 * Represents an HTML form element within the Pair PHP Framework.
 * This class provides an object-oriented interface for building, rendering,
 * and managing complete web forms, encapsulating structure, attributes,
 * and submission behavior.
 *
 * A Form instance can dynamically register and manage its FormControl
 * children, handle form-level validation, and automatically inject CSRF
 * protection tokens when required. It supports advanced configuration of
 * form attributes such as `action`, `method`, `enctype`, `target`, and
 * allows programmatic control over client-side and server-side behaviors.
 *
 * The Form class is designed to be lightweight, expressive, and easily
 * integrated with the MVC architecture of the Pair Framework. Its purpose
 * is to separate the logical structure of a form from its HTML presentation,
 * promoting code reusability and maintainability.
 *
 * Typical use:
 *  - Create a new form instance.
 *  - Add FormControl objects.
 *  - Set attributes, default values, and validation rules.
 *  - Render the form in a view or serialize it for AJAX submission.
 */
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
	private ?string $labelClasses = null;

	/**
	 * Add a FormControl object to controls list of this Form. Chainable method.
	 */
	public function add(FormControl $control): FormControl {

		$this->controls[$control->name] = $control;
		return $control;

	}

	/**
	 * Adds an Address input object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function address(string $name, array $attributes = []): Address {

		$control = new Address($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Set all registered controls as disabled.
	 */
	public function allDisabled(): void {

		foreach ($this->controls as $control) {
			$control->disabled();
		}

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
	 * Creates an HTML button form control prepending an optional icon.
	 *
	 * @param	string	Text for the button.
	 * @param	string	Type (submit -default-, button, reset).
	 * @param	string	HTML name for this control (optional).
	 * @param	string	More parameters as associative array tag=>value (optional).
	 */
	public static function buildButton(string $value, string $type = 'submit', ?string $name = null, $attributes = []): string {

		$control = new Button($name, $attributes);
		$control->type($type)->value($value);

		return $control->render();

	}

	/**
	 * Creates an HTML input form control.
	 *
	 * @param	string	HTML name for this control.
	 * @param	string	Default value (null default).
	 * @param	string	Type (text -default-, password, email, url, etc.).
	 * @param	array	More parameters as associative array tag=>value (optional).
	 */
	public static function buildInput(string $name, ?string $value = null, ?string $type = null, array $attributes = []): string {

		if (!$type) {
			$type = 'text';
		}

		$class = 'Pair\Html\FormControls\\' . ucfirst($type);

		$control = new $class($name, $attributes);
		$control->value($value);

		return $control->render();

	}

	/**
	 * Create an HTML select control starting from an object array and setting a default
	 * value (optional).
	 *
	 * @param	string	Select’s name.
	 * @param	Collection|array	Array with object as options.
	 * @param	string	Property name of the value for option object (default is “value”).
	 * @param	string	Property name of the text for option object (default is “text”).
	 * @param	string	Value selected in this select (default null).
	 * @param	string	Extended parameters as associative array tag=>value.
	 * @param	string	Prepend empty value (default null, no prepend).
	 */
	public static function buildSelect(string $name, Collection|array $list, string $valName = 'value', string $textName = 'text', $value = null, $attributes = null, $prependEmpty = null): string {

		$control = new Select($name, $attributes);
		$control->options($list, $valName, $textName)->value($value);

		if ($prependEmpty) {
			$control->empty($prependEmpty);
		}

		return $control->render();

	}

	/**
	 * Creates a TextArea input field.
	 *
	 * @param	string	HTML name for this control.
	 * @param   int		Rows value.
	 * @param   int		Columns value.
	 * @param	string	Default value (null default).
	 * @param	string	More parameters as associative array tag=>value (optional).
	 */
	public static function buildTextarea(string $name, int $rows, int $cols, $value = null, $attributes = []): string {

		$control = new Textarea($name, $attributes);
		$control->rows($rows)->cols($cols)->value($value);

		return $control->render();

	}

	/**
	 * Adds a Button object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function button(string $name, array $attributes = []): Button {

		$control = new Button($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Adds a Checkbox input object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function checkbox(string $name, array $attributes = []): Checkbox {

		$control = new Checkbox($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Checks whether the CSRF token provided by the client matches the one stored in the session. Intended
	 * for conditional flows (e.g., early returns). For an exception-based variant, use validateToken().
	 */
	public function checkToken(): bool {

		// checks if token is present in session
		if ('POST' !== $_SERVER['REQUEST_METHOD'] or !isset($_SESSION['csrf_token'])) {
			return false;
		}

		// checks if token is present in POST data
		$postedToken = Post::trim('csrf_token');
		if (!$postedToken) {
			return false;
		}

		// compares both tokens
		return hash_equals($_SESSION['csrf_token'], $postedToken);

	}

	/**
	 * Adds a common CSS class to all controls of this form at render time. Chainable method.
	 *
	 * @param	string	CSS Class name.
	 */
	public function classForControls(string $class): Form {

		$this->controlClasses[] = $class;

		return $this;

	}

	/**
	 * Sets a common CSS class for all labels of this form.
	 *
	 * @param	string	CSS Class name.
	 */
	public function classForLabels(string $class): Form {

		$this->labelClasses = $class;

		return $this;

	}

	/**
	 * Adds a Color input object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function color(string $name, array $attributes = []): Color {

		$control = new Color($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Return the control object by its name.
	 */
	public function control(string $controlName): ?FormControl {

		if (substr($controlName, -2) == '[]') {
			$controlName = substr($controlName, 0, -2);
		}

		if (!$this->controlExists($controlName)) {
			throw new AppException('Field control “' . $controlName . '” has not been defined in Form object', ErrorCodes::FORM_CONTROL_NOT_FOUND);
		}
		return $this->controls[$controlName];

	}

	/**
	 * Check whether the control exists.
	 */
	public function controlExists(string $controlName): bool {

		return array_key_exists($controlName, $this->controls);

	}

	/**
	 * Returns all FormControl subclass objects registered in this Form object.
	 *
	 * @return FormControl[]
	 */
	public function controls(): array {

		return $this->controls;

	}

	/**
	 * Adds a Date input object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function date(string $name, array $attributes = []): Date {

		$control = new Date($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Adds a Datetime input object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function datetime(string $name, array $attributes = []): Datetime {

		$control = new Datetime($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Adds an Email input object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function email(string $name, array $attributes = []): Email {

		$control = new Email($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Adds a File input object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function file(string $name, array $attributes = []): File {

		$control = new File($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Generates and returns a CSRF token field for form security.
	 *
	 * @return Hidden Hidden input field with the CSRF token.
	 */
	public function generateToken(): Hidden {

		// generates a new token if not present in session
		if (!isset($_SESSION['csrf_token'])) {
			$_SESSION['csrf_token'] = bin2hex(\random_bytes(32));
		}

		return $this->hidden('csrf_token')->value($_SESSION['csrf_token']);

	}

	/**
	 * Adds a Hidden input object to this Form object. Default type is Text.
	 * Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function hidden(string $name, array $attributes = []): Hidden {

		$control = new Hidden($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Adds an Image input object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function image(string $name, array $attributes = []): Image {

		$control = new Image($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Validates all form field controls and returns a FormValidation result object.
	 */
	public function isValid(): bool {

		$valid = true;

		foreach ($this->controls as $control) {

			if (!$control->validate()) {
				$valid = false;
			}

		}

		return $valid;

	}

	/**
	 * Adds a Meter input object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function meter(string $name, array $attributes = []): Meter {

		$control = new Meter($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Adds a Month input object to this Form object. Chainable method.
	 */
	public function month(string $name, array $attributes = []): Month {

		$control = new Month($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Adds a Number input object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function number(string $name, array $attributes = []): Number {

		$control = new Number($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Adds a Password input object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function password(string $name, array $attributes = []): Password {

		$control = new Password($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Print the HTML code of a form control by its name.
	 *
	 * @param	string	HTML name of the wanted control.
	 */
	public function printControl(string $name): void {

		print $this->renderControl($name);

	}

	/**
	 * Print the HTML code of a control’s label.
	 *
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
	 * Print a CSRF token field for form security.
	 */
	public function printToken(): void {

		print $this->generateToken();

	}

	/**
	 * Adds a Progress input object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function progress(string $name, array $attributes = []): Progress {

		$control = new Progress($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Remove a control form a Form object.
	 */
	public function removeControl(string $controlName): bool {

		if (substr($controlName, -2) == '[]') {
			$controlName = substr($controlName, 0, -2);
		}

		if (!$this->controlExists($controlName)) {
			return false;
		}

		unset($this->controls[$controlName]);
		return true;

	}

	/**
	 * Creates an HTML form control getting its object by its name.
	 *
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
	 * Adds a Search input object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function search(string $name, array $attributes = []): Search {

		$control = new Search($name, $attributes);
		$this->add($control);
		return $control;

	}


	/**
	 * Adds a Select object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function select(string $name, array $attributes = []): Select {

		$control = new Select($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Adds a Tel input object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function tel(string $name, array $attributes = []): Tel {

		$control = new Tel($name, $attributes);
		$this->add($control);
		return $control;

	}

		/**
	 * Adds a text Input object to this Form object. Default type is Text.
	 * Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function text(string $name, array $attributes = []): Text {

		$control = new Text($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Adds a Textarea object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function textarea(string $name, array $attributes = []): Textarea {

		$control = new Textarea($name, $attributes);
		$this->add($control);
		return $control;

	}

	public function time(string $name, array $attributes = []): Time {

		$control = new Time($name, $attributes);
		$this->add($control);
		return $control;

	}

	public function toggle(string $name, array $attributes = []): Toggle {

		$control = new Toggle($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Adds an URL input object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of attributes.
	 */
	public function url(string $name, array $attributes = []): Url {

		$control = new Url($name, $attributes);
		$this->add($control);
		return $control;

	}

	/**
	 * Return a list of unvalid FormControl objects.
	 *
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
	 * Validate a submitted CSRF token or throw an AppException. If you need a boolean
	 * result, use checkToken() method.
	 *
	 * @throws	AppException
	 */
	public static function validateToken(): void {

		$token = Post::trim('csrf_token');

		// check if the CSRF token is set in the session
		if (!isset($_SESSION['csrf_token'])) {
			throw new AppException('CSRF token not found in session', ErrorCodes::CSRF_TOKEN_NOT_FOUND);
		}

		// regenerate the token after validation
		if (!hash_equals($_SESSION['csrf_token'], $token)) {
			throw new AppException('Invalid CSRF token', ErrorCodes::CSRF_TOKEN_INVALID);
		}

		$_SESSION['csrf_token'] = bin2hex(\random_bytes(32));

	}

	/**
	 * Assigns all attributes of passed ActiveRecord children to controls with same name.
	 * This must be used after all controls have been defined.
	 * Note: only properties with matching control names will be assigned.
	 * 
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

}