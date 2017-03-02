<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

?><div class="col-lg-12">
	<div class="ibox">
		<div class="ibox-title">
			<h5><?php $this->_('DEVELOPER') ?></h5>
		</div>
		<div class="ibox-content">
			<div class="table-responsive">
				<table class="table table-hover">
					<thead>
						<tr>
							<th><?php $this->_('TABLE_NAME') ?></th>
							<th>&nbsp;</th>
							<th>&nbsp;</th>
						</tr>
					</thead>
					<tbody><?php 
					
						foreach ($this->unmanagedTables as $t) {
					
							?><tr>
								<td><?php print $t ?></td>
								<td><a href="developer/classWizard/<?php print $t ?>" class="btn btn-default btn-xs"><i class="fa fa-magic"></i> <?php $this->_('CREATE_CLASS') ?></a></td>
								<td><a href="developer/moduleWizard/<?php print $t ?>" class="btn btn-default btn-xs"><i class="fa fa-magic"></i> <?php $this->_('CREATE_MODULE') ?></a></td>
							</tr><?php 
							
						}
						
					?></tbody>
				</table>
			</div>
		</div>
	</div>
</div>

