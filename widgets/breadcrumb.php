<?php 

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Application;
use Pair\Breadcrumb;

$app = Application::getInstance();
$breadcrumb = Breadcrumb::getInstance();

?><div class="row wrapper border-bottom white-bg page-heading">
	<div class="col-lg-10">
		<h2><?php print $app->pageTitle ?></h2><?php

			if (is_a($breadcrumb, 'Pair\Breadcrumb') and count($breadcrumb->getPaths())) {
	            
				?><ol class="breadcrumb"><?php

				foreach ($breadcrumb->getPaths() as $item) {

				?><li<?php print ((property_exists($item,'active') and $item->active) ? ' class="active"' : '') ?>><?php
				
					if ($item->url) {
						?><a href="<?php print $item->url ?>"><?php print $item->title ?></a><?php
					} else {
						print $item->title;
					}
					
				?></li><?php

				}

				?></ol><?php

			}
                    
			?></div>
	<div class="col-lg-2"></div>
</div>
