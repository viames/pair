<?php

declare(strict_types=1);

use Pair\Core\Controller;
use Pair\Html\Breadcrumb;

class UserController extends Controller {

	/**
	 * Prepare the legacy page breadcrumb.
	 */
	protected function _init(): void {

		Breadcrumb::path($this->lang('USER'), 'user');

	}

	/**
	 * Keep the old implicit MVC flow for the fixture app.
	 */
	public function defaultAction(): void {

		if (!$this->model->getLoginForm()->isValid()) {
			$this->toastError($this->lang('ERROR_FORM_IS_NOT_VALID'));
			$this->setView('default');
			return;
		}

		$this->redirect('user/profile');

	}

}
