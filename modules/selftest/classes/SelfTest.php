<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

class SelfTest {
	
	/**
	 * The tests list.
	 * @var array
	 */
	private $list = array();
	
	/**
	 * Returns property’s value if set or NULL if not set.
	 *
	 * @param	string	Property’s name.
	 * @return	mixed|NULL
	 */
	public function __get($name) {
	
		try {
			return $this->$name;
		} catch (Exception $e) {
			return NULL;
		}
	
	}

	/**
	 * Verifies that values strictly equal.
	 * 
	 * @param	string	Title of this test.
	 * @param	mixed	First value to check.
	 * @param	mixed	Second value to check.
	 * @param	string	Test section.
	 */
	public function assertEquals($label, $val1, $val2, $section) {
		
		$result = ($val1===$val2 ? TRUE : FALSE);
		
		$this->addTest($label, $result, $section);
		
	}
	
	/**
	 * Verifies that param object is instance of the specified class name.
	 *
	 * @param	string	Title of this test.
	 * @param	mixed	The object to check.
	 * @param	mixed	Name of the class.
	 * @param	string	Test section.
	 */
	public function assertInstanceOf($label, $object, $class, $section) {
		
		$result = (is_a($object, $class) ? TRUE : FALSE);
		
		$this->addTest($label, $result, $section);
		
	}

	/**
	 * Verifies that a class really has a property with passed name.
	 *
	 * @param	string	Title of this test.
	 * @param	mixed	The attribute to check.
	 * @param	mixed	Name of the class.
	 * @param	string	Test section.
	 */
	public function assertClassHasAttribute($label, $attributeName, $class, $section) {

		$reflect	= new ReflectionClass($class);
		$result		= $reflect->hasProperty($attributeName);

		$this->addTest($label, $result, $section);
		
	}

	/**
	 * Verifies that a class has static attribute
	 *
	 * @param	string	Title of this test.
	 * @param	mixed	Static attribute to check.
	 * @param	mixed	Name of the class.
	 * @param	string	Test section.
	 */
	public function assertClassHasStaticAttribute($label, $staticAttribute, $class, $section) {

		$reflect	= new ReflectionClass($class);
		$attribute	= $reflect->getProperty($staticAttribute);
		$result		= ($attribute->isStatic() ? TRUE : FALSE);

		$this->addTest($label, $result, $section);

	}

	/**
	 * Verifies that a class contains a specified function.
	 *
	 * @param	string	Title of this test.
	 * @param	mixed	Method to check.
	 * @param	mixed	Name of the class.
	 * @param	string	Test section.
	 */
	public function assertClassMethodExist($label, $methodName, $class, $section) {

		$reflect = new ReflectionClass($class);
		$result = $reflect->hasMethod($methodName);

		$this->addTest($label, $result, $section);

	}
	
	/**
	 * Verifies if var is TRUE.
	 * 
	 * @param	string	Title of this test.
	 * @param	bool	Value to check.
	 * @param	string	Test section.
	 */
	public function assertTrue($label, $var, $section) {
		
		$this->addTest($label, (bool)$var, $section);
		
	}

	/**
	 * Verifies that var is exactly 0.
	 *
	 * @param	string	Title of this test.
	 * @param	int		Value to check.
	 * @param	string	Test section.
	 */
	public function assertIsZero($label, $var, $section) {
	
		$this->addTest($label, (0 === $var ? TRUE : FALSE), $section);
	
	}

	/**
	 * Verifies that var is greater than param value.
	 *
	 * @param	string	Title of this test.
	 * @param	int		Value to check.
	 * @param	int		Value to compare.
	 * @param	string	Test section.
	 */
	public function assertGreaterThan($label, $var, $comparing, $section) {
	
		$this->addTest($label, ($var > $comparing ? TRUE : FALSE), $section);
	
	}

	/**
	 * Adds a test to list property.
	 * 
	 * @param	string	Test description label.
	 * @param	bool	TRUE if test was ok.
	 * @param	string	Test section.
	 */
	private function addTest($label, $result, $section) {
		
		$test			= new stdClass();
		$test->label	= $label;
		$test->result	= $result;

		$this->list[$section][]	= $test;
		
	}
	
}