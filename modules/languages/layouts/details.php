<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Utilities;

?><div class="col-lg-12">
	<div class="ibox">
		<div class="ibox-title">
			<h5><?php $this->_('DETAILS_OF_LANGUAGE', $this->language->languageName) ?></h5>
		</div>
		<div class="ibox-content">
			<div class="table-responsive"><?php

if (count($this->language->details)) {

	?><table class="table table-hover">
			<thead>
				<tr>
					<th><?php $this->_('MODULE') ?></th>
					<th><?php $this->_('PERCENTAGE') ?></th>
					<th><?php $this->_('TRANSLATED_LINES') ?></th>
					<th><?php $this->_('EDITED') ?></th>
					<th><?php $this->_('EDIT') ?></th>
				</tr>
			</thead>
			<tbody><?php

			foreach ($this->language->details as $module=>$detail) {

				?><tr>
					<td class="lft"><?php print htmlspecialchars(ucfirst($module)) ?></td>
					<td class="text-center" style="width:30%"><?php print $detail->progressBar ?></td>
					<td class="text-center"><?php print $detail->count . '/' . $detail->default ?></td>
					<td class="text-center small"><?php print $detail->dateChanged ?></td>
					<td class="text-center"><?php print $detail->editButton ?></td>
				</tr><?php 
						
			}
					
			?></tbody>
		</table><?php
				
} else {
			
	Utilities::printNoDataMessageBox();
			
}	
	
?></div>
		</div>
	</div>
</div>