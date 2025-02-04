<?php

namespace Pair\Html\FormControls;

use Pair\Core\Logger;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Helpers\Post;
use Pair\Helpers\Translator;
use Pair\Html\FormControl;
use Pair\Orm\Collection;

class Select extends FormControl {

	/**
	 * Items list of \stdClass objs with value and text attributes.
	 */
	private array $list = [];

	/**
	 * Flag to enable this control to multiple values.
	 */
	private bool $multiple = FALSE;

	/**
	 * If populated with text, add an empty option before the list of values.
	 */
	private ?string $emptyOption;

	/**
	 * Check whether this select control has options.
	 */
	public function hasOptions(): bool {

		return count($this->list) > 0;

	}

	/**
	 * Populates select control with an object array. Each object must have properties
	 * for value and text. If property text includes a couple of round parenthesys, will
	 * invoke a function without parameters. It’s a chainable method.
	 * 
	 * @param	array|Collection	Associative array [value=>label] or object list [{value,label,attributes}].
	 * @param	string	Name of property’s value.
	 * @param	string	Name of property’s text or an existent object function.
	 * @param 	string	Optional attributes [name=>value].
	 */
	public function options(array|Collection $list, ?string $propertyValue=NULL, ?string $propertyText=NULL, ?array $propertyAttributes=NULL): self {

		$allowedValues = ['string','integer','double'];

		// check if associative array
		if (is_array($list) and in_array(gettype(reset($list)), $allowedValues)) {

			$objectList = [];

			// convert associative array to a stdClass array
			foreach ($list as $value=>$text) {
				$object = new \stdClass();
				$object->value = $value;
				$object->text = (string)$text;
				$objectList[] = $object;
			}

			$list = $objectList;

			$propertyValue = 'value';
			$propertyText = 'text';

		}

		// for each item of the list, add an option
		foreach ($list as $opt) {

			if (!$propertyValue or !$propertyText) {
				throw new PairException($this->name . ' select control requires a property for value and text', ErrorCodes::MALFORMED_SELECT);
			}

			$option = new \stdClass();
			$option->value = $opt->$propertyValue;
			$option->attributes = [];

			if (is_array($propertyAttributes)) {
				foreach ($propertyAttributes as $pa) {
					array_push($option->attributes, ['name' => $pa, 'value' => $opt->$pa]);
				}
			} else if (is_string($propertyAttributes)) {
				array_push($option->attributes, ['name' => $propertyAttributes, 'value' => $opt->$propertyAttributes]);
			}

			// check wheter the propertyText is a function call
			if (FALSE !== strpos($propertyText,'()') and strpos($propertyText,'()')+2 == strlen($propertyText)) {
				$functionName = substr($propertyText, 0, strrpos($propertyText,'()'));
				$option->text = $opt->$functionName();
			} else {
				$option->text = $opt->$propertyText;
			}

			$this->list[] = $option;

		}

		return $this;

	}

	/**
	 * Populate this control through an array in which each element is the group title and
	 * in turn contains a list of objects with the value and text properties. Chainable.
	 * 
	 * @param	array:\stdClass[]	Two-dimensional list.
	 */
	public function grouped(array $list): self {

		$this->list = $list;

		return $this;

	}

	/**
	 * Adds a null value as first item. Chainable method.
	 * 
	 * @param	string|NULL	Option text for first null value.
	 */
	public function empty(?string $text=NULL): self {

		$this->emptyOption = is_null($text) ? Translator::do('SELECT_NULL_VALUE') : $text;

		return $this;

	}

	/**
	 * Enables this select control to accept multiple choises. Chainable method.
	 */
	public function multiple(): self {

		$this->multiple = TRUE;
		return $this;

	}

	/**
	 * Renders a Select field tag as HTML code.
	 */
	public function render(): string {

		// add an initial line to the options of this select
		if (isset($this->emptyOption) and !is_null($this->emptyOption)) {
			$option			= new \stdClass();
			$option->value	= '';
			$option->text	= ($this->disabled or $this->readonly) ? '' : $this->emptyOption;
			$this->list = array_merge([$option], $this->list);
		}

		$ret = '<select ' . $this->nameProperty();

		if ($this->multiple) {
			$ret .= ' multiple';
		}

		$ret .= $this->processProperties() . ">\n";

		// build each option
		foreach ($this->list as $item) {

			// recognize optgroup
			if (isset($item->list) and is_array($item->list) and count($item->list)) {

				$ret .= '<optgroup label="' . htmlspecialchars(isset($item->group) ? (string)$item->group : '') . "\">\n";
				foreach ($item->list as $option) {
					$ret .= $this->renderOption($option);
				}
				$ret .= "</optgroup>\n";

			} else {

				$ret .= $this->renderOption($item);

			}

		}

		$ret .= "</select>\n";
		return $ret;

	}

	/**
	 * Renders an option tag as HTML code.
	 */
	private function renderOption(\stdClass $option): string {

	   // check on required properties
	   if (!isset($option->value) or !isset($option->text)) {
		   return '';
	   }

	   // check if value is an array
	   if (is_array($this->value)) {
		   $selected = in_array($option->value, $this->value) ? ' selected="selected"' : '';
	   } else {
		   $selected = $this->value == $option->value ? ' selected="selected"' : '';
	   }

	   $attributes = '';

	   if (isset($option->attributes) and count($option->attributes)) {
		   foreach($option->attributes as $a) {
			   $attributes .= ' ' . $a['name'] . '="' . $a['value'] . '"';
		   }
	   }

	   // build the option
	   return '<option value="' . htmlspecialchars((string)$option->value) . '"' . $selected . $attributes . '>' .
			   htmlspecialchars((string)$option->text) . "</option>\n";

	}

	/**
	 * Validates this control and returns TRUE if is valid.
	 */
	public function validate(): bool {

		$value = Post::get($this->name);

		// check if the value is required but empty
		if ($this->required and (''==$value or is_null($value))) {

			Logger::notice('Control validation on field “' . $this->name . '” has failed (required)');
			$valid = FALSE;

		// check if the value is in the allowed list
		} else if (count($this->list)) {

			// this Select contains an empty option as the first element
			if (isset($this->emptyOption) and !is_null($this->emptyOption) and !$this->required and (''==$value or is_null($value))) {

				$valid = TRUE;

			} else {

				$valid = FALSE;

				// check if the value corresponds to one of the options
				foreach ($this->list as $item) {
					if ($item->value == $value) $valid = TRUE;
				}

				if (!$valid) {
					Logger::notice('Control validation on field “' . $this->name . '” has failed (value “' . $value . '” is not in list)');
				}

			}

		// empty list and value not required
		} else {

			$valid = TRUE;

		}

		return $valid;

	}

	/**
	 * Set value or multiple values.
	 */
	public function value(string|int|float|\DateTime|array|NULL $value): static {

		if (is_array($value)) {

			// special behavior for array values
			$this->value = $value;

		} else {

			parent::value($value);

		}

		return $this;

	}

}