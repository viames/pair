<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

?><div class="ibox float-e-margins">
	<div class="ibox-title">
		<h5><?php $this->_('DEVELOPER') ?></h5>
	</div>
	<div class="ibox-content">
		<form action="developer/moduleCreation" method="post" class="form-horizontal">
			<fieldset>
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('OBJECT_NAME')?></label>
					<div class="col-sm-4"><?php print $this->form->renderControl('objectName') ?></div>
					<div class="col-sm-6 description"><?php $this->_('OBJECT_NAME_DESCRIPTION')?></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('MODULE_NAME')?></label>
					<div class="col-sm-4"><?php print $this->form->renderControl('moduleName') ?></div>
					<div class="col-sm-6 description"><?php $this->_('MODULE_NAME_DESCRIPTION')?></div>
				</div>
				<?php print $this->form->renderControl('tableName') ?>
				<div class="buttonBar">
					<button type="submit" class="btn btn-primary" value="save" name="action"><i class="fa fa-save"></i> <?php $this->_('CREATE_MODULE')?></button>
					<a href="developer/default" class="btn btn-default"><i class="fa fa-times"></i> <?php $this->_('CANCEL')?></a>
				</div>
			</fieldset>
		</form>
	</div>
</div>