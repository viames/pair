<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	VMS
 */

use VMS\Breadcrumb;
use VMS\Group;
use VMS\Router;
use VMS\View;
use VMS\Widget;

class UsersViewGroupEdit extends View {

	/**
	 * Computes data and assigns values to layout.
	 * 
	 * @see View::render()
	 */
	public function render() {
		
		$route	= Router::getInstance();

		$groupId	= $route->getParam(0);
		$group		= new Group($groupId);
		
		$this->app->pageTitle = $this->lang('GROUP_EDIT');
		$this->app->activeMenuItem = 'users/groupList';
		
		$breadcrumb = Breadcrumb::getInstance();
		$breadcrumb->addPath($this->lang('GROUPS'), 'users/groupList');
		$breadcrumb->addPath('Gruppo ' . $group->name, 'users/groupEdit/' . $group->id);
		
		$widget = new Widget();
		$this->app->breadcrumbWidget = $widget->render('breadcrumb');
		
		$widget = new Widget();
		$this->app->sideMenuWidget = $widget->render('sideMenu');

		$modules = $this->model->getAcl($group->id);

		// check if acls exist
		$group->modules = count($modules) ? TRUE : FALSE;

		// populate form fields
		$form = $this->model->getGroupForm();
		$form->getControl('defaultAclId')->setListByObjectArray($modules,'id','moduleAction');
		$form->setValuesByObject($group);

		if ($group->default) {
			$form->getControl('default')->setDisabled();
		}

		// get default acl
		$acl = $group->getDefaultAcl();
		
		// set acl value if there’s a default one
		if ($acl) {
			$form->getControl('defaultAclId')->setValue($acl->id);
		}

		$this->assign('group',	$group);
		$this->assign('form',	$form);
		
	}

}
