<?php

declare(strict_types=1);

use Pair\Core\Controller;
use Pair\Helpers\Plugin;
use Pair\Helpers\Upload;
use Pair\Models\Template;

class CatalogController extends Controller {

	/**
	 * Exercise old installable plugin calls for the upgrader.
	 */
	public function installAction(): void {

		Plugin::removeOldFiles();

		$plugin = new Plugin();
		$plugin->installPackage(TEMP_PATH . 'catalog.zip');

		$upload = new Upload('package');
		$upload->save(TEMP_PATH);

		$manifest = Plugin::getManifestByFile(APPLICATION_PATH . '/modules/sample/manifest.xml');
		Plugin::createPluginByManifest($manifest);

		$template = Template::getPluginByName((string)$manifest->plugin->name);
		$templatePlugin = $template?->getPlugin();
		$templatePlugin?->createManifestFile();
		$templatePlugin?->downloadPackage();

	}

}
