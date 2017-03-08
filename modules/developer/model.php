<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Application;
use Pair\Form;
use Pair\Language;
use Pair\Model;
use Pair\Translator;

class DeveloperModel extends Model {

	/**
	 * Name of db table.
	 * @var string
	 */
	protected $tableName;
	
	/**
	 * Db Table primary or compound key.
	 * @var string|array
	 */
	protected $tableKey;

	/**
	 * List of couples property => db_field.
	 * @var array
	 */
	protected $binds;
	
	/**
	 * Class name with uppercase first char.
	 * @var string
	 */
	protected $objectName;
	
	/**
	 * List of properties type (property_name => type).
	 * @var array
	 */
	protected $propType;
	
	/**
	 * List of values for each enum/set property.
	 * @var array
	 */
	private $members;
	
	/**
	 * Name of CRUD module, all lowercase with no underscore.
	 * @var string
	 */
	protected $moduleName;
	
	/**
	 * File author meta tag.
	 * @var string
	 */
	protected $author;
	
	/**
	 * File package meta tag.
	 * @var string
	 */
	protected $package;
	
	/**
	 * Returns db tables name that has no classes who manages them.
	 * 
	 * @return array:string
	 */
	public function getUnmappedTables() {
		
		$this->db->setQuery('SHOW TABLES');
		$dbTables = $this->db->loadResultList();
		
		$mappedTables = $this->getMappedTables();
		
		$unmappedTables = array_diff($dbTables, $mappedTables);
		
		return $unmappedTables;
		
	}
	
	/**
	 * Returns list of class names that inherit from ActiveRecord.
	 *
	 * @return array
	 */
	private function getMappedTables() {
	
		$mappedTables = array();
	
		// list of class files
		$classFiles = array_diff(scandir('classes'), array('..', '.', '.DS_Store'));
		
		foreach ($classFiles as $file) {
	
			// cuts .php from file name
			$class = substr($file, 0, -4);
			
			// needed for new classes not already included
			include_once ('classes/' . $file);
			
			if (class_exists($class)) {
				
				$reflection = new ReflectionClass($class);
				
				// will adds just requested children
				if ($reflection->isSubclassOf('Pair\ActiveRecord') and !$reflection->isAbstract()) {
					$mappedTables[] = $class::TABLE_NAME;
				}
			
			}
	
		}

		// list of class files
		$frameworkFiles = array_diff(scandir('vendor/pair'), array('..', '.', '.DS_Store'));
		
		foreach ($frameworkFiles as $file) {
		
			// cut .php from file name
			$class = substr($file, 0, -4);
				
			// needed for new classes not already included
			include_once ('vendor/pair/' . $file);
				
			// will adds just requested children
			if (is_subclass_of($class, 'Pair\ActiveRecord')) {
				$mappedTables[] = $class::TABLE_NAME;
			}
		
		}
		
		// list of class files in modules
		$modules = array_diff(scandir('modules'), array('..', '.', '.DS_Store'));
	
		foreach ($modules as $module) {
				
			$classesFolder = 'modules/' . $module . '/classes';
				
			if (is_dir($classesFolder)) {
	
				$classFiles = array_diff(scandir($classesFolder), array('..', '.', '.DS_Store'));
	
				foreach ($classFiles as $file) {
	
					// only .php files are included
					if ('.php' == substr($file,-4)) {
	
						include_once ($classesFolder . '/' . $file);
	
						// cut .php from file name
						$class = substr($file, 0, -4);
						
						// will adds just requested children
						if (is_subclass_of($class, 'Pair\ActiveRecord')) {
							$mappedTables[] = $class::TABLE_NAME;
						}
	
					}
	
				}
	
			}
	
		}
		
		sort($mappedTables);
	
		return $mappedTables;
	
	}
	
	public function getClassWizardForm() {
		
		$form = new Form();
		$form->addInput('objectName')->setRequired();
		$form->addInput('tableName')->setType('hidden')->setRequired();
		return $form;
		
	}

	public function getModuleWizardForm() {
	
		$form = new Form();
		$form->addInput('objectName')->setRequired();
		$form->addInput('moduleName')->setRequired();
		$form->addInput('tableName')->setType('hidden')->setRequired();
		return $form;
	
	}
	
	private function getCamelCase($text, $capFirst=FALSE) {
		
		$camelCase = str_replace(' ', '', ucwords(str_replace('_', ' ', $text)));
		
		if (!$capFirst) {
			$camelCase[0] = lcfirst($camelCase[0]);
		}
		
		return $camelCase;
	
	}
	
	private function getObjectNameHint($tableName) {
		
		if ('s' == substr($tableName,-1)) {
			$tableName = substr($tableName,0,-1);
		}
		
		return $this->getCamelCase($tableName, TRUE);
		
	}
	
	/**
	 * Setups all needed variables before to proceed class/module creation.
	 * 
	 * @param	string	Table name all lowercase with underscores.
	 * @param	string	Optional object name with uppercase first and case sensitive.
	 * @param	string	Optional module name all lowercase alpha chars only.
	 */
	public function setupVariables($tableName, $objectName=NULL, $moduleName=NULL) {
		
		$app = Application::getInstance();
		
		$this->tableName	= $tableName;
		$this->moduleName	= $moduleName ? $moduleName : strtolower(str_replace('_', '', $tableName));
		$this->objectName	= $objectName ? $objectName : $this->getObjectNameHint($tableName);
		$this->author		= $app->currentUser->fullName;
		$this->package		= PRODUCT_NAME;
		
		$this->propType		= array();
		$this->binds		= array();
		
		$boolTypes = array('bool', 'tinyint(1)', 'smallint(1)', 'int(1)');
		
		// table columns
		$this->db->setQuery('SHOW COLUMNS FROM ' . $this->db->escape($tableName));
		$columns = $this->db->loadObjectList();
		
		// iterates all found columns
		foreach ($columns as $column) {
			
			$property = $this->getCamelCase($column->Field);
			
			// set the table key
			if ('PRI' == $column->Key) {
				
				// if already set a primary key, change to compound key
				if (is_string($this->tableKey)) {
					
					$this->tableKey = array($this->tableKey, $column->Field);
				
				// otherwise it’s already a compound key
				} else if (is_array($this->tableKey) and count($this->tableKey)>1) {
					
					$this->tableKey[] = $column->Field;
					
				// otherwise set a primary key
				} else {
					
					$this->tableKey = $column->Field;
					
				}
			}

			// datetime type
			if ('date' == $column->Type) {
				
				$this->propType[$property] = 'date';

			} else if ('datetime' == $column->Type) {
				
				$this->propType[$property] = 'datetime';
				
			// bool type
			} else if (in_array(substr($column->Type,0,(strrpos($column->Type, ')') ? strrpos($column->Type, ')')+1 : strlen($column->Type))), $boolTypes)) {
				
				$this->propType[$property] = 'bool';
				
			// int type
			} else if ('int' == substr($column->Type,0,3) or 'bigint' == substr($column->Type,0,6)) {
				
				$this->propType[$property] = 'int';

			// string type
			} else if ('varchar' == substr($column->Type,0,7) or 'decimal' == substr($column->Type,0,7)) {
				
				$this->propType[$property] = 'text';

			// enum type
			} else if ('enum' == substr($column->Type,0,4)) {
				
				$this->propType[$property] = 'enum';
				$this->members[$property] = explode("','", substr($column->Type, 6, -2));

			// set type
			} else if ('set' == substr($column->Type,0,3)) {
			
				$this->propType[$property] = 'set';
				$this->members[$property] = explode("','", substr($column->Type, 5, -2));
				
			// text type
			} else {
				
				$this->propType[$property] = 'text';
				
			}
			
			// populates binds with object properties as key and db column as value
			$this->binds[$property] = $column->Field;
			
		}
		
	}
	
	public function saveClass($file) {
		
		// here starts building of property cast
		$inits		= array();
		$datetimes	= array();
		$integers	= array();
		$booleans	= array();
		
		// populates properties and binds
		foreach ($this->binds as $property=>$field) {
			
			// assembles php-doc
			$prop = "\t/**\r\n\t * Property that binds db field $field.\r\n\t * @var ";

			// sets right property type
			switch ($this->propType[$property]) {
				
				case 'text':	
				case 'enum':
				case 'set':
					$prop .= 'string';
					break;
				
				case 'int':
					$prop .= 'int';
					$integers[] = "'" . $property . "'";
					break;
					
				case 'bool':
					$prop .= 'bool';
					$booleans[] = "'" . $property . "'";
					break;
				
				case 'date':
				case 'datetime':
					$prop .= 'DateTime';
					$datetimes[] = "'" . $property . "'";
					break;
					
				default:
					$prop .= 'unknown';
					break;
					
			}

			$prop .= "\r\n\t */\r\n\tprotected $" . $property . ';';
			$properties[] = $prop;

			$binds[] = "\r\n\t\t\t'" . $property . "' => '" . $field . "'";
			
		}

		if (count($datetimes)) {
			$inits[] = '$this->bindAsDatetime(' . implode(', ', $datetimes) . ');';
		}
		
		if (count($integers)) {
			$inits[] = '$this->bindAsInteger(' . implode(', ', $integers) . ');';
		}
			
		if (count($booleans)) {
			$inits[] = '$this->bindAsBoolean(' . implode(', ', $booleans) . ');';
		}

		// at least one var group need to be cast
		if (count($inits)) {

			$init = 
"	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function init() {

		" . implode("\r\n\r\n\t\t", $inits) . "

	}\r\n\r\n";

		}
		
		// here starts code collect
		$content = '<?php
		
/**
 * @version	$Id'.'$
 * @author	' . $this->author . ' 
 * @package	' . $this->package . '
 */

use Pair\ActiveRecord;

class ' . $this->objectName . ' extends ActiveRecord {

' . implode("\n\r", $properties) . '

	/**
	 * Name of related db table.
	 * @var string
	 */
	const TABLE_NAME = \'' . $this->tableName . '\';
		
	/**
	 * Name of primary key db field.
	 * @var string
	 */
	const TABLE_KEY = ' . $this->getFormattedTableKey() . ';
		
' . $init .
'	/**
	 * Returns array with matching object property name on related db fields.
	 *
	 * @return	array
	 */
	protected static function getBinds() {
		
		$varFields = array (' . implode(",", $binds) . ');
		
		return $varFields;
		
	}
	
}';
		
		// writes the code into the file
		$this->writeFile($file, $content);
	
	}
	
	public function saveModel($file) {

		$fields = array();
		
		foreach ($this->binds as $property=>$field) {
			
			// hidden inputs
			if ($this->isTableKey($field)) {
				
				$field = "\$form->addInput('" . $property . "')->setType('hidden')";
			
			// standard inputs
			} else {
			
				switch ($this->propType[$property]) {
					
					case 'date':
						$field = "\$form->addInput('" . $property . "')->setType('date')";
						break;

					case 'datetime':
						$field = "\$form->addInput('" . $property . "')->setType('datetime')";
						break;
								
					case 'bool':
						$field = "\$form->addInput('" . $property . "')->setType('bool')";
						break;
					
					case 'int':
						$field = "\$form->addInput('" . $property . "')->setType('number')";
						break;
					
					case 'enum':
					case 'set':
						$values = array();
						foreach($this->members[$property] as $value) {
							$values [] = "'" . $value . "'=>'" . $value . "'";
						}
						$field  = "\$form->addSelect('" . $property . "')";
						if ('set'==$this->propType[$property]) {
							$field .= '->setMultiple()';
						}
						if (count($values)) {
							$field .= '->setListByAssociativeArray(array(' . implode(',', $values) . '))';
						}
						break;
					
					default:
						$field = "\$form->addInput('" . $property . "')";
						break;
				
				}
				
			}
			
			// indentation
			$fields[] = "\t\t" . $field . ";";
		
		}
		
		// here starts code collect
		$content = '<?php

/**
 * @version $Id'.'$
 * @author	' . $this->author . ' 
 * @package	' . $this->package . '
 */

use Pair\Form;
use Pair\Model;

class ' . ucfirst($this->moduleName) . 'Model extends Model {

	/**
	 * Returns object list with pagination.
	 *
	 * @return	array:' . $this->objectName . '
	 */
	public function get' . ucfirst($this->moduleName) . '() {

		$query = \'SELECT * FROM `' . $this->tableName . '` LIMIT \' . $this->pagination->start . \', \' . $this->pagination->limit;
		$this->db->setQuery($query);
		$list = $this->db->loadObjectList();

		$' . $this->getCamelCase($this->tableName) . ' = array();

		foreach ($list as $row) {
			$' . $this->getCamelCase($this->tableName) . '[] = new ' . $this->objectName . '($row);
		}

		return $' . $this->getCamelCase($this->tableName) . ';

	}

	/**
	 * Returns count of available objects.
	 *
	 * @return	int
	 */
	public function count' . ucfirst($this->moduleName) . '() {

		$query = \'SELECT COUNT(1) FROM ' . $this->tableName . '\';
		$this->db->setQuery($query);
		return $this->db->loadCount();

	}

	/**
	 * Returns the Form object for create/edit ' . $this->objectName . ' objects.
	 * 
	 * @return Form
	 */ 
	public function get' . $this->objectName . 'Form() {
		
		$form = new Form();
			
		$form->addControlClass(\'form-control\');
			
' . implode("\r\n", $fields) . '
		
		return $form;
		
	}
				
}';
		
		// writes the code into the file
		$this->writeFile($file, $content);

	}
	
	public function saveController($file) {
		
		// initialize
		$propList = array();
		$newList  = array();
		$editList = array();
		
		// build each input line
		foreach ($this->binds as $property=>$field) {
			// FIXME make it working for compound key
			if (!$this->isTableKey($field)) {
				$newList[]  = "\t\t\$" . lcfirst($this->objectName) . '->' . $property . ' = Input::get(\'' . $property . '\');';
				$editList[] = "\t\t\$" . lcfirst($this->objectName) . '->' . $property . ' = Input::get(\'' . $property . '\');';
			}
		}
		
		// here starts code collect
		$content = '<?php

/**
 * @version $Id'.'$
 * @author	' . $this->author . ' 
 * @package	' . $this->package . '
 */

use Pair\Controller;
use Pair\Input;
use Pair\Router;
 		
class ' . ucfirst($this->moduleName) . 'Controller extends Controller {

	protected function init() {

		include (\'classes/' . $this->objectName . '.php\');

	}
				
	/**
	 * Adds a new object.
	 */
	public function addAction() {
	
		$' . lcfirst($this->objectName) . ' = new ' . $this->objectName . '();
' . implode("\r\n", $newList) . '

		$result = $' . lcfirst($this->objectName) . '->create();
		
		if ($result) {
			$this->enqueueMessage($this->lang(\'' . strtoupper($this->objectName) . '_HAS_BEEN_CREATED\'));
			$this->redirect(\'' . $this->moduleName . '/default\');
		} else {
			$msg = $this->lang(\'' . strtoupper($this->objectName) . '_HAS_NOT_BEEN_CREATED\') . \':\';
			foreach ($' . lcfirst($this->objectName) . '->getErrors() as $error) {
				$msg .= " \n" . $error;
			}
			$this->enqueueError($msg);
			$this->view = \'default\';
		}					

	}

	/**
	 * Shows form for edit a ' . $this->objectName . ' object.
	 */
	public function editAction() {
	
		$' . lcfirst($this->objectName) . ' = $this->getObjectRequestedById(\'' . $this->objectName . '\');
	
		$this->view = $' . lcfirst($this->objectName) . ' ? \'edit\' : \'default\';
	
	}

	/**
	 * Modifies a ' . $this->objectName . ' object.
	 */
	public function changeAction() {

		$' . lcfirst($this->objectName) . ' = new ' . $this->objectName . '(Input::get(' . $this->getFormattedTableKey() . '));

' . implode("\r\n", $editList) . '

		// apply the update
		$result = $' . lcfirst($this->objectName) . '->update();

		if ($result) {

			// notify the change and redirect
			$this->enqueueMessage($this->lang(\'' . strtoupper($this->objectName) . '_HAS_BEEN_CHANGED_SUCCESFULLY\'));
			$this->redirect(\'' . $this->moduleName . '/default\');

		} else {

			// get error list from object
			$errors = $' . lcfirst($this->objectName) . '->getErrors();

			if (count($errors)) { 
				$message = $this->lang(\'ERROR_ON_LAST_REQUEST\') . ": \n" . implode(" \n", $errors);
				$this->enqueueError($message);
				$this->view = \'default\';
			} else {
				$this->redirect(\'' . $this->moduleName . '/default\');
			}

		}

	}

	/**
	 * Deletes a ' . $this->objectName . ' object.
	 */
	public function deleteAction() {

		$route = Router::getInstance();

	 	$' . lcfirst($this->objectName) . ' = new ' . $this->objectName . '($route->getParam(0));

		// execute deletion
		$result = $' . lcfirst($this->objectName) . '->delete();

		if ($result) {

			$this->enqueueMessage($this->lang(\'' . strtoupper($this->objectName) . '_HAS_BEEN_DELETED_SUCCESFULLY\'));
			$this->redirect(\'' . $this->moduleName . '/default\');

		} else {

			// get error list from object
			$errors = $' . lcfirst($this->objectName) . '->getErrors();

			if (count($errors)) { 
				$message = $this->lang(\'ERROR_DELETING_' . strtoupper($this->objectName) . '\') . ": \n" . implode(" \n", $errors);
				$this->enqueueError($message);
				$this->view = \'default\';
			} else {
				$this->redirect(\'' . $this->moduleName . '/default\');
			}

		}

	}

}';

		// writes the code into the file
		$this->writeFile($file, $content);

	}

	public function saveLanguage($file) {
		
		$tran = Translator::getInstance();
		
		// sets language for create the right file
		$userLang = $tran->current;
		$tran->current = $tran->default;

		// gets language name
		$language = Language::getLanguageByCode($tran->default);
		
		$ucObject = strtoupper($this->objectName);
		
		$fields = array();

		// list of table fields
		foreach ($this->binds as $field) {
		
			$fields[] = strtoupper($field) . ' = "' . str_replace('_', ' ', ucfirst($field)) . '"';

		}
		
		// here starts code collect
		$content = '; $Id'.'$
; ' . $language->languageName . ' language

' . strtoupper($this->tableName) . ' = "' . str_replace('_', ' ', ucfirst($this->tableName)) .'"
' . $ucObject . '_HAS_BEEN_CREATED = "' . $tran->translate('OBJECT_HAS_BEEN_CREATED', $this->objectName) . '"
' . $ucObject . '_HAS_NOT_BEEN_CREATED = "' . $tran->translate('OBJECT_HAS_NOT_BEEN_CREATED', $this->objectName) . '"
' . $ucObject . '_HAS_BEEN_CHANGED_SUCCESFULLY = "' . $tran->translate('OBJECT_HAS_BEEN_CHANGED_SUCCESFULLY', $this->objectName) . '"
' . $ucObject . '_HAS_BEEN_DELETED_SUCCESFULLY = "' . $tran->translate('OBJECT_HAS_BEEN_DELETED_SUCCESFULLY', $this->objectName) . '"
ERROR_DELETING_' . $ucObject . ' = "' . $tran->translate('ERROR_DELETING_OBJECT', $this->objectName) . '"
NEW_' . strtoupper($this->objectName) . ' = "' . $tran->translate('NEW_OBJECT', $this->objectName) . '"
EDIT_' . strtoupper($this->objectName) . ' = "' . $tran->translate('EDIT_OBJECT', $this->objectName) . '"
' . implode("\r\n", $fields) . '
';
		
		// writes the code into the file
		$this->writeFile($file, $content);

		// sets back the user language
		$tran->current = $userLang;
		
	}
	
	public function saveViewDefault($file) {
		
		// here starts code collect
		$content = '<?php
		
/**
 * @version $Id'.'$
 * @author	' . $this->author . '
 * @package	' . $this->package . '
 */

use Pair\View;

class ' . ucfirst($this->moduleName) . 'ViewDefault extends View {

	public function render() {

		$this->app->pageTitle		= $this->lang(\'' . strtoupper($this->tableName) . '\');
		$this->app->activeMenuItem	= \'' . $this->moduleName . '\';

		$' . $this->getCamelCase($this->tableName) . ' = $this->model->get' .  ucfirst($this->moduleName) . '();

		$this->pagination->count = $this->model->count' . ucfirst($this->moduleName) . '();

		$this->assign(\'' . $this->getCamelCase($this->tableName) . '\', $' . $this->getCamelCase($this->tableName) . ');

	}

}';

		// writes the code into the file
		$this->writeFile($file, $content);

	}
	
	public function saveLayoutDefault($file) {
		
		$headers = array();
		$rows = array();
		
		foreach ($this->binds as $property=>$field) {
		
			if (!$this->isTableKey($field)) {
				$headers[] = "\t\t\t\t\t\t\t\t<th><?php \$this->_('" . strtoupper($field) . "') ?></th>";
				if ('date' == $this->propType[$property]) {
					$rows[] = "\t\t\t\t\t\t\t\t<td><?php print htmlspecialchars(\$o->" . $property . "->format('Y-m-d')) ?></td>";
				} else if ('datetime' == $this->propType[$property]) {
					$rows[] = "\t\t\t\t\t\t\t\t<td><?php print htmlspecialchars(\$o->" . $property . "->format('Y-m-d H:i')) ?></td>";
				} else {
					$rows[] = "\t\t\t\t\t\t\t\t<td><?php print htmlspecialchars(\$o->" . $property . ") ?></td>";
				}
			}
			
			
		}

		// edit icon
		$headers[] = "\t\t\t\t\t\t\t\t<th></th>";
		$rows[] = "\t\t\t\t\t\t\t\t<td><a class=\"btn btn-default btn-xs\" href=\"" . $this->moduleName . '/edit/<?php print ' . $this->getTableKeyAsCgiParams('$o') . ' ?>"><i class="fa fa-pencil"></i> <?php $this->_(\'EDIT\') ?></a></td>';
		
		// here starts code collect
		$content = '<?php

/**
 * @version $Id'.'$
 * @author	' . $this->author . '
 * @package	' . $this->package . '
 */

use Pair\Utilities;

?><div class="col-lg-12">
	<div class="ibox">
		<div class="ibox-title">
				<h5><?php $this->_(\'' . strtoupper($this->tableName) . '\') ?></h5>
				<div class="ibox-tools">
					<a class="btn btn-primary btn-xs" href="' . $this->moduleName . '/new"><i class="fa fa-plus-circle"></i> <?php $this->_(\'NEW_' . strtoupper($this->objectName) . '\') ?></a>
				</div>
			</div>
			<div class="ibox-content">
				<div class="table-responsive"><?php
		
if (count($this->' . $this->getCamelCase($this->tableName) . ')) {
		
					?><table class="table table-hover">
						<thead>
							<tr>
' . implode("\r\n", $headers) . '
							</tr>
						</thead>
						<tbody><?php
			
							foreach ($this->' . $this->getCamelCase($this->tableName) . ' as $o) {
		
							?><tr>
' . implode("\r\n", $rows) . '
							</tr><?php 
				
							}
							
						?></tbody>
					</table><?php
		
	print $this->getPaginationBar();
		
} else {

	Utilities::printNoDataMessageBox();

}

			?></div>
		</div>
	</div>
</div>';

		// writes the code into the file
		$this->writeFile($file, $content);

	}
	
	
	public function saveViewNew($file) {

		// here starts code collect
		$content = '<?php

/**
 * @version $Id'.'$
 * @author	' . $this->author . '
 * @package	' . $this->package . '
 */

use Pair\View;

class ' . ucfirst($this->moduleName) . 'ViewNew extends View {

	public function render() {

		$this->app->pageTitle = $this->lang(\'NEW_' . strtoupper($this->objectName) . '\');
		$this->app->activeMenuItem = \'' . $this->moduleName . '\';

		$form = $this->model->get' . $this->objectName . 'Form();
		
		$this->assign(\'form\', $form);
		
	}

}
';

		// writes the code into the file
		$this->writeFile($file, $content);

	}
	
	public function saveLayoutNew($file) {
	
		$fields = array();
		
		foreach ($this->binds as $property=>$field) {
			
			if (!$this->isTableKey($field)) {

				$fields[] = '
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_(\'' . strtoupper($field) . '\') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl(\'' . $property . '\') ?></div>
				</div>';
				
			}
			
		}
		
		// here starts code collect
		$content = '<?php
	
/**
 * @version $Id'.'$
 * @author	' . $this->author . '
 * @package	' . $this->package . '
 */
	
?><div class="ibox float-e-margins">
	<div class="ibox-title">
		<h5><?php $this->_(\'NEW_' . strtoupper($this->objectName) . '\') ?></h5>
	</div>
	<div class="ibox-content">
		<form action="' . $this->moduleName . '/add" method="post" class="form-horizontal">
			<fieldset>' . implode('', $fields)  . ' 
			</fieldset>
			<div class="hr-line-dashed"></div>
			<div class="form-group">
				<div class="col-sm-4 col-sm-offset-2">
					<button type="submit" class="btn btn-primary" value="add" name="action"><i class="fa fa-asterisk"></i> <?php $this->_(\'INSERT\') ?></button>
					<a href="' . $this->moduleName . '/default" class="btn btn-default"><i class="fa fa-times"></i> <?php $this->_(\'CANCEL\') ?></a>
				</div>
			</div>
		</form>
	</div>
</div>';

		// writes the code into the file
		$this->writeFile($file, $content);

	}
	
	public function saveViewEdit($file) {
		
		// need loop route-params for each table key
		if (is_array($this->tableKey)) {

			$params	= '';
			$vars	= array();
			
			foreach ($this->tableKey as $index => $k) {
				$var	= '$' . $this->getCamelCase($k);
				$vars[]	= $var;
				$params.= '		' . $var . ' = $route->getParam(' . $index . ");\n";
			}
			
			$key = 'array(' . implode(', ', $vars) . ')';
				
		} else {
			$key	= '$' . $this->getCamelCase($this->tableKey);
			$params	= '		' . $key . ' = $route->getParam(0);';
		}		

		// here starts code collect
		$content = '<?php

/**
 * @version $Id'.'$
 * @author	' . $this->author . '
 * @package	' . $this->package . '
 */

use Pair\Router;
use Pair\View;

class ' . ucfirst($this->moduleName) . 'ViewEdit extends View {

	public function render() {

		$this->app->pageTitle = $this->lang(\'EDIT_' . strtoupper($this->objectName) . '\');
		$this->app->activeMenuItem = \'' . $this->moduleName . '\';

		$route = Router::getInstance();
' . $params . '
		$' . lcfirst($this->objectName) . ' = new ' . $this->objectName . '(' . $key . ');

		$form = $this->model->get' . ucfirst($this->objectName) . 'Form();
		$form->setValuesByObject($' . lcfirst($this->objectName) . ');

		$this->assign(\'form\', $form);
		$this->assign(\'' . lcfirst($this->objectName) . '\', $' . lcfirst($this->objectName) . ');
		
	}
	
}
';

		// writes the code into the file
		$this->writeFile($file, $content);

}
	
	public function saveLayoutEdit($file) {
		
		$fields = array();
		
		foreach ($this->binds as $property=>$field) {
				
			if (!$this->isTableKey($field)) {
		
				$fields[] = '
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_(\'' . strtoupper($field) . '\') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl(\'' . $property . '\') ?></div>
				</div>';
		
			} else {
				
				$fields[] = "\r\n\t\t\t\t".'<?php print $this->form->renderControl(\'' . $property . '\') ?>';
				
			}
				
		}
		
		// here starts code collect
		$content = '<?php

/**
 * @version $Id'.'$
 * @author	' . $this->author . '
 * @package	' . $this->package . '
 */

?><div class="ibox float-e-margins">
	<div class="ibox-title">
			<h5><?php $this->_(\'EDIT_' . strtoupper($this->objectName) . '\') ?></h5>
	</div>
	<div class="ibox-content">
		<form action="' . $this->moduleName . '/change" method="post" class="form-horizontal">
			<fieldset>' . implode('', $fields) . '
			</fieldset>
			<div class="hr-line-dashed"></div>
			<div class="form-group">
				<div class="col-sm-4 col-sm-offset-2">
					<button type="submit" class="btn btn-primary" value="edit" name="action"><i class="fa fa-save"></i> <?php $this->_(\'CHANGE\') ?></button>
					<a href="' . $this->moduleName . '/default" class="btn btn-default"><i class="fa fa-times"></i> <?php $this->_(\'CANCEL\') ?></a>
					<a href="' . $this->moduleName . '/delete/<?php print ' . $this->getTableKeyAsCgiParams() . ' ?>" class="btn btn-default confirmDelete"><i class="fa fa-trash-o"></i> <?php $this->_(\'DELETE\') ?></a>
				</div>
			</div>
		</form>
	</div>
</div>
';
		
		// writes the code into the file
		$this->writeFile($file, $content);
		
	}

	/**
	 * Writes content into file with 0777 permissions.
	 * 
	 * @param	string	File name and full path.
	 * @param	string	File content.
	 */
	private function writeFile($file, $content) {
		
		$old = umask(0);
		file_put_contents($file, $content);
		umask($old);
		chmod($file, 0777);
		
	}

	/**
	 * Return the table key formatted for use in class text as string or array.
	 * 
	 * @return string
	 */
	private function getFormattedTableKey() {
		
		if (is_array($this->tableKey)) {
			return "array('" . implode("', '", $this->tableKey) . "')";
		} else {
			return "'" . $this->tableKey . "'";
		}
		
	}
	
	/**
	 * Assert that passed field is in table key.
	 * 
	 * @param	string	Field name.
	 * 
	 * @return	boolean
	 */
	private function isTableKey($field) {
		
		if (is_array($this->tableKey)) {
			return (in_array($field, $this->tableKey));
		} else {
			return ($field == $this->tableKey);
		}
		
	}
	
	/**
	 * Format table key variables to be append on CGI URLs.
	 * 
	 * @param	string	Optional variable name with $ prefix to put before each key var.
	 * 
	 * @return	string
	 */
	private function getTableKeyAsCgiParams($object=NULL) {
		
		if (is_null($object)) {
			$object = '$this->' . lcfirst($this->objectName);
		}
		
		if (is_array($this->tableKey)) {
			
			$vars = array();
			
			foreach ($this->tableKey as $k) {
				$vars[] = $object . '->' . $this->getCamelCase($k);
			}
			
			return implode(" . '/' . ", $vars);
			
		} else {
			
			return $object . '->' . $this->getCamelCase($this->tableKey);
			
		}
		
	}

}