<?php

/**
 * @version	$Id$
 * @author	Judmir Karriqi
 * @package	Pair
 */

use Pair\Utilities;

 ?><div>
	<div class="moduleHead">
		<div class="titleSide">
			<div class="moduleTitle"><?php $this->_('RULES') ?></div>
				</div>
				<div class="buttonSide">
					<a href="rules/new"><button type="button" class="buttons"><i class="fa fa-plus-circle"></i> <?php $this->_('NEW') ?></button></a>
				</div>
			</div><?php
		
	if (count($this->rules)) {
		
		?><table class="content">
			<thead>
				<tr>
					<th><?php $this->_('MODULE') ?></th>
					<th><?php $this->_('ACTION') ?></th>
					<th><?php $this->_('ADMIN_ONLY') ?></th>
					<th><?php $this->_('EDIT') ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody><?php
			
			foreach ($this->rules as $o) {
		
				?><tr>
					<td><?php print htmlspecialchars($o->name) ?></td>
					<td class="text-center"><?php print htmlspecialchars($o->action) ?></td>
					<td><?php print $o->adminIcon ?></td>
					<td><a href="rules/edit/<?php print $o->id ?>"><i class="fa fa-pencil"></i></a></td>
				</tr><?php 
				
			}
			
			?></tbody>
		</table><?php
		
		print $this->getPaginationBar();
		
} else {

	Utilities::printNoDataMessageBox();

}
		
?></div>