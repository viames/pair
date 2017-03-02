<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

?><div>
<div id="app-logo"></div>
	<?php /*
	<div><h1 class="logo-name"><?php print PRODUCT_NAME ?></h1></div>
	<h3><?php $this->_('LOGIN') ?></h3>
	*/?>
	<form class="m-t" role="form" action="user/login" method="post">
		<div class="form-group">
		<?php print $this->form->renderControl('username') ?>
	</div>
		<div class="form-group">
		<?php print $this->form->renderControl('password') ?>
	</div><?php
	
		print $this->form->renderControl('referer');
		print $this->form->renderControl('timezone');
	
		?><button type="submit" class="btn btn-primary block full-width m-b">Login</button>
</form>
</div>