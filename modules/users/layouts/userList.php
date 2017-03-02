<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Utilities;

if (count($this->users)) {

	?><div class="col-lg-12">
		<div class="ibox">
	    	<div class="ibox-title">
            	<h5>Lista degli utenti del sistema</h5>
				<div class="ibox-tools">
					<a class="btn btn-primary btn-xs" href="users/userNew"><i class="fa fa-plus-circle"></i> Nuovo utente</a>
				</div>
			</div>
			<div class="ibox-content">
				<div class="table-responsive">
					<table class="table table-hover">
						<thead>
							<tr>
								<th><?php $this->_('NAME') ?></th>
								<th><?php $this->_('USERNAME') ?></th>
								<th><?php $this->_('EMAIL') ?></th>
								<th><?php $this->_('GROUP') ?></th>
								<th><?php $this->_('ENABLED') ?></th>
								<th><?php $this->_('LAST_LOGIN') ?></th>
								<th><?php $this->_('EDIT') ?></th>
							</tr>
						</thead>
						<tbody><?php
		
						foreach ($this->users as $user) {
	
							?><tr>
								<td><?php print htmlspecialchars($user->fullName) ?></td>
								<td><?php print $user->username ?></td>
								<td class="cnt"><?php print $user->email ?></td>
								<td class="small cnt"><?php print $user->groupName ?></td>
								<td class="text-center"><?php print $user->enabledIcon ?></td>
								<td class="small text-center"><?php print Utilities::getTimeago($user->lastLogin) ?></td>
								<td class="text-center"><a class="btn btn-default btn-xs" href="users/userEdit/<?php print $user->id ?>"><i class="fa fa-pencil"></i> <?php $this->_('EDIT') ?></a></td>
							</tr><?php
			
						}
		
					?></tbody>
					</table>
				</div>
			</div>
		</div>
	</div><?php

	print $this->getPaginationBar();

} else {

	Utilities::printNoDataMessageBox();

}