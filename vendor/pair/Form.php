<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

namespace Pair;

class Form {
	
	/**
	 * List of all controls added to this form.
	 * @var array:multitype
	 */
	private $controls = array();
	
	/**
	 * List of class to add on each controls.
	 * @var array:string
	 */
	private $controlClasses = array();

	/**
	 * Adds an FormControlInput object to this Form object. Default type is Text.
	 * Chainable method.
	 * 
	 * @param	string	Control name.
	 * @param	array	List of properties.
	 * 
	 * @return	FormControlInput
	 */
	public function addInput($name, $properties = array()) {

		$control = new FormControlInput($name, $properties);
		$this->addControl($control);
		
		return $control;
		
	}

	/**
	 * Adds an FormControlSelect object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of properties.
	 *
	 * @return	FormControlSelect
	 */
	public function addSelect($name, $properties = array()) {
	
		$control = new FormControlSelect($name, $properties);
		$this->addControl($control);
	
		return $control;
	
	}

	/**
	 * Adds an FormControlTextarea object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of properties.
	 *
	 * @return	FormControlTextarea
	 */
	public function addTextarea($name, $properties = array()) {
	
		$control = new FormControlTextarea($name, $properties);
		$this->addControl($control);
	
		return $control;
	
	}
	
	/**
	 * Adds an FormControlButton object to this Form object. Chainable method.
	 *
	 * @param	string	Control name.
	 * @param	array	List of properties.
	 *
	 * @return	FormControlButton
	 */
	public function addButton($name, $properties = array()) {
	
		$control = new FormControlButton($name, $properties);
		$this->addControl($control);
	
		return $control;
	
	}
	
	/**
	 * Add a FormControl object to controls list of this Form.
	 * 
	 * @param	multitype	FormControl children class object.
	 */
	private function addControl($control) {
		
		$this->controls[$control->name] = $control;
		
	}
	
	/**
	 * Return the control object by its name.
	 * 
	 * @param	string	Control name
	 * 
	 * @return	array:multitype|NULL
	 */
	public function getControl($name) {
		
		if (substr($name, -2) == '[]') {
			$name = substr($name, 0, -2);
		}
		
		if (array_key_exists($name, $this->controls)) {
			return $this->controls[$name];
		} else {
			$log = Logger::getInstance();
			$log->addError('Field control “' . $name . '” has not been defined in Form object'); 
			return NULL;
		}
		
	}
	
	/**
	 * Assigns all properties of passed ActiveRecord children to controls with same name.
	 * 
	 * @param	multitype	An object inherited by ActiveRecord.
	 */
	public function setValuesByObject($object) {

		if (is_object($object) and is_subclass_of($object, 'Pair\ActiveRecord')) {

			$properties = $object->getAllProperties();

			foreach ($properties as $name=>$value) {
				if (array_key_exists($name, $this->controls)) {
					$control = $this->getControl($name);
					$control->setValue($value);
				}
			}
			
		}
		
	}

	/**
	 * Returns all FormControl subclass objects registered in this Form object. 
	 * 
	 * @return array:FormControl (subclasses)
	 */
	public function getAllControls() {
	
		return $this->controls;
	
	}
	
	/**
	 * Creates an HTML form control getting its object by its name.
	 *
	 * @param	string	HTML name for this control.
	 *
	 * @return	string
	 */
	public function renderControl($name) {

		// gets control object
		$control = $this->getControl($name);
		
		if ($control) {
			
			// adds common CSS classes to requested control
			if (count($this->controlClasses)) {
				$control->addClass(implode(' ', $this->controlClasses));
			}
			
			print $control->render();
			
		}
		
	}
	
	/**
	 * Validates all form field controls and returns a FormValidation result object. 
	 *
	 * @return	bool
	 */
	public function isValid() {
		
		$valid = TRUE;
		
		foreach ($this->controls as $control) {
			
			$value = Input::get($control->name);
			
			if (!$control->validate()) {
				$valid = FALSE;
			}
			
		}
		
		return $valid;
		
	}
	
	/**
	 * Adds a common CSS class to all controls of this form at render time.
	 * 
	 * @param	string	CSS Class name.
	 */
	public function addControlClass($class) {
		
		$this->controlClasses[] = $class;
		
	}

	/**
	 * Create an HTML select control starting from an object array and setting a default
	 * value (optional).
	 * 
	 * @param	string	Select’s name.
	 * @param	array	Array with object as options.
	 * @param	string	Property name of the value for option object (default is “value”).
	 * @param	string	Property name of the text for option object (default is “text”).
	 * @param	string	Value selected in this select (default NULL).
	 * @param	string	Extended parameters as associative array tag=>value.
	 * @param	string	Prepend empty value (default NULL, no prepend).
	 * 
	 * @return	string
	 */
	public static function buildSelect($name, $list, $valName='value', $textName='text', $value=NULL, $properties=NULL, $prependEmpty=NULL) {
		
		$control = new FormControlSelect($name, $properties);
		$control->setListByObjectArray($list, $valName, $textName)->setValue($value);
		
		if ($prependEmpty) {
			$control->prependEmpty($prependEmpty);
		}
		
		return $control->render();

	}
	
	/**
	 * Proxy for buildSelect that allow to start option list from a simple array.
	 * 
	 * @param	string	Select’s name.
	 * @param	array	Associative array value=>text for options.
	 * @param	string	Value selected in this select (default NULL).
	 * @param	string	Extended parameters as associative array tag=>value (optional).
	 * @param	string	Prepend empty value (default NULL, no prepend).
	 * 
	 * @return	string
	 */
	public static function buildSelectFromArray($name, $list, $value=NULL, $properties=NULL, $prependEmpty=NULL) {

		$control = new FormControlSelect($name, $properties);
		$control->setListByAssociativeArray($list)->prependEmpty($prependEmpty)->setValue($value);
		return $control->render();
	
	}
	
	/**
	 * Creates an HTML input form control.
	 * 
	 * @param	string	HTML name for this control.
	 * @param	string	Default value (NULL default).
	 * @param	string	Type (text -default-, password, number, bool, date, datetime, hidden, address, file).
	 * @param	string	More parameters as associative array tag=>value (optional).
	 * 
	 * @return	string
	 */
	public static function buildInput($name, $value=NULL, $type='text', $properties=array()) {

		$control = new FormControlInput($name, $properties);
		$control->setType($type)->setValue($value);
		return $control->render();
		
	}

	/**
	 * Creates a TextArea input field.
	 *
	 * @param	string	HTML name for this control.
	 * @param   int		Rows value.
	 * @param   int		Columns value.
	 * @param	string	Default value (NULL default).
	 * @param	string	More parameters as associative array tag=>value (optional).
	 *
	 * @return string
	 */
	public static function buildTextarea($name, $rows, $cols, $value=NULL, $properties=array()) {

		$control = new FormControlTextarea($name, $properties);
		$control->setRows($rows)->setCols($cols)->setValue($value);
		return $control->render();

	}
	
	/**
	 * Creates an HTML button form control prepending an optional icon.
	 *
	 * @param	string	Text for the button.
	 * @param	string	Type (submit -default-, button, reset).
	 * @param	string	HTML name for this control (optional).
	 * @param	string	More parameters as associative array tag=>value (optional).
	 * @param	string	Name of Font Awesome icon class (optional).
	 * 
	 * @return	string
	 */
	public static function buildButton($value,  $type='submit', $name=NULL, $properties=array(), $faIcon=NULL) {

		$control = new FormControlButton($name, $properties);
		$control->setValue($value)->setType($type)->setFaIcon($faIcon);
		return $control->render();

	}

}

abstract class FormControl {
	
	/**
	 * Name of this control is HTML control name tag.
	 * @var string
	 */
	private $name;

	/**
	 * DOM object unique ID.
	 * @var string
	 */
	private $id;
	
	/**
	 * Current value for this control object.
	 * @var unknown
	 */
	private $value;
	
	/**
	 * Flag for set this field as required.
	 * @var boolean
	 */
	private $required = FALSE;
	
	/**
	 * Flag for set this field as disabled.
	 * @var boolean
	 */
	private $disabled = FALSE;
	
	/**
	 * Flag for set this field as readonly.
	 * @var boolean
	 */
	private $readonly = FALSE;
	
	/**
	 * Flag for set this control name as array.
	 * @var boolean 
	 */
	private $arrayName = FALSE;

	/**
	 * Control placeholder text.
	 * @var NULL|string
	 */
	private $placeholder;
	
	/**
	 * Minimum allowed length for value.
	 * @var NULL|integer
	 */
	private $minLength;
	
	/**
	 * Maximum allowed length for value.
	 * @var NULL|integer
	 */
	private $maxLength;
	
	/**
	 * List of optional properties as associative array.
	 * @var array:string
	 */
	private $properties = array();
	
	/**
	 * Container for all control CSS classes.
	 * @var array:string
	 */
	private $class = array();
	
	/**
	 * Builds control with HTML name tag and optional properties.
	 * 
	 * @param	string	Control name.
	 * @param	array	Optional properties (tag=>value).
	 */
	public function __construct($name, $properties = array()) {
		
		// legacy
		if (is_array($properties) and array_key_exists('class', $properties)) {
			$this->addClass($properties['class']);
			unset($properties['class']);
		}
		
		// remove [] from array and set TRUE to arrayName property
		if (substr($name, -2) == '[]') {
			$name = substr($name, 0, -2);
			$this->setArrayName();
		}
		
		$this->name			= $name;
		$this->properties	= (array)$properties;
		
	}
	
	/**
	 * Will returns property’s value if set. Throw an exception and returns NULL if not set.
	 *
	 * @param	string	Property’s name.
	 * @throws	Exception
	 * @return	mixed|NULL
	 */
	public function __get($name) {
	
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
	public function __set($name, $value) {
	
		try {
			$this->$name = $value;
		} catch (\Exception $e) {
			$this->addError('Property ' . $name . ' cannot get value ' . $value);
		}
	
	}
	
	abstract public function render();
	
	abstract public function validate();
	
	/**
	 * Sets value for this control subclass.
	 * 
	 * @param	string	Value for this control.
	 * 
	 * @return	FormControl
	 */
	public function setValue($value) {

		// special behavior for DateTime
		if (is_a($value, 'DateTime') and is_a($this, 'Pair\FormControlInput')) {

			// sets user timezone
			$app = Application::getInstance();
			$value->setTimezone($app->currentUser->getDateTimeZone());

			// can be datetime or just date
			$this->value = $value->format('datetime' == $this->type ? 'Y-m-d H:i' : 'Y-m-d');

		} else {

			$this->value = $value;

		}

		return $this;

	}

	public function setId($id) {
	
		$this->id = $id;
		return $this;
	
	}
	
	/**
	 * Sets this field as required (enables JS client-side and PHP server-side validation).
	 * Chainable method.
	 * 
	 * @return	FormControl subclass
	 */
	public function setRequired() {
		
		$this->required = TRUE;
		return $this;
		
	}
	
	/**
	 * Sets this field as disabled only. Chainable method.
	 * 
	 * @return	FormControl subclass
	 */
	public function setDisabled() {
		
		$this->disabled = TRUE;
		return $this;
		
	}
	
	/**
	 * Sets this field as read only. Chainable method.
	 * 
	 * @return	FormControl subclass
	 */
	public function setReadonly() {
		
		$this->readonly = TRUE;
		return $this;
		
	}
	
	/**
	 * Sets this field as array. Will add [] to control name. Chainable method.
	 * 
	 * @return	FormControl subclass
	 */
	public function setArrayName() {
		
		$this->arrayName = TRUE;
		return $this;
		
	}

	/**
	 * Sets placeholder text. Chainable method.
	 * 
	 * @return	FormControl subclass
	 */
	public function setPlaceholder($text) {

		$this->placeholder = $text;
		return $this;
		
	}
	
	/**
	 * Sets minimum length for value of this control. It’s a chainable method.
	 *
	 * @param	int		Minimum length for value.
	 *
	 * @return	FormControl subclass
	 */
	public function setMinLength($length) {
		
		$this->minLength = (int)$length;
		return $this;
		
	}

	/**
	 * Sets maximum length for value of this control. It’s a chainable method.
	 *
	 * @param	int		Maximum length for value.
	 *
	 * @return	FormControl subclass
	 */
	public function setMaxLength($length) {
	
		$this->maxLength = (int)$length;
		return $this;
	
	}
	
	/**
	 * Adds CSS single class, classes string or classes array to this control, avoiding
	 * duplicates. This method is chainable.
	 * 
	 * @param	string|array	Single class name, list space separated or array of class names.
	 * 
	 * @return	FormControl subclass
	 */
	public function addClass($class) {

		// classes array
		if (is_array($class)) {
			
			// adds all of them
			foreach ($class as $c) {
				if (!in_array($c, $this->class)) {
					$this->class[] = $c;
				}
			}
			
		// single class
		} else if (!in_array($class, $this->class)) {
			
			$this->class[] = $class;
			
		}

		return $this;

	}
	
	/**
	 * Process and return the common control properties.
	 * 
	 * @return string
	 */
	protected function processProperties() {

		$ret = '';

		if ($this->required) {
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
		
		// misc tag properties
		foreach ($this->properties as $prop=>$val) {
			$ret .= ' ' . $prop . '="' . addslashes($val) . '"';
		}
		
		return $ret;

	}

	/**
	 * Create a control name escaping special chars and adding array puncts in case of.
	 * 
	 * @return string
	 */
	protected function getNameProperty() {
		
		return 'name="' . htmlspecialchars($this->name . ($this->arrayName ? '[]' : '')) . '"';
		
	}

}

class FormControlInput extends FormControl {
	
	/**
	 * Can be text, email, tel, url, color, password, number, bool, date, datetime, file, address, hidden.
	 * @var string
	 */
	protected $type;
	
	/**
	 * Accepted file type file_extension, audio/*, video/*, image/* or media_type.
	 */
	protected $accept;
	
	/**
	 * Extends parent constructor in order to sets default type to text.
	 * 
	 * @param	string	Control name.
	 * @param	array	Additional properties (for instance, tag=>value).
	 */
	public function __construct($name, $properties = array()) {
		
		parent::__construct($name, $properties);
		
		$this->setType('text');
		
	}
	
	/**
	 * Sets type for a FormControlInput. Chainable method.
	 * 
	 * @param	string	Input type (text, password, number, bool, tel, email, url, color, date, datetime, file, address,
	 * hidden)
	 * 
	 * @return	FormControlInput
	 */
	public function setType($type) {
		
		$this->type = $type;
		return $this;
		
	}

	/**
	 * Set accepted file type by input field (only affects the “file” input).
	 * 
	 * @param	string	File type: file_extension, audio/*, video/*, image/*, media_type.
	 * 
	 * @return	FormControlInput
	 */
	public function setAccept($fileType) {
		
		$this->accept = $fileType;
		return $this;
		
	}
		
	/**
	 * Renders and returns an HTML input form control.
	 *
	 * @return	string
	 */
	public function render() {
	
		$ret = '<input ' . $this->getNameProperty();
	
		switch ($this->type) {
				
			default:
			case 'text':
			case 'email':
			case 'tel':
			case 'url':
			case 'color':
			case 'password':
			case 'number':
				$ret .= ' type="' . htmlspecialchars($this->type) . '" value="'. htmlspecialchars($this->value) .'"';
				break;
	
			case 'bool':
				$ret .= ' type="checkbox" value="1"';
				if ($this->value) $ret .= ' checked="checked"';
				break;
	
			case 'date':
				$ret .= ' type="date" value="' . htmlspecialchars($this->value) . '"';
				$this->addClass('datepicker');
				break;

			case 'datetime':
				$ret .= ' type="datetime-local" value="' . htmlspecialchars($this->value) . '"';
				$this->addClass('datetimepicker');
				break;
				
			case 'file':
				$ret .= ' type="file"';
				break;
	
			case 'address':
				$ret .= ' type="text" value="'. htmlspecialchars($this->value) .'" size="50" autocomplete="on" placeholder=""';
				$this->addClass('googlePlacesAutocomplete');
				break;
	
			case 'hidden':
				$ret .= ' type="hidden" value="' . htmlspecialchars($this->value) . '"';
				break;
	
		}
	
		// set minlength attribute
		if ($this->minLength) {
			$ret .= ' minlength="' . $this->minLength . '"';
		}
		
		// set maxlength attribute
		if ($this->maxLength) {
			$ret .= ' maxlength="' . $this->maxLength . '"';
		}
		
		// set accept attribute
		if ($this->accept) {
			$ret .= ' accept="' . $this->accept . '"';
		}
		
		$ret .= $this->processProperties() . ' />';
	
		return $ret;
	
	}
	
	/**
	 * Validates this control against empty values, minimum length, maximum length,
	 * and returns TRUE if is all set checks pass.
	 *
	 * @return	bool
	 */
	public function validate() {

		$app	= Application::getInstance();
		$value	= Input::get($this->name);
		$valid	= TRUE;

		if ($this->required) {
			
			switch ($this->type) {

				default:
				case 'text':
				case 'password':
				case 'date':
				case 'datetime':
				case 'file':
				case 'tel':
				case 'address':
				case 'color':
				case 'hidden':
					if (''==$value) {
						$app->logEvent('Control validation on field “' . $this->name . '” has failed (required)');
						$valid = FALSE;
					}
					break;

				case 'email':
					if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
						$app->logEvent('Control validation on field “' . $this->name . '” has failed (email required)');
						$valid = FALSE;
					}
					break;
					
				case 'url':
					if (!filter_var($value, FILTER_VALIDATE_URL)) {
						$app->logEvent('Control validation on field “' . $this->name . '” has failed (url required)');
						$valid = FALSE;
					}
					break;
					
				case 'number':
					if (!is_numeric($value)) {
						$app->logEvent('Control validation on field “' . $this->name . '” has failed (number required)');
						$valid = FALSE;
					}
					break;
				
				case 'bool':
					break;
					
			}
			
		}
		
		if ($this->minLength and ''!=$value and strlen($value) < $this->minLength) {
			$app->logEvent('Control validation on field “' . $this->name . '” has failed (minLength=' . $this->minLength . ')');
			$valid = FALSE;
		}

		if ($this->maxLength and strlen($value) > $this->maxLength) {
			$app->logEvent('Control validation on field “' . $this->name . '” has failed (maxLength=' . $this->maxLength . ')');
			$valid = FALSE;
		}

		return $valid;

	}

}

class FormControlSelect extends FormControl {

	/**
	 * Items list of stdClass objs with value and text properties.
	 * @var array
	 */
	private $list = array();
	
	/**
	 * Flag to enable this control to multiple values.
	 * @var boolean
	 */
	private $multiple = FALSE;
	
	/**
	 * Populates select control with an associative array. Chainable method.
	 * 
	 * @param	array	Associative array (value=>text).
	 * 
	 * @return	FormControlSelect
	 */
	public function setListByAssociativeArray($list) {
		
		foreach ($list as $value=>$text) {
				
			$option			= new \stdClass();
			$option->value	= $value;
			$option->text	= $text;

			$this->list[]	= $option;
				
		}
		
		return $this;
		
	}
	
	/**
	 * Populates select control with an object array. Each object must have properties
	 * for value and text. It's a chainable method.
	 * 
	 * @param	array:stdClass	Object with value and text properties.
	 * @param	string			Name of value property.
	 * @param	string			Name of text property.
	 * 
	 * @return	FormControlSelect
	 */
	public function setListByObjectArray($list, $valueProperty, $textProperty) {

		foreach ($list as $opt) {

			$option			= new \stdClass();
			$option->value	= $opt->$valueProperty;
			$option->text	= $opt->$textProperty;
				
			$this->list[]	= $option;

		}

		return $this;
	
	}
	
	/**
	 * Adds a null value as first item. Chainable method.
	 * 
	 * @param	string	Option text for first null value.
	 * 
	 * @return	FormControlSelect
	 */
	public function prependEmpty($text=NULL) {
		
		$t = Translator::getInstance();
		
		$option			= new \stdClass();
		$option->value	= '';
		$option->text	= is_null($text) ? $t->translate('SELECT_NULL_VALUE') : $text;

		$this->list = array_merge(array($option), $this->list);
		
		return $this;
		
	}
	
	/**
	 * Enables this select control to accept multiple choises. Chainable method.
	 *
	 * @return	FormControlSelect
	 */
	public function setMultiple() {
	
		$this->multiple = TRUE;
		return $this;
	
	}
	
	/**
	 * Renders a Select field tag as HTML code.
	 *
	 * @return string
	 */
	public function render() {
	
		$ret = '<select ' . $this->getNameProperty();
		
		if ($this->multiple) {
			$ret .= ' multiple';
		}
		
		$ret .= $this->processProperties() . '>';
	
		try {
			
			// build each option
			foreach ($this->list as $option) {
				
				// check if value is an array
				if (is_array($this->value)) {
					$selected = in_array($option->value, $this->value) ? ' selected="selected"' : '';
				} else {
					$selected = $this->value == $option->value ? ' selected="selected"' : '';
				}
				
				// build the option
				$ret .= '<option value="' . htmlspecialchars($option->value) . '"' . $selected . '>'
						. htmlspecialchars($option->text) . '</option>';
				
			}
			
		} catch (\Exception $e) {
			
			print $e->getMessage();
			
		}
	
		$ret .= "</select>\n";
		return $ret;
	
	}

	/**
	 * Validates this control and returns TRUE if is valid.
	 *
	 * @return	bool
	 */
	public function validate() {
	
		$app = Application::getInstance();
		
		$valid = TRUE;
	
		$value = Input::get($this->name);

		// checks if empty and required
		if ($this->required and !$value) {
			
			$app->logEvent('Control validation on field “' . $this->name . '” has failed (required)');
			$valid = FALSE;
			
		// checks if value is in allowed list
		} else if (count($this->list)) {
			
			$valid = FALSE;
			
			foreach ($this->list as $item) {
				if ($item->value == $value) $valid = TRUE;
			}

			if (!$valid) {
				$app->logEvent('Control validation on field “' . $this->name . '” has failed (value “' . $value . '” is not in list)');
			}

		}
		
		return $valid;
	
	}

}

class FormControlTextarea extends FormControl {
	
	private $rows = 2;
	
	private $cols = 20;
	
	/**
	 * Sets rows for this textarea. Chainable method.
	 * 
	 * @param	int		Rows number.
	 * 
	 * @return	FormControlTextarea
	 */
	public function setRows($num) {
		
		$this->rows = (int)$num;
		return $this;
		
	}

	/**
	 * Sets columns for this textarea. Chainable method.
	 * 
	 * @param	int		Columns number.
	 * 
	 * @return	FormControlTextarea
	 */
	public function setCols($num) {
	
		$this->cols = (int)$num;
		return $this;
	
	}
	
	/**
	 * Renders a TextArea field tag as HTML code.
	 *
	 * @return string
	 */
	public function render() {
	
		$ret  = '<textarea ' . $this->getNameProperty();
		$ret .= ' rows="' . $this->rows . '" cols="' . $this->cols . '"';
		$ret .= $this->processProperties() . '>';
		$ret .= htmlspecialchars($this->value) . '</textarea>';
	
		return $ret;
	
	}
	
	/**
	 * Validates this control against empty values, minimum length, maximum length,
	 * and returns TRUE if is all set checks pass.
	 *
	 * @return	bool
	 */
	public function validate() {
	
		$app	= Application::getInstance();
		$value	= Input::get($this->name);
		$valid	= TRUE;

		if ($this->required and ''==$value) {
			$app->logEvent('Control validation on field “' . $this->name . '” has failed (required)');
			$valid = FALSE;
		}
		
		if ($this->minLength and ''!=$value and strlen($value) < $this->minLength) {
			$app->logEvent('Control validation on field “' . $this->name . '” has failed (minLength=' . $this->minLength . ')');
			$valid = FALSE;
		}

		if ($this->maxLength and strlen($value) > $this->maxLength) {
			$app->logEvent('Control validation on field “' . $this->name . '” has failed (maxLength=' . $this->maxLength . ')');
			$valid = FALSE;
		}
		
		return $valid;

	}
	
}

class FormControlButton extends FormControl {
	
	/**
	 * Button type (submit, reset, button).
	 * @var string
	 */
	private $type;
	
	/**
	 * FontAwesome icon class.
	 * @var string
	 */
	private $faIcon;

	/**
	 * Sets type for a FormControlButton (submit, reset, button). Chainable method.
	 *
	 * @param	string	The button type.
	 *
	 * @return	FormControlButton
	 */
	public function setType($type) {
	
		$this->type = $type;
		return $this;
	
	}
	
	/**
	 * Sets a FontAwesome icon for this button object. Chainable method.
	 *
	 * @param	string	The icon class.
	 *
	 * @return	FormControlButton
	 */
	public function setFaIcon($class) {
	
		$this->faIcon = $class;
		return $this;
	
	}
	
	/**
	 * Renders an HTML button form control prepending an optional FontAwesome icon.
	 *
	 * @return	string
	 */
	public function render() {
	
		$ret = '<button type="' . $this->type . '"' ;
	
		if ($this->id) {
			$ret .= 'id=' . $this->id;
		}
	
		if ($this->name) {
			$ret .= ' ' . $this->getNameProperty();
		}
	
		$ret .= $this->processProperties() . '>';
	
		if ($this->faIcon) {
			$ret .= '<i class="fa ' . $this->faIcon . '"></i> ';
		}
	
		$ret .= trim(htmlspecialchars($this->value)) . ' </button>';
	
		return $ret;
	
	}
	
	/**
	 * Validation is disabled for buttons, returns always TRUE.
	 *
	 * @return	bool
	 */
	public function validate() {
		
		return TRUE;
		
	}
	
}
