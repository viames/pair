<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

?><div class="row">
	<div class="col-lg-12">
		<div class="ibox float-e-margins">
			<div class="ibox-title">
				<h5>Carica un nuovo template <small>da pacchetto ZIP</small></h5>
			</div>
			<div class="ibox-content">
				<div class="row">
					<div class="col-sm-6 b-r">
						<h3 class="m-t-none m-b">Nuovo template</h3>
						<p>Selezionare il pacchetto ZIP da installare tramite il modulo a destra.
						Questo sarà caricato e registrato nel sistema.</p>
					</div>
					<div class="col-sm-6">
						<form role="form" action="templates/add" method="post" enctype="multipart/form-data">
							<label>Seleziona il pacchetto</label> 
							<?php print $this->form->renderControl('package') ?>
							<div class="hr-line-dashed"></div>
							<div class="form-group">
								<button type="submit" class="btn btn-primary" value="add" name="action"><i class="fa fa-asterisk"></i> <?php $this->_('INSERT')?></button>
								<a href="templates/default" class="btn btn-default cancel"><i class="fa fa-times"></i> <?php $this->_('CANCEL')?></a>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>