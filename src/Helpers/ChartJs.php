<?php

namespace Pair\Helpers;

use Pair\Core\Application;
use Pair\Exceptions\AppException;
use Pair\Models\Session;
use Pair\Models\User;

/**
 * Class to setup and render a javascript chart using the Chart.js library (https://chartjs.org).
 * The Chart.js library is not included in the Pair framework, so you need to include it in your project.
 * In Pair\Core\View subclasses, you can use the following code to include the Chart.js library:
 * $this->loadScript('https://cdn.jsdelivr.net/npm/chart.js');
 */
class ChartJs {

	/**
	 * The HTML element ID where the chart will be rendered.
	 */
	private string $elementId;

	/**
	 * The type of the chart (line, bar, radar, doughnut, pie, polarArea, bubble, scatter).
	 */
	private string $type;

	/**
	 * The labels common to all datasets.
	 */
	private array $labels = [];

	/**
	 * List of ChartJsDataset objects.
	 */
	private array $datasets = [];

	/**
	 * The options to configure the chart.
	 */
	private array $options = [];

	/**
	 * Extends the base list of default colors.
	 */
	const COLORS = [
		'#57A0E5', // bright sky blue
		'#ED6E85', // soft coral pink
		'#F1A454', // warm apricot orange
		'#F7CF6B', // golden pastel yellow
		'#6CBEBF', // soft teal blue
		'#9269F7', // vibrant lavender purple
		'#C9D1CB', // muted sage gray
		'#3E7CB1', // deep ocean blue
		'#D47A5E', // warm terracotta orange
		'#7ACC91', // fresh mint green
		'#F2A2E8', // soft lavender pink
		'#F7D6A3', // soft golden yellow
		'#A3A380', // muted olive green
		'#F2C3A9', // soft peach pink
		'#B5EAD7', // soft mint green
		'#F2E8C4'  // soft beige yellow
	];

	/**
	 * Constructor. Set the HTML element ID and the type of the chart.
	 *
	 * @param	string	The HTML element ID where the chart will be rendered.
	 * @param	string	The type of the chart (line, bar, radar, doughnut, pie, polarArea, bubble, scatter).
	 */
	public function __construct(string $elementId, string $type) {

		$validTypes = ['line', 'bar', 'radar', 'doughnut', 'pie', 'polarArea', 'bubble', 'scatter'];

		if (!in_array($type, $validTypes)) {
			throw new AppException('Invalid chart type: ' . $type);
		}

		$this->type = $type;

		if (!preg_match('/^[a-zA-Z][a-zA-Z0-9-_]*$/', $elementId)) {
			throw new AppException('Invalid element ID: ' . $elementId);
		}

		$this->elementId = $elementId;

	}

	/**
	 * Return the script to render the chart.
	 */
	public function __toString(): string {

		return $this->render();

	}

	/**
	 * Set the animations options.
	 *
	 * @param   int     The number of milliseconds that the animation will take to complete.
	 * @param   string  The easing function to use for the animation. Possible values for easing are:
	 * 					linear, easeInQuad, easeOutQuad, easeInOutQuad, easeInCubic, easeOutCubic, easeInOutCubic,
	 * 					easeInQuart, easeOutQuart, easeInOutQuart, easeInQuint, easeOutQuint, easeInOutQuint,
	 * 					easeInSine, easeOutSine, easeInOutSine, easeInExpo, easeOutExpo, easeInOutExpo, easeInCirc,
	 * 					easeOutCirc, easeInOutCirc, easeInElastic, easeOutElastic, easeInOutElastic, easeInBack,
	 * 					easeOutBack, easeInOutBack, easeInBounce, easeOutBounce, easeInOutBounce.
	 * @param	string	onProgress optional JS callback is called when animations start.
	 * @param	string	onComplete optional JS callback is called when animations complete.
	 */
	public function animation(int $duration, string $easing = 'linear', ?string $onProgress = NULL, ?string $onComplete = NULL): self {

		$this->options['animation'] = [
			'tension' => [
				'duration' => $duration,
				'easing' => $easing
			]
		];

		if ($onProgress) {
			$this->options['animation']['onProgress'] = $onProgress;
		}

		if ($onComplete) {
			$this->options['animation']['onComplete'] = $onComplete;
		}

		return $this;

	}

	/**
	 * Set the aspect ratio of the chart. The aspect ratio is used to calculate the size of the chart.
	 *
	 * @param	float	The aspect ratio of the chart, e.g. 3/2 for a 3:2 aspect ratio.
	 * @throws	AppException	If the aspect ratio is not valid (less than or equal to 0).
	 */
	public function aspectRatio(float $ratio): self {

		if ($ratio <= 0) {
			throw new AppException('Invalid aspect ratio: ' . $ratio);
		}

		$this->options['aspectRatio'] = $ratio;

		return $this;

	}

	/**
	 * Proxy method to setup the ChartDataLabels plugin
	 *
	 * @param	array	The options of the ChartDataLabels plugin.
	 */
	public function datalabels(array $pluginOptions): self {

		return $this->plugin('datalabels', $pluginOptions);

	}

	/**
	 * Add a new ChartJsDataset to the chart.
	 */
	public function dataset(ChartJsDataset $dataset): self {

		$this->datasets[] = $dataset;

		return $this;

	}

	/**
	 * The decimation plugin can be used with line charts to automatically decimate data at the start of the chart
	 * lifecycle. This can be useful for performance reasons when you have a large number of data points.
	 * To use the decimation plugin, the following requirements must be met:
	 *  1. The dataset must have an indexAxis of 'x'
     *  2. The dataset must be a line
     *  3. The X axis for the dataset must be either a 'linear' or 'time' type axis
     *  4. Data must not need parsing, i.e. parsing must be false
     *  5. The dataset object must be mutable. The plugin stores the original data as dataset._data and then defines a new data property on the dataset.
     *  6. There must be more points on the chart than the threshold value. Take a look at the Configuration Options for more information.
	 *
	 * @param	string	The decimation algorithm to use. Possible values are: min-max, lttb.
	 *					Min/max preserve peaks in your data but could require up to 4 points for each pixel. Work well for a very noisy signal where you need to see data peaks.
	 *					LTTB reduces the number of data points significantly. This is most useful for showing trends in data using only a few data points.
	 * @param	int		The number of samples in the output dataset.
	 * @param	int		If the number of samples in the current axis range is above this value, the decimation will be triggered. The number of point after decimation can be higher
	 * 					than the threshold value.
	 */
	public function decimation(string $algorithm, int $samples, int $threshold): self {

		if (!in_array($algorithm, ['min-max', 'lttb'])) {
			throw new AppException('Invalid decimation algorithm: ' . $algorithm);
		}

		if ($samples < 1) {
			throw new AppException('Invalid decimation samples: ' . $samples);
		}

		if ($threshold < 1) {
			throw new AppException('Invalid decimation threshold: ' . $threshold);
		}

		if ($this->type != 'line') {
			throw new AppException('Decimation can be used only with line charts');
		}

		$this->options['decimation'] = [
			'enabled' => TRUE,
			'algorithm' => $algorithm,
			'samples' => $samples,
			'threshold' => $threshold
		];

		return $this;

	}

	/**
	 * Set the elements arc options.
	 *
	 * @param   string  The arc background color.
	 * @param   string  The arc border color.
	 * @param   int     The width of the arc border in pixels.
	 */
	public function elementsArc(string $backgroundColor, string $borderColor, int $borderWidth): self {

		$this->options['elements']['arc'] = [
			'backgroundColor' => $backgroundColor,
			'borderColor' => $borderColor,
			'borderWidth' => $borderWidth
		];

		return $this;

	}

	/**
	 * Set the elements point radius options.
	 *
	 * @param   int     The radius of the point shape. If set to 0, the point is not rendered.
	 * @param   string  The point background color.
	 * @param   string  The point border color.
	 * @param   int     The width of the point border in pixels.
	 */
	public function elementsPoint(int $radius, string $backgroundColor, string $borderColor, int $borderWidth): self {

		$this->options['elements']['point'] = [
			'radius' => $radius,
			'backgroundColor' => $backgroundColor,
			'borderColor' => $borderColor,
			'borderWidth' => $borderWidth
		];

		return $this;

	}

	/**
	 * Set the elements rectangle options.
	 *
	 * @param   string  The rectangle background color.
	 * @param   string  The rectangle border color.
	 * @param   int     The width of the rectangle border in pixels.
	 */
	public function elementsRectangle(string $backgroundColor, string $borderColor, int $borderWidth): self {

		$this->options['elements']['rectangle'] = [
			'backgroundColor' => $backgroundColor,
			'borderColor' => $borderColor,
			'borderWidth' => $borderWidth
		];

		return $this;

	}

	/**
	 * The chart legend is default enabled. You can hide the legend by setting the display key to false.
	 */
	public function hideLegend(): self {

		$this->options['plugins']['legend']['display'] = FALSE;
		return $this;

	}

    /**
     * Set the chart to be horizontal.
     */
    public function horizontal(): self {

        $this->options['indexAxis'] = 'y';
        return $this;

    }

	/**
	 * Set the hover options.
	 *
	 * @param   string  The interaction mode. Possible values are: point, nearest, index, dataset, x, y.
	 * @param   bool    If true, the hover mode only applies when the mouse position intersects an item on the chart.
	 * @param   int     The number of milliseconds that the hover animation will take to complete.
	 */
	public function hover(string $mode, bool $intersect, int $animationDuration): self {

		$this->options['hover'] = [
			'mode' => $mode,
			'intersect' => $intersect,
			'animationDuration' => $animationDuration
		];

		return $this;

	}

	/**
	 * Set the interaction options.
	 *
	 * @param	string	The interaction mode. Possible values are: point, nearest, index, dataset, x, y.
	 * @param	bool	If true, the hover mode only applies when the mouse position intersects an item on the chart.
	 * @param	string	The axis to detect the interaction on. Possible values are: x, y, xy.
	 */
	public function interaction(string $mode, bool $intersect, string $axis): self {

		$this->options['interaction'] = [
			'mode' => $mode,
			'intersect' => $intersect,
			'axis' => $axis
		];

		return $this;

	}

	/**
	 * If the labels property of the main data property is used, it has to contain the same amount of elements
	 * as the dataset with the most values. These labels are used to label the index axis (default x axes).
	 * The values for the labels have to be provided in an array. The provided labels can be of the type string
	 * or number to be rendered correctly. In case you want multiline labels you can provide an array with each
	 * line as one entry in the array.
	 */
	public function labels(array $labels): self {

		$this->labels = $labels;

		return $this;

	}

	/**
	 * Configure the legend of the chart. When using the chartArea position value, the legend position is at the moment
	 * not configurable, it will always be on the left side of the chart in the middle.
	 *
	 * @param	string	Position of the legend: top, bottom, left, right, chartArea. Default is top.
	 * @param	string	Alignment of the legend: start, center, end. Default is center.
	 * @param	array	Additional configuration options: boxWidth, boxHeight, color, font, padding, generateLabels,
	 * 					filter, sort, pointStyle, textAlign, usePointStyle, pointStyleWidth, useBorderRadius, borderRadius.
	 */
	public function legend(string $position, string $align, array $config=[]): self {


		if (!in_array($position, ['top', 'bottom', 'left', 'right', 'chartArea'])) {
			throw new AppException('Invalid legend position: ' . $position);
		}

		if (!in_array($align, ['start', 'center', 'end'])) {
			throw new AppException('Invalid legend alignment: ' . $align);
		}

		$this->options['plugins']['legend'] = [
			'position' => $position,
			'align' => $align
		];

		$validSettings = ['boxWidth', 'boxHeight', 'color', 'font', 'padding', 'generateLabels', 'filter', 'sort',
			'pointStyle', 'textAlign', 'usePointStyle', 'pointStyleWidth', 'useBorderRadius', 'borderRadius'];

		foreach ($config as $setting => $value) {

			if (!in_array($setting, $validSettings)) {
				throw new AppException('Invalid legend setting: ' . $setting);
			} else {
				$this->options['plugins']['legend'][$setting] = $value;
			}

		}

		return $this;

	}

	/**
	 * Load the Chart.js library from a CDN.
	 * This method should be called in the view to load the library before rendering the chart.
	 */
	public static function load(): void {

		$app = Application::getInstance();
		$app->loadScript('https://cdn.jsdelivr.net/npm/chart.js');

	}

	/**
	 * Load the Chart.js Datalabels plugin from a CDN and register it with Chart.js.
	 * This method should be called in the view to load the plugin before rendering the chart.
	 * The Datalabels plugin is used to display labels on the chart data points.
	 * It can be used with any chart type that supports data labels.
	 */
	public static function loadDatalabels(): void {

		$app = Application::getInstance();
		$app->loadScript('https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels');
		$app->addScript('Chart.register(ChartDataLabels);');

	}

	/**
	 * Set the aspect ratio of the chart.
	 *
	 * @param	bool	If true, the aspect ratio is maintained.
	 */
	public function maintainAspectRatio(bool $maintainAspectRatio): self {

		$this->options['maintainAspectRatio'] = $maintainAspectRatio;

		return $this;

	}

	/**
	 * Set the margins options.
	 *
	 * @param	int		The top margin of the chart.
	 * @param	int		The right margin of the chart.
	 * @param	int		The bottom margin of the chart.
	 * @param	int		The left margin of the chart.
	 */
	public function margins(int $top, int $right, int $bottom, int $left): self {

		$this->options['margins'] = [
			'top' => $top,
			'right' => $right,
			'bottom' => $bottom,
			'left' => $left
		];

		return $this;

	}

	/**
	 * Chart.js is fastest if you provide data with indices that are unique, sorted, and consistent
	 * across datasets and provide the normalized: true option to let Chart.js know that you have
	 * done so.
	 */
	public function normalized(bool $normalized): self {

		$this->options['normalized'] = $normalized;

		return $this;

	}

	/**
	 * Set an option for the chart.
	 *
	 * @param	string	The name of the option.
	 * @param	array|string|bool	The value of the option.
	 */
	public function option(string $name, array|string|bool $value): self {

		$this->options[$name] = $value;

		return $this;

	}

	/**
	 * Set the layout padding options. The padding can be set to a number or an object with the
	 * keys left, right, top, and bottom.
	 *
	 * @param	array|int	The padding value or an array with the keys left, right, top, and bottom.
	 */
	public function padding(array|int $padding): self {

		if (is_array($padding)) {

			foreach ($padding as $key=>$value) {

				if (!in_array($key, ['left', 'right', 'top', 'bottom'])) {
					throw new AppException('Invalid padding key: ' . $key);
				} else if (!is_int($value)) {
					throw new AppException('Invalid padding value: ' . $value);
				} else {
					$this->options['layout']['padding'][$key] = $value;
				}

			}

		} else if (is_int($padding)) {

			$this->options['layout']['padding'] = $padding;

		}

		return $this;

	}

	/**
	 * How to parse the dataset. The parsing can be disabled by specifying parsing: false at chart
	 * options or dataset. If parsing is disabled, data must be sorted and in the formats the
	 * associated chart type and scales use internally.
	 */
	public function parsing(bool $parsing): self {

		$this->options['parsing'] = $parsing;

		return $this;

	}

	/**
	 * Add an array structured to configure a Chart.js plugin, such as ChartDataLabels.
	 *
	 * @param	string	The name of the plugin options.
	 * @param	array	The options of the plugin.
	 */
	public function plugin(string $pluginName, array $pluginOptions): self {

		$this->options['plugins'][$pluginName] = $pluginOptions;

		return $this;

	}

	/**
	 * Build a label range for the chart, based on the start date, end date of the period
	 * and interval. The interval can be one of the following values: day, week, month, year.
	 *
	 * @throws AppException If the interval is not valid or if the user or session is not available.
	 */
	public function rangeLabels(\DateTime $start, \DateTime $end, string $interval): self {

		// check the interval
		if (!in_array($interval, ['day', 'week', 'month', 'year'])) {
			throw new AppException('Label interval (day|week|month|year) is not valid: ' . $interval);
		}

		if (!User::current() or !Session::current()) {
			throw new AppException('User or session not available');
		}

		// set the internationalization formatter
		switch ($interval) {

			case 'day':
				$duration = 'P1D';
				$pattern = 'd MMMM';
				break;

			case 'week':
				$duration = 'P1W';
				$pattern = 'd MMMM';
				break;

			case 'month':
				$duration = 'P1M';
				$pattern = 'MMMM yyyy';
				break;

			case 'year':
				$duration = 'P1Y';
				$pattern = 'yyyy';
				break;

		}

		$localeRepr = User::current()->getLocale()->getRepresentation('_');
		$timezoneName = Session::current()->timezoneName;

		$formatter = new \IntlDateFormatter(
			$localeRepr,
			\IntlDateFormatter::FULL,
			\IntlDateFormatter::NONE,
			$timezoneName,
			\IntlDateFormatter::GREGORIAN,
			$pattern
		);

		$interval = new \DateInterval($duration);
		$period = new \DatePeriod($start, $interval, $end);

		$labels = [];

		foreach ($period as $date) {
			$labels[] = $formatter->format($date);
		}

		// invert to have the labels in the correct order (older to newer)
		if ($start > $end) {
			$labels = array_reverse($labels);
		}

		$this->labels($labels);

		return $this;

	}

	/**
	 * Return the JS script to render the chart.
	 */
	public function render(): string {

		$data = json_encode([
			'labels' => $this->labels,
			'datasets' => array_map(function($dataset) {
				return $dataset->export();
			}, $this->datasets)
		]);

		$script = 'var chartJsObject = document.getElementById("' . $this->elementId . '");' . PHP_EOL;
		$script.= 'new Chart(chartJsObject, {type: "' . $this->type . '", data: ' . $data;

		if (count($this->options)) {
			$script .= ', options: ' . json_encode($this->options);
		}

		$script .= '});';

		$app = Application::getInstance();
		$app->addScript($script);

		return '<canvas id="' . $this->elementId . '"></canvas>';

	}

	/**
	 * Set the responsive options.
	 *
	 * @param	bool	If true, the chart is responsive.
	 */
	public function responsive(bool $responsive): self {

		$this->options['responsive'] = $responsive;

		return $this;

	}

	/**
	 * Set stacked options for x and y axes of the chart.
	 */
	public function stacked(bool $xStacked, bool $yStacked): self {

		$this->options['scales']['x']['stacked'] = $xStacked ? true : false;
		$this->options['scales']['y']['stacked'] = $yStacked ? true : false;

		return $this;

	}

	/**
	 * Show the chart subtitle and configure its options.
	 *
	 * @param	string	Subtitle text.
	 * @param	array	Subtitle configuration array: align, color, display, fullSize, position, font, padding.
	 */
	public function subtitle(string $text, array $config=[]): self {

		$this->options['plugins']['subtitle'] = [
			'display' => TRUE,
			'text' => $text
		];

		$settings = ['align', 'color', 'display', 'fullSize', 'position', 'font', 'padding'];

		foreach ($config as $setting => $value) {

			if (!in_array($setting, $settings)) {
				throw new AppException('Invalid subtitle setting: ' . $setting);
			} else {
				$this->options['plugins']['subtitle'][$setting] = $value;
			}

		}

		return $this;

	}

	/**
	 * Show the chart title and configure its options.
	 *
	 * @param	string	Title text.
	 * @param	array	Title configuration array: align, color, display, fullSize, position, font, padding.
	 */
	public function title(string $text, array $config=[]): self {

		$this->options['plugins']['title'] = [
			'display' => TRUE,
			'text' => $text
		];

		$settings = ['align', 'color', 'display', 'fullSize', 'position', 'font', 'padding'];

		foreach ($config as $setting => $value) {

			if (!in_array($setting, $settings)) {
				throw new AppException('Invalid title setting: ' . $setting);
			} else {
				$this->options['plugins']['title'][$setting] = $value;
			}

		}

		return $this;

	}

}