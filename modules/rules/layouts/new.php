<?php
	
/**
 * @version	$Id$
 * @author	judmir karriqi
 * @package	Pair
 */
	
?><div class="moduleTitle"><?php $this->_('NEW_RULE') ?></div>
<div class="col-lg-8 col-lg-offset-2">
	<form action="rules/add" method="post">
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
			<button type="submit" class="buttons" value="add" name="action_add"><i class="fa fa-plus-circle"></i> <?php $this->_('INSERT') ?></button>
			<a href="rules/default" class="buttons grey"><i class="fa fa-times"></i> <?php $this->_('CANCEL') ?></a>
		</div>
	</form>
</div>