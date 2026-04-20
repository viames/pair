<?php

declare(strict_types=1);

use Pair\Core\View;
use Pair\Html\Breadcrumb;

class UserViewDefault extends View {

	public function render(): void {

		$this->pageTitle($this->lang('USER'));
		$this->app->activeMenuItem = 'user';
		Breadcrumb::path($this->lang('USER'), 'user');
		$this->assign('userName', $this->model->user->html('name'));

	}

}
