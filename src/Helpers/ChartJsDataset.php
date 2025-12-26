<?php

namespace Pair\Helpers;

use Pair\Exceptions\AppException;

/**
 * This class represents a dataset for a ChartJS chart. The data property of a dataset for a line chart can be passed in two formats.
 * 1. Array of numbers - the simplest form and is used when the data is just a list of numbers.
 * 2. Object - used when you want to display multiple datasets on the same chart. The object has to contain the data property, which is an array of numbers.
 */
class ChartJsDataset {

	/**
	 * The label of this dataset.
	 */
	private ?string $label = null;

	/**
	 * The data of this dataset.
	 */
	private array $data = [];

	/**
	 * The background color of the dataset.
	 */
	private array $backgroundColors = [];

	/**
	 * The border color of the dataset.
	 */
	private ?string $borderColor = null;

	/**
	 * The dataset type, useful when mixing chart types.
	 */
	private ?string $type = null;

	/**
	 * The border width of the dataset.
	 */
	private ?int $borderWidth = null;

	/**
	 * Fill option for line/area datasets.
	 */
	private ?bool $fill = null;

	/**
	 * Point radius for line and radar datasets.
	 */
	private ?int $pointRadius = null;

	/**
	 * The cubic interpolation mode of the dataset (default, monotone).
	 */
	private ?string $cubicInterpolationMode = null;

	/**
	 * The tension of the dataset.
	 */
	private ?float $tension = null;

	/**
	 * Constructor. The data property of a dataset for a line chart can be passed in two formats. The first format
	 * is an array of numbers. This is the simplest form and is used when the data is just a list of numbers. The
	 * second format is an object. This object is used when you want to display multiple datasets on the same chart.
	 * The object has to contain the data property, which is an array of numbers.
	 * 
	 * @param	array	$data	An array of data points for the dataset.
	 * @param	string	$label	Optional label for the dataset.
	 * @throws	AppException	Throws an exception if the data array is empty.
	 */
	public function __construct(array $data, ?string $label = null) {

		if (!count($data)) {
			throw new AppException('ChartJS dataset must have at least one data point');
		}

		$this->data = $data;

		if ($label) {
			$this->label($label);
		}

	}

	/**
	 * Set the background color for each data point.
	 * 
	 * @param	array	$colors	An array of color strings.
	 * @return	ChartJsDataset	Returns the current instance for method chaining.
	 */
	public function backgroundColors(array $colors): self {

		$this->backgroundColors = $colors;
		return $this;

	}

	/**
	 * Apply a callback to compute the background color for each data point.
	 * 
	 * @param	callable	$callback	A callback function that receives the context and returns a color string.
	 * @return	ChartJsDataset			Returns the current instance for method chaining.
	 * @throws	AppException			If the callback does not return a string.
	 */
	public function colorFunction(callable $callback): self {

		$datasetContext = (object)['data' => $this->data];
		$colors = [];

		foreach ($this->data as $index => $value) {

			$context = (object)[
				'dataset' => $datasetContext,
				'dataIndex' => $index,
				'parsed' => $value
			];

			$color = $callback($context);

			if (!is_string($color)) {
				throw new AppException('ChartJS color function must return a string');
			}

			$colors[] = $color;

		}

		$this->backgroundColors = $colors;

		return $this;

	}

	/**
	 * Set the dataset label.
	 * 
	 * @param	string	$label	The label for the dataset.
	 * @return	ChartJsDataset	Returns the current instance for method chaining.
	 */
	public function label(string $label): self {

		$this->label = $label;
		return $this;

	}

	/**
	 * Set dataset type. This is useful for mixed charts (e.g. a line on a bar chart).
	 * 
	 * @param	string	$type	The dataset type. Possible values are: line, bar, radar, doughnut, pie, polarArea, bubble, scatter.
	 * @return	ChartJsDataset	Returns the current instance for method chaining.
	 * @throws	AppException	Throws an exception if the type is not valid.
	 */
	public function type(string $type): self {

		$validTypes = ['line', 'bar', 'radar', 'doughnut', 'pie', 'polarArea', 'bubble', 'scatter'];

		if (!in_array($type, $validTypes)) {
			throw new AppException('ChartJs dataset type is not valid: ' . $type);
		}

		$this->type = $type;

		return $this;

	}

	/**
	 * Set the dataset border color.
	 * 
	 * @param	string	$color	The border color.
	 * @return	ChartJsDataset	Returns the current instance for method chaining.
	 */
	public function borderColor(string $color): self {

		$this->borderColor = $color;
		return $this;

	}

	/**
	 * Set the dataset border width.
	 * 
	 * @param	int	$width	The border width in pixels.
	 * @return	ChartJsDataset	Returns the current instance for method chaining.
	 * @throws	AppException	Throws an exception if the width is negative.
	 */
	public function borderWidth(int $width): self {

		if ($width < 0) {
			throw new AppException('ChartJS dataset border width is not valid: ' . $width);
		}

		$this->borderWidth = $width;
		return $this;

	}

	/**
	 * Enable or disable dataset fill.
	 * 
	 * @param	bool	$fill	True to enable fill, false to disable.
	 * @return	ChartJsDataset	Returns the current instance for method chaining.
	 */
	public function fill(bool $fill): self {

		$this->fill = $fill;
		return $this;

	}

	/**
	 * Set the point radius for the dataset.
	 * 
	 * @param	int	$radius	The point radius in pixels.
	 * @return	ChartJsDataset	Returns the current instance for method chaining.
	 * @throws	AppException	Throws an exception if the radius is negative.
	 */
	public function pointRadius(int $radius): self {

		if ($radius < 0) {
			throw new AppException('ChartJS dataset point radius is not valid: ' . $radius);
		}

		$this->pointRadius = $radius;
		return $this;

	}

	/**
	 * The following interpolation modes are supported: default, monotone. The 'default' algorithm
	 * uses a custom weighted cubic interpolation, which produces pleasant curves for all types of
	 * datasets. The 'monotone' algorithm is more suited to y = f(x) datasets: it preserves monotonicity
	 * (or piecewise monotonicity) of the dataset being interpolated, and ensures local extremums
	 * (if any) stay at input data points. If left untouched (undefined), the global
	 * options.elements.line.cubicInterpolationMode property is used.
	 * Tension is the Bezier curve tension of the line. Set to 0 to draw straightlines. This option is
	 * ignored if monotone cubic interpolation is used.
	 * 
	 * @param	string	$mode		The interpolation mode. Possible values are: default, monotone.
	 * @param	float	$tension	The Bezier curve tension of the line.
	 * @return	ChartJsDataset		Returns the current instance for method chaining.
	 * @throws	AppException		Throws an exception if the mode is not valid or the tension is out of range.
	 */
	public function interpolation(string $mode = 'default', float $tension = 0.4): self {

		if (!in_array($mode, ['default', 'monotone'])) {
			throw new AppException('ChartJs dataset interpolation mode is not valid: ' . $mode);
		}

		if ($tension < 0 or $tension > 1) {
			throw new AppException('ChartJS dataset tension is not valid: ' . $tension);
		}

		$this->cubicInterpolationMode = $mode;
		$this->tension = $tension;

		return $this;

	}

	/**
	 * If the labels property of the main data property is used, it has to contain the same amount of elements
	 * as the dataset with the most values. These labels are used to label the index axis (default x axes).
	 * The values for the labels have to be provided in an array. The provided labels can be of the type string
	 * or number to be rendered correctly. In case you want multiline labels you can provide an array with each
	 * line as one entry in the array.
	 * 
	 * @param	array	$labels	An array of labels for the dataset.
	 * @return	ChartJsDataset	Returns the current instance for method chaining.
	 */
	public function labels(array $labels): self {

		$this->data['labels'] = $labels;

		return $this;

	}

	/**
	 * Return the dataset in the format required by ChartJS. The data property of a dataset for a line chart can be
	 * passed in two formats. This is the simplest form and is used when the data is just a list of numbers. The second
	 * format is an object. This object is used when you want to display multiple datasets on the same chart. The object
	 * has to contain the data property, which is an array of numbers.
	 * The following properties are supported:
	 * - label: The label for the dataset which appears in the legend and tooltips.
	 * - backgroundColor: The fill color under the line.
	 * - borderColor: The color of the line.
	 * - type: Dataset type (line, bar, radar, doughnut, pie, polarArea, bubble, scatter).
	 * - borderWidth: Line/border width.
	 * - fill: Fill the area under the line.
	 * - pointRadius: Radius of points on line/radar charts.
	 * - cubicInterpolationMode: default|monotone.
	 * - tension: The Bezier curve tension of the line.
	 * 
	 * @return	array	An associative array representing the dataset for ChartJS.
	 */
	public function export(): array {

		$dataset = [
			'label' => $this->label,
			'data' => $this->data
		];

		if ($this->type) {
			$dataset['type'] = $this->type;
		}

		if ($this->borderColor) {
			$dataset['borderColor'] = $this->borderColor;
		}

		if (!is_null($this->borderWidth)) {
			$dataset['borderWidth'] = $this->borderWidth;
		}

		if (count($this->backgroundColors)) {
			$dataset['backgroundColor'] = $this->backgroundColors;
		}

		if (!is_null($this->fill)) {
			$dataset['fill'] = $this->fill;
		}

		if (!is_null($this->pointRadius)) {
			$dataset['pointRadius'] = $this->pointRadius;
		}

		if ($this->cubicInterpolationMode) {
			$dataset['cubicInterpolationMode'] = $this->cubicInterpolationMode;
		}

		if ($this->tension) {
			$dataset['tension'] = $this->tension;
		}

		return $dataset;

	}

}
