<?php

/**
 * @version	$Id$
 * @author	Viames Marino
 * @package	Pair
 */

use Pair\Model;
use Pair\Translator;
use Pair\Language;

class LanguagesModel extends Model {

	/**
	 * Compares all languages files with default language files and returns percentage of completeness.
	 *
	 * @param	array:Language	List of languages.
	 */
	public function setLanguagePercentage($languages) {

		// initilize default lines
		$defLines = 0;
		
		// instance of current language translator
		$translator = Translator::getInstance();
		
		// initialize counter of fails
		foreach ($languages as $language) {
			$translated[$language->code] = 0;
		}
		
		// paths
		$defaultLang = $translator->default . '.ini';

		$folders = Language::getLanguageFolders();
		
		// scan on each language folder
		foreach ($folders as $module=>$folder) {

			// checks that folder and language file exists
			if (is_dir($folder) and file_exists($folder . '/' . $defaultLang)) {
				
				// gets all default language’s keys
				$langData = parse_ini_file($folder . '/' . $defaultLang);
				$defaultKeys = array_keys($langData);
				
				// translated lines for default
				$defLines += count($langData);
				
				// compares each other language file
				foreach ($languages as $language) {
					
					// initilize details utility property
					if (!property_exists($language, 'details')) {
						$language->details = array();
					}
					
					// compares to default language
					if ($language->code != $translator->default) {
						
						// details of comparing lines
						$details = new stdClass();
						$details->default = count($defaultKeys);
						
						$file = $folder . '/' . $language->code . '.ini';

						// sets language details
						if (file_exists($file)) {

							// scans file and gets all translation keys
							$langData = parse_ini_file($file);
							$otherKeys = array_keys($langData);
	
							// details of translated lines
							$details->count	= count($defaultKeys) - count(array_diff($defaultKeys, $otherKeys));
							$details->perc	= $details->default ? floor(($details->count / $details->default) * 100) : 0;
							$details->date	= filemtime($file);
	
							// sum to other modules for the same language
							$translated[$language->code] += $details->count;
	
						// sets empty detail properties
						} else {
							
							$details->count	= 0;
							$details->perc	= 0;
							$details->date	= NULL;
							
						}
						
						// assigns to a Language property
						$language->details[$module] = $details;
						
					}
						
				}
			
			}
			
		}
		
		// sets 100% to default language and zero to the rest
		foreach ($languages as $language) {

			if ($translator->default == $language->code) {
				$language->perc		= 100.0;
				$language->complete	= $defLines;
			} else {
				$language->perc		= floor(($translated[$language->code] / $defLines * 100));
				$language->complete	= $translated[$language->code];
			}
		}
		
	}

	/**
	 * Builds and sets a coloured progress bar as property Object->progressBar by reference.
	 *
	 * @param	Object	The target object with integer property “perc” (percentual).
	 */
	public static function setProgressBar(&$object) {
	
		$object->progressBar = '<div class="progress progress-mini">';
		
		// perc is not set
		if (!property_exists($object, 'perc')) {
			$object->progressBar .= '<div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%">
				<span>0%</span></div></div>';
			
			return;
		}
	
		$green = $red = 215;
	
		if (100 == $object->perc) {
			$red = 0;
		} else if (50 <= $object->perc) {
			$red = 255;
			$green = round(($object->perc / 50) * 110);
		} else {
			$green = 0;
		}
	
		$bgColor = 'rgb(' . $red . ',' . $green . ',0)';
	
		$object->progressBar .=
			'<div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="' .
			$object->perc .'" aria-valuemin="0" aria-valuemax="100"' . ' style="background-color:' . 
			$bgColor . ';width:' . $object->perc . '%"><span>' . $object->perc .
			'%</span></div></div>';
		
	}
	
}
