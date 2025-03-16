<?php

namespace Pair\Services;

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
	private ?string $label = NULL;

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
	private ?string $borderColor = NULL;

	/**
	 * The cubic interpolation mode of the dataset (default, monotone).
	 */
	private ?string $cubicInterpolationMode = NULL;

	/**
	 * The tension of the dataset.
	 */
	private ?float $tension = NULL;

	/**
	 * Constructor. The data property of a dataset for a line chart can be passed in two formats. The first format
	 * is an array of numbers. This is the simplest form and is used when the data is just a list of numbers. The
	 * second format is an object. This object is used when you want to display multiple datasets on the same chart.
	 * The object has to contain the data property, which is an array of numbers.
	 */
	public function __construct(array $data, ?string $label=NULL) {

		if (!count($data)) {
			throw new AppException('ChartJS dataset must have at least one data point');
		}

		$this->data = $data;

		if ($label) {
			$this->label($label);
		}

	}

	public function backgroundColors(array $colors): self {

		$this->backgroundColors = $colors;
		return $this;

	}
		
	public function label(string $label): self {

		$this->label = $label;
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
	 * @param	string	The interpolation mode. Possible values are: default, monotone.
	 * @param	float	The Bezier curve tension of the line.
	 */
	public function interpolation(string $mode='default', float $tension=0.4): self {

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
	 * - cubicInterpolationMode: default|monotone.
	 * - tension: The Bezier curve tension of the line.
	 */
	public function export(): array {

		$dataset = [
			'label' => $this->label,
			'data' => $this->data
		];

		if ($this->borderColor) {
			$dataset['borderColor'] = $this->borderColor;
		}

		if (count($this->backgroundColors)) {
			$dataset['backgroundColor'] = $this->backgroundColors;
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