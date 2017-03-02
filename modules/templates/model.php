<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Form;
use Pair\Model;
use Pair\Template;

class TemplatesModel extends Model {
	
	/**
	 * Returns list of all registered templates.
	 *
	 * @return array:Template
	 */
	public function getTemplates() {
	
		$this->db->setQuery('SELECT t.* FROM templates AS t ORDER BY t.date_installed DESC');
		$list = $this->db->loadObjectList();

		$templates = array();
	
		foreach ($list as $item) {
			$templates[] = new Template($item);
		}
	
		return $templates;
	
	}
	
	/**
	 * Returns records count.
	 *
	 * @return	int
	 */
	public function countTemplates() {
	
		$this->db->setQuery('SELECT COUNT(*) FROM templates');
		return (int)$this->db->loadResult();
	
	}
	
	public function getTemplateForm() {
	
		$form = new Form();
		$form->addInput('package')->setType('file')->setRequired();
		return $form;
	
	}
	
}
