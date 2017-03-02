<?php

/**
 * @version	$Id$
 * @author	judmir karriqi
 * @package	Pair
 */

?><div class="moduleTitle"><?php $this->_('EDIT_RULE') ?></div>
<div class="col-lg-8 col-lg-offset-2">
	<form action="rules/change" method="post">
		<?php print $this->form->renderControl('id') ?>

		<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12 form-group">
			<div class="col-lg-12 col-md-12">
				<label><?php $this->_('MODULE')?></label>
			</div>
			<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
				<?php print $this->form->renderControl('module') ?>
			</div>
		</div>

		<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12 form-group">
			<div class="col-lg-12 col-md-12">
				<label><?php $this->_('ACTION')?></label>
			</div>
			<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
				<?php print $this->form->renderControl('actionAcl') ?>
			</div>
		</div>

		<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12 form-group">
			<div class="col-lg-12 col-md-12">
				<label><?php $this->_('ADMIN_ONLY')?></label>
			</div>
			<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
				<?php print $this->form->renderControl('adminOnly') ?>
			</div>
		</div>
		<div class="buttonBar">
			<button type="submit" class="buttons" value="edit" name="action"><i class="fa fa-save"></i> <?php $this->_('CHANGE') ?></button>
			<a href="rules/default" class="buttons grey"><i class="fa fa-times"></i> <?php $this->_('CANCEL') ?></a>
			<button type="submit" class="buttons naked alert confirmDelete" value="delete" name="action"><i class="fa fa-trash-o"></i> <?php $this->_('DELETE') ?></button>
		</div>
	</form>
</div>
