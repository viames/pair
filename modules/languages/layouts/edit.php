<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

?><div class="col-lg-12">
	<div class="ibox">
		<div class="ibox-title">
			<h5><?php $this->_('EDIT_LANGUAGE_FILE', array($this->language->languageName, ucfirst($this->module))) ?></h5>
		</div>
		<div class="ibox-content">
			<form action="languages/change" class="fullWidth" method="post"><?php
			
			print $this->form->renderControl('l');
			print $this->form->renderControl('m');
			
			foreach ($this->defStrings as $key=>$value) {
			
				$control = $this->form->getControl($key);
				
				$class = $control->value ? '' : ' alert';
				
				?><div class="field">
					<div class="description<?php print $class ?>"><?php print htmlspecialchars($value) ?></div>
					<?php print $control->render() ?>
				</div><?php 
						
			}

				?><div class="buttonBar">
					<button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Modifica</button>
					<a class="btn btn-default" href="<?php print $this->referer ?>"><i class="fa fa-times"></i> Annulla</a>
				</div>
			</form>
		</div>
	</div>
</div>