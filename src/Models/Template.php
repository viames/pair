<?php

namespace Pair\Models;

use Pair\Core\Logger;
use Pair\Exceptions\CriticalException;
use Pair\Helpers\Plugin;
use Pair\Helpers\PluginBase;
use Pair\Helpers\Utilities;

class Template extends PluginBase {

	/**
	 * ID as primary key.
	 */
	protected int $id;

	/**
	 * Unique name with no space.
	 */
	protected string $name;

	/**
	 * Release version.
	 */
	protected string $version;

	/**
	 * Publication date, properly converted when inserted into db.
	 */
	protected \DateTime $dateReleased;

	/**
	 * Version of application on which installs.
	 */
	protected string $appVersion;

	/**
	 * Flag for default template only.
	 */
	protected bool $isDefault;

	/**
	 * User ID of installer.
	 */
	protected int $installedBy;

	/**
	 * Installation date, properly converted when inserted into db.
	 */
	protected \DateTime $dateInstalled;

	/**
	 * Palette for charts as CSV of HEX colors.
	 */
	protected array $palette = [];

	/**
	 * Name of related db table.
	 */
	const TABLE_NAME = 'templates';

	/**
	 * Name of primary key db field.
	 */
	const TABLE_KEY = 'id';

	/**
	 * Properties that are stored in the shared cache.
	 */
	const SHARED_CACHE_PROPERTIES = ['installedBy'];

	/**
	 * Table structure [Field => Type, Null, Key, Default, Extra].
	 */
	const TABLE_DESCRIPTION = [
		'id'			=> ['int unsigned', 'NO', 'PRI', NULL, 'auto_increment'],
		'name'			=> ['varchar(50)', 'NO', 'UNI', NULL, ''],
		'version'		=> ['varchar(10)', 'NO', '', NULL, ''],
		'date_released'	=> ['datetime', 'NO', '', NULL, ''],
		'app_version'	=> ['varchar(10)', 'NO', '', '1.0', ''],
		'is_default'	=> ['tinyint(1)', 'NO', '', '0', ''],
		'installed_by'	=> ['int unsigned', 'NO', 'MUL', NULL, ''],
		'date_installed'=> ['datetime', 'NO', '', NULL, ''],
		'palette'		=> ['json', 'NO', '', NULL, '']
	];

	/**
	 * Default palette, composed by 24 distinct HEX colors that are visually balanced and suitable for charts.
	 */
	const DEFAULT_PALETTE = [
		'#57A0E5', // bright sky blue
		'#ED6E85', // soft coral pink
		'#F1A454', // warm apricot orange
		'#F7CF6B', // golden pastel yellow
		'#6CBEBF', // soft teal blue
		'#9269F7', // vibrant lavender purple
		'#C9D1CB', // muted sage gray
		'#3E7CB1', // deep ocean blue
		'#8CD0A4', // pale seafoam green
		'#D3A4F9', // light orchid purple
		'#FFBC8B', // light apricot orange
		'#A1C9F1', // light periwinkle blue
		'#FFE599', // pastel butter yellow
		'#B9CBC2', // cool light sage
		'#F88D9C', // bright soft rose
		'#9ED0F0', // icy blue
		'#C295D8', // dusty lilac
		'#F6A785', // peachy coral
		'#A8BFA3', // soft olive-sage green
		'#F0E1B2', // balanced soft beige-yellow
		'#B37D5E', // soft cocoa brown
		'#95B2C2', // light steel blue
		'#6F9E75', // desaturated moss green
		'#D65555'  // warm coral red
	];

	/**
	 * Hook called after prepareData() method execution.
	 *
	 * @param \stdClass $dbObj PrepareData() returned variable (passed here by reference).
	 */
	protected function afterPrepareData(\stdClass &$dbObj): void {

		if (!property_exists($dbObj, 'palette')) {
			return;
		}

		$this->palette = $this->sanitizePalette($this->palette);
		$dbObj->palette = json_encode($this->palette);

	}

	/**
	 * Method called by constructor just after having populated the object.
	 */
	protected function _init(): void {

		$this->bindAsBoolean('isDefault');

		$this->bindAsCsv('palette');

		$this->bindAsDatetime('dateReleased', 'dateInstalled');

		$this->bindAsInteger('id', 'installedBy');

		$this->palette = $this->sanitizePalette((array)$this->palette);

	}

	/**
	 * Removes files of this Module object before its deletion.
	 *
	 * @throws PairException if the plugin folder could not be deleted.
	 */
	protected function beforeDelete(): void {

		// delete plugin folder
		$plugin = $this->getPlugin();

		if (!Utilities::deleteFolder($plugin->baseFolder)) {
			throw new PairException('Could not delete template folder ' . $plugin->baseFolder);
		}

		$logger = Logger::getInstance();
		$logger->info('Plugin folder ' . $plugin->baseFolder . ' has been deleted');

	}

	/**
	 * Hook called before populate() method execution.
	 *
	 * @param \stdClass $dbRow Object with which populate(), here passed by reference.
	 */
	protected function beforePopulate(\stdClass &$dbRow): void {

		if (!property_exists($dbRow, 'palette') or !is_string($dbRow->palette) or !json_validate($dbRow->palette)) {
			return;
		}

		$palette = json_decode($dbRow->palette, true);

		if (is_array($palette)) {
			$dbRow->palette = implode(',', $palette);
		}

	}

	/**
	 * Hook called before store() method execution.
	 */
	protected function beforeStore(): void {

		$this->palette = $this->sanitizePalette($this->palette);

	}

	/**
	 * Builds a normalized sequence from a palette.
	 *
	 * @param array $palette Palette list.
	 * @param int $length Optional max number of colors.
	 * @param int $offset Optional starting index.
	 * @return array Sequence of colors from palette with applied length and offset, or the full palette if length is not positive. Colors are normalized and invalid ones are skipped.
	 */
	private static function buildPaletteSequence(array $palette, int $length = 0, int $offset = 0): array {

		if (!count($palette)) {
			$palette = static::DEFAULT_PALETTE;
		}

		if ($length <= 0) {
			return $palette;
		}

		$colors = [];

		for ($i = 0; $i < $length; $i++) {
			$colors[] = static::pickPaletteColor($palette, $offset + $i);
		}

		return $colors;

	}

	/**
	 * Build the target RGB color from name, clarity and intensity. The method first checks if the color name is a
	 * valid HEX color, in which case it converts it directly to RGB and ignores modifiers. Otherwise, it resolves the
	 * color name to a base color and modifiers, applies the modifiers to clarity and intensity, scales them to
	 * lightness and saturation ranges of the base color, and finally converts the resulting HSL color to RGB. This
	 * allows for flexible color descriptions while still producing consistent palette matches.
	 *
	 * @param string $colorName English color name.
	 * @param int $clarity Clarity from 1 (dark) to 10 (light).
	 * @param int $intensity Intensity from 1 (soft) to 10 (vivid).
	 * @return array Target RGB color as associative array with keys 'r', 'g', 'b' and values in range 0..255.
	 */
	private static function buildTargetColorRgb(string $colorName, int $clarity, int $intensity): array {

		$colorName = trim($colorName);

		// if the color name is a valid HEX color, convert it directly to RGB and ignore modifiers
		if (static::isValidPaletteColor($colorName)) {

			$rgb = static::hexColorToRgb($colorName);

			if (is_array($rgb)) {
				return $rgb;
			}

		}

		// resolve color name to base color and modifiers, for example "bright red" would resolve to base color "red"
		// with modifiers "bright"
		$descriptor = static::resolveEnglishColorDescriptor($colorName);

		// apply modifiers to clarity and intensity, for example "bright red" would have higher clarity and intensity
		// than "red", while "muted red" would have lower intensity. The resulting values are clamped to 1..10 range.
		$clarity = static::clampScaleValue($clarity + $descriptor['clarityShift']);
		$intensity = static::clampScaleValue($intensity + $descriptor['intensityShift']);

		// scale clarity and intensity to lightness and saturation ranges of the base color, for example if the base
		// color has lightness range 0.2..0.8, then clarity 1 would correspond to lightness 0.2, clarity 10 would
		// correspond to lightness 0.8 and clarity 5 would correspond to lightness 0.5
		$lightness = static::scaleToRange($clarity, $descriptor['lightnessMin'], $descriptor['lightnessMax']);
		$saturation = static::scaleToRange($intensity, $descriptor['saturationMin'], $descriptor['saturationMax']);

		return static::hslToRgb((float)$descriptor['hue'], $saturation, $lightness);

	}

	/**
	 * Clamps clarity/intensity value to 1..10.
	 *
	 * @param int $value Scale value.
	 * @return int Clamped value.
	 */
	private static function clampScaleValue(int $value): int {

		return max(1, min(10, $value));

	}

	/**
	 * Returns a deterministic palette color from a unique alphanumeric identifier.
	 *
	 * @param string $identifier Unique identifier.
	 * @return string Deterministic palette color.
	 */
	public function colorByIdentifier(string $identifier): string {

		$palette = $this->getPaletteColors();

		if (!count($palette)) {
			return static::defaultPaletteColor(0);
		}

		$identifier = trim($identifier);

		if ('' === $identifier) {
			return $palette[0];
		}

		// use a hash of the identifier to get a deterministic index in the palette
		$index = static::stringHashToIndex(md5($identifier), count($palette));

		return $palette[$index];

	}

	/**
	 * Returns a color from the default chart palette by index with cyclic fallback.
	 *
	 * @param int $index Color index.
	 * @return string Palette color.
	 */
	public static function defaultPaletteColor(int $index = 0): string {

		return static::pickPaletteColor(static::DEFAULT_PALETTE, $index);

	}

	/**
	 * Returns colors from default chart palette.
	 *
	 * @param int	$length	Optional max number of colors.
	 * @param int	$offset	Optional starting index.
	 * @return array Sequence of colors from default palette with applied length and offset, or the full default palette if length is not positive. Colors are normalized and invalid ones are skipped.
	 */
	public static function defaultPaletteColors(int $length = 0, int $offset = 0): array {

		return static::buildPaletteSequence(static::DEFAULT_PALETTE, $length, $offset);

	}

	/**
	 * Supported English color aliases.
	 *
	 * @return array Associative array with alias keys and their corresponding color keys as values. Aliases can be full color names or modifiers, and they are normalized to lowercase with spaces.
	 */
	private static function englishColorAliases(): array {

		return [
			'aqua' => 'cyan',
			'azure' => 'blue',
			'beige' => 'beige',
			'black' => 'black',
			'blue' => 'blue',
			'brown' => 'brown',
			'charcoal' => 'black',
			'coral' => 'orange',
			'crimson' => 'red',
			'cyan' => 'cyan',
			'fuchsia' => 'magenta',
			'gold' => 'gold',
			'golden' => 'gold',
			'gray' => 'gray',
			'green' => 'green',
			'grey' => 'gray',
			'indigo' => 'indigo',
			'ivory' => 'white',
			'lavender' => 'violet',
			'lime' => 'lime',
			'magenta' => 'magenta',
			'maroon' => 'maroon',
			'mint' => 'mint',
			'navy' => 'navy',
			'olive' => 'olive',
			'orange' => 'orange',
			'pink' => 'pink',
			'purple' => 'purple',
			'red' => 'red',
			'rose' => 'rose',
			'silver' => 'gray',
			'sky' => 'sky',
			'tan' => 'beige',
			'teal' => 'teal',
			'turquoise' => 'teal',
			'violet' => 'violet',
			'white' => 'white',
			'yellow' => 'yellow'
		];

	}

	/**
	 * Supported English color definitions in HSL ranges.
	 * The hue is a fixed angle in degrees, while lightness and saturation are ranges that can be
	 * adjusted by modifiers.
	 *
	 * @return array Associative array with color keys and their HSL definitions. Each definition is an associative array with keys: hue, lightnessMin, lightnessMax, saturationMin, saturationMax.
	 */
	private static function englishColorDefinitions(): array {

		return [
			'beige' => ['hue' => 42.0, 'lightnessMin' => 0.50, 'lightnessMax' => 0.92, 'saturationMin' => 0.08, 'saturationMax' => 0.48],
			'black' => ['hue' => 0.0, 'lightnessMin' => 0.02, 'lightnessMax' => 0.22, 'saturationMin' => 0.00, 'saturationMax' => 0.10],
			'blue' => ['hue' => 220.0, 'lightnessMin' => 0.10, 'lightnessMax' => 0.76, 'saturationMin' => 0.20, 'saturationMax' => 1.00],
			'brown' => ['hue' => 26.0, 'lightnessMin' => 0.12, 'lightnessMax' => 0.56, 'saturationMin' => 0.22, 'saturationMax' => 0.78],
			'cyan' => ['hue' => 190.0, 'lightnessMin' => 0.18, 'lightnessMax' => 0.82, 'saturationMin' => 0.20, 'saturationMax' => 1.00],
			'gold' => ['hue' => 48.0, 'lightnessMin' => 0.30, 'lightnessMax' => 0.86, 'saturationMin' => 0.24, 'saturationMax' => 1.00],
			'gray' => ['hue' => 0.0, 'lightnessMin' => 0.16, 'lightnessMax' => 0.86, 'saturationMin' => 0.00, 'saturationMax' => 0.12],
			'green' => ['hue' => 125.0, 'lightnessMin' => 0.12, 'lightnessMax' => 0.76, 'saturationMin' => 0.18, 'saturationMax' => 1.00],
			'indigo' => ['hue' => 255.0, 'lightnessMin' => 0.10, 'lightnessMax' => 0.72, 'saturationMin' => 0.20, 'saturationMax' => 0.94],
			'lime' => ['hue' => 95.0, 'lightnessMin' => 0.20, 'lightnessMax' => 0.84, 'saturationMin' => 0.22, 'saturationMax' => 1.00],
			'magenta' => ['hue' => 320.0, 'lightnessMin' => 0.14, 'lightnessMax' => 0.82, 'saturationMin' => 0.20, 'saturationMax' => 1.00],
			'maroon' => ['hue' => 350.0, 'lightnessMin' => 0.08, 'lightnessMax' => 0.50, 'saturationMin' => 0.22, 'saturationMax' => 0.92],
			'mint' => ['hue' => 150.0, 'lightnessMin' => 0.28, 'lightnessMax' => 0.90, 'saturationMin' => 0.10, 'saturationMax' => 0.72],
			'navy' => ['hue' => 220.0, 'lightnessMin' => 0.06, 'lightnessMax' => 0.42, 'saturationMin' => 0.26, 'saturationMax' => 0.90],
			'olive' => ['hue' => 74.0, 'lightnessMin' => 0.10, 'lightnessMax' => 0.58, 'saturationMin' => 0.16, 'saturationMax' => 0.70],
			'orange' => ['hue' => 30.0, 'lightnessMin' => 0.16, 'lightnessMax' => 0.84, 'saturationMin' => 0.20, 'saturationMax' => 1.00],
			'pink' => ['hue' => 335.0, 'lightnessMin' => 0.32, 'lightnessMax' => 0.92, 'saturationMin' => 0.14, 'saturationMax' => 0.94],
			'purple' => ['hue' => 285.0, 'lightnessMin' => 0.10, 'lightnessMax' => 0.78, 'saturationMin' => 0.20, 'saturationMax' => 0.98],
			'red' => ['hue' => 0.0, 'lightnessMin' => 0.12, 'lightnessMax' => 0.80, 'saturationMin' => 0.20, 'saturationMax' => 1.00],
			'rose' => ['hue' => 345.0, 'lightnessMin' => 0.24, 'lightnessMax' => 0.88, 'saturationMin' => 0.12, 'saturationMax' => 0.86],
			'sky' => ['hue' => 202.0, 'lightnessMin' => 0.34, 'lightnessMax' => 0.92, 'saturationMin' => 0.08, 'saturationMax' => 0.78],
			'teal' => ['hue' => 180.0, 'lightnessMin' => 0.10, 'lightnessMax' => 0.70, 'saturationMin' => 0.16, 'saturationMax' => 0.82],
			'violet' => ['hue' => 272.0, 'lightnessMin' => 0.16, 'lightnessMax' => 0.84, 'saturationMin' => 0.16, 'saturationMax' => 0.92],
			'white' => ['hue' => 0.0, 'lightnessMin' => 0.82, 'lightnessMax' => 0.98, 'saturationMin' => 0.00, 'saturationMax' => 0.08],
			'yellow' => ['hue' => 56.0, 'lightnessMin' => 0.30, 'lightnessMax' => 0.94, 'saturationMin' => 0.18, 'saturationMax' => 1.00]
		];

	}

	/**
	 * Supported English modifiers used in color names and their corresponding clarity and intensity shifts. Modifiers can be used to adjust the lightness and saturation of the base color, for example "bright red" would have higher clarity and intensity than "red", while "muted red" would have lower intensity. Modifiers are normalized to lowercase with spaces.
	 * 
	 * @return array Associative array with modifier keys and their corresponding clarity and intensity shifts as values. Modifiers can be full words and are normalized to lowercase with spaces.
	 */
	private static function englishColorModifiers(): array {

		return [
			'bright' => ['clarity' => 1, 'intensity' => 2],
			'dark' => ['clarity' => -2, 'intensity' => 1],
			'deep' => ['clarity' => -2, 'intensity' => 2],
			'dull' => ['clarity' => 0, 'intensity' => -3],
			'light' => ['clarity' => 2, 'intensity' => -1],
			'muted' => ['clarity' => 0, 'intensity' => -3],
			'pale' => ['clarity' => 2, 'intensity' => -2],
			'pastel' => ['clarity' => 2, 'intensity' => -3],
			'rich' => ['clarity' => 0, 'intensity' => 2],
			'soft' => ['clarity' => 1, 'intensity' => -2],
			'vivid' => ['clarity' => 1, 'intensity' => 3]
		];

	}

	/**
	 * Returns absolute path to plugin folder for this Template object.
	 *
	 * @return string Absolute path to plugin folder.
	 */
	public function getBaseFolder(): string {

		return APPLICATION_PATH . '/templates';

	}

	/**
	 * Returns array with matching object property name on related db fields.
	 *
	 * @return array Array with matching object property name on related db fields, for example ['id'=>'id', 'name'=>'name', ...].
	 */
	protected static function getBinds(): array {

		return [
			'id'			=> 'id',
			'name'			=> 'name',
			'version'		=> 'version',
			'dateReleased'	=> 'date_released',
			'appVersion'	=> 'app_version',
			'isDefault'		=> 'is_default',
			'installedBy'	=> 'installed_by',
			'dateInstalled'	=> 'date_installed',
			'palette'		=> 'palette'
		];

	}

	/**
	 * Returns the palette color that best matches an English color description, optionally adjusted by clarity
	 * and intensity modifiers. The color description can be a simple color name (e.g. "blue"), a color name with
	 * modifiers (e.g. "bright red", "muted green") or a HEX color string (e.g. "#FF5733"). Modifiers adjust the
	 * lightness and saturation of the base color, while the base color is determined by the closest match to the
	 * English color name or directly from the HEX string. The method then finds the palette color that has the
	 * smallest weighted RGB distance to the target color, which better reflects human perception than simple
	 * Euclidean distance.
	 *
	 * @param string $colorName English color name, for example “blue” or “white”.
	 * @param int $clarity Clarity from 1 (dark) to 10 (light).
	 * @param int $intensity Intensity from 1 (soft) to 10 (vivid).
	 * @return string Closest matching palette color.
	 */
	public function getClosestPaletteColor(string $colorName, int $clarity = 5, int $intensity = 5): string {

		$palette = $this->getPaletteColors();
		$targetRgb = static::buildTargetColorRgb($colorName, $clarity, $intensity);

		// initialize with the first palette color as fallback in case all colors are invalid
		$closestColor = $palette[0];
		$closestDistance = INF;

		// compare target color with each palette color and keep the closest one
		foreach ($palette as $paletteColor) {

			$paletteRgb = static::hexColorToRgb($paletteColor);

			if (!is_array($paletteRgb)) {
				continue;
			}

			// use weighted RGB distance to find the closest color, as it better reflects human perception than simple Euclidean distance
			$distance = static::rgbDistance($targetRgb, $paletteRgb);

			// if this palette color is closer to the target than the previously closest one, update the closest color and distance
			if ($distance < $closestDistance) {
				$closestDistance = $distance;
				$closestColor = $paletteColor;
			}

		}

		return $closestColor;

	}

	/**
	 * Returns the default Template object.
	 *
	 * @return Template|null Default Template object or null if not found.
	 */
	public static function getDefault(): ?self {

		return self::getObjectByQuery('SELECT * FROM `templates` WHERE `is_default`=1');

	}

	/**
	 * Returns one palette color by index with cyclic fallback.
	 *
	 * @param int $index Color index.
	 * @return string Palette color.
	 */
	public function getPaletteColor(int $index = 0): string {

		return static::pickPaletteColor($this->getPaletteColors(), $index);

	}

	/**
	 * Returns palette colors.
	 *
	 * @param int $length Optional max number of colors.
	 * @param int $offset Optional starting index.
	 * @return array Sequence of colors from palette with applied length and offset, or the full palette if length is not positive. Colors are normalized and invalid ones are skipped.
	 */
	public function getPaletteColors(int $length = 0, int $offset = 0): array {

		$this->palette = $this->sanitizePalette($this->palette);

		return static::buildPaletteSequence($this->palette, $length, $offset);

	}

	/**
	 * Returns the path to the template folder.
	 *
	 * @return string Path to the template folder.
	 */
	public function getPath(): string {

		return APPLICATION_PATH . '/templates/' . strtolower($this->name) . '/';

	}

	/**
	 * Load and return a Template object by its name.
	 *
	 * @param string $name Template name.
	 * @return Template|null Template object or null if not found.
	 */
	public static function getPluginByName(string $name): ?self {

		return self::getObjectByQuery('SELECT * FROM `templates` WHERE `name`=?', [$name]);

	}

	/**
	 * Creates and returns the Plugin object of this Template object.
	 *
	 * @return Plugin Plugin object of this Template.
	 * @throws PairException if the plugin folder does not exist or is not readable.
	 */
	public function getPlugin(): Plugin {

		$folder = $this->getBaseFolder() . '/' . strtolower(str_replace([' ', '_'], '', $this->name));
		$dateReleased = $this->dateReleased->format('Y-m-d');

		// special parameters for Template plugin
		$options = [
			'palette' => implode(',', $this->getPaletteColors())
		];

		return new Plugin('Template', $this->name, $this->version, $dateReleased, $this->appVersion, $folder, $options);

	}

	/**
	 * Get the style page file absolute path.
	 *
	 * @param string $styleName Style name.
	 * @return string Absolute path to the style file.
	 */
	public function getStyleFile(string $styleName): string {

		// by default load template style
		$styleFile = $this->getBaseFolder() . '/' . strtolower($this->name) . '/' . $styleName . '.php';

		if (!file_exists($styleFile)) {
			throw new CriticalException('Template style ' . $styleName . ' not found');
		}

		return $styleFile;

	}

	/**
	 * Converts HEX color string to RGB values and ignores alpha channel.
	 *
	 * @param string $hexColor HEX color string.
	 * @return array|null RGB color as associative array with keys 'r', 'g', 'b' and values in range 0..255, or null if invalid.
	 */
	private static function hexColorToRgb(string $hexColor): ?array {

		$hex = ltrim(trim($hexColor), '#');

		if (4 == strlen($hex) or 3 == strlen($hex)) {

			$hex = $hex[0] . $hex[0]
				. $hex[1] . $hex[1]
				. $hex[2] . $hex[2];

		} else if (8 == strlen($hex) or 6 == strlen($hex)) {

			$hex = substr($hex, 0, 6);

		} else {

			return null;

		}

		if (!ctype_xdigit($hex)) {
			return null;
		}

		return [
			'r' => hexdec(substr($hex, 0, 2)),
			'g' => hexdec(substr($hex, 2, 2)),
			'b' => hexdec(substr($hex, 4, 2))
		];

	}

	/**
	 * Convert HSL to RGB color and returns it as associative array with keys 'r', 'g', 'b' and values in range 0..255..
	 *
	 * @param float $hue Hue angle in degrees.
	 * @param float $saturation Saturation in range 0..1.
	 * @param float $lightness Lightness in range 0..1.
	 * @return array RGB color as associative array with keys 'r', 'g', 'b' and values in range 0..255.
	 */
	private static function hslToRgb(float $hue, float $saturation, float $lightness): array {

		$hue = fmod($hue, 360.0);

		if ($hue < 0) {
			$hue += 360.0;
		}

		$saturation = static::limitToUnit($saturation);
		$lightness = static::limitToUnit($lightness);

		if (0.0 == $saturation) {
			$channel = (int)round($lightness * 255);
			return ['r' => $channel, 'g' => $channel, 'b' => $channel];
		}

		$q = $lightness < 0.5
			? $lightness * (1 + $saturation)
			: $lightness + $saturation - ($lightness * $saturation);

		$p = 2 * $lightness - $q;
		$h = $hue / 360.0;

		$r = static::hueToRgb($p, $q, $h + (1 / 3));
		$g = static::hueToRgb($p, $q, $h);
		$b = static::hueToRgb($p, $q, $h - (1 / 3));

		return [
			'r' => (int)round($r * 255),
			'g' => (int)round($g * 255),
			'b' => (int)round($b * 255)
		];

	}

	/**
	 * Returns channel value used by HSL to RGB conversion.
	 *
	 * @param float $p First temporary value.
	 * @param float $q Second temporary value.
	 * @param float $t Temporary hue value.
	 * @return float Channel value in range 0..1.
	 */
	private static function hueToRgb(float $p, float $q, float $t): float {

		if ($t < 0) {
			$t += 1;
		}

		if ($t > 1) {
			$t -= 1;
		}

		if ($t < (1 / 6)) {
			return $p + ($q - $p) * 6 * $t;
		}

		if ($t < (1 / 2)) {
			return $q;
		}

		if ($t < (2 / 3)) {
			return $p + ($q - $p) * ((2 / 3) - $t) * 6;
		}

		return $p;

	}

	/**
	 * Checks if color string is valid. Supported formats: #RGB, #RGBA, #RRGGBB, #RRGGBBAA.
	 *
	 * @param string $color Color string.
	 * @return bool True if valid, false otherwise.
	 */
	public static function isValidPaletteColor(string $color): bool {

		return (bool)preg_match('/^#(?:[a-fA-F0-9]{3}|[a-fA-F0-9]{4}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})$/', trim($color));

	}

	/**
	 * Limits a float value to 0..1 range.
	 *
	 * @param float $value Input value.
	 * @return float Limited value.
	 */
	private static function limitToUnit(float $value): float {

		return max(0.0, min(1.0, $value));

	}

	/**
	 * Normalize color and returns null if invalid.
	 *
	 * @param string $color Color string.
	 * @return string|null Normalized color string or null if invalid.
	 */
	private static function normalizePaletteColor(string $color): ?string {

		$color = strtoupper(trim($color));

		if ('' === $color) {
			return null;
		}

		if (!str_starts_with($color, '#')) {
			$color = '#' . $color;
		}

		return static::isValidPaletteColor($color) ? $color : null;

	}

	/**
	 * Returns a palette color by index with cyclic fallback.
	 *
	 * @param array $palette Palette list.
	 * @param int $index Color index.
	 * @return string Selected color.
	 */
	private static function pickPaletteColor(array $palette, int $index): string {

		if (!count($palette)) {
			$palette = static::DEFAULT_PALETTE;
		}

		$paletteCount = count($palette);
		$normalizedIndex = $index % $paletteCount;

		if ($normalizedIndex < 0) {
			$normalizedIndex += $paletteCount;
		}

		return $palette[$normalizedIndex];

	}

	/**
	 * Checks if Template is already installed in this application.
	 *
	 * @param string $name Name of Template to search.
	 * @return bool True if Template exists, false otherwise.
	 */
	public static function pluginExists(string $name): bool {

		return (bool)self::countAllObjects(['name'=>$name]);

	}

	/**
	 * Resolves color descriptor from English color name and modifiers.
	 *
	 * @param string $colorName English color name.
	 * @return array Color descriptor with clarity and intensity shifts.
	 */
	private static function resolveEnglishColorDescriptor(string $colorName): array {

		$definitions = static::englishColorDefinitions();
		$aliases = static::englishColorAliases();
		$modifiers = static::englishColorModifiers();

		$normalized = strtolower(trim(str_replace(['-', '_'], ' ', $colorName)));
		$normalized = preg_replace('/\s+/', ' ', $normalized);
		$normalized = is_string($normalized) ? trim($normalized) : '';

		if ('' === $normalized) {
			$normalized = 'blue';
		}

		$tokens = array_values(array_filter(explode(' ', $normalized)));

		$colorKey = $aliases[$normalized] ?? null;

		if (!is_string($colorKey)) {

			foreach (array_reverse($tokens) as $token) {

				if (array_key_exists($token, $aliases)) {
					$colorKey = $aliases[$token];
					break;
				}

			}

		}

		if (!is_string($colorKey) or !array_key_exists($colorKey, $definitions)) {
			$colorKey = 'blue';
		}

		$clarityShift = 0;
		$intensityShift = 0;

		foreach ($tokens as $token) {

			if (!array_key_exists($token, $modifiers)) {
				continue;
			}

			$clarityShift += $modifiers[$token]['clarity'];
			$intensityShift += $modifiers[$token]['intensity'];

		}

		$descriptor = $definitions[$colorKey];
		$descriptor['clarityShift'] = $clarityShift;
		$descriptor['intensityShift'] = $intensityShift;

		return $descriptor;

	}

	/**
	 * Returns weighted RGB distance between two colors.
	 *
	 * @param array $from First RGB color.
	 * @param array $to Second RGB color.
	 * @return float Weighted distance between colors.
	 */
	private static function rgbDistance(array $from, array $to): float {

		$deltaRed = (float)$from['r'] - (float)$to['r'];
		$deltaGreen = (float)$from['g'] - (float)$to['g'];
		$deltaBlue = (float)$from['b'] - (float)$to['b'];

		return sqrt(
			0.299 * $deltaRed * $deltaRed
			+ 0.587 * $deltaGreen * $deltaGreen
			+ 0.114 * $deltaBlue * $deltaBlue
		);

	}

	/**
	 * Keep only valid colors and guarantee a non-empty palette.
	 *
	 * @param array $palette Palette list to sanitize.
	 * @return array Sanitized palette list.
	 */
	private function sanitizePalette(array $palette): array {

		$sanitized = [];

		foreach ($palette as $color) {

			$normalizedColor = static::normalizePaletteColor((string)$color);

			if (!is_null($normalizedColor)) {
				$sanitized[] = $normalizedColor;
			}

		}

		$sanitized = array_values(array_unique($sanitized));

		if (!count($sanitized)) {
			return static::DEFAULT_PALETTE;
		}

		return $sanitized;

	}

	/**
	 * Converts a 1..10 value to a float range.
	 *
	 * @param int $value Input scale value.
	 * @param float $min Minimum output value.
	 * @param float $max Maximum output value.
	 * @return float Scaled output value.
	 */
	private static function scaleToRange(int $value, float $min, float $max): float {

		$value = static::clampScaleValue($value);
		$ratio = ($value - 1) / 9;

		return $min + (($max - $min) * $ratio);

	}

	/**
	 * Get option parameters and store this object loaded by a Plugin.
	 *
	 * @param \SimpleXMLElement $options Options from plugin XML file.
	 * @return bool True on success, false on failure.
	 */
	public function storeByPlugin(\SimpleXMLElement $options): bool {

		// get options
		$children = $options->children();

		$palette = [];

		if (isset($children->palette)) {

			// support both old XML array format and CSV string format.
			$paletteChildren = $children->palette->children();

			if (count($paletteChildren)) {
				foreach ($paletteChildren as $color) {
					$palette[] = (string)$color;
				}
			} else {
				$paletteText = trim((string)$children->palette);
				$palette = '' === $paletteText ? [] : explode(',', $paletteText);
			}

		}

		$this->palette = $this->sanitizePalette($palette);

		return $this->store();

	}

	/**
	 * Converts a hex hash string to an index in a fixed-size list. The method processes each character in the
	 * hash string, converts it to a numeric value, and combines it to produce a final index that is uniformly
	 * distributed across the list size. This allows for consistent mapping of identifiers to palette colors while
	 * minimizing collisions.
	 *
	 * @param string $hashHex Hex hash string.
	 * @param int $size List size.
	 * @return int Calculated index.
	 */
	private static function stringHashToIndex(string $hashHex, int $size): int {

		if ($size < 1) {
			return 0;
		}

		$hashHex = strtolower(trim($hashHex));
		$index = 0;

		foreach (str_split($hashHex) as $char) {

			// skip non-hexadecimal characters, although in a valid MD5 hash there should not be any
			if (!preg_match('/^[0-9a-f]$/', $char)) {
				continue;
			}

			// combine the character's numeric value with the current index using a prime multiplier and modulo
			// to ensure a uniform distribution
			$index = (($index * 16) + hexdec($char)) % $size;

		}

		return $index;

	}

}
