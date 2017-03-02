<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

?><div class="ibox float-e-margins">
	<div class="ibox-title">
		<h5><?php $this->_('GROUP_EDIT') ?> "<?php print $this->group->name ?>"</h5>
	</div>
	<div class="ibox-content">
		<form action="users/groupChange" method="post" class="form-horizontal">
			<fieldset>

				<?php print $this->form->renderControl('id') ?>

				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('NAME') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl('name') ?></div>
				</div>
				<div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('IS_DEFAULT') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl('default') ?></div>
				</div><?php
		
			if ($this->group->modules) { 

				?><div class="form-group">
					<label class="col-sm-2 control-label"><?php $this->_('DEFAULT_MODULE') ?></label>
					<div class="col-sm-10"><?php print $this->form->renderControl('defaultAclId') ?></div>
				</div><?php
		
			}
		
			?><div class="buttonBar">
				<button type="submit" class="btn btn-primary" value="edit" name="action"><i class="fa fa-save"></i> <?php $this->_('CHANGE') ?></button>
				<a href="users/groupList" class="btn btn-default"><i class="fa fa-times"></i> <?php $this->_('CANCEL') ?></a><?php
	
				if ($this->group->canBeDeleted()) {
					?><a href="users/groupDelete/<?php print $this->group->id ?>" class="btn btn-default confirmDelete"><i class="fa fa-trash-o"></i> <?php $this->_('DELETE') ?></a><?php				
				}
	
			?></div>
			</fieldset>
		</form>
	</div>
</div>