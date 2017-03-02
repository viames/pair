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
           	<h5><?php $this->_('ACCESS_LIST_OF_GROUP', $this->group->name) ?></h5><?php
           	
			// show button if acl is not full
			if ($this->missingAcl) {
				?><div class="ibox-tools">
					<a href="users/aclNew/<?php print $this->group->id ?>"><button type="button" class="btn btn-primary btn-xs"><i class="fa fa-plus-circle"></i> <?php $this->_('ADD') ?></button></a>
           		</div><?php
			}
			
		?></div>
		<div class="ibox-content">
			<div class="table-responsive"><?php

	if (count($this->acl)) {

		?><table class="table table-hover">
		<thead>
		<tr>
			<th><?php $this->_('MODULE_NAME') ?></th>
			<th><?php $this->_('MODULE_ACTION') ?></th>
			<th><?php $this->_('DELETE') ?></th>
		</tr>
		</thead>
		<tbody><?php
		
		foreach ($this->acl as $item) {
			?><tr>
				<td class="lft"><?php print ucfirst($item->moduleName) ?></td>
				<td class="text-center"><?php print $item->action ? ucfirst($item->action) : 'full access' ?></td>
				<td class="text-center"><?php
				
				// avoid deletion of default ACL
				if (!$item->default) {
					?><a class="btn btn-default btn-xs" href="users/aclDelete/<?php print $item->id ?>"><i class="fa fa-times"></i></a><?php
				} else {
					?><i class="fa fa-times disabled"></i><?php ;
				}
				?></td>
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