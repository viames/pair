<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

?><div class="col-lg-12">
	<form action="options/save" method="post">
		<fieldset><?php 

				$currentGroup = NULL;
			
				foreach ($this->groupedOptions as $groupName=>$group) {

					?><div class="ibox">
					<div class="ibox-title"><h5><?php print $groupName ?></h5></div>
					<div class="ibox-content"><?php 
										
					foreach ($group as $o) {
			
						?><div class="form-group row">
							<label class="col-sm-2 control-label"><?php print $o->label ?> <small><?php print $o->name ?></small></label>
							<div class="col-sm-10"><?php print $this->form->renderControl($o->name)  ?></div>
						</div><?php
						
					}
										
					?></div>
					</div><?php

				}
	
				?><div class="buttonBar">
					<button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> <?php $this->_('SAVE') ?></button>
				</div>
		</fieldset>
	</form>
</div>