<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

?><div class="col-lg-12">
	<div class="ibox">
		<div class="ibox-title">
			<h5><?php $this->_('SELF_TEST') ?></h5>
			<div class="ibox-tools">
				<a class="btn btn-primary btn-xs" href="selftest/default"><i class="fa fa-repeat"></i> <?php $this->_('REFRESH') ?></a>
			</div>
		</div>
		<div class="ibox-content">
			<div class="table-responsive">
				<table class="table table-hover">
					<tbody><?php
	
						foreach ($this->sections as $name=>$tests) {

							?><tr><th colspan="2"><?php print $name ?></th></tr><?php

							foreach ($tests as $item) {
						
								?><tr>
									<td><?php print $item->label ?></td>
									<td><?php print $item->result ? $this->iconMark : $this->iconCross ?></td>
								</tr><?php
								
							}
							
						}
	
					?></tbody>
				</table>
			</div>
		</div>
	</div>
</div>
