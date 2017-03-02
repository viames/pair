<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

?><div class="ibox float-e-margins">
	<div class="ibox-title">
		<h5><?php $this->_('NEW_GROUP') ?></h5>
	</div>
	<div class="ibox-content">
		<form action="users/groupAdd" method="post" class="form-horizontal">
			<fieldset>
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('NAME') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl('name') ?></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('IS_DEFAULT') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl('default') ?></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('DEFAULT_MODULE') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl('defaultAclId') ?></div>
				</div>
				<div class="buttonBar">
					<button type="submit" class="btn btn-primary" value="add" name="action"><i class="fa fa-asterisk"></i> <?php $this->_('INSERT') ?></button>
					<a href="users/groupList" class="btn btn-default"><i class="fa fa-times"></i> <?php $this->_('CANCEL') ?></a>
				</div>
			</fieldset>
		</form>
	</div>
</div>