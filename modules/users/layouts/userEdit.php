<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

?><div class="ibox float-e-margins">
	<div class="ibox-title">
		<h5><?php $this->_('USER_EDIT') ?></h5>
	</div>
	<div class="ibox-content">
		<form action="users/userChange" method="post" class="form-horizontal">
			<fieldset>

				<?php print $this->form->renderControl('id') ?>

				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('NAME') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl('name') ?></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('SURNAME') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl('surname') ?></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('EMAIL') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl('email') ?></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('ENABLED') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl('enabled') ?></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('LDAP_USER') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl('ldapUser') ?></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('USERNAME') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl('username') ?></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('PASSWORD') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl('password') ?></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('SHOW_PASSWORD') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl('showPassword') ?></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('LANGUAGE') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl('languageId') ?></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('GROUP') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl('groupId') ?></div>
				</div>
				<div class="buttonBar">
					<button type="submit" class="btn btn-primary" value="edit" name="action"><i class="fa fa-save"></i> <?php $this->_('CHANGE')?></button>
					<a href="users/userList" class="btn btn-default"><i class="fa fa-times"></i> <?php $this->_('CANCEL') ?></a>
					<a href="users/userDelete/<?php print $this->user->id ?>" class="btn btn-default confirmDelete"><i class="fa fa-trash-o"></i> <?php $this->_('DELETE')?></a>
				</div>
			</fieldset>
		</form>
	</div>
</div>