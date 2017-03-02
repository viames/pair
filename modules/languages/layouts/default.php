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
			<h5><?php $this->_('LANGUAGES') ?></h5>
		</div>
		<div class="ibox-content">
			<div class="table-responsive" id="pageLanguages"><?php

if (count($this->languages)) {

	?><table class="table table-hover">
			<thead>
				<tr>
					<th><?php $this->_('NAME') ?></th>
					<th><?php $this->_('PERCENTAGE') ?></th>
					<th><?php $this->_('TRANSLATED_LINES') ?></th>
					<th><?php $this->_('DEFAULT') ?></th>
					<th><?php $this->_('CODE') ?></th>
					<th><?php $this->_('REPRESENTATION') ?></th>
					<th><?php $this->_('DETAILS') ?></th>
				</tr>
			</thead>
			<tbody><?php

			foreach ($this->languages as $language) {

				?><tr>
					<td><?php print htmlspecialchars($language->languageName) ?></td>
					<td class="text-center" style="width:30%"><?php print $language->progressBar ?></td>
					<td class="text-center"><?php print $language->complete ?></td>
					<td class="text-center"><?php print $language->defaultIcon ?></td>
					<td class="text-center"><?php print $language->code ?></td>
					<td class="text-center"><?php print $language->representation ?></td>
					<td class="text-center"><?php

					if (!$language->default) {
						?><a href="languages/details/<?php print $language->id ?>" title="<?php $this->_('SEE_DETAILS') ?>" class="btn btn-primary btn-sm"><i class="fa fa-eye"></i></a><?php
					}			
					
				?></tr><?php 
						
			}
					
			?>
			</tbody>
				</table><?php
				
	print $this->getPaginationBar();
			
} else {
			
	Utilities::printNoDataMessageBox();
			
}	
	
			?></div>
		</div>
	</div>
</div>