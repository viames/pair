<?php

namespace Pair\Services;

use Pair\Core\Application;
use Pair\Models\Locale;
use Pair\Orm\Database;
use Pair\Support\Translator;
use Pair\Support\Utilities;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
/**
 * This class is a base for creating Excel reports.
 */
abstract class Report {

	/**
	 * Document title.
	 */
	protected string $title;

	/**
	 * Sheet subject.
	 */
   	protected string $subject;

	/**
	 * Contains a SQL query to execute.
	 */
	private ?string $query = NULL;

	/**
	 * Column definitions (header, format).
	 */
	private array $columns = [];

	/**
	 * Rows of cell data, for each row an array with zero-based numeric index.
	 */
	private array $data = [];

	/**
	 * Contains the name of the builder class.
	 */
	private string $builder = 'PhpSpreadsheet';

	/**
	 * Populates the title and subject defaults of the Report document.
	 */
	public function __construct() {

		$this->title = 'report-' . date('Ymd-His');
		$this->subject = 'Data';

	}

	/**
	 * Adds to a property of the class, the specification of a column to be created, numbering it
	 * with a zero-based index.
	 */
	protected function addColumn(string $head, string $format = NULL): self {

		$column = new \stdClass;

		// column header
		$column->head = $head;

		// if no format specified, use an Excel default
		if ($format) {
			$column->format = $format;
		}

		$this->columns[] = $column;

		return $this;

	}

	/**
	 * Adds a row of data supplied as an array of values.
	 */
	protected function addRow(array $indexedCellsValue): void {

		$this->data[] = $indexedCellsValue;

	}

	/**
	 * Hook for custom processing before saving the Excel document.
	 */
	protected function beforeSave(): void {}

	/**
	 * Hook for custom processing after saving the Excel document.
	 */
	protected function afterSave(): void {}

	/**
	 * Return the number of rows of this Report.
	 */
	protected function countRows(): int {

		return count($this->data);

	}

	/**
	 * Public function to process the Excel document, save it to disk in a temporary file
	 * and send it to the browser for download.
	 */
	public function download(): void {

		// gets the filename from the document title
		$filePath = TEMP_PATH . Utilities::localCleanFilename($this->title . '.xlsx');

		$this->save($filePath);

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Length: ' . filesize($filePath));
		header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
		header('Cache-Control: max-age=0');
		readfile($filePath);

		unlink($filePath);

	}

	/**
	 * Formats a boolean value for display in the Excel sheet.
	 */
	protected function formatBooleanCell(bool $value): string {

		return (
			$value
			? Translator::do('WORD_TRUE', NULL, FALSE, 'True')
			: Translator::do('WORD_FALSE', NULL, FALSE, 'False')
		);

	}

	/**
	 * Set the format and value of a cell in the Excel sheet.
	 */
	private function formatCell(Cell &$cell, mixed $value, string|Callable $format=NULL): void {

		// default is auto format
		if (is_null($format)) {

			$cell->setValue($value);
			return;

		} else if (is_callable($format)) {

			$cell->setValue($format($value));
			return;

		}

		// otherwise select the appropriate format
		switch ($format) {

			case 'int':
			case 'integer':
			case 'numeric':
				$cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
				break;

			case 'currency':
				$cell->setValueExplicit($value, DataType::TYPE_NUMERIC);
				$cell->getStyle()->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_EUR);
				break;

			case 'stringDate':
			case 'stringDateTime':
			case 'Date':
			case 'DateTime':
				$cell->setValue($this->formatDateCell($value, $format));
				break;

			case 'bool':
			case 'boolean':
				$cell->setValue($this->formatBooleanCell((bool)(int)$value));
				break;

			case 'string':
			default:
				$cell->setValueExplicit($value, DataType::TYPE_STRING);
				break;

		}

	}

	/**
	 * Formats a date value for display in the Excel sheet.
	 */
	protected function formatDateCell($value, string $format): ?string {

		switch ($format) {

			case 'stringDate':
				$dt = \DateTime::createFromFormat('Y-m-d', substr($value, 0, 10));
				if (is_a($dt,'DateTime')) {
					return $dt->format('d/m/Y');
				}
				break;

			case 'stringDateTime':
				$dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
				if (is_a($dt,'DateTime')) {
					return $dt->format('d/m/Y H:i:s');
				}
				break;

			case 'Date':
				return is_a($value,'DateTime') ? $value->format('d/m/Y') : NULL;
				break;

			case 'DateTime':
				return is_a($value,'DateTime') ? $value->format('d/m/Y H:i:s') : NULL;
				break;

		}

		return NULL;

	}

	/**
	 * Generic method for creating an Excel document from an array of data.
	 */
	public function getSpreadsheet(): Spreadsheet {

		// if there is no data and a query has been set, runs it to populate the data
		if (empty($this->data) and !is_null($this->query)) {
			$this->setDataAndColumnsFromDictionary(Database::load($this->query, [], PAIR_DB_DICTIONARY));
		}

		// create the document and set up the sheet
		$spreadsheet = new Spreadsheet();
		$activeSheet = $spreadsheet->setActiveSheetIndex(0);

		// set the locale
		$app = Application::getInstance();
		$cUser = $app->currentUser;
		$locale = $cUser
			? $cUser->getLocale()->getRepresentation()
			: Locale::getDefault()->getRepresentation();
		Settings::setLocale(str_replace('-','_',$locale));

		// set column names (header)
		foreach ($this->columns as $col => $def) {
			$activeSheet->getCell([$col+1, 1])->setValue($def->head)->getStyle()->getFont()->setBold(TRUE);
		}

		// start values from second row
		$row = 2;

		// sets the rows of the table
		foreach ($this->data as $o) {

			// column number (zero-based) and defined properties
			foreach ($this->columns as $col => $def) {

				$value = $o[$col];

				// Cell object to write to
				$cell = $activeSheet->getCell([$col+1, $row]);

				$this->formatCell($cell, $value, $def->format ?? NULL);

			}

			$row++;

		}

		// set document properties
		$spreadsheet->getProperties()
			->setCreator(PRODUCT_NAME . ' ' . PRODUCT_VERSION)
			->setLastModifiedBy(PRODUCT_NAME . ' ' . PRODUCT_VERSION)
			->setTitle($this->title)
			->setSubject($this->subject);

		return $spreadsheet;

	}

	/**
	 * Set the builder library to use (CSV or PhpSpreadsheet).
	 */
	public function setBuilder(string $builder): self {

		$this->builder = $builder;

		return $this;

	}

	/**
	 * Save the Excel file to disk on the specified absolute path.
	 */
	public function save(string $filePath): bool {

		// hook for custom processing
		$this->beforeSave();

		if ('CSV' == $this->builder and defined('CSV2XLSX_PATH') and is_executable(CSV2XLSX_PATH)) {

			$this->saveCsvAndConvert($filePath);

		} else {

			$writer = new Xlsx($this->getSpreadsheet());
			$writer->save($filePath);

		}

		// hook for custom processing
		$this->afterSave();

		return file_exists($filePath);

	}

	/**
	 * Defines a particular column by its index zero-based.
	 */
	protected function setColumn(int $index, string $head, string|Callable $format = NULL): self {

		$column = new \stdClass;
		$column->head = $head;

		// if no format specified, use an Excel default
		if ($format) {
			$column->format = $format;
		}

		$this->columns[$index] = $column;

		return $this;

	}

	/**
	 * Set column headers and cell values using an associative array.
	 */
	protected function setDataAndColumnsFromDictionary(array $dictionary): self {

		// empty list
		if (!isset($dictionary[0])) {
			return $this;
		}

		// extracts the property names of the first object
		$varNames = array_keys($dictionary[0]);

		// adds column headers
		foreach ($varNames as $varName) {
			$this->addColumn($varName);
		}

		// populates all cells in all rows
		foreach ($dictionary as $line) {

			$this->addRow(array_values($line));

		}

		return $this;

	}

	/**
	 * Set column headers and cell values using an array of objects.
	 */
	protected function setDataAndColumnsFromObjects(array $objectList): self {

		// empty list
		if (!isset($objectList[0])) {
			return $this;
		}

		// extracts the property names of the first object
		$varNames = array_keys(get_object_vars($objectList[0]));

		// adds column headers
		foreach ($varNames as $varName) {
			$this->addColumn($varName);
		}

		// populate the cells
		$this->setDataFromObjects($objectList);

		return $this;

	}

	/**
	 * Set cell values using an object array and column map.
	 */
	protected function setDataFromObjects(array $objectList): self {

		// create an array [column_name => column_index]
		$columns = array_flip(array_map(function($o) { return $o->head; }, $this->columns));

		// assigns values to cells based on the column name
		foreach ($objectList as $object) {

			$row = [];

			foreach ($columns as $name => $index) {
				$row[$index] = $object->$name;
			}

			$this->addRow($row);

		}

		return $this;

	}

	/**
	 * Set up a SQL query that getSpreadsheet() will execute to easily populate data and columns.
	 */
	protected function setQuery(string $query): self {

		$this->query = $query;

		return $this;

	}

	private function saveCsvAndConvert(string $filePath): void {

		$csvFile = $filePath . '.csv';

		$fp = fopen($csvFile, 'w');

		// write the header
		fputcsv($fp, array_map(function($o) { return $o->head; }, $this->columns));

		$dateFormats = ['stringDate', 'stringDateTime', 'Date', 'DateTime'];

		// write the data
		foreach ($this->data as $row) {

			foreach ($row as $key => $value) {

				if (in_array($this->columns[$key]->format, $dateFormats)) {
					$row[$key] = $this->formatDateCell($value, $this->columns[$key]->format);
				} else if (in_array($this->columns[$key]->format, ['currency','numeric'])) {
					$row[$key] = $value;
				} else {
					$row[$key] = '\'' . $value; // forced to string in converter
				}

			}

			fputcsv($fp, $row);
		}

		fclose($fp);

		// convert to Excel
		$command = CSV2XLSX_PATH . ' -d "," -o ' . $filePath . ' ' . $csvFile;
		shell_exec($command);

		unlink($csvFile);

	}

	/**
	 * Set the subject of the document.
	 */
	public function setSubject(string $subject): self {

		$this->subject = $subject;

		return $this;

	}

	/**
	 * Set the title of the document.
	 */
	public function setTitle(string $title): self {

		$this->title = $title;

		return $this;

	}

}